@extends('layouts.app')
@section('content')
@section('title' , 'تقرير الارباح')
@if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
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
            <h5 class="card-title mb-0">تقرير الارباح</h5>
            <!--
               <a href="{{route('site.invoices_create')}}">
               <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                   <i class="ri-file-add-line"></i>
                   اضافة فاتورة
                   </button>
               </a>
               -->
         </div>
         <div class="card-body">
            <center>
               <div class="alert alert-dark border-0">
                  <i class="ri-information-line"></i>
                  يرجي العلم ان الفواتير المظللة باللون الاسود فواتير مشتركة بين 2 موظفين
               </div>
            </center>
            <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100% !important;">
                  <thead>
                     <tr>
                        <th data-ordering="false" style="text-align:right;">#</th>
                        <th data-ordering="false" style="text-align:right;">تاريخ الفاتورة</th>
                        <th data-ordering="false" style="text-align:right;">الربح</th>
                        <th data-ordering="false" style="text-align:right;">العمولة</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach($invoices as $invoice)
                     <?php
                        if($invoice->invoice_shared == 0){
                            $bg = "";
                            $style = "";
                        }else{
                            $bg = "bg-dark";
                            $style = "color:white;";
                        }
                        
                        $result = substr($invoice->es_id, 0, 6);
                          if($result == "FLY-RD"){
                               $bg = "bg-success";
                            $style = "color:white;";
                          
                        }else{
                             $bg = "";
                            $style = "";
                        }
                        
                        
                        ?>
                     <?php
                        $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice->ticket_system_id)->get();
                          
                          $total_client_net_price = 0; // Fixed typo: was $total_client_net_pice
                          $total_client_bought_price = 0;
                          foreach($users as $user){
                              // Cast to float and handle null values
                              $total_client_net_price += (float)($user->client_net_pice ?? 0);
                              $total_client_bought_price += (float)($user->client_bought_price ?? 0);
                          }
                        
                        
                        $mem_info = App\Models\User::select('*')->where('id' ,$invoice->invoice_create_by)->first();
                          
                        // Check if user exists
                        if(!$mem_info) {
                           continue; // Skip this invoice if user not found
                        }
                          
                        
                        if($invoice->invoice_shared == 0){     
                        $value = (float)($mem_info->commission ?? 0);
                        }else{
                         $value = 5;
                        }
                        $percent = $value / 100;
                        
                                 if($result == "FLY-RD"){
                               $mostarad = App\Models\AccountStatement::select('*')->where('es_id',$invoice->es_id)
                                   ->where('credit_balance',0)->first();
                               
                                $mortaga = App\Models\AccountStatement::select('*')->where('es_id',$invoice->es_id)
                                   ->where('debit_balance',0)->first();
                              
                            // Check if records exist and cast to float
                            if($mostarad && $mortaga) {
                                $perbest = (float)($mostarad->debit_balance ?? 0) - (float)($mortaga->credit_balance ?? 0);
                            } else {
                                $perbest = 0;
                            }
                              
                          }else{
                          $perbest = $total_client_bought_price - $total_client_net_price;
                          }
                          
                        $totalValue = $perbest - ($perbest * $percent);   
                        //                 dd($value);
                        ?>
                     <tr>
                        <td class="{{$bg}}" style="text-align:right;{{$style}}">{{$invoice->es_id}}</td>
                        <td class="{{$bg}}" style="text-align:right;{{$style}}">{{$invoice->invoice_date}}</td>
                        <td class="{{$bg}}" style="{{$style}}">
                           @if($result == "FLY-RD")
                           <?php
                              /* 
                               net_pice_total = المسترد له / debit_balance
                               bought_price_total = المرتجع لنا / credit_balance
                              */
                              
                              $mostarad = App\Models\AccountStatement::select('*')->where('es_id',$invoice->es_id)
                                  ->where('credit_balance',0)->first();
                              
                               $mortaga = App\Models\AccountStatement::select('*')->where('es_id',$invoice->es_id)
                                  ->where('debit_balance',0)->first();
                              
                              
                              
                              ?>
                           @if($mostarad && $mortaga)
                           {{(float)($mostarad->debit_balance ?? 0) - (float)($mortaga->credit_balance ?? 0)}} ج.م
                           @else
                           0 ج.م
                           @endif
                           @else
                           {{$total_client_bought_price - $total_client_net_price}} ج.م
                           @endif
                        </td>
                        <td class="{{$bg}}" style="{{$style}}">{{$perbest - $totalValue}} ج.م</td>
                     </tr>
                     @endforeach
                     <?php
                        $shared_invoices = App\Models\Invoice::select('*')->where('invoice_shared' , 1)->where('invoice_account_1',Auth::user()->id)->get();
                        if(count($shared_invoices) == 0){
                        $shared_invoices = App\Models\Invoice::select('*')->where('invoice_shared' , 1)->where('invoice_account_2',Auth::user()->id)->get();
                        
                        } 
                        ?>
                     @foreach($shared_invoices as $invoice)
                     <?php
                        if($invoice->invoice_shared == 0){
                            $bg = "";
                            $style = "";
                        }else{
                            $bg = "bg-dark";
                            $style = "color:white;";
                        }
                        ?>
                     <?php
                        $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice->ticket_system_id)->get();
                          
                          $total_client_net_price = 0; // Fixed typo
                          $total_client_bought_price = 0;
                          foreach($users as $user){
                              // Cast to float and handle null values
                              $total_client_net_price += (float)($user->client_net_pice ?? 0);
                              $total_client_bought_price += (float)($user->client_bought_price ?? 0);
                          }
                        
                        
                        $mem_info = App\Models\User::select('*')->where('id' ,$invoice->invoice_create_by)->first();
                          
                        // Check if user exists
                        if(!$mem_info) {
                           continue; // Skip this invoice if user not found
                        }
                          
                          if($invoice->invoice_shared == 0){     
                        $value = (float)($mem_info->commission ?? 0);
                        }else{
                         $value = 5;
                        }
                        $percent = $value / 100;
                        
                            $perbest = $total_client_bought_price - $total_client_net_price;
                        $totalValue = $perbest - ($perbest * $percent);    
                        
                        ?>
                     <tr>
                        <td class="{{$bg}}" style="text-align:right;{{$style}}">{{$invoice->es_id}}</td>
                        <td class="{{$bg}}" style="text-align:right;{{$style}}">{{$invoice->invoice_date}}</td>
                        <td class="{{$bg}}" style="text-align:right;{{$style}}">{{$total_client_bought_price - $total_client_net_price}} ج.م</td>
                        <td class="{{$bg}}" style="{{$style}}">{{$perbest - $totalValue}} ج.م</td>
                     </tr>
                     @endforeach
                  </tbody>
                  <tfoot>
                     <tr>
                        <td style="text-align:right;">الاجمالي</td>
                        <td style="text-align:right;">--</td>
                        <td style="text-align:right;color:white;" id="subtotal" class="bg-dark">0</td>
                        <td style="text-align:right;color:white;" id="total" class="bg-dark">0</td>
                     </tr>
                  </tfoot>
               </table>
            </div>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
