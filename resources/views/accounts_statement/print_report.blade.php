@extends('layouts.print')
@section('content')
<?php

if($st == 0){
    $title_1 = "كشف حساب عام";
}else{
    $title_1 = "كشف حساب $supplier->name";
}

?>
@section('title',$title_1)
<style>
    body {
        font-family: 'Arial', sans-serif;
        direction: rtl;
        text-align: right;
        font-weight: bold;
    }
    
    .page_print {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
        padding: 15px;
        background: white;
    }
    
    .header-section {
        border: 3px solid #333;
        padding: 15px;
        margin-bottom: 15px;
        background: #f8f9fa;
    }
    
    .company-logo {
        width: 120px;
        height: auto;
        float: right;
        margin-left: 15px;
    }
    
    .header-title {
        font-size: 18px;
        font-weight: bold;
        margin: 0;
        line-height: 1.4;
        color: #333;
    }
    
    .balance-section {
        border: 2px solid #333;
        padding: 10px;
        margin: 10px 0;
        background: #f0f0f0;
        display: flex;
        justify-content: space-between;
    }
    
    .balance-item {
        font-size: 14px;
        font-weight: bold;
        color: #333;
    }

    #professional-table {
        font-family: 'Arial', sans-serif;
        border-collapse: collapse;
        width: 100%;
        table-layout: fixed;
        margin: 10px 0;
        border: 3px solid #333;
    }

    #professional-table thead {
        display: table-header-group; /* repeat header row on every printed page */
    }

    #professional-table tr {
        page-break-inside: avoid;
    }

    #professional-table th {
        background: #333;
        color: white;
        padding: 8px 4px;
        text-align: right;
        font-weight: bold;
        font-size: 11px;
        border: 2px solid #333;
    }

    #professional-table td {
        padding: 6px 4px;
        border: 1px solid #333;
        font-size: 10px;
        font-weight: bold;
        vertical-align: top;
        line-height: 1.3;
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .ey-details-line{
        white-space: normal;
    }

    #professional-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    
    .transaction-details {
        line-height: 1.2;
        font-weight: bold;
    }

    .inline-info {
        display: inline;
        margin-left: 8px;
    }

    .passenger-names {
        margin-top: 3px;
        font-weight: bold;
    }

    /* table-layout:fixed already caps this column's width; this is an extra
       safety net so a long passenger name can never push a cell wider than
       its fixed column even under an edge case. */
    .ey-trip-info{
        max-width: 420px;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    .ey-trip-info > div{
        margin-bottom: 3px;
    }
    .ey-trip-info > div:last-child{
        margin-bottom: 0;
    }
    .ey-trip-info .ey-passenger{
        padding-right: 10px;
    }
    .ey-ltr{
        direction: ltr;
        unicode-bidi: isolate;
        display: inline-block;
        max-width: 100%;
    }
    .amount-debit, .amount-credit, .amount-balance {
        color: #000;
        font-weight: bold;
        text-align: center;
    }

    /* #professional-table td (an ID + element selector) is more specific than a
       plain class selector, so it was winning over the class rule above and
       still breaking these numbers mid-value despite nowrap. Match/exceed its
       specificity here instead of relying on source order. */
    #professional-table td.amount-debit,
    #professional-table td.amount-credit,
    #professional-table td.amount-balance {
        white-space: nowrap !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
    }
    
    .total-row {
        background: #333 !important;
        color: white !important;
        font-weight: bold;
    }
    
    .total-row td {
        border: 2px solid #333 !important;
        padding: 10px 4px !important;
        font-size: 11px !important;
        font-weight: bold !important;
    }

    #professional-table tr.total-row td {
        white-space: nowrap !important;
        word-break: normal !important;
        overflow-wrap: normal !important;
    }
    
    .summary-table {
        border: 3px solid #333;
        margin-top: 15px;
        width: 100%;
        border-collapse: collapse;
    }
    
    .summary-table td {
        padding: 8px 10px;
        border: 1px solid #333;
        font-weight: bold;
        font-size: 12px;
    }
    
    .summary-table .summary-label {
        background: #f0f0f0;
        font-weight: bold;
        color: #333;
        width: 40%;
    }
    
    .summary-table .summary-amount {
        font-weight: bold;
        color: #333;
        text-align: center;
        white-space: nowrap;
    }
    
    .summary-table .final-total {
        background: #333;
        color: white;
    }

    /* .summary-amount's own `color: #333` (above) otherwise beats the inherited
       white from .final-total, since an explicit rule on the cell itself always
       wins over an inherited value -- making this row's amount invisible (dark
       text on the row's dark background). Target the cell directly instead. */
    .summary-table .final-total .summary-amount {
        color: white !important;
    }
    
    @media print {
        .page_print {
            padding: 10px;
        }
        
        body, * {
            font-weight: bold !important;
            color: #000 !important;
        }
        
        #professional-table th {
            background: #333 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
        }
        
        .total-row {
            background: #333 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
        }
        
        .summary-table .final-total {
            background: #333 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
        }
    }
