@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب")
<style>
    .buttons-collection{
          width: 100%;
    }
    .dt-column-title{
        font-weight: normal;
  font-size: 12px;
    }
    /* Controlled width for the grouped trip/passenger block: without this,
       a booking with several long passenger names has no width to wrap
       within, so the cell (and with it the whole DataTable, which has no
       fixed table-layout) stretches to fit the longest unwrapped name
       instead of wrapping inside the column. */
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
    .ey-details-line{
        white-space: normal;
    }
    /* Columns: 1 رقم العملية, 2 نوع العملية, 3 تاريخ العملية, 4 نوع الطيران,
       5 خط السير, 6 تاريخ السفر, 7 المسافر, 8 رقم الحجز, 9 مدين, 10 دائن, 11 الرصيد. */
    #InvoicesTable td:nth-child(5){
        max-width: 220px;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    /* المسافر column: controlled width so a long name can never stretch the
       surrounding DataTable, which has no fixed table-layout. */
    #InvoicesTable td:nth-child(7){
        max-width: 300px;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    #InvoicesTable td:nth-child(8){
        max-width: 140px;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    #InvoicesTable td:nth-child(9), #InvoicesTable td:nth-child(10), #InvoicesTable td:nth-child(11){
        white-space: nowrap;
    }
</style>
<?php

//dd()

$url2 = route('site.accounts_statement_print_excel' , [
"invoice_beneficiaries" => $invoice_beneficiaries,
"date_from" => $date_from,
"date_to" => $date_to,
"transaction_type" => $transaction_type,
]);
?>
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <x-page-header>
            <x-slot:heading>
                كشف حساب
             @if($st == 1)
                @if($supplier->acc_type == 1) العميل @else المورد @endif
                : <b>{{$supplier->name}}</b>
                @else
                عام
                @endif
                 @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif
            </x-slot:heading>
<button class="btn btn-dark" onclick="printdiv()">
                           <i class="ri-printer-line"></i> طباعة التقرير
                           </button>
             &nbsp;
             <a href="{{$url2}}">
                  <button class="btn btn-primary" onclick="">
                           <i class="ri-file-excel-2-line"></i> اصدار التقرير اكسيل
                           </button>
             </a>
