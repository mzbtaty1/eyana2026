@extends('layouts.print')
@section('content')
<style>
    /* Visual refinement pass (presentation only -- no value/markup changes below
       touch any calculated or displayed number, es_id, or transaction text). */

    .border_print{
    }

    .ey-opening-balances{
        display: flex;
        flex-wrap: wrap;
        gap: 10px 28px;
        background: var(--ey-brand-light);
        border: 1px solid var(--ey-border);
        border-radius: 4px;
        padding: 10px 16px;
        margin-bottom: 16px;
    }
    .ey-opening-balances .b_balnce{
        font-size: 13px;
        font-weight: 700;
        color: var(--ey-brand);
        line-height: 1.4;
    }

    /* Base table look (border-collapse/thead-repeat/page-break already come from
       the shared .ey-print-table rules in layouts/print.blade.php). */
    #customers{
        font-family: "Noto Kufi Arabic", Arial, Helvetica, sans-serif;
        table-layout: fixed;
    }
    #customers tr:nth-child(even){
        background-color: #f7f9fb;
    }
    #customers th{
        font-size: 11px;
        line-height: 1.4;
        vertical-align: middle;
    }
    #customers td{
        font-size: 12px;
        line-height: 1.65;
        vertical-align: top;
        padding: 8px 10px;
    }

    /* Column widths (7 columns: # | نوع العملية | تاريخ العملية | بيانات الرحلة/المسافر
       | مدين | دائن | الرصيد). The grouped trip/passenger column gets the most room. */
    #customers th:nth-child(1), #customers td:nth-child(1){ width: 6%; }
    #customers th:nth-child(2), #customers td:nth-child(2){ width: 10%; }
    #customers th:nth-child(3), #customers td:nth-child(3){ width: 9%; }
    #customers th:nth-child(4), #customers td:nth-child(4){ width: 45%; }
    #customers th:nth-child(5), #customers td:nth-child(5),
    #customers th:nth-child(6), #customers td:nth-child(6),
    #customers th:nth-child(7), #customers td:nth-child(7){ width: 10%; }

    /* Grouped trip/passenger column: allow natural wrapping/line breaks instead of
       a cramped single line. */
    #customers td:nth-child(4){
        white-space: normal;
        word-break: break-word;
    }
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
    /* Keep Latin passenger names / booking / ticket references from being
       reversed by the surrounding RTL context. */
    .ey-ltr{
        direction: ltr;
        unicode-bidi: isolate;
        display: inline-block;
        max-width: 100%;
    }

    /* Financial columns: clearly separated from the descriptive columns, bold,
       aligned numerals. */
    #customers th:nth-child(5), #customers td:nth-child(5),
    #customers th:nth-child(6), #customers td:nth-child(6),
    #customers th:nth-child(7), #customers td:nth-child(7){
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        background: #fbfcfd;
        white-space: nowrap;
    }
    #customers td:nth-child(5){
        border-right: 2px solid var(--ey-brand);
    }

    /* Totals / summary block */
    .ey-summary{
        margin-top: 14px;
        border: 1px solid var(--ey-border);
        border-radius: 4px;
        overflow: hidden;
        page-break-inside: avoid;
    }
    .ey-summary table{
        width: 100%;
        border-collapse: collapse;
    }
    .ey-summary td{
        padding: 8px 14px;
        font-size: 12px;
        border-bottom: 1px solid var(--ey-border);
    }
    .ey-summary tr:last-child td{
        border-bottom: none;
    }
    .ey-summary .ey-summary-label{
        color: var(--ey-muted);
    }
    .ey-summary .ey-summary-amount{
        text-align: left;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .ey-summary tr.ey-summary-final td{
        background: var(--ey-brand);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
    }
</style>
<div class="border_print">

    <x-print-header
        title="كشف حساب شامل"
        :heading="($supplier->acc_type == 1 ? 'العميل' : 'المورد').' : '.$supplier->name"
    />
        <div class="ey-opening-balances">
            <span class="b_balnce">رصيد افتتاحى (دائن) : {{$supplier->opening_credit_balance}} جنيها</span>
            <span class="b_balnce">رصيد افتتاحى (مدين) : {{$supplier->debit_opening_balance}} جنيها</span>
        </div>

        <div class="t">
               <table id="customers" class="ey-print-table" dir="rtl">
               <thead>
                  <tr>
                     <th style="text-align: right;">رقم العملية</th>
                     <th style="text-align: right;">نوع العملية</th>
                     <th style="text-align: right;">تاريخ العملية</th>
                     <th style="text-align: right;">بيانات الرحلة / المسافر</th>
                     <th style="text-align: right;">مدين</th>
                     <th style="text-align: right;">دائن</th>
                     <th style="text-align: right;">الرصيد</th>


                  </tr>
               </thead>
               <tbody>
                   <?php

                    $total_cumulative_balance = 0;
                   foreach($AccountStatements as $AccountStatement){
$total_cumulative_balance += $AccountStatement->cumulative_balance;
 }

                   // Batch-fetch invoices/ticket-users once instead of per-row (was N+1: 2 queries per statement row).
                   $es_ids = $AccountStatements->pluck('es_id')->unique()->values()->all();
                   $ticket_info_by_es_id = App\Models\Invoice::whereIn('es_id', $es_ids)->get()
                       ->groupBy('es_id')->map(function($g){ return $g->first(); });
                   $ticket_system_ids = $ticket_info_by_es_id->pluck('ticket_system_id')->unique()->filter()->values()->all();
                   $ticket_users_by_ticket_system_id = App\Models\TicketUser::whereIn('ticket_system_id', $ticket_system_ids)->get()
                       ->groupBy('ticket_system_id');
                   ?>
                  @foreach($AccountStatements as $AccountStatement)

                   <?php
                   // Defensive fallback: a small number of AccountStatement rows reference an
                   // es_id with no matching Invoice (pre-existing data/view mismatch, not
                   // introduced by this batch — see Batch E report). Without this, the whole
                   // print view throws instead of just leaving that row's invoice-only columns blank.
                   $ticket_info = $ticket_info_by_es_id->get($AccountStatement->es_id) ?? new App\Models\Invoice();
                   $users = $ticket_users_by_ticket_system_id->get($ticket_info->ticket_system_id, collect());


                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                     
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                      
                   
                   
                   ?>
                  <tr>
                     <td style="text-align: right;">{{$AccountStatement->es_id}}</td>
                     <td style="text-align: right;"><?php
                          if($AccountStatement->invoice_type == 1){
                              $type = "فواتير الطيران";
                          }elseif($AccountStatement->invoice_type == 2){
                              $type = "فواتير تأشيرات";
                          }elseif($AccountStatement->invoice_type == 3){
                              $type = "فواتير سياحه داخليه";
                          }elseif($AccountStatement->invoice_type == 4){
                              $type = "فواتير سياحه خارجيه";
                          }elseif($AccountStatement->invoice_type == 5){
                              $type = "فواتير سياحه دينيه";
                          }elseif($AccountStatement->invoice_type == 6){
                              $type = "فواتير تأمينات السفر";
                          }elseif($AccountStatement->invoice_type == 7){
                              $type = "فواتير تحاليل السفر";
                          }elseif($AccountStatement->invoice_type == 8){
                              $type = "فواتير نقل سياحى";
                          }elseif($AccountStatement->invoice_type == 9){
                              $type = "سند دفع";
                          }elseif($AccountStatement->invoice_type == 10){
                              $type = "سند قبض";
                          }elseif($AccountStatement->invoice_type == 11){
                              $type = "أرصدة";
                          }elseif($AccountStatement->invoice_type == 12){
                              $type = "سداد فاتورة";
                          }else{
                              // Defensive fallback: this switch previously had no default case
                              // and crashed the whole print view (undefined $type) for any
                              // invoice_type outside 1-8 -- pre-existing gap, not introduced by
                              // this batch, see Batch E report. Labels above match the fuller
                              // switch already used in accounts_statement/print_report.blade.php.
                              $type = "أخرى";
                          }
                          ?>
                         @if($AccountStatement->transaction_type == 1)
                         تذاكر / 
                         @endif
                      {{$type}}
                      </td>
                     <td style="text-align: right;">{{$ticket_info->invoice_date}}</td>
                     <td>
                         <div class="ey-trip-info">
                         @if($AccountStatement->transaction_type == 1)
                             <div><b>حجز الرحلة:</b> {{$AccountStatement->transaction_txt}}</div>
                             <div><b>خط الطيران:</b> <span class="ey-ltr">{{$ticket_info->invoice_airline}}</span></div>
                             <div><b>المسافر:</b>
                                 @foreach($users as $user)
                                 <div class="ey-passenger ey-ltr">{{$user->client_name}}</div>
                                 @endforeach
                             </div>
                             <div><b>خط السير:</b> <span class="ey-ltr">{{$ticket_info->from_location}} - {{$ticket_info->to_location}}</span></div>
                             <div><b>تاريخ السفر:</b> {{$ticket_info->invoice_travel_date}}</div>
                             <div><b>أرقام الحجز:</b> <span class="ey-ltr">@foreach($users as $user){{$user->client_booking_id}}@if(!$loop->last) / @endif @endforeach</span></div>
                             <div><b>أرقام التذاكر:</b> <span class="ey-ltr">@foreach($users as $user){{$user->client_ticket_id}}@if(!$loop->last) / @endif @endforeach</span></div>
                         @else
                             {{$AccountStatement->transaction_txt}}
                         @endif
                         </div>
                      </td>

                     <td style="text-align: right;">{{$AccountStatement->debit_balance}}
                       @if($AccountStatement->transaction_type == 2)
<!--                         <br><span class="badge bg-primary my_badge">المرتجع لنا</span>-->
                         @endif

                      </td>
                     <td style="text-align: right;">{{$AccountStatement->credit_balance}}
                      @if($AccountStatement->transaction_type == 2)
<!--                         <br><span class="badge bg-primary my_badge">المسترد له</span>-->
                         @endif
                      </td>
                     <td style="text-align: right;">{{$AccountStatement->debit_balance + $AccountStatement->credit_balance}}</td>
                  </tr>
                  @endforeach
               </tbody>
            </table>

             </div>

    <div class="ey-summary">
        <table dir="rtl">
            <tbody>
                <tr>
                    <td class="ey-summary-label">رصيد افتتاحى (دائن)</td>
                    <td class="ey-summary-amount">{{number_format($supplier->opening_credit_balance, 2)}} جنيه</td>
                </tr>
                <tr>
                    <td class="ey-summary-label">رصيد افتتاحى (مدين)</td>
                    <td class="ey-summary-amount">{{number_format($supplier->debit_opening_balance, 2)}} جنيه</td>
                </tr>
                <tr>
                    <td class="ey-summary-label">إجمالي المدين</td>
                    <td class="ey-summary-amount">{{number_format($total_debit_balance, 2)}} جنيه</td>
                </tr>
                <tr>
                    <td class="ey-summary-label">إجمالي الدائن</td>
                    <td class="ey-summary-amount">{{number_format($total_credit_balance, 2)}} جنيه</td>
                </tr>
                <tr class="ey-summary-final">
                    <td class="ey-summary-label">الرصيد الختامى</td>
                    <td class="ey-summary-amount">{{number_format($supplier->opening_credit_balance + $supplier->debit_opening_balance + $total_debit_balance - $total_credit_balance, 2)}} جنيه</td>
                </tr>
            </tbody>
        </table>
    </div>

    </div>

<x-print-footer note="كشف حساب شامل" />

@endsection