<script>
   let Mytable = new DataTable('#InvoicesTable', {
       pageLength: 50,
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
<script>
   $(function() {
   // Calculate totals for all rows, not just visible ones
   calculateAllTotals();
    
   //          $("#total0").html(calc_get());
   
    
    
   });
   
   function calc_get(){
    
    var val1 = parseInt($("#subtotal").text());
    var val2 = parseInt($("#total").text());
    var val1_total = val1 + val2;
    
     $('#total0').html(val1_total);
   }
   
   function calculateAllTotals() {
   var profitTotal = 0;
   var commissionTotal = 0;
   
   // Get all table data, not just visible rows
   Mytable.rows().every(function() {
   var data = this.data();
   
   // Extract profit value (column 3, index 2)
   var profitText = $(data[2]).text() || data[2];
   var profitValue = parseFloat(profitText.replace(/[^\d.-]/g, '')) || 0;
   profitTotal += profitValue;
   
   // Extract commission value (column 4, index 3)  
   var commissionText = $(data[3]).text() || data[3];
   var commissionValue = parseFloat(commissionText.replace(/[^\d.-]/g, '')) || 0;
   commissionTotal += commissionValue;
   });
   
   $("#subtotal").html(profitTotal + " ج.م ");
   $("#total").html(commissionTotal + " ج.م ");
   }
   
   function sumColumn(index) {
   var total = 0;
   $("td:nth-child(" + index + ")").each(function() {
   // Improved parsing to handle Arabic currency symbol
   var text = $(this).text().replace(/[^\d.-]/g, ''); // Remove non-numeric characters except decimal and minus
   total += parseFloat(text) || 0;
   
   });  
   return total + " ج.م ";
   }
   
   
</script>  
<script>
   function Removecustomer(id){
       
         swal({
        title: "هل انت متأكد؟",
        text: "سيتم حذف ذلك الفاتورة وازالة كل البيانات المرتبطه بها",
        icon: "warning",
        buttons: true,
        dangerMode: true,
      })
      .then((willDelete) => {
        if (willDelete) {
      //       var url = "http://teacher.cuoratech.com/aladmin_srp/sections/" + id + "/remove";
      var url = "{{url('')}}/customers/" + id + "/delete";
   //                     alert(url);
            window.location.href = url;
            
        } else {
          swal("تم الغاء عملية الحذف بنجاح");
        }
      });
       
   }
</script>
@endsection
