<?php

namespace App\Services;

use App\Models\{AccountStatement, Invoice, TicketUser, TicketVendor};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Passenger-level accounting for flight invoices.
 *
 * One invoice number (es_id) holds several passengers; every passenger's ticket
 * is its own financial item. The invoice's ledger rows (account_statements,
 * transaction_type 1) are on a DEBIT account and a CREDIT account:
 *
 *   invoice / reissue (FLY-A, FLY-RS): client debit,   supplier credit
 *   refund            (FLY-RD):        supplier debit, client credit
 *
 * Each passenger's TicketUser carries its CURRENT share of both
 * (client_bought_price = debit share, client_net_pice = credit share), so each
 * account's rows always add up to the sum over its passengers.
 *
 * Row marker (account_statements.description, JSON, new rows only):
 *   {"kind": "sale"|"edit"|"reissue"|"refund", "mode": "full"|"single" (refunds),
 *    "scope": "whole"|"passenger" (edits),
 *    "lines": [{"pid", "name", "booking", "debit", "credit"}, ...]}
 * "lines" is that row's exact per-passenger breakdown as shown on the Account
 * Statement / Print Preview / Excel. Rows without a marker (all historical rows)
 * are shown from the passengers' current amounts, as before.
 *
 * Date rule: an edit never changes the original rows; it adds adjustment rows
 * dated TODAY with each passenger's difference. Refunds (incl. a full refund of the
 * whole invoice) are dated
 * today too. The original sale keeps its date and amounts.
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

    public static function vendorId(Invoice $invoice): ?int
    {
        $v = TicketVendor::where('ticket_system_id', $invoice->ticket_system_id)->value('vendor_id');
        return $v === null ? null : (int) $v;
    }

    /** [debit account id, credit account id] of the invoice. */
    public static function accounts(Invoice $invoice, ?int $vendorId = null, ?int $clientId = null): array
    {
        $vendorId = $vendorId ?? self::vendorId($invoice);
        $clientId = $clientId ?? (int) $invoice->invoice_beneficiaries;
        return self::isRefund($invoice) ? [$vendorId, $clientId] : [$clientId, $vendorId];
    }

    public static function ledgerRowsAll(Invoice $invoice): Collection
    {
        return AccountStatement::where('transaction_type', 1)->where('es_id', $invoice->es_id)
            ->where('is_storage', '!=', 1)->orderBy('id')->get();
    }

    /** Net ledger total on the debit account (debit - credit) and on the credit account (credit - debit). */
    public static function ledgerTotals(Invoice $invoice): array
    {
        [$debitAcct, $creditAcct] = self::accounts($invoice);
        $rows = self::ledgerRowsAll($invoice);
        $sum = fn ($acct) => $rows->filter(fn ($r) => (int) $r->supp_client_id === (int) $acct);
        $d = $sum($debitAcct); $c = $sum($creditAcct);
        return [
            $d->isEmpty() ? null : round($d->sum(fn ($r) => (float) $r->debit_balance - (float) $r->credit_balance), 2),
            $c->isEmpty() ? null : round($c->sum(fn ($r) => (float) $r->credit_balance - (float) $r->debit_balance), 2),
        ];
    }

    /**
     * Each passenger's current [debit share, credit share], keyed by TicketUser id.
     * Normally the passengers' own amounts; for historical refunds whose passengers
     * still hold the original ticket prices, the ledger amount split equally.
     */
    public static function currentShares(Invoice $invoice, ?Collection $users = null): array
    {
        $users = ($users ?? self::passengers($invoice))->values();
        [$debitTotal, $creditTotal] = self::ledgerTotals($invoice);

        $debit = $debitTotal !== null
            ? AccountStatement::passengerAmounts($users, 'client_bought_price', $debitTotal)
            : $users->map(fn ($u) => round((float) $u->client_bought_price, 2))->all();
        $credit = $creditTotal !== null
            ? AccountStatement::passengerAmounts($users, 'client_net_pice', $creditTotal)
            : $users->map(fn ($u) => round((float) $u->client_net_pice, 2))->all();

        $out = [];
        foreach ($users as $i => $u) {
            $out[$u->id] = [$debit[$i] ?? 0.0, $credit[$i] ?? 0.0];
        }
        return $out;
    }

    /**
     * Store the shares the statement shows into the passenger records when they
     * differ (historical FLY-RD refunds kept the original ticket prices). Ledger
     * rows are not touched. Refunds only: a normal invoice's passenger prices are
     * its source data and are never rewritten.
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

    // ------------------------------------------------------------------ refund totals for reports

    /**
     * A refund invoice's totals from ALL its ledger rows, per account -- so an
     * edit of the refund (adjustment rows dated later) is included:
     *   supplier = «مرتجع لنا من المورد» = supplier-account debits minus credits
     *   client   = «مسترد للعميل»        = client-account credits minus debits
     * For a refund that was never edited this equals the old first-row values
     * (supplier row debit, client row credit).
     * $rows: the invoice's account_statements rows (any transaction_type is filtered here).
     */
    public static function refundTotalsFromRows($rows, $vendorId, $clientId): array
    {
        $rows = collect($rows)->filter(fn ($r) => (int) $r->transaction_type === 1);
        $supplier = $rows->filter(fn ($r) => (string) $r->supp_client_id === (string) $vendorId)
            ->sum(fn ($r) => (float) $r->debit_balance - (float) $r->credit_balance);
        $client = $rows->filter(fn ($r) => (string) $r->supp_client_id === (string) $clientId)
            ->sum(fn ($r) => (float) $r->credit_balance - (float) $r->debit_balance);
        return ['supplier' => round($supplier, 2), 'client' => round($client, 2)];
    }

    /** refundTotalsFromRows() for one invoice (model or row with es_id, ticket_system_id, invoice_beneficiaries). */
    public static function refundTotals($invoice): array
    {
        $vendorId = TicketVendor::where('ticket_system_id', $invoice->ticket_system_id)->value('vendor_id');
        $rows = AccountStatement::where('es_id', $invoice->es_id)->where('is_storage', '!=', 1)
            ->get(['supp_client_id', 'transaction_type', 'debit_balance', 'credit_balance']);
        return self::refundTotalsFromRows($rows, $vendorId, $invoice->invoice_beneficiaries);
    }

    /**
     * The same totals shaped like the reports' former first-row lookups:
     * [$mostarad, $mortaga] with $mostarad->debit_balance = «مرتجع لنا من المورد»
     * and $mortaga->credit_balance = «مسترد للعميل».
     */
    public static function refundRows($invoice): array
    {
        $t = self::refundTotals($invoice);
        return [
            (object) ['debit_balance' => $t['supplier'], 'credit_balance' => 0],
            (object) ['debit_balance' => 0, 'credit_balance' => $t['client']],
        ];
    }

    // ------------------------------------------------------------------ markers / statement display

    public static function marker($row): ?array
    {
        $m = $row->description ?? null;
        if (!is_string($m) || $m === '' || $m[0] !== '{') {
            return null;
        }
        $m = json_decode($m, true);
        return is_array($m) && isset($m['kind']) ? $m : null;
    }

    /**
     * The passenger lines of one ledger row as the statement shows them:
     * [['name', 'booking', 'debit' (?float), 'credit' (?float)], ...], or null when
     * the row is not broken down by passenger.
     */
    public static function statementLines($row, Collection $users, bool $hasBreakdown): ?array
    {
        $m = self::marker($row);
        if ($m && !empty($m['lines'])) {
            // amounts are the row's recorded ones; name / booking number come from the
            // passenger's current record, so a data correction shows everywhere
            $byId = collect($users)->keyBy('id');
            return array_map(fn ($l) => [
                'name' => (string) (isset($l['pid'], $byId[$l['pid']]) ? $byId[$l['pid']]->client_name : ($l['name'] ?? '')),
                'booking' => (string) (isset($l['pid'], $byId[$l['pid']]) ? $byId[$l['pid']]->client_booking_id : ($l['booking'] ?? '')),
                'debit' => isset($l['debit']) ? (float) $l['debit'] : null,
                'credit' => isset($l['credit']) ? (float) $l['credit'] : null,
            ], $m['lines']);
        }
        if (!$hasBreakdown) {
            return null;
        }
        $users = $users->values();
        $dH = (float) $row->debit_balance > 0;
        $cH = (float) $row->credit_balance > 0;
        $d = $dH ? AccountStatement::passengerAmounts($users, 'client_bought_price', $row->debit_balance) : [];
        $c = $cH ? AccountStatement::passengerAmounts($users, 'client_net_pice', $row->credit_balance) : [];
        $lines = [];
        foreach ($users as $i => $u) {
            $lines[] = ['name' => (string) $u->client_name, 'booking' => (string) $u->client_booking_id,
                        'debit' => $dH ? (float) $d[$i] : null, 'credit' => $cH ? (float) $c[$i] : null, 'pid' => $u->id];
        }
        return $lines;
    }

    /**
     * Operation kind of a ticket ledger row, from explicit data only (row marker,
     * invoice number prefix) -- never from amounts:
     * 'sale' | 'edit' | 'reissue' | 'refund', or null for non-ticket rows.
     *   refund   = refund of one passenger OR of the whole invoice ("cancellation" is
     *              a full refund: same workflow, same accounting; only its label differs)
     *   edit     = correction of an existing invoice (adjustment rows, same invoice)
     *   reissue  = new invoice from the re-issue workflow (FLY-RS; marker 'reissue' since then)
     */
    public static function rowKind($row): ?string
    {
        if ((int) $row->transaction_type !== 1 || $row->es_id === 'FLY-OPEN-BALANCE') {
            return null;
        }
        $m = self::marker($row);
        $kind = $m['kind'] ?? null;
        if ($kind === 'edit') {
            return 'edit';
        }
        if ($kind === 'refund') {
            return 'refund';
        }
        if ($kind === 'reissue') {
            return 'reissue';
        }
        // historical rows: the refund / re-issue workflows give their new invoice its own number prefix
        $es = (string) $row->es_id;
        if (str_starts_with($es, 'FLY-RD')) {
            return 'refund';            // historical refund: its mode was not recorded
        }
        if (str_starts_with($es, 'FLY-RS')) {
            return 'reissue';
        }
        return 'sale';
    }

    /**
     * «نوع العملية» of a ticket ledger row (null for non-ticket rows). $invoice is
     * the row's invoice (for its invoice_shared flag); from explicit data only.
     */
    public static function kindLabel($row, $invoice = null): ?string
    {
        $kind = self::rowKind($row);
        if ($kind === null) {
            return null;
        }
        $m = self::marker($row);
        $shared = $invoice && (int) ($invoice->invoice_shared ?? 0) === 1 ? ' مشتركة' : '';
        $firstName = $m['lines'][0]['name'] ?? '';
        switch ($kind) {
            case 'edit':
                return 'تعديل تذكرة' . $shared . (($m['scope'] ?? '') === 'passenger' && $firstName !== '' ? ' - ' . $firstName : '');
            case 'reissue':
                return 'إعادة إصدار تذكرة' . $shared;
            case 'refund':
                if (!$m) {
                    return 'مرتجع';                       // historical refund: mode not recorded
                }
                return ($m['mode'] ?? '') === 'single' ? 'مرتجع - ' . $firstName : 'مرتجع كامل للفاتورة';
            default:
                return 'بيع تذكرة' . $shared;
        }
    }

    /** Date shown for the row: adjustments, new refunds and new re-issues show the day they happened. */
    public static function displayDate($row, $ticketInfo): string
    {
        $kind = self::marker($row)['kind'] ?? null;
        if ($kind === 'edit' || $kind === 'refund' || $kind === 'reissue') {
            return (string) $row->crt_date;
        }
        return $ticketInfo ? (string) $ticketInfo->invoice_date : (string) $row->created_at;
    }

    // ------------------------------------------------------------------ writing

    protected static function encode(array $marker): string
    {
        return json_encode($marker, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Before the first adjustment of an invoice: record each existing (unmarked)
     * ledger row's current passenger breakdown in its marker, so the original sale
     * keeps showing its original per-passenger amounts after passengers change.
     * Amounts, dates and every other column are left as they are.
     */
    public static function freezeRows(Invoice $invoice, Collection $users, string $kind = 'sale'): void
    {
        foreach (self::ledgerRowsAll($invoice) as $row) {
            if (self::marker($row)) {
                continue;
            }
            $lines = self::statementLines($row, $users, $users->count() > 0) ?? [];
            DB::table('account_statements')->where('id', $row->id)->update(['description' => self::encode([
                'kind' => $kind,
                'lines' => array_map(fn ($l) => ['pid' => $l['pid'] ?? null, 'name' => $l['name'], 'booking' => $l['booking'], 'debit' => $l['debit'], 'credit' => $l['credit']], $lines),
            ])]);
        }
    }

    /** Current passenger values used to measure an edit: pid => [name, booking, debit share, credit share]. */
    public static function snapshot(Invoice $invoice): array
    {
        $out = [];
        foreach (self::passengers($invoice) as $u) {
            $out[$u->id] = ['name' => (string) $u->client_name, 'booking' => (string) $u->client_booking_id,
                            'debit' => round((float) $u->client_bought_price, 2), 'credit' => round((float) $u->client_net_pice, 2)];
        }
        return $out;
    }

    /**
     * Start an edit: store the displayed shares of a historical refund in its
     * passengers. Returns the "before" snapshot (rows are frozen by recordEdit,
     * and only if the edit moves money).
     */
    public static function beginEdit(Invoice $invoice): array
    {
        self::materializeShares($invoice);
        return self::snapshot($invoice);
    }

    /** Passenger-like objects from a snapshot() (for freezing rows as they were before an edit). */
    protected static function snapshotUsers(array $snapshot): Collection
    {
        return collect($snapshot)->map(fn ($p, $pid) => (object) ['id' => $pid, 'client_name' => $p['name'], 'client_booking_id' => $p['booking'],
            'client_bought_price' => $p['debit'], 'client_net_pice' => $p['credit']])->values();
    }

    /**
     * Book an edit as adjustment rows dated today (the original rows never change).
     * $before/$after: snapshot() results; $oldAccounts/$newAccounts: [debit account, credit account].
     * Unchanged account: one row per side with each passenger's difference.
     * Changed account: the old account is reversed and the new account booked, both today.
     * Returns the created rows.
     */
    public static function recordEdit(Invoice $invoice, array $before, array $after, array $oldAccounts, array $newAccounts, $invoiceType, string $scope = 'whole'): array
    {
        // A pure data correction (names, ticket numbers ...) moves no money: nothing is
        // booked and the existing rows are left completely untouched.
        $moves = $oldAccounts !== $newAccounts;
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $pid) {
            foreach (['debit', 'credit'] as $side) {
                if (abs(($after[$pid][$side] ?? 0) - ($before[$pid][$side] ?? 0)) >= 0.005) $moves = true;
            }
        }
        if (!$moves) {
            return [];
        }
        // before the first adjustment: record the existing rows' breakdown as it was
        self::freezeRows($invoice, self::snapshotUsers($before));

        $created = [];
        foreach ([0 => 'debit', 1 => 'credit'] as $i => $side) {
            if ($oldAccounts[$i] === $newAccounts[$i]) {
                $lines = [];
                foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $pid) {
                    $delta = round(($after[$pid][$side] ?? 0) - ($before[$pid][$side] ?? 0), 2);
                    if (abs($delta) >= 0.005) {
                        $p = $after[$pid] ?? $before[$pid];
                        $lines[] = self::line($pid, $p, $side, $delta);
                    }
                }
                if ($lines) {
                    $created[] = self::adjustmentRow($invoice, $newAccounts[$i], $lines, $invoiceType, $scope);
                }
            } else {
                $rev = [];
                foreach ($before as $pid => $p) {
                    if (abs($p[$side]) >= 0.005) $rev[] = self::line($pid, $p, $side, -$p[$side]);
                }
                $new = [];
                foreach ($after as $pid => $p) {
                    if (abs($p[$side]) >= 0.005) $new[] = self::line($pid, $p, $side, $p[$side]);
                }
                if ($rev && $oldAccounts[$i]) $created[] = self::adjustmentRow($invoice, $oldAccounts[$i], $rev, $invoiceType, $scope);
                if ($new) $created[] = self::adjustmentRow($invoice, $newAccounts[$i], $new, $invoiceType, $scope);
            }
        }
        return $created;
    }

    /** A passenger's line on a debit-side or credit-side row: a positive difference stays on that side. */
    protected static function line($pid, array $p, string $side, float $delta): array
    {
        $onSide = $delta > 0;
        $amount = round(abs($delta), 2);
        $debit = ($side === 'debit') === $onSide ? $amount : null;
        return ['pid' => $pid, 'name' => $p['name'], 'booking' => $p['booking'], 'debit' => $debit, 'credit' => $debit === null ? $amount : null];
    }

    protected static function adjustmentRow(Invoice $invoice, $account, array $lines, $invoiceType, string $scope = 'whole'): AccountStatement
    {
        $net = round(array_sum(array_map(fn ($l) => ($l['debit'] ?? 0) - ($l['credit'] ?? 0), $lines)), 2);
        return AccountStatement::create([
            'supp_client_id' => $account,
            'invoice_type' => $invoiceType,
            'es_id' => $invoice->es_id,
            'invoice_date' => date('Y-m-d'),
            'debit_balance' => $net > 0 ? $net : 0,
            'credit_balance' => $net < 0 ? -$net : 0,
            'ledger_net_effect' => $net,
            'transaction_txt' => 'تعديل الفاتورة ' . $invoice->es_id,
            'transaction_type' => 1,
            'added_by' => Auth::id(),
            'crt_date' => date('Y-m-d'),
            'description' => self::encode(['kind' => 'edit', 'scope' => $scope, 'lines' => $lines]),
        ]);
    }

    /**
     * Refund passengers: copy $sourceUsers into $newSystemId with the entered
     * refund amounts split equally between them ($debitTotal is booked as the
     * refund's debit, $creditTotal as its credit). For a single-passenger refund
     * pass just that passenger: it receives the full amounts.
     * Returns the refund rows' markers: ['debit' => marker, 'credit' => marker].
     */
    public static function createRefundPassengers(Collection $sourceUsers, string $newSystemId, $debitTotal, $creditTotal, string $mode = 'full'): array
    {
        $sourceUsers = $sourceUsers->values();
        $debit = self::splitEqually($debitTotal, $sourceUsers->count());
        $credit = self::splitEqually($creditTotal, $sourceUsers->count());
        $dLines = []; $cLines = [];

        foreach ($sourceUsers as $i => $user) {
            $u = TicketUser::create([
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
            $dLines[] = ['pid' => $u->id, 'name' => (string) $user->client_name, 'booking' => (string) $user->client_booking_id, 'debit' => $debit[$i], 'credit' => null];
            $cLines[] = ['pid' => $u->id, 'name' => (string) $user->client_name, 'booking' => (string) $user->client_booking_id, 'debit' => null, 'credit' => $credit[$i]];
        }
        return [
            'debit' => self::encode(['kind' => 'refund', 'mode' => $mode, 'lines' => $dLines]),
            'credit' => self::encode(['kind' => 'refund', 'mode' => $mode, 'lines' => $cLines]),
        ];
    }

    /**
     * Edit ONE passenger of an invoice. Only that TicketUser changes; the original
     * rows stay as they are and adjustment rows dated today carry exactly that
     * passenger's difference. Returns [debit difference, credit difference].
     */
    public static function updatePassenger(Invoice $invoice, int $ticketUserId, array $attrs): array
    {
        return DB::transaction(function () use ($invoice, $ticketUserId, $attrs) {
            $user = TicketUser::where('ticket_system_id', $invoice->ticket_system_id)
                ->where('id', $ticketUserId)->lockForUpdate()->first();
            if (!$user) {
                throw new RuntimeException('passenger does not belong to this invoice');
            }
            $accounts = self::accounts($invoice);
            if (!$accounts[0] || !$accounts[1] || self::ledgerRowsAll($invoice)->isEmpty()) {
                throw new RuntimeException('invoice ledger rows not found');
            }

            $before = self::beginEdit($invoice);
            $user->refresh();
            $newDebit = round((float) $attrs['client_bought_price'], 2);
            $newCredit = round((float) $attrs['client_net_pice'], 2);
            $user->update(array_merge($attrs, ['client_bought_price' => $newDebit, 'client_net_pice' => $newCredit]));
            $after = self::snapshot($invoice);

            self::recordEdit($invoice, $before, $after, $accounts, $accounts, $invoice->invoice_section, 'passenger');

            // TicketVendor.price mirrors the cost total on normal invoices (it is not read anywhere).
            if (!self::isRefund($invoice)) {
                TicketVendor::where('ticket_system_id', $invoice->ticket_system_id)
                    ->update(['price' => round((float) self::passengers($invoice)->sum('client_net_pice'), 2)]);
            }

            return [round($newDebit - $before[$ticketUserId]['debit'], 2), round($newCredit - $before[$ticketUserId]['credit'], 2)];
        });
    }
}
