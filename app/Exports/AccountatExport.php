<?php

namespace App\Exports;

use App\Models\{AccountStatement, Supplier, Bond, Invoice, TicketUser, Bank, Collector, SubStorage, User};
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Account Statement Excel export.
 *
 * Mirrors the CURRENT Account Statement screen (accounts_statement/show.blade.php)
 * and Print Preview (accounts_statement/print_report.blade.php):
 *  - same filters and chronological order (crt_date ASC, id ASC)
 *  - same carried-forward opening balance (AccountStatement::openingBalanceBefore)
 *  - same running balance: once per transaction, float, rounded to 2 dp per row
 *  - same passenger breakdown: one row per passenger for flight tickets, all
 *    under the same invoice number; each passenger's ticket is its own
 *    movement (own amount, running balance steps through them), except when
 *    the ticket prices don't add up to the ledger amount (FLY-RD cancellations)
 *  - same columns (plus employee, as on the screen)
 *
 * All display rows are built here, so the Blade view runs no queries.
 */
class AccountatExport implements FromView, WithEvents, WithTitle
{
    /** Last column of the 12-column layout (RTL: column A is the right-most). */
    const LAST_COL = 'L';
    const HEADER_LABEL = 'رقم العملية';

    protected $invoice_beneficiaries;
    protected $date_from;
    protected $date_to;
    protected $transaction_type;

    function __construct($invoice_beneficiaries, $date_from, $date_to, $transaction_type)
    {
        $this->invoice_beneficiaries = $invoice_beneficiaries;
        $this->date_from = $date_from;
        $this->date_to = $date_to;
        $this->transaction_type = $transaction_type;
    }

    public function title(): string
    {
        return 'كشف حساب';
    }

