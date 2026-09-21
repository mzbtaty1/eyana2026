@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب")
@php
    use App\Models\TicketUser;
    use App\Models\AccountStatement;
    use App\Models\User;
    use App\Models\Invoice;

    // متغير لتجميع إجمالي الأرباح لكل الفواتير
    $total_all_profit = 0;
@endphp
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
  <form>
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> 
                
                تقرير عمليات الربح والمصروف
                @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif
             </h5>
             
        
         </div>
         <div class="card-body">
       
          <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align: right;">اسم المصروف</th>
                    
                     <th data-ordering="false" style="text-align: right;display:none;">--</th>
                       <th data-ordering="false" style="text-align: right;">اجمالي المبالغ المصروفه به</th>
                     <th data-ordering="false" style="text-align: right;display:none;">--</th>
      

                  </tr>
               </thead>
               <tbody>

                   
                   <?php

                 
                   $x =0;
                  
                   ?> 
                   @foreach($expenses as $expense)
                   
                   <?php
                   
        $bonds = App\Models\Bond::select('*');
        if(isset($date_from) && isset($date_to)){
            $bonds = $bonds->whereBetween('crt_date' , [$date_from , $date_to]);
        }
                   $bonds = $bonds->where('type' , 1)
                       ->where('from_type' , 'storage')
                       ->where('to_type' , 'supplier')
                       ->where('to_account' , $expense->id);
                $bonds = $bonds->get();
                   
                   $total_bonds_amount = 0;
                 foreach($bonds as $bond){
                     $total_bonds_amount += $bond->amount;
                 }  
                   ?>
                 <tr>
                   <td>{{$expense->name}}</td>
                   
                     
                   <td style="text-align:right;display:none;">--</td>
                     <td style="text-align:right;" class="amount-cell">{{$total_bonds_amount}}</td>
                     <td style="text-align:right;display:none;">--</td>
                   </tr>
                   <?php $x++; ?>
                  @endforeach 
                   <tr>
                   
                       <td>الاجمالي</td>
                     
                       <td style="text-align:right;display:none;">--</td>
                         <td style="text-align:right;color:white;" id="subtotal" class="bg-dark"></td>
                       <input type="hidden" name="sub_two"  id="sub_two" value="">
                   <td style="text-align:right;display:none;">--</td>
                   </tr>
               </tbody>
                   
            </table>
             
             </div>
   
               <h5>
             ملخص تقرير التذاكر والفواتير
                    @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif
             </h5>

             <table class="table">
                        <tbody>

                    @php
$invoicesQuery = Invoice::with(['users', 'accountStatements']);
if (isset($date_from) && isset($date_to)) {
    $invoicesQuery = $invoicesQuery->whereBetween('invoice_date', [$date_from, $date_to]);
}
$invoices = $invoicesQuery->get();
                    @endphp
                 @foreach($invoices as $invoice)
    @php
        $result = substr($invoice->es_id, 0, 6);

        $total_client_net_price = $invoice->users->sum(fn($u) => (float)($u->client_net_pice ?? 0));
        $total_client_bought_price = $invoice->users->sum(fn($u) => (float)($u->client_bought_price ?? 0));

        if($result == "FLY-RD"){
            $mostarad = $invoice->accountStatements->where('credit_balance',0)->first();
            $mortaga  = $invoice->accountStatements->where('debit_balance',0)->first();
            $invoice_profit = ($mostarad && $mortaga)
                ? (float)($mostarad->debit_balance ?? 0) - (float)($mortaga->credit_balance ?? 0)
                : 0;
        }else{
            $invoice_profit = $total_client_bought_price - $total_client_net_price;
        }

        $total_all_profit += $invoice_profit;
    @endphp
@endforeach



                        <tr class="table-success">
                            <td class="font-weight-bold">
                            اجمالي المصروفات
                                   @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif 
                            </td>
                             <td class="font-weight-bold" id="total_exps">جاري الحساب..........</td>
                        </tr>
                            <tr class="table-primary">
                            <td class="font-weight-bold">
                            اجمالي ارباح الفواتير
                                   @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif 
                            </td>
                             <td class="font-weight-bold">{{$total_all_profit}}</td>
                        </tr>
                            <tr class="table-dark">
                            <td class="font-weight-bold">
                            صافي الارباح
                                   @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif 
                            </td>
                             <td class="font-weight-bold" id="my_total">جاري الحساب..........</td>
                        </tr>
                    </tbody></table>
             
   
        
         </div>
      </div>
  
       </form>
    </div>
   <!--end col-->
</div>

<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>-->




  <script>


   
               $(function() {
   $("#subtotal").html(sumColumn());
//   $("#sub_two").html(sumColumn(3));
//   $("#subtotal_invoices").html(sumColumnTwo(2));
           
});
      
function sumColumn() {
  var total = 0;
  $(".amount-cell").each(function() {
     var text = $(this).text().replace(/[^\d.-]/g, '');
     var value = parseFloat(text);
     if (!isNaN(value)) {
         total += value;
     }
  });  
  return total + " ج.م ";
}
      function sumColumnTwo(index) {
  var total = 0;
  $("td:nth-child(" + index + ")").each(function() {
     total += parseInt($(this).text(), 10) || 0;
    
  });  
//          $('#subtotal_two').html(total);
  return total + " ج.م ";
           
//          subtotal_two
          
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
      
      
         
      window.onload = setTimeout(function(){
    
 var subtotalText = $("#subtotal").text().replace(/[^\d.-]/g, '');
 var val1 = parseFloat(subtotalText);
 if (isNaN(val1)) {
     val1 = 0;
 }
          
//          total_exps
           $('#total_exps').html(val1 + " ج.م");
var val2 = {{$total_all_profit}};
                                 
                 
    var var_total = val2 -  val1;                             
     $('#my_total').html(var_total + " ج.م");     
          
//          alert(val1);
//    window.location = 'http://www.example.com';
}, 3000);
        </script>  
@endsection