</x-page-header>
<div class="card-body">
             @if($st == 1)
            <h5 class="card-title mb-0"> 
                
             رصيد افتتاحى (دائن)

             <tag style="  border-style: ;  padding: 5px;  display: inline-block;">
                {{$supplier->opening_credit_balance}} جنيها

                </tag>
             </h5>
             
              <h5 class="card-title mb-0" style="float: left;
  margin-top: -39px;"> 
                
             رصيد افتتاحى (مدين)

             <tag style="  border-style: ;  padding: 5px;  display: inline-block;">
                {{$supplier->debit_opening_balance}} جنيها

                </tag>
             </h5>
             
             @endif
                       <hr>  
          <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead> 
                  <tr>
                     <th data-ordering="false" style="text-align: right;">رقم العملية</th>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ العملية</th>
                     <th data-ordering="false" style="text-align: right;">نوع الطيران</th>
                     <th data-ordering="false" style="text-align: right;">خط السير</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ السفر</th>
                     <th data-ordering="false" style="text-align: right;">المسافر</th>
                     <th data-ordering="false" style="text-align: right;">رقم الحجز</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">مدين</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">دائن</th>
                             <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">الرصيد</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">الموظف</th>
                       @if(Auth::user()->account_type == 2)    
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">المطابقة</th>
@endif
                  </tr>
               </thead>
              <tbody>
    @if($st == 1)
    <!-- نفس الكود الخاص بالأرصدة الافتتاحية بدون تغيير -->
    @endif

    <?php
    // Seeded with the carried-forward historical balance (0 when there is no
    // date filter, so the full statement is unaffected) so the running balance
    // below continues from the account's real balance instead of restarting at 0.
    $total_blnc = $opening_balance_for_period;
    $total_cumulative_balance = 0;
    foreach($AccountStatements as $AccountStatement){
        $total_cumulative_balance += $AccountStatement->cumulative_balance;
    }
    ?>

    <?php $x = 0; ?>
    @foreach($AccountStatements as $key => $AccountStatement)
    <?php
    // $total_blnc = balance after this whole AccountStatement row. For a
    // multi-passenger invoice the rows below step through each passenger's
    // ticket from $balance_before_tx and end on this same value.
    // Balances are DECIMAL(14,2): keep the cents (an (int) cast truncated them).
    $closing = (float) $AccountStatement->debit_balance - (float) $AccountStatement->credit_balance;
    $balance_before_tx = $total_blnc;
    $total_blnc = round($total_blnc + $closing, 2);

    $ticket_info = null;
    $users = collect();
    $bond = null;

    if($AccountStatement->trans_storage == 1){
        $ticket_info = $bondsByEsId[$AccountStatement->es_id] ?? null;
        $bond = $ticket_info;
    }else{
        if($AccountStatement->is_supp_account == 0){
            $ticket_info = $invoicesByEsId[$AccountStatement->es_id] ?? null;
            if($ticket_info){
                $users = $usersByTicketSystemId[$ticket_info->ticket_system_id] ?? collect();
            }
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
    // real, resolvable TicketUser records. Everything else (bonds, invoices
    // with no resolvable passenger rows, etc.) falls back to exactly one row,
    // same as before this change.
    $hasPassengerBreakdown = $AccountStatement->transaction_type == 1 && $ticket_info && $users->count() > 0;
    // Passenger lines of this ledger row, from InvoicePassengerLedger::statementLines()
    // (shared with the Print Preview / Account Statement screen and the Excel export):
    // one row per passenger ticket under the same invoice number, each with its own
    // amount and its own running-balance step. Rows written since the passenger-level
    // model (edits dated today, refunds) carry their exact per-passenger lines.
    $paxLines = App\Services\InvoicePassengerLedger::statementLines($AccountStatement, $users, $hasPassengerBreakdown);
    $hasPassengerBreakdown = $paxLines !== null;
    $breakdownRows = $hasPassengerBreakdown ? collect($paxLines) : collect([null]);
    // «نوع العملية» from explicit data only (row marker / invoice number), never from amounts.
    $kindLabel = App\Services\InvoicePassengerLedger::kindLabel($AccountStatement);

    $mem = $usersById[$AccountStatement->added_by] ?? null;
    ?>

    @foreach($breakdownRows as $rowIndex => $user)
    <?php
    if ($hasPassengerBreakdown) {
        $balance_before_tx = round($balance_before_tx + ($user['debit'] ?? 0) - ($user['credit'] ?? 0), 2);
        $row_balance = $balance_before_tx;
    } else {
        $row_balance = $total_blnc;
    }
    ?>
    <tr>
        <td style="text-align: right;">{{$AccountStatement->es_id}}</td>
        <td style="text-align: right;">
            @if($AccountStatement->transaction_type == 1)
                {{$kindLabel ?? 'تذاكر'}} /
            @elseif($AccountStatement->transaction_type == 2)
                سندات /
            @elseif($AccountStatement->transaction_type == 3)
                أرصدة افتتاحية /
            @elseif($AccountStatement->transaction_type == 4)
                سداد /
            @endif
            {{$type}}
        </td>

        <td style="text-align: right;">
            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                {{App\Services\InvoicePassengerLedger::displayDate($AccountStatement, $ticket_info)}}
            @else
                {{$AccountStatement->created_at}}
            @endif
        </td>

        <td style="text-align: right;">
            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                <span class="ey-ltr">{{$ticket_info->invoice_airline}}</span>
            @endif
        </td>
        <td style="text-align: right;">
            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                <span class="ey-ltr">{{$ticket_info->from_location}} - {{$ticket_info->to_location}}</span>
            @endif
        </td>
        <td style="text-align: right;">
            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                {{$ticket_info->invoice_travel_date}}
            @endif
        </td>

        <td>
            @if($hasPassengerBreakdown)
                <span class="ey-ltr">{{$user['name']}}</span>
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
                                <?php $bank_info = $banksById[$bond->bank_id] ?? null; ?>
                                @if($bank_info && $bank_info->bank_name) - {{$bank_info->bank_name}}@endif
                            @else
                                تحصيل من المندوب:
                                <?php $collector_info = $collectorsById[$bond->collector_info] ?? null; ?>
                                @if($collector_info){{$collector_info->name}}@endif
                            @endif
                            {{$bond->info}}
                        </div>
                    @endif

                    @if($AccountStatement->trans_storage == 1 && $AccountStatement->sub_id != 0)
                        <?php
                        $sub_id = (int) $AccountStatement->sub_id;
                        $min_info3 = $subStoragesById[$sub_id] ?? null;
                        ?>
                        @if($min_info3)
                            <div>خزينة فرعية: <b>{{$min_info3->name}}</b></div>
                        @endif
                    @endif
                </div>
            @endif
        </td>

        <td style="text-align: right;">
            @if($hasPassengerBreakdown)
                <span class="ey-ltr">{{$user['booking']}}</span>
            @endif
        </td>

        <td style="text-align: right;">
            @if($hasPassengerBreakdown)
                @if($user['debit'] !== null){{number_format($user['debit'], 2)}}@endif
            @else
                {{number_format($AccountStatement->debit_balance , 2)}}
            @endif
        </td>
        <td style="text-align: right;color:#ff7900;">
            @if($hasPassengerBreakdown)
                @if($user['credit'] !== null){{number_format($user['credit'], 2)}}@endif
            @else
                {{number_format($AccountStatement->credit_balance , 2)}}
            @endif
        </td>
        <td style="text-align: right;">
            {{-- Balance after this row (after this passenger's ticket for a multi-passenger invoice). --}}
            {{number_format($row_balance, 2)}}
        </td>

        <td style="text-align: right;">
            @if($rowIndex === 0 && $mem)
                {{$mem->name}}
            @endif
        </td>

        @if(Auth::user()->account_type == 2)
            <td>
                @if($rowIndex === 0)
                    @if($AccountStatement->transaction_approved == 0)
                        <button class="btn btn-primary" id="rvd_{{$AccountStatement->id}}" style="border-radius: 55px;font-size: 10px;" onclick="do_approved({{$AccountStatement->id}})">تأكيد العملية</button>
                        <button class="btn btn-success" id="apprvd_{{$AccountStatement->id}}" style="border-radius: 55px;font-size: 10px;display:none;">تم التأكيد</button>
                    @else
                        <button class="btn btn-success" style="border-radius: 55px;font-size: 10px;">تم التأكيد</button>
                    @endif
                @endif
            </td>
        @endif
    </tr>
    @endforeach
    <?php $x++; ?>
    @endforeach
</tbody>

        
                   <tfoot>
                     <tr>
                   
                       <td>
                       الاجمالي
                       </td>
                       <td></td>
                       <td></td>
                       <td></td>
                       <td></td>
                       <td></td>
                       <td></td>
                       <td></td>

                  <td style="text-align:center;  background-color: #198754 !important;color:white;">
                       {{number_format($total_debit_balance , 2)}}
                       </td>
                  <td colspan="" style="text-align:center;  background-color: #198754 !important;color:white;">
                       {{number_format($total_credit_balance , 2)}}

                       </td>
                        @if($st == 1)
                            <td class="font-weight-bold" style="text-align:center;  background-color: #198754 !important;color:white;">

                      {{number_format($total_blnc , 2)}}
                       </td>
                            @else
<td class="font-weight-bold" style="text-align:center;  background-color: #198754 !important;color:white;">
                                              {{number_format($total_blnc , 2)}}

                       </td>

                            @endif
                       <td colspan="2"></td>
                   </tr>
                   </tfoot>
                   
            </table>
             
             </div>
<table class="table">
                        <tbody>
                            @if($st == 1)
                            <tr>
                            <td>رصيد افتتاحى (دائن)</td>
                            <td>{{number_format($supplier->opening_credit_balance , 2)}} جنيها</td>
                        </tr>
   <tr>
                            <td>رصيد افتتاحى (مدين)</td>
                            <td>{{number_format($supplier->debit_opening_balance , 2)}} جنيها</td>
                        </tr>
@endif



                        @if($date_from !== null && $date_to !== null)
                        <tr>
                            <td>الرصيد الافتتاحي (بداية الفترة)</td>
                            <td>{{number_format($opening_balance_for_period , 2)}} جنيها</td>
                        </tr>
                        @endif
                        <tr>
                            <td>إجمالى المدين@if($date_from !== null && $date_to !== null) للفترة @endif</td>
                            <td>{{number_format($total_debit_balance , 2)}} جنيها</td>
                        </tr>
                        <tr>
                            <td>إجمالى الدائن@if($date_from !== null && $date_to !== null) للفترة @endif</td>
                            <td>{{number_format($total_credit_balance , 2)}} جنيها</td>
                        </tr>
                        <tr class="table-dark">
                            <td class="font-weight-bold">@if($date_from !== null && $date_to !== null) الرصيد النهائي @else الإجمالى @endif</td>
                            <td class="font-weight-bold">
                                {{number_format($total_blnc , 2)}}
                            جنيها
                            </td>
                        </tr>
                    </tbody></table>
             
             
         </div>
      </div>
   </div>
   <!--end col-->
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<?php


$url = route('site.accounts_statement_print_all' , [ 
"invoice_beneficiaries" => $invoice_beneficiaries,
"date_from" => $date_from,
"date_to" => $date_to,
"transaction_type" => $transaction_type,
]);

?>
<script type="application/javascript">
let Mytable = new DataTable('#InvoicesTable', {
    pageLength: 50,
     ordering: false, 
    // order: [[2, 'asc']],
    columnDefs: [
        // يمكنك إضافة تعريفات الأعمدة هنا إذا كنت بحاجة إلى تعديل أعمدة معينة
        // {
        //     target: 0,
        //     visible: false, // مثال لإخفاء العمود الأول
        // },
    ],
    responsive: true,
    layout: {
        topStart: {
            buttons: ['colvis'] // زر عرض/إخفاء الأعمدة
        }
    },
});
</script>
  <script>
        

      
      function printdiv(){
          var url = "{{$url}}";
//    alert(url);
           var printWindow = window.open( url, 'Print', 'left=200, top=200, width=950, height=500, toolbar=0, resizable=0');
       printWindow.addEventListener('load', function(){
           printWindow.print();
//           printWindow.close();
       }, true);
          
      }
         function report_excel(){
          var url = "{{$url2}}";
//    alert(url);
           var printWindow = window.open( url, 'Print', 'left=200, top=200, width=950, height=500, toolbar=0, resizable=0');
       printWindow.addEventListener('load', function(){
//           printWindow.print();
//           printWindow.close();
       }, true);
          
      }
      
      function do_approved(id){
//          alert(id);
          
   var url = "{{url('')}}/accounts-statement/" + id + "/approve";
//          console.log(url);
//           
           $(document).ready(function () {

      $.ajax({
         type: "GET",
         url: url,
         headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
         },
         data: "id=" + id,
         //   beforeSend: function() {
         //   $('.message_box').html(
         //   '<img src="Loader.gif" width="25" height="25"/>'
         //   );
         //   },
         success: function (data) {
            //var dataResult = JSON.parse(data);

            //console.log(data.status_code);

            var status_code = data.status_code;
 
             
             if(status_code == 200){
                 swal("", "تم تأكيد العملية بنجاح", "success");
                 
                 var n1 = "rvd_" + id;
                 var n2 = "apprvd_" + id;
                  document.getElementById(n1).style.display = "none"; 
                  document.getElementById(n2).style.display = "block"; 
//                  document.getElementById("myDIV").style.display = "none"; 
                 
             }else{
                 swal("", "فشل اثناء تأكيد العملية", "error");
             }


         }
      });
      //   });

   });
          
          
          
      }
      

      
      
      
        </script>  
@endsection
