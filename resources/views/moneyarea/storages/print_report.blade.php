@extends('layouts.print')
@section('content')
<?php

$title_1 = "كشف حساب خزينة";

?>
@section('title',$title_1)
<style>

    .border_print{
/*        border-style: solid;*/
    }
    .b_balnce{
        font-size:17px;
    }
    .th_d{
        font-size: 10px !important;
    }
    /* Controlled width for the grouped trip/passenger block: without this, a
       booking with several long passenger names has no width to wrap within,
       so the cell (and with it the table, which has no declared column
       widths) stretches to fit the longest unwrapped name instead of
       wrapping inside the column. */
    .ey-trip-info{
        max-width: 380px;
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
    #customers th:nth-child(5), #customers td:nth-child(5),
    #customers th:nth-child(6), #customers td:nth-child(6){
        white-space: nowrap;
    }
    #customers {
  font-family: Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

#customers td, #customers th {
  border: 1px solid #ddd;
  padding: 8px;
}

#customers tr:nth-child(even){background-color: #f2f2f2;}

#customers tr:hover {background-color: #ddd;}

#customers th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #04AA6D;
  color: white;
}
    .page_print{
            print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    }
</style>
<br>
<div class="page_print">

    <div class="border_print">
    
    <x-print-header
        title="كشف حساب خزينة"
        :heading="$st == 1 ? ('خزينة : '.$storage_info->name) : 'خزائن عام'"
        :date-from="$date_from ?? null"
        :date-to="$date_to ?? null"
    />

        <div class="t">
               <table id="customers" class="ey-print-table" dir="rtl">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;">الخزينة المنفذه</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ العملية</th>
                      
                     <th data-ordering="false" style="text-align: right;">بيان العملية</th>
                      
                      
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">مدين</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">دائن</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">الرصيد</th>

                  </tr>
               </thead>
                 <tbody>
                   <?php

                    $total_cumulative_balance = 0;
                   $tota_debit_balance = 0;
                   $tota_credit_balance = 0;
                   foreach($AccountStatements as $AccountStatement){
$total_cumulative_balance += $AccountStatement->cumulative_balance;
                       $tota_debit_balance += $AccountStatement->debit_balance;
                       $tota_credit_balance += $AccountStatement->credit_balance;
 }

                   // Running balance: same logic as the screen (moneyarea.storages.acc_all)
                   // -- seeded with the carried-forward opening balance, recalculated fresh
                   // from the chronologically-ordered rows on every request.
                   $total_blnc = $opening_balance_for_period;

                   // Batch-fetch every per-row lookup once instead of inside the loop (was N+1:
                   // up to 5 queries per row across Bond/Invoice/TicketUser/Storage/Bank/Collector).
                   $storage_es_ids = [];
                   $invoice_es_ids = [];
                   foreach ($AccountStatements as $as_row) {
                       if ($as_row->trans_storage == 1) {
                           $storage_es_ids[] = $as_row->es_id;
                       } else {
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

                   $storage_ids_for_rows = $AccountStatements->pluck('supp_client_id')->unique()->filter()->values()->all();
                   $storages_by_id = App\Models\Storage::whereIn('id', $storage_ids_for_rows)->get()->keyBy('id');

                   $bank_ids = $bonds_by_es_id->where('money_way', 2)->pluck('bank_id')->unique()->filter()->values()->all();
                   $banks_by_id = App\Models\Bank::whereIn('id', $bank_ids)->get()->keyBy('id');

                   $collector_ids = $bonds_by_es_id->reject(function($b){ return in_array($b->money_way, [1, 2]); })
                       ->pluck('collector_info')->unique()->filter()->values()->all();
                   $collectors_by_id = App\Models\Collector::whereIn('id', $collector_ids)->get()->keyBy('id');
                   ?>
                  @foreach($AccountStatements as $AccountStatement)

                   <?php
                    // Balances are DECIMAL(14,2): keep the cents (an (int) cast truncated them).
                    $closing = (float) $AccountStatement->debit_balance - (float) $AccountStatement->credit_balance;
                    $total_blnc = round($total_blnc + $closing, 2);

                if($AccountStatement->trans_storage == 1){
                  $ticket_info = $bonds_by_es_id->get($AccountStatement->es_id) ?? new App\Models\Bond();
                  $bond = $ticket_info;

                }else{
                  // Defensive fallback: some storage rows have trans_storage != 1 (an
                  // invoice-paid-by-storage row) yet the payment-method block below still
                  // unconditionally reads $bond->money_way (pre-existing view bug, not
                  // introduced by this batch -- see Batch E report). An empty Bond avoids a
                  // crash; money_way is null so it falls into the fallback display branch below.
                  $bond = new App\Models\Bond();

                   $ticket_info = $invoices_by_es_id->get($AccountStatement->es_id) ?? new App\Models\Invoice();
                   $users = $ticket_users_by_ticket_system_id->get($ticket_info->ticket_system_id, collect());


                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;

                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }


                }



                   ?>
                  <tr>
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
                              $type = "ارصدة";
                          }elseif($AccountStatement->invoice_type == 12){
                              $type = "سداد فاتورة";
                          }else{
                              $type = "أخرى";
                          }
                          ?>
                         @if($AccountStatement->transaction_type == 1)
                         تذاكر / 
                        @elseif($AccountStatement->transaction_type == 2)
                         سندات /
                         @endif
                      {{$type}}
                      </td>
                      <td>

                      <?php

        abort_if(!$storages_by_id->has($AccountStatement->supp_client_id), 404);
        $storage_info = $storages_by_id->get($AccountStatement->supp_client_id);
        ?>
        {{$storage_info->name}}
                      </td>
                      <td>{{$AccountStatement->created_at}}</td>
                     
                     
                   
                      
                     <td>
                         <div class="ey-trip-info">
                         @if($AccountStatement->transaction_type == 1 && $AccountStatement->trans_storage != 1)
                             <div><b>حجز الرحلة:</b> {{$AccountStatement->transaction_txt}}</div>
                             <div><b>المسافر:</b>
                                 @foreach($users as $user)
                                 <div class="ey-passenger ey-ltr">{{$user->client_name}}</div>
                                 @endforeach
                             </div>
                             <div class="ey-ltr">
                                 <b>التفاصيل:</b>
                                 {{$ticket_info->invoice_airline}}
                                 | {{$ticket_info->from_location}} - {{$ticket_info->to_location}}
                                 | حجز: @foreach($users as $user){{$user->client_booking_id}}@if(!$loop->last)/@endif @endforeach
                                 | تذكرة: @foreach($users as $user){{$user->client_ticket_id}}@if(!$loop->last)/@endif @endforeach
                             </div>
                             <div><b>تاريخ السفر:</b> {{$ticket_info->invoice_travel_date}}</div>
                         @else
                             <div>
                                 {{$AccountStatement->transaction_txt}}
                                 <br>
                                 @if($bond->money_way == 1)
                                     دفع نقدي
                                 @elseif($bond->money_way == 2)
                                     تحويل بنكي
                                     <?php
                                     $bank_info = $banks_by_id->get($bond->bank_id) ?? new App\Models\Bank();
                                     ?>
                                     {{$bank_info->bank_name}}
                                 @else
                                     تحصيل من المندوب :
                                     <?php
                                     $collector_info = $collectors_by_id->get($bond->collector_info) ?? new App\Models\Collector();
                                     ?>
                                     {{$collector_info->name}}
                                 @endif
                             </div>
                             @if($AccountStatement->es_id)
                             <div class="text-muted" style="font-size:11px;">المرجع: {{$AccountStatement->es_id}}</div>
                             @endif
                         @endif
                         </div>
                      </td>
                      
