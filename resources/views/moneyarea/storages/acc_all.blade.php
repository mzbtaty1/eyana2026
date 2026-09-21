@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب خزينة")
<style>
    .buttons-collection{
          width: 100%;
    }
    .dt-column-title{
        font-weight: normal;
  font-size: 12px;
    }
</style>
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> كشف حساب 
             @if($st == 1)
                خزينة : {{$storage_info->name}}
                @else
                خزائن عام
                @endif
             </h5>
             
                             

             
                             
     <button class="btn btn-dark" onclick="printdiv()" style="float: left;margin-top: -22px;">
                           <i class="ri-printer-line"></i> طباعة التقرير
                           </button>
         </div>
         <div class="card-body">
           
         
             
                       <hr>  
          <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align: right;">#</th>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;">الخزينة المنفذه</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ العملية</th>
                      
                     <th data-ordering="false" style="text-align: right;">بيان العملية</th>
                      
                      
<!--                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">تراكمي</th>-->
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">مدين</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">دائن</th>
                      
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">الموظف</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">المطابقة</th>

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
                   
                   ?>
                  @foreach($AccountStatements as $AccountStatement)
                   
                   <?php
                if($AccountStatement->trans_storage == 1){

            $check_ticket = $invoicesByEsId[$AccountStatement->es_id] ?? null;
                    if($check_ticket){
                        $std = 1; // Ticket
                    }else{
                  $bond = $bondsByEsId[$AccountStatement->es_id] ?? null;
                        $std = 0; // Bond
                    }
                }else{

                          $ticket_info = $invoicesByEsId[$AccountStatement->es_id] ?? null;
                   $users = $ticket_info ? ($usersByTicketSystemId[$ticket_info->ticket_system_id] ?? collect()) : collect();


                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;

                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }


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
                              $type = "ارصدة";
                          }elseif($AccountStatement->invoice_type == 12){
                              $type = "سداد فاتورة";
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
        $row_storage_info = $storagesById[$AccountStatement->supp_client_id] ?? null;
        abort_if(!$row_storage_info , 404);
        ?>
        {{$row_storage_info->name}}
                      </td>
                      <td>{{$AccountStatement->created_at}}</td>
                     
                     
                    
                      
                     <td style="text-align: right;">
                         {{$AccountStatement->transaction_txt}}
                        <br>
                   @if($std == 1)
                         سداد لصالح تذكرة
                         @else
                      
                          @if($bond->money_way == 1)
                         دفع نقدي
                         @elseif($bond->money_way == 2)
                         تحويل بنكي
                         {{ ($banksById[$bond->bank_id] ?? null)->bank_name ?? '' }}
                         @else
                         تحصيل من المندوب :
                         {{ ($collectorsById[$bond->collector_info] ?? null)->name ?? '' }}
                         @endif
                         @endif
                         
                         
                      </td>
                      
                      <td style="text-align: right;">{{number_format($AccountStatement->debit_balance,2)}}
                       @if($AccountStatement->transaction_type == 2)
<!--                         <br><span class="badge bg-primary my_badge">المرتجع لنا</span>-->
                         @endif
                         
                      </td>
                     <td style="text-align: right;">{{number_format($AccountStatement->credit_balance,2)}}
                      @if($AccountStatement->transaction_type == 2)
<!--                         <br><span class="badge bg-primary my_badge">المسترد له</span>-->
                         @endif
                      </td>
                  <td style="text-align: right;">
                    <?php
                    $mem = $usersById[$AccountStatement->added_by] ?? null;
                    ?>
                    {{$mem->name ?? ''}}

                      </td>
                      <?php
//                      if($std == 1){
//                           if($ticket_info->invoice_status == 0){
//                          $color = "danger";
//                      }else{
//                          $color = "success";
//                      } 
//                      }
                    
                      
                      ?>
                      @if($AccountStatement->transaction_type == 1)
           
                      @else
                      <td style="text-align: right;" class="">
                    --
                    
                      </td>
               @endif
                  </tr>
                  @endforeach
               </tbody>
                <tfoot>
<!--
                 <tr>
                      <td>المجموع</td>
                   <td>--</td>
                   <td>--</td>
                   <td>--</td>
                   <td>--</td>
                        <td style="text-align:right;" id="subtotal"></td>
    <td style="text-align:right;" id="total"></td>
    <td style="text-align:right;" class="" id="">--</td>
    <td style="text-align:right;">--</td>
                    </tr>
-->
                   </tfoot>
            </table>
             
              <table class="table">
                        <tbody>
                            
                            <tr>
                            <td>اجمالي (دائن)</td>
                            <td>{{number_format($tota_credit_balance , 2)}} جنيها</td>
                        </tr>
   <tr>
                            <td>اجمالي (مدين)</td>
                            <td>{{number_format($tota_debit_balance , 2)}} جنيها</td>
                        </tr>
                       
                            <tr class="table-dark">
                            <td class="font-weight-bold">النهائي</td>
<td class="font-weight-bold">
    {{number_format($tota_debit_balance - $tota_credit_balance , 2)}} جنيها
</td>
</tr>  
                      
                      
                    </tbody></table>
             
 
              
             </div>

             
             
             
         </div>
      </div>
   </div>
   <!--end col-->
</div>

<?php


$url = route('site.stor_acc_print' , [
"storage_id" => $storage_id,
"date_from" => $date_from,
"date_to" => $date_to,
"transaction_type" => $transaction_type,
]);
?>

  <script>
       $(function() {
  $("#subtotal").html(sumColumn(6));
  $("#total").html(sumColumn(7));
          
//          $("#total0").html(calc_get());
  
          
          
});

      function calc_get(){
          
          var val1 = parseInt($("#subtotal").text().replace(',',''));
          var val2 = parseInt($("#total").text().replace(',',''));
          var val1_total = val1 + val2;
          
          const formatter = new Intl.NumberFormat('en');
    var val1_totaltrurn = formatter.format(val1_total);
          
           $('#total0').html(val1_totaltrurn);
      }
      
function sumColumn(index) {
  var total = 0;
  $("td:nth-child(" + index + ")").each(function() {
//       let total = total.replace(",", ""); 
      
     total += parseInt($(this).text().replace(',',''), 10) || 0;
    
  });  
    const formatter = new Intl.NumberFormat('en');
    return formatter.format(total);
//  return total;
}
            function printdiv(){
          var url = "{{$url}}";
//    alert(url);
           var printWindow = window.open( url, 'Print', 'left=200, top=200, width=950, height=500, toolbar=0, resizable=0');
       printWindow.addEventListener('load', function(){
           printWindow.print();
//           printWindow.close();
       }, true);
          
      }
      
       let table = new DataTable('#InvoicesTable', {
           ordering: false,
           columnDefs: [
//        {
//            target: 0,
//            visible: false,
//        },

    ],
    responsive: true,
           layout: {
        topStart: {
            buttons: ['colvis']
        }
    },
});
        </script>  
@endsection
