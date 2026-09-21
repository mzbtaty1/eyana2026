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
         <div class="card-header">
            <h5 class="card-title mb-0"> كشف حساب 
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
             </h5>
             
             
              <button class="btn btn-dark" onclick="printdiv()" style="float: left;margin-top: -22px;">
                           <i class="ri-printer-line"></i> طباعة التقرير
                           </button>
             &nbsp;
             <a href="{{$url2}}">
                  <button class="btn btn-primary" onclick="" style="float: left;margin-top: -22px;  margin-left: 7px;">
                           <i class="ri-file-excel-2-line"></i> اصدار التقرير اكسيل
                           </button>
             </a>
            
         </div>
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
                     <th data-ordering="false" style="text-align: right;">#</th>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ العملية</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ السفر</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-danger">وجهة الاستلام / الوصول</th>
                      
                     <th data-ordering="false" style="text-align: right;">بيانات الركاب</th>
                     <th data-ordering="false" style="text-align: right;">بيان العملية</th>
                      
                      
              
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

    $ticket_info = null;
    $users = [];
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
    ?>

    <tr>
        <td style="text-align: right;">{{$AccountStatement->es_id}}</td>
        <td style="text-align: right;">
            <?php
            if($AccountStatement->invoice_type == 1){ $type = "فواتير الطيران"; }
            elseif($AccountStatement->invoice_type == 2){ $type = "فواتير تأشيرات"; }
            elseif($AccountStatement->invoice_type == 3){ $type = "فواتير سياحه داخليه"; }
            elseif($AccountStatement->invoice_type == 4){ $type = "فواتير سياحه خارجيه"; }
            elseif($AccountStatement->invoice_type == 5){ $type = "فواتير سياحه دينيه"; }
            elseif($AccountStatement->invoice_type == 6){ $type = "فواتير تأمينات السفر"; }
            elseif($AccountStatement->invoice_type == 7){ $type = "فواتير تحاليل السفر"; }
            elseif($AccountStatement->invoice_type == 8){ $type = "فواتير نقل سياحى"; }
            elseif($AccountStatement->invoice_type == 9){ $type = "سند دفع"; }
            elseif($AccountStatement->invoice_type == 10){ $type = "سند قبض"; }
            elseif($AccountStatement->invoice_type == 11){ $type = "ارصدة"; }
            elseif($AccountStatement->invoice_type == 12){ $type = "سداد فاتورة"; }
            ?>
            @if($AccountStatement->transaction_type == 1)
                تذاكر /
            @elseif($AccountStatement->transaction_type == 2)
                سندات /
            @elseif($AccountStatement->transaction_type == 3)
                ارصدة افتتاحية /
            @elseif($AccountStatement->transaction_type == 4)
                سداد /
            @endif
            {{$type}}
        </td>

        <td style="text-align: right;">
            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                {{$ticket_info->invoice_date}}
            @else
                {{$AccountStatement->created_at}}
            @endif
        </td>

        <td style="text-align: right;">
            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                {{$ticket_info->invoice_travel_date}}
            @else
                {{$AccountStatement->created_at}}
            @endif
        </td>

        <td>
            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                {{$ticket_info->from_location}} - {{$ticket_info->to_location}}
            @else
                --
            @endif
        </td>

        <td style="text-align: right;">
            @if(in_array($AccountStatement->transaction_type, [1, 4]))
                @foreach($users as $user)
                    {{$user->client_name}}<br>
                @endforeach
            @else
                --
            @endif
        </td>

        <td style="text-align: right;">
            @if($result == "FLY-RD")
                الغاء تذكرة {{$AccountStatement->es_id}}
            @elseif($result == "FLY-RS")
                اعادة اصدار تذكرة {{$AccountStatement->es_id}}
            @else
                {{$AccountStatement->transaction_txt}}
            @endif

            @if($AccountStatement->transaction_type == 1 && $ticket_info)
                / خط الطيران : {{$ticket_info->invoice_airline}}
                <br>
                ارقام الحجز :
                @foreach($users as $user)
                    {{$user->client_booking_id}} /
                @endforeach
                <br>
                ارقام التذاكر :
                @foreach($users as $user)
                    {{$user->client_ticket_id}} /
                @endforeach
            @elseif($AccountStatement->transaction_type == 2 && $bond)
                @if($bond->money_way == 1)
                    دفع نقدي
                @elseif($bond->money_way == 2)
                    تحويل بنكي
                    <?php
                    $bank_info = $banksById[$bond->bank_id] ?? null;
                    ?>
                    @if($bank_info)
                        {{$bank_info->bank_name}}
                    @endif
                @else
                    تحصيل من المندوب :
                    <?php
                    $collector_info = $collectorsById[$bond->collector_info] ?? null;
                    ?>
                    @if($collector_info)
                        {{$collector_info->name}}
                    @endif
                @endif
                {{$bond->info}}
            @endif

            @if($AccountStatement->trans_storage == 1 && $AccountStatement->sub_id != 0)
                <br>
                <?php
                $sub_id = (int) $AccountStatement->sub_id;
                $min_info3 = $subStoragesById[$sub_id] ?? null;
                ?>
                @if($min_info3)
                    خزينة فرعية : <b>{{$min_info3->name}}</b>
                @endif
            @endif
        </td>

        <td style="text-align: right;">{{number_format($AccountStatement->debit_balance , 2)}}</td>
        <td style="text-align: right;color:#ff7900;">{{number_format($AccountStatement->credit_balance , 2)}}</td>
        <td style="text-align: right;">{{number_format($total_blnc , 2)}}</td>

        <td style="text-align: right;">
            <?php
            $mem = $usersById[$AccountStatement->added_by] ?? null;
            ?>
            @if($mem)
                {{$mem->name}}
            @endif
        </td>

        @if(Auth::user()->account_type == 2)
            <td>
                @if($AccountStatement->transaction_approved == 0)
                    <button class="btn btn-primary" id="rvd_{{$AccountStatement->id}}" style="border-radius: 55px;font-size: 10px;" onclick="do_approved({{$AccountStatement->id}})">تأكيد العملية</button>
                    <button class="btn btn-success" id="apprvd_{{$AccountStatement->id}}" style="border-radius: 55px;font-size: 10px;display:none;">تم التأكيد</button>
                @else
                    <button class="btn btn-success" style="border-radius: 55px;font-size: 10px;">تم التأكيد</button>
                @endif
            </td>
        @endif
    </tr>
    <?php $x++; ?>
    @endforeach