    public function view(): View
    {
        $search_status = (int) $this->invoice_beneficiaries;

        $AccountStatements = AccountStatement::select('*')->where('is_storage', '!=', 1);

        if ($search_status == 0) {
            $supplier = null;
            $st = 0;
            $AccountStatements = $AccountStatements->where('is_supp_account', '!=', 1);
        } else {
            $AccountStatements = $AccountStatements->where('supp_client_id', $search_status);
            $supplier = Supplier::select('*')->where('id', $search_status)->first();
            abort_if(!$supplier, 404);
            $st = 1;
        }

        $isDateFiltered = isset($this->date_from) && isset($this->date_to);

        if ($isDateFiltered) {
            $AccountStatements = $AccountStatements->whereBetween('crt_date', [$this->date_from, $this->date_to]);
        }

        if (isset($this->transaction_type)) {
            $AccountStatements = $AccountStatements->where('transaction_type', $this->transaction_type);
        }

        // Same chronological ledger order as the screen and Print Preview.
        $AccountStatements = $AccountStatements->orderBy('crt_date', 'asc')->orderBy('id', 'asc')->get();

        // Same carried-forward opening balance as the screen and Print Preview.
        $opening_balance_for_period = $isDateFiltered
            ? AccountStatement::openingBalanceBefore($search_status, $this->invoice_beneficiaries, $this->date_from, $this->transaction_type)
            : 0;

        $total_debit_balance = 0;
        $total_credit_balance = 0;
        foreach ($AccountStatements as $AccountStatement) {
            $total_debit_balance += $AccountStatement->debit_balance;
            $total_credit_balance += $AccountStatement->credit_balance;
        }

        // Batched lookups (same as accounts_statement_search()) -- no per-row queries.
        $esIds = $AccountStatements->pluck('es_id')->filter()->unique();
        $bondsByEsId = Bond::whereIn('es_id', $esIds)->get()->keyBy('es_id');
        $invoicesByEsId = Invoice::whereIn('es_id', $esIds)->get()->keyBy('es_id');

        $ticketSystemIds = $invoicesByEsId->pluck('ticket_system_id')->filter()->unique();
        $usersByTicketSystemId = TicketUser::whereIn('ticket_system_id', $ticketSystemIds)->get()->groupBy('ticket_system_id');

        $bankIds = $bondsByEsId->where('money_way', 2)->pluck('bank_id')->filter()->unique();
        $banksById = Bank::whereIn('id', $bankIds)->get()->keyBy('id');

        $collectorIds = $bondsByEsId->filter(fn ($b) => $b->money_way != 1 && $b->money_way != 2)
            ->pluck('collector_info')->filter()->unique();
        $collectorsById = Collector::whereIn('id', $collectorIds)->get()->keyBy('id');

        $subIds = $AccountStatements->filter(fn ($a) => $a->trans_storage == 1 && (int) $a->sub_id !== 0)
            ->pluck('sub_id')->filter()->unique();
        $subStoragesById = SubStorage::whereIn('id', $subIds)->get()->keyBy('id');

        $addedByIds = $AccountStatements->pluck('added_by')->filter()->unique();
        $usersById = User::whereIn('id', $addedByIds)->get()->keyBy('id');

        $rows = [];
        $total_blnc = $opening_balance_for_period;

        foreach ($AccountStatements as $AccountStatement) {
            $closing = (float) $AccountStatement->debit_balance - (float) $AccountStatement->credit_balance;
            $balance_before_tx = $total_blnc;
            $total_blnc = round($total_blnc + $closing, 2);

            $ticket_info = null;
            $users = collect();
            $bond = null;

            if ($AccountStatement->trans_storage == 1) {
                $ticket_info = $bondsByEsId->get($AccountStatement->es_id);
                $bond = $ticket_info;
            } elseif ($AccountStatement->is_supp_account == 0) {
                $ticket_info = $invoicesByEsId->get($AccountStatement->es_id);
                if ($ticket_info) {
                    $users = $usersByTicketSystemId->get($ticket_info->ticket_system_id) ?? collect();
                }
            }

            $isTicket = $AccountStatement->transaction_type == 1 && $ticket_info;
            $hasPassengerBreakdown = $AccountStatement->es_id != 'FLY-OPEN-BALANCE'
                && $AccountStatement->transaction_type == 1 && $ticket_info && $users->count() > 0;
            // Same rule as the screen/print: one invoice number, but each passenger's
            // ticket is its own movement (own amount, own running balance). Only when
            // the TicketUser prices do not add up to the ledger amount (FLY-RD
            // cancellations) is the ledger amount kept as one movement on the first row.
            $debitHasAmount = $AccountStatement->debit_balance > 0;
            $creditHasAmount = $AccountStatement->credit_balance > 0;
            $perPassenger = $hasPassengerBreakdown
                && (!$debitHasAmount || abs($users->sum('client_bought_price') - $AccountStatement->debit_balance) < 0.005)
                && (!$creditHasAmount || abs($users->sum('client_net_pice') - $AccountStatement->credit_balance) < 0.005);

            $base = [
                'es_id' => $AccountStatement->es_id,
                'type' => $this->typeLabel($AccountStatement),
                'date' => $isTicket ? (string) $ticket_info->invoice_date : (string) $AccountStatement->created_at,
                'airline' => $isTicket ? (string) $ticket_info->invoice_airline : '',
                'route' => $isTicket ? trim($ticket_info->from_location . ' - ' . $ticket_info->to_location) : '',
                'travel_date' => $isTicket ? (string) $ticket_info->invoice_travel_date : '',
            ];
            $mem = $usersById->get($AccountStatement->added_by);

            if ($hasPassengerBreakdown) {
                foreach ($users->values() as $i => $user) {
                    if ($perPassenger) {
                        $debit = $debitHasAmount ? (float) $user->client_bought_price : null;
                        $credit = $creditHasAmount ? (float) $user->client_net_pice : null;
                        $balance_before_tx = round($balance_before_tx + (float) $debit - (float) $credit, 2);
                        $rowBalance = $balance_before_tx;
                    } else {
                        $debit = $debitHasAmount && $i === 0 ? (float) $AccountStatement->debit_balance : null;
                        $credit = $creditHasAmount && $i === 0 ? (float) $AccountStatement->credit_balance : null;
                        $rowBalance = $total_blnc;
                    }
                    $rows[] = $base + [
                        'details' => (string) $user->client_name,
                        'booking' => (string) $user->client_booking_id,
                        'debit' => $debit,
                        'credit' => $credit,
                        'balance' => $rowBalance,
                        'employee' => $i === 0 && $mem ? $mem->name : '',
                        'first' => $i === 0,
                    ];
                }
            } else {
                $rows[] = $base + [
                    'details' => $this->detailsLines($AccountStatement, $bond, $banksById, $collectorsById, $subStoragesById),
                    'booking' => '',
                    'debit' => (float) $AccountStatement->debit_balance,
                    'credit' => (float) $AccountStatement->credit_balance,
                    'balance' => $total_blnc,
                    'employee' => $mem ? $mem->name : '',
                    'first' => true,
                ];
            }
        }

        return view('excel.accountat_excel', [
            'rows' => $rows,
            'st' => $st,
            'supplier' => $supplier,
            'total_debit_balance' => $total_debit_balance,
            'total_credit_balance' => $total_credit_balance,
            'opening_balance_for_period' => $opening_balance_for_period,
            'total_blnc' => $total_blnc,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'isDateFiltered' => $isDateFiltered,
        ]);
    }

