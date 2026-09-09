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
        margin: 10px 0;
        border: 3px solid #333;
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
    
    .amount-debit {
        color: #000;
        font-weight: bold;
        text-align: center;
    }
    
    .amount-credit {
        color: #000;
        font-weight: bold;
        text-align: center;
    }
    
    .amount-balance {
        color: #000;
        font-weight: bold;
        text-align: center;
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
    }
    
    .summary-table .final-total {
        background: #333;
        color: white;
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
        <img src="{{asset('assets/images/flymix_colored.png')}}" class="company-logo" alt="Company Logo">
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
                <th style="width: 10%;">رقم العملية</th>
                <th style="width: 15%;">نوع العملية</th>
                <th style="width: 12%;">تاريخ العملية</th>
                <th style="width: 43%;">بيان العملية</th>
                <th style="width: 7%;">مدين</th>
                <th style="width: 7%;">دائن</th>
                <th style="width: 6%;">الرصيد</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_blnc = 0;
            $total_cumulative_balance = 0;
            foreach($AccountStatements as $AccountStatement){
                $total_cumulative_balance += $AccountStatement->cumulative_balance;
            }
            ?>

            <?php $x = 0; ?>
            @foreach($AccountStatements as $key => $AccountStatement)
            <?php
            $closing = (int) $AccountStatement->debit_balance - (int) $AccountStatement->credit_balance;
            $total_blnc += $closing;

            if($AccountStatement->trans_storage == 1){
                $ticket_info = App\Models\Bond::select('*')->where('es_id' , $AccountStatement->es_id)->get();
                $ticket_info = $ticket_info[0];    
                $bond = $ticket_info;    
            }else{
                if($AccountStatement->is_supp_account == 0){
                    $ticket_info = App\Models\Invoice::select('*')->where('es_id' , $AccountStatement->es_id)->get();
                    $ticket_info = $ticket_info[0];
                    $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $ticket_info->ticket_system_id)->get();
                    
                    $total_client_net_pice = 0;
                    $total_client_bought_price = 0;
                    
                    foreach($users as $user){
                        $total_client_net_pice += $user->client_net_pice;
                        $total_client_bought_price += $user->client_bought_price;
                    }  
                }  
            }
            
            $result = substr($AccountStatement->es_id, 0, 6);
            ?> 
            <tr>
                @if($AccountStatement->es_id == "FLY-OPEN-BALANCE")
                    <td colspan="4" style="text-align: center; font-weight: bold; background: #f0f0f0;">الأرصدة الافتتاحية</td>
                @else
                    <td style="font-weight: bold;">{{$AccountStatement->es_id}}</td>
                    <td>
                        <?php
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
                        ?>
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
                        @if($AccountStatement->transaction_type == 1) 
                            {{$ticket_info->invoice_date}}
                        @else
                            {{$AccountStatement->created_at}}
                        @endif
                    </td>
                    
                    <td>
                        <div class="transaction-details">
                            @if($result == "FLY-RD")
                                إلغاء تذكرة {{$AccountStatement->es_id}}
                            @elseif($result == "FLY-RS")
                                إعادة إصدار تذكرة {{$AccountStatement->es_id}}
                            @else
                                {{$AccountStatement->transaction_txt}}
                            @endif
                            
                            @if($AccountStatement->transaction_type == 1)
                                <span class="inline-info">خط الطيران: {{$ticket_info->invoice_airline}}</span>
                                <span class="inline-info">وجهة السفر: {{$ticket_info->from_location}} - {{$ticket_info->to_location}}</span>
                                <span class="inline-info">أرقام الحجز: 
                                    @foreach($users as $user) 
                                        {{$user->client_booking_id}}@if(!$loop->last) / @endif
                                    @endforeach
                                </span>

                                <div class="passenger-names">
                                    الركاب: @foreach($users as $user) 
                                        {{$user->client_name}}@if(!$loop->last) / @endif
                                    @endforeach
                                </div>
                            @endif
                            
                            @if($AccountStatement->transaction_type == 2)
                                @if($bond->money_way == 1)
                                    دفع نقدي 
                                @elseif($bond->money_way == 2)
                                    تحويل بنكي
                                    <?php
                                    $bank_info = App\Models\Bank::select('*')->where('id',$bond->bank_id)->get();
                                    $bank_info = $bank_info[0];
                                    ?>
                                    - {{$bank_info->bank_name}}
                                @else
                                    تحصيل من المندوب: 
                                    <?php
                                    $collector_info = App\Models\Collector::select('*')->where('id',$bond->collector_info)->get();
                                    $collector_info = $collector_info[0];
                                    ?>
                                    {{$collector_info->name}}
                                @endif
                            @endif
                            
                            @if($AccountStatement->trans_storage == 1 && $AccountStatement->sub_id != 0)
                                <?php
                                $sub_id = (int) $AccountStatement->sub_id;
                                $min_info3 = App\Models\SubStorage::select('*')->where('id' , $sub_id)->get();
                                $min_info3 = $min_info3[0];
                                ?>
                                <span class="inline-info">خزينة فرعية: {{$min_info3->name}}</span>
                            @endif
                        </div>
                    </td>
                @endif
                
                <td class="amount-debit">{{number_format($AccountStatement->debit_balance , 2)}}</td>
                <td class="amount-credit">{{number_format($AccountStatement->credit_balance , 2)}}</td>
                <td class="amount-balance">{{number_format($total_blnc , 2)}}</td>
            </tr>
            <?php $x++; ?>
            @endforeach
            
            <!-- Total Row -->
            <tr class="total-row">
                <td colspan="4" style="text-align: center;">الإجمالي</td>
                <td>{{number_format($total_debit_balance , 2)}}</td>
                <td>{{number_format($total_credit_balance , 2)}}</td>
                @if($st == 1)
                    <td>{{number_format($supplier->opening_credit_balance + $supplier->debit_opening_balance + $total_debit_balance - $total_credit_balance,2)}}</td>
                @else
                    <td>{{number_format($total_debit_balance - $total_credit_balance,2)}}</td>
                @endif
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
            <tr>
                <td class="summary-label">إجمالي المدين</td>
                <td class="summary-amount">{{number_format($total_debit_balance , 2)}} جنيه</td>
            </tr>
            <tr>
                <td class="summary-label">إجمالي الدائن</td>
                <td class="summary-amount">{{number_format($total_credit_balance , 2)}} جنيه</td>
            </tr>
            <tr class="final-total">
                <td class="summary-label">الإجمالي النهائي</td>
                @if($st == 1)
                    <td class="summary-amount">
                        @if(count($AccountStatements) == 1)
                            {{number_format($total_debit_balance - $total_credit_balance , 2)}}          
                        @else
                            {{number_format($total_debit_balance - $total_credit_balance , 2)}}                         
                        @endif
                        جنيه
                    </td>
                @else
                    <td class="summary-amount">{{number_format($total_debit_balance - $total_credit_balance , 2)}} جنيه</td>
                @endif
            </tr>
        </tbody>
    </table>
</div>

@endsection