<!--
 <td style="text-align: right;">
                         <?php
                         $last_row = App\Models\AccountStatement::where('id', '<', $AccountStatement->id)->where('is_storage' , 1)->get();
                         if(count($last_row) == 0){
                             $t = $AccountStatement->debit_balance + $AccountStatement->credit_balance;
                         }else{
                             $last_row = $last_row[0];
                             $t = $last_row->debit_balance + $last_row->credit_balance;
                         }

                         ?> 
                         {{$AccountStatement->debit_balance + $AccountStatement->credit_balance}}
                         {{$AccountStatement->debit_balance + $AccountStatement->credit_balance}}
                      
                      
                      </td>
-->
                      <td style="text-align: right;">{{number_format($AccountStatement->debit_balance, 2)}}


                      </td>
                     <td style="text-align: right;">{{number_format($AccountStatement->credit_balance, 2)}}

                      </td>
                      <td style="text-align: right;">{{number_format($total_blnc, 2)}}</td>
                  </tr>
                  @endforeach
                  <tr class="table-dark">
                      <td colspan="4" style="text-align: center; font-weight: bold;">الإجمالي</td>
                      <td style="text-align: right; font-weight: bold;">{{number_format($tota_debit_balance, 2)}}</td>
                      <td style="text-align: right; font-weight: bold;">{{number_format($tota_credit_balance, 2)}}</td>
                      <td style="text-align: right; font-weight: bold;">{{number_format($total_blnc, 2)}}</td>
                  </tr>
               </tbody>
            </table>

            <table class="table" style="margin-top: 15px;">
                <tbody>
                    @if($date_from !== null && $date_to !== null)
                    <tr>
                        <td>الرصيد الافتتاحي (بداية الفترة)</td>
                        <td>{{number_format($opening_balance_for_period, 2)}} جنيها</td>
                    </tr>
                    @endif
                    <tr>
                        <td>اجمالي (دائن)@if($date_from !== null && $date_to !== null) للفترة @endif</td>
                        <td>{{number_format($tota_credit_balance, 2)}} جنيها</td>
                    </tr>
                    <tr>
                        <td>اجمالي (مدين)@if($date_from !== null && $date_to !== null) للفترة @endif</td>
                        <td>{{number_format($tota_debit_balance, 2)}} جنيها</td>
                    </tr>
                    <tr class="table-dark">
                        <td class="font-weight-bold">@if($date_from !== null && $date_to !== null) الرصيد النهائي @else النهائي @endif</td>
                        <td class="font-weight-bold">{{number_format($total_blnc, 2)}} جنيها</td>
                    </tr>
                </tbody>
            </table>

             </div>

    </div>


</div>

<x-print-footer note="كشف حساب خزينة" />

@endsection
