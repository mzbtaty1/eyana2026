<?php

namespace App\Services;

use App\Models\{AccountStatement, Invoice, TicketUser, TicketVendor};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Passenger-level accounting for flight invoices.
 *
 * One invoice number (es_id) holds several passengers; every passenger's ticket
 * is its own financial item. The invoice has two ledger rows (account_statements,
 * transaction_type 1): one DEBIT row and one CREDIT row. Each passenger's
 * TicketUser record carries that passenger's share of both:
 *
 *   client_bought_price = the passenger's share of the DEBIT row
 *   client_net_pice     = the passenger's share of the CREDIT row
 *
 * so each ledger row always equals the sum over its passengers. This is what
 * invoice creation already does, what the Account Statement displays
 * (AccountStatement::passengerAmounts) and what every edit/refund must keep.
 *
 * Which account is on which side:
 *   invoice / reissue (FLY-A, FLY-RS): client debit,   supplier credit
 *   refund            (FLY-RD):        supplier debit, client credit
 */
class InvoicePassengerLedger
{
    public static function isRefund(Invoice $invoice): bool
    {
        return str_starts_with((string) $invoice->es_id, 'FLY-RD');
    }

    /** $total in $n equal whole-cent shares; any leftover cent goes to the last share. */
    public static function splitEqually($total, int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $cents = (int) round(((float) $total) * 100);
        $each = intdiv($cents, $n);
        $shares = array_fill(0, $n, $each / 100);
        $shares[$n - 1] = ($cents - $each * ($n - 1)) / 100;

        return $shares;
    }

    public static function passengers(Invoice $invoice): Collection
    {
        return TicketUser::where('ticket_system_id', $invoice->ticket_system_id)->orderBy('id')->get();
    }

    /** [debit row, credit row] of the invoice (either may be null if missing). */
    public static function ledgerRows(Invoice $invoice): array
    {
        $vendorId = TicketVendor::where('ticket_system_id', $invoice->ticket_system_id)->value('vendor_id');
        $rows = AccountStatement::where('transaction_type', 1)
            ->where('es_id', $invoice->es_id)
            ->where('is_storage', '!=', 1)
            ->get();
        $vendorRow = $rows->first(fn ($r) => (string) $r->supp_client_id === (string) $vendorId);
        $clientRow = $rows->first(fn ($r) => (string) $r->supp_client_id === (string) $invoice->invoice_beneficiaries);

        return self::isRefund($invoice) ? [$vendorRow, $clientRow] : [$clientRow, $vendorRow];
    }

    /**
     * Each passenger's [debit share, credit share] exactly as the Account
     * Statement shows them, keyed by TicketUser id.
     */
    public static function currentShares(Invoice $invoice, ?Collection $users = null): array
    {
        $users = ($users ?? self::passengers($invoice))->values();
        [$debitRow, $creditRow] = self::ledgerRows($invoice);

        $debit = $debitRow
            ? AccountStatement::passengerAmounts($users, 'client_bought_price', $debitRow->debit_balance)
            : $users->map(fn ($u) => round((float) $u->client_bought_price, 2))->all();
        $credit = $creditRow
            ? AccountStatement::passengerAmounts($users, 'client_net_pice', $creditRow->credit_balance)
            : $users->map(fn ($u) => round((float) $u->client_net_pice, 2))->all();

        $out = [];
        foreach ($users as $i => $u) {
            $out[$u->id] = [$debit[$i] ?? 0.0, $credit[$i] ?? 0.0];
        }

        return $out;
    }

    /**
     * Store the shares the statement shows into the passenger records when
     * they differ (historical FLY-RD refunds kept the original ticket prices).
     * Ledger rows are not touched, so no balance changes. Refunds only: a normal
     * invoice's passenger prices are its source data and are never rewritten.
     */
    public static function materializeShares(Invoice $invoice): void
    {
        if (!self::isRefund($invoice)) {
            return;
        }
        $users = self::passengers($invoice);
        foreach (self::currentShares($invoice, $users) as $id => [$d, $c]) {
            $u = $users->firstWhere('id', $id);
            if (abs((float) $u->client_bought_price - $d) > 0.001 || abs((float) $u->client_net_pice - $c) > 0.001) {
                $u->update(['client_bought_price' => $d, 'client_net_pice' => $c]);
            }
        }
    }

    /**
     * Refund passengers: copy $sourceUsers into $newSystemId with the entered
     * refund amounts split equally between them ($debitTotal is booked as the
     * refund's debit, $creditTotal as its credit). For a single-passenger refund
     * pass just that passenger: it receives the full amounts.
     */
    public static function createRefundPassengers(Collection $sourceUsers, string $newSystemId, $debitTotal, $creditTotal): void
    {
        $sourceUsers = $sourceUsers->values();
        $debit = self::splitEqually($debitTotal, $sourceUsers->count());
        $credit = self::splitEqually($creditTotal, $sourceUsers->count());

        foreach ($sourceUsers as $i => $user) {
            TicketUser::create([
                'crt_at' => date('Y-m-d'),
                'ticket_system_id' => $newSystemId,
                'client_name' => $user->client_name,
                'client_type' => $user->client_type,
                'client_bought_price' => $debit[$i],
                'client_net_pice' => $credit[$i],
                'client_booking_id' => $user->client_booking_id,
                'client_ticket_id' => $user->client_ticket_id,
                'client_phone' => $user->client_phone,
            ]);
        }
    }

    /**
     * Edit ONE passenger of an invoice. Only that TicketUser changes; the
     * invoice's ledger rows move by exactly that passenger's difference.
     * Returns [debit difference, credit difference].
     */
    public static function updatePassenger(Invoice $invoice, int $ticketUserId, array $attrs): array
    {
        return DB::transaction(function () use ($invoice, $ticketUserId, $attrs) {
            self::materializeShares($invoice);

            $user = TicketUser::where('ticket_system_id', $invoice->ticket_system_id)
                ->where('id', $ticketUserId)->lockForUpdate()->first();
            if (!$user) {
                throw new RuntimeException('passenger does not belong to this invoice');
            }
            [$debitRow, $creditRow] = self::ledgerRows($invoice);
            if (!$debitRow || !$creditRow) {
                throw new RuntimeException('invoice ledger rows not found');
            }

            $newDebit = round((float) $attrs['client_bought_price'], 2);
            $newCredit = round((float) $attrs['client_net_pice'], 2);
            $dDebit = round($newDebit - (float) $user->client_bought_price, 2);
            $dCredit = round($newCredit - (float) $user->client_net_pice, 2);

            $user->update(array_merge($attrs, ['client_bought_price' => $newDebit, 'client_net_pice' => $newCredit]));

            if ($dDebit != 0) {
                $debitRow->debit_balance = round((float) $debitRow->debit_balance + $dDebit, 2);
                $debitRow->ledger_net_effect = round((float) $debitRow->debit_balance - (float) $debitRow->credit_balance, 2);
                $debitRow->save();
            }
            if ($dCredit != 0) {
                $creditRow->credit_balance = round((float) $creditRow->credit_balance + $dCredit, 2);
                $creditRow->ledger_net_effect = round((float) $creditRow->debit_balance - (float) $creditRow->credit_balance, 2);
                $creditRow->save();
            }

            // TicketVendor.price mirrors the cost total on normal invoices (it is not read anywhere).
            if (!self::isRefund($invoice)) {
                TicketVendor::where('ticket_system_id', $invoice->ticket_system_id)
                    ->update(['price' => round((float) self::passengers($invoice)->sum('client_net_pice'), 2)]);
            }

            return [$dDebit, $dCredit];
        });
    }
}