</tbody>

        
                   <tfoot>
                     <tr>
                   
                       <td>
                       الاجمالي
                       </td>
                       <td></td>
                       
                       <td></td> <td></td> <td></td>
                       
                      <td></td> <td></td> <td></td>

                  <td style="text-align:center;  background-color: #198754 !important;color:white;">
                       {{number_format($total_debit_balance , 2)}}
                       </td>
                  <td colspan="" style="text-align:center;  background-color: #198754 !important;color:white;">
                       {{number_format($total_credit_balance , 2)}}

                       </td>
                        @if($st == 1)
                            <td class="font-weight-bold" style="text-align:center;  background-color: #198754 !important;color:white;">
                                
                      {{number_format($total_debit_balance - $total_credit_balance , 2)}}
                       </td>
                            @else
<td class="font-weight-bold" style="text-align:center;  background-color: #198754 !important;color:white;">
                                              {{number_format($total_debit_balance - $total_credit_balance , 2)}}

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
                       
                            
                      
                        <tr>
                            <td>إجمالى المدين</td>
                            <td>{{number_format($total_debit_balance , 2)}} جنيها</td>
                        </tr>
                        <tr>
                            <td>إجمالى الدائن</td>
                            <td>{{number_format($total_credit_balance , 2)}} جنيها</td>
                        </tr>
                        <tr class="table-dark">
                            <td class="font-weight-bold">الإجمالى</td>
                            @if($st == 1)
                            <td class="font-weight-bold">
                          
@if(count($AccountStatements) == 1)
                        {{number_format($total_debit_balance - $total_credit_balance , 2)}}          
     @else
<!--                                 {{$supplier->opening_credit_balance + $supplier->debit_opening_balance + $total_debit_balance - $total_credit_balance}} -->
                 {{number_format($total_debit_balance - $total_credit_balance , 2)}}                         
                                @endif
                            جنيها
                          

                                 
                            </td>
                            @else
<td class="font-weight-bold">                 {{number_format($total_debit_balance - $total_credit_balance , 2)}}                         
 جنيها</td>

                            @endif
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