</style>

<div class="page_print">
    <!-- Header Section -->
    <div class="header-section">
        {{-- logo-dark.png is an unused Velzon starter-template asset; flymix_colored.png
             is the actual Eyana brand logo used app-wide. --}}
        <img src="{{asset('assets/images/flymix_colored.png')}}" class="company-logo" alt="Eyana">
        <div>
            <h1 class="header-title">
                كشف حساب 
                @if($st == 1)
                    @if($supplier->acc_type == 1) العميل @else المورد @endif
                    : {{$supplier->name}}
                @else
                    عام
                @endif
            </h1>
            @if($date_from !== null && $date_to !== null)
                <p style="font-size: 14px; margin: 5px 0;">فترة التقرير: من {{$date_from}} إلى {{$date_to}}</p>
            @endif
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Balance Section -->
    @if($st == 1)
    <div class="balance-section">
        <div class="balance-item">
            رصيد افتتاحي دائن: {{number_format($supplier->opening_credit_balance, 2)}} جنيه
        </div>
        <div class="balance-item">
            رصيد افتتاحي مدين: {{number_format($supplier->debit_opening_balance, 2)}} جنيه
        </div>
    </div>
    @endif

    <!-- Transactions Table -->
    <table id="professional-table" dir="rtl">
        <thead>
            <tr>
                <th style="width: 6%;">رقم العملية</th>
                <th style="width: 7%;">نوع العملية</th>
                <th style="width: 6%;">تاريخ العملية</th>
                <th style="width: 8%;">نوع الطيران</th>
                <th style="width: 11%;">خط السير</th>
                <th style="width: 7%;">تاريخ السفر</th>
                <th style="width: 16%;">المسافر</th>
                <th style="width: 8%;">رقم الحجز</th>
                <th style="width: 11%;">مدين</th>
                <th style="width: 11%;">دائن</th>
                <th style="width: 9%;">الرصيد</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Seeded with the carried-forward historical balance (0 when there is no
            // date filter, so the full statement is unaffected) so the running balance
            // below continues from the account's real balance instead of restarting at 0.
            $total_blnc = $opening_balance_for_period;
            $total_cumulative_balance = 0;
            foreach($AccountStatements as $AccountStatement){
                $total_cumulative_balance += $AccountStatement->cumulative_balance;
            }

            // Batch-fetch every per-row lookup once instead of inside the loop (was N+1:
            // up to 5 queries per row across Bond/Invoice/TicketUser/Bank/Collector/SubStorage).
            $storage_es_ids = [];
            $invoice_es_ids = [];
            foreach ($AccountStatements as $as_row) {
                if ($as_row->trans_storage == 1) {
                    $storage_es_ids[] = $as_row->es_id;
                } elseif ($as_row->is_supp_account == 0) {
                    $invoice_es_ids[] = $as_row->es_id;
                }
            }

            $bonds_by_es_id = App\Models\Bond::whereIn('es_id', array_unique($storage_es_ids))->get()
                ->groupBy('es_id')->map(function($g){ return $g->first(); });

            $invoices_by_es_id = App\Models\Invoice::whereIn('es_id', array_unique($invoice_es_ids))->get()
                ->groupBy('es_id')->map(function($g){ return $g->first(); });

            $ticket_system_ids = $invoices_by_es_id->pluck('ticket_system_id')->unique()->filter()->values()->all();
            $ticket_users_by_ticket_system_id = App\Models\TicketUser::whereIn('ticket_system_id', $ticket_system_ids)->get()
                ->groupBy('ticket_system_id');

            $bank_ids = $bonds_by_es_id->where('money_way', 2)->pluck('bank_id')->unique()->filter()->values()->all();
            $banks_by_id = App\Models\Bank::whereIn('id', $bank_ids)->get()->keyBy('id');

            $collector_ids = $bonds_by_es_id->reject(function($b){ return in_array($b->money_way, [1, 2]); })
                ->pluck('collector_info')->unique()->filter()->values()->all();
            $collectors_by_id = App\Models\Collector::whereIn('id', $collector_ids)->get()->keyBy('id');

            $sub_storage_ids = [];
            foreach ($AccountStatements as $as_row) {
                if ($as_row->trans_storage == 1 && $as_row->sub_id != 0) {
                    $sub_storage_ids[] = (int) $as_row->sub_id;
                }
            }
            $sub_storages_by_id = App\Models\SubStorage::whereIn('id', array_unique($sub_storage_ids))->get()->keyBy('id');
            ?>

            <?php $x = 0; ?>
            @foreach($AccountStatements as $key => $AccountStatement)
            <?php
            // Cumulative running balance: computed exactly once per accounting
            // transaction (this AccountStatement row), exactly as before this
            // change. Passenger breakdown rows below only DISPLAY this same
            // value on their first row -- never recomputed or added again --
            // so a multi-passenger invoice still advances the running balance
            // by exactly one transaction's worth, never once per passenger.
            // Balances are DECIMAL(14,2): keep the cents (an (int) cast truncated them).
            $closing = (float) $AccountStatement->debit_balance - (float) $AccountStatement->credit_balance;
            $total_blnc = round($total_blnc + $closing, 2);
            $total_blnc_display = number_format($total_blnc, 2);

            $ticket_info = null;
            $users = collect();
            $bond = null;

            if($AccountStatement->trans_storage == 1){
                // Defensive fallback: guards against a row where transaction_type implies a
                // bond but no matching Bond row exists (pre-existing data/view edge case, not
                // introduced by this batch -- see Batch E report).
                $ticket_info = $bonds_by_es_id->get($AccountStatement->es_id) ?? new App\Models\Bond();
                $bond = $ticket_info;
            }else{
                if($AccountStatement->is_supp_account == 0){
                    $ticket_info = $invoices_by_es_id->get($AccountStatement->es_id) ?? new App\Models\Invoice();
                    $users = $ticket_users_by_ticket_system_id->get($ticket_info->ticket_system_id, collect());
                }
            }

            $result = substr($AccountStatement->es_id, 0, 6);

            if($AccountStatement->invoice_type == 1){ $type = "فواتير الطيران"; }
            elseif($AccountStatement->invoice_type == 2){ $type = "فواتير تأشيرات"; }
            elseif($AccountStatement->invoice_type == 3){ $type = "فواتير سياحة داخلية"; }
            elseif($AccountStatement->invoice_type == 4){ $type = "فواتير سياحة خارجية"; }
            elseif($AccountStatement->invoice_type == 5){ $type = "فواتير سياحة دينية"; }
            elseif($AccountStatement->invoice_type == 6){ $type = "فواتير تأمينات السفر"; }
            elseif($AccountStatement->invoice_type == 7){ $type = "فواتير تحاليل السفر"; }
            elseif($AccountStatement->invoice_type == 8){ $type = "فواتير نقل سياحي"; }
            elseif($AccountStatement->invoice_type == 9){ $type = "سند دفع"; }
            elseif($AccountStatement->invoice_type == 10){ $type = "سند قبض"; }
            elseif($AccountStatement->invoice_type == 11){ $type = "أرصدة"; }
            elseif($AccountStatement->invoice_type == 12){ $type = "سداد فاتورة"; }
            else { $type = "أخرى"; }

            // Passenger breakdown only applies to flight/ticket transactions with
            // real, resolvable TicketUser records. Everything else (bonds, the
            // special opening-balance row, invoices with no resolvable passenger
            // rows) falls back to exactly one row, same as before this change.
            $isOpeningBalanceRow = $AccountStatement->es_id == "FLY-OPEN-BALANCE";
            $hasPassengerBreakdown = !$isOpeningBalanceRow && $AccountStatement->transaction_type == 1 && $ticket_info && $users->count() > 0;
            $breakdownRows = $hasPassengerBreakdown ? $users->values() : collect([null]);

            // One ticket = one financial transaction, however many passengers it has.
            // Its debit/credit is shown ONCE, on the first passenger row (other passenger
            // rows leave the amount blank), exactly as it is counted once in $total_blnc
            // and in the period totals. Only the side that carries an amount is shown.
            $debitHasAmount = $AccountStatement->debit_balance > 0;
            $creditHasAmount = $AccountStatement->credit_balance > 0;
            ?>
            @foreach($breakdownRows as $rowIndex => $user)
            {{-- The booked opening-balance row (FLY-OPEN-BALANCE) is a real ledger row: render it
                 in full like the screen and Excel (its amounts and balance were hidden before). --}}
            <tr @if($isOpeningBalanceRow) style="font-weight: bold; background: #f0f0f0;" @endif>
                <td style="font-weight: bold;">{{$AccountStatement->es_id}}</td>
                <td>
                    @if($AccountStatement->transaction_type == 1)
                        تذاكر /
                    @elseif($AccountStatement->transaction_type == 2)
                        سندات /
                    @elseif($AccountStatement->transaction_type == 3)
                        أرصدة افتتاحية /
                    @elseif($AccountStatement->transaction_type == 4)
                        سداد /
                    @endif
                    {{$type}}
                </td>

                <td>
                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                        {{$ticket_info->invoice_date}}
                    @else
                        {{$AccountStatement->created_at}}
                    @endif
                </td>

                <td>
                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                        <span class="ey-ltr">{{$ticket_info->invoice_airline}}</span>
                    @endif
                </td>
                <td>
                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                        <span class="ey-ltr">{{$ticket_info->from_location}} - {{$ticket_info->to_location}}</span>
                    @endif
                </td>
                <td>
                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                        {{$ticket_info->invoice_travel_date}}
                    @endif
                </td>

                <td>
                    @if($hasPassengerBreakdown)
                        <span class="ey-ltr">{{$user->client_name}}</span>
                    @else
                        <div class="ey-trip-info">
                            <div>
                                @if($result == "FLY-RD")
                                    إلغاء تذكرة {{$AccountStatement->es_id}}
                                @elseif($result == "FLY-RS")
                                    إعادة إصدار تذكرة {{$AccountStatement->es_id}}
                                @else
                                    {{$AccountStatement->transaction_txt}}
                                @endif
                            </div>

                            @if($AccountStatement->transaction_type == 2 && $bond)
                                <div>
                                @if($bond->money_way == 1)
                                    دفع نقدي
                                @elseif($bond->money_way == 2)
                                    تحويل بنكي
                                    <?php
                                    $bank_info = $banks_by_id->get($bond->bank_id) ?? new App\Models\Bank();
                                    ?>
                                    - {{$bank_info->bank_name}}
                                @else
                                    تحصيل من المندوب:
                                    <?php
                                    $collector_info = $collectors_by_id->get($bond->collector_info) ?? new App\Models\Collector();
                                    ?>
                                    {{$collector_info->name}}
                                @endif
                                </div>
                            @endif

                            @if($AccountStatement->trans_storage == 1 && $AccountStatement->sub_id != 0)
                                <?php
                                $sub_id = (int) $AccountStatement->sub_id;
                                $min_info3 = $sub_storages_by_id->get($sub_id) ?? new App\Models\SubStorage();
                                ?>
                                <div>خزينة فرعية: {{$min_info3->name}}</div>
                            @endif
                        </div>
                    @endif
                </td>

                <td>
                    @if($hasPassengerBreakdown)
                        <span class="ey-ltr">{{$user->client_booking_id}}</span>
                    @endif
                </td>

                <td class="amount-debit">
                    @if(!$hasPassengerBreakdown || ($debitHasAmount && $rowIndex === 0))
                        {{number_format($AccountStatement->debit_balance , 2)}}
                    @endif
                </td>
                <td class="amount-credit">
                    @if(!$hasPassengerBreakdown || ($creditHasAmount && $rowIndex === 0))
                        {{number_format($AccountStatement->credit_balance , 2)}}
                    @endif
                </td>
                <td class="amount-balance">
                    {{-- Repeated on every passenger row of this same booking, same as
                    the screen -- display-only, the transaction is still counted once. --}}
                    {{$total_blnc_display}}
                </td>
            </tr>
            @endforeach
            <?php $x++; ?>
            @endforeach
            
            <!-- Total Row -->
            <tr class="total-row">
                <td colspan="8" style="text-align: center;">الإجمالي</td>
                <td>{{number_format($total_debit_balance , 2)}}</td>
                <td>{{number_format($total_credit_balance , 2)}}</td>
                <td>{{number_format($total_blnc,2)}}</td>
            </tr>
        </tbody>
    </table>

    <!-- Summary Table -->
    <table class="summary-table" dir="rtl">
        <tbody>
            @if($st == 1)
            <tr>
                <td class="summary-label">رصيد افتتاحي (دائن)</td>
                <td class="summary-amount">{{number_format($supplier->opening_credit_balance , 2)}} جنيه</td>
            </tr>
            <tr>
                <td class="summary-label">رصيد افتتاحي (مدين)</td>
                <td class="summary-amount">{{number_format($supplier->debit_opening_balance , 2)}} جنيه</td>
            </tr>
            @endif
            @if($date_from !== null && $date_to !== null)
            <tr>
                <td class="summary-label">الرصيد الافتتاحي (بداية الفترة)</td>
                <td class="summary-amount">{{number_format($opening_balance_for_period , 2)}} جنيه</td>
            </tr>
            @endif
            <tr>
                <td class="summary-label">إجمالي المدين@if($date_from !== null && $date_to !== null) للفترة @endif</td>
                <td class="summary-amount">{{number_format($total_debit_balance , 2)}} جنيه</td>
            </tr>
            <tr>
                <td class="summary-label">إجمالي الدائن@if($date_from !== null && $date_to !== null) للفترة @endif</td>
                <td class="summary-amount">{{number_format($total_credit_balance , 2)}} جنيه</td>
            </tr>
            <tr class="final-total">
                <td class="summary-label">@if($date_from !== null && $date_to !== null) الرصيد النهائي @else الإجمالي النهائي @endif</td>
                <td class="summary-amount">{{number_format($total_blnc , 2)}} جنيه</td>
            </tr>
        </tbody>
    </table>

    <x-print-footer :note="$title_1" />
</div>

@endsection