    /** "تذاكر / فواتير الطيران" etc. -- same wording as the Print Preview. */
    private function typeLabel($AccountStatement): string
    {
        $prefix = [1 => 'تذاكر / ', 2 => 'سندات / ', 3 => 'أرصدة افتتاحية / ', 4 => 'سداد / '][$AccountStatement->transaction_type] ?? '';
        $type = [
            1 => 'فواتير الطيران', 2 => 'فواتير تأشيرات', 3 => 'فواتير سياحة داخلية',
            4 => 'فواتير سياحة خارجية', 5 => 'فواتير سياحة دينية', 6 => 'فواتير تأمينات السفر',
            7 => 'فواتير تحاليل السفر', 8 => 'فواتير نقل سياحي', 9 => 'سند دفع',
            10 => 'سند قبض', 11 => 'أرصدة', 12 => 'سداد فاتورة',
        ][$AccountStatement->invoice_type] ?? 'أخرى';

        return $prefix . $type;
    }

    /** Transaction description lines for non-passenger rows (same content as the screen). */
    private function detailsLines($AccountStatement, $bond, $banksById, $collectorsById, $subStoragesById): array
    {
        $result = substr($AccountStatement->es_id, 0, 6);
        if ($result == 'FLY-RD') {
            $lines = ['إلغاء تذكرة ' . $AccountStatement->es_id];
        } elseif ($result == 'FLY-RS') {
            $lines = ['إعادة إصدار تذكرة ' . $AccountStatement->es_id];
        } else {
            $lines = [(string) $AccountStatement->transaction_txt];
        }

        if ($AccountStatement->transaction_type == 2 && $bond) {
            if ($bond->money_way == 1) {
                $way = 'دفع نقدي';
            } elseif ($bond->money_way == 2) {
                $bank = $banksById->get($bond->bank_id);
                $way = 'تحويل بنكي' . ($bank && $bank->bank_name ? ' - ' . $bank->bank_name : '');
            } else {
                $collector = $collectorsById->get($bond->collector_info);
                $way = 'تحصيل من المندوب: ' . ($collector ? $collector->name : '');
            }
            $lines[] = trim($way . ' ' . $bond->info);
        }

        if ($AccountStatement->trans_storage == 1 && (int) $AccountStatement->sub_id !== 0) {
            $sub = $subStoragesById->get((int) $AccountStatement->sub_id);
            if ($sub) {
                $lines[] = 'خزينة فرعية: ' . $sub->name;
            }
        }

        return array_values(array_filter(array_map('trim', $lines), 'strlen'));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COL;
                $highest = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

                // Locate the transaction table (header row .. totals row).
                $headerRow = null;
                $totalRow = null;
                for ($r = 1; $r <= $highest; $r++) {
                    $a = trim((string) $sheet->getCell("A$r")->getValue());
                    if ($headerRow === null && $a === self::HEADER_LABEL) {
                        $headerRow = $r;
                    } elseif ($headerRow !== null && $a === 'الإجمالي') {
                        $totalRow = $r;
                        break;
                    }
                }

                foreach (['A' => 17, 'B' => 24, 'C' => 20, 'D' => 12, 'E' => 18, 'F' => 13,
                          'G' => 38, 'H' => 16, 'I' => 15, 'J' => 15, 'K' => 16, 'L' => 16] as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }

                $all = $sheet->getStyle("A1:{$last}{$highest}");
                $all->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP)
                    ->setReadOrder(Alignment::READORDER_RTL);

                // Title block
                $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A1:{$last}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($headerRow) {
                    $end = $totalRow ?? $highest;
                    $sheet->getStyle("A{$headerRow}:{$last}{$end}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');

                    $head = $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}");
                    $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('212529');
                    $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                    // Amount columns right-aligned as numbers.
                    $sheet->getStyle("I" . ($headerRow + 1) . ":K{$end}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    if ($totalRow) {
                        $tot = $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}");
                        $tot->getFont()->setBold(true);
                        $tot->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
                    }

                    $sheet->freezePane('A' . ($headerRow + 1));
                    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
                }

                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }
}
