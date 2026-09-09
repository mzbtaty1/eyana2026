@extends('layouts.app')
@section('content')
@section('title' , 'التقرير اليومي')
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
            <h5 class="card-title mb-0">
             
             التقرير اليومي - {{date('Y/m/d')}}
             </h5>
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
             <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100% !important;">
               <thead>
                  <tr>
                     <th data-ordering="false">#</th>
                     <th data-ordering="false">تاريخ السفر</th>
                     <th data-ordering="false">المستفيد</th>
                     <th data-ordering="false">المورد</th>
                     <th data-ordering="false">الاستلام / الوصول</th>
                     <th data-ordering="false">بيانات الراكب</th>
                     <th data-ordering="false">بيانات الحجز</th>
                     <th data-ordering="false">الموظف</th>
                     <th data-ordering="false" class="bg-dark" style="color:white;">الربح</th>
                      <th data-ordering="false" class="bg-dark" style="color:white;">العمولة</th>
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
                   ?>
                  <tr>
                     <td class="{{$bg}}" style="{{$style}}">{{$invoice->es_id}}</td>
                     <td class="{{$bg}}" style="{{$style}}">{{$invoice->invoice_travel_date}}</td>
                     <td class="{{$bg}}" style="{{$style}}">
                        <?php
                         $ben_info = App\Models\Supplier::select('*')->where('id' , $invoice->invoice_beneficiaries)->get();
                            if(count($ben_info) == 0){
                                $ben_info = [];
                            } else {
                                $ben_info = $ben_info[0];
                            }
                             ?>
                         {{$ben_info->name}}
                     </td>
                     <td class="{{$bg}}"  style="{{$style}}">
                      <?php
                         $vendors = App\Models\TicketVendor::select('*')->where('ticket_system_id' , $invoice->ticket_system_id)->get();
                           
                             ?>
                         @foreach($vendors as $vendor)
                         <?php
                         $vendor_info = App\Models\Supplier::select('*')->where('id' , $vendor->vendor_id)->get();
                         $vendor_info = $vendor_info[0];
                         ?>
                         {{$vendor_info->name}} /
                         @endforeach
                      </td>
                     <?php
                      $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice->ticket_system_id)->get();
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                      
                      ?>
                      <td class="{{$bg}}" style="{{$style}}">{{$invoice->from_location}} - {{$invoice->to_location}}</td>
                      <td class="{{$bg}}" style="{{$style}}">
                      @foreach($users as $user) 
    
                         {{$user->client_name}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                          
                      </td>
                      

                      <td class="{{$bg}}" style="{{$style}}">
                         ارقام الحجز : @foreach($users as $user) 
                        
                         {{$user->client_booking_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                         
                          <br>
                         
                          ارقام التذاكر : @foreach($users as $user) 
                        
                         {{$user->client_ticket_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                     
                        
                      </td>
                      <?php
                      $mem_info = App\Models\User::select('*')->where('id' ,$invoice->invoice_create_by)->get();
                      $mem_info = $mem_info[0];
                      
                 if($invoice->invoice_shared == 0){     
                $value = $mem_info->commission;
                 }else{
                     $value = 5;
                 }
                $percent = $value / 100;
     $perbest = $total_client_bought_price - $total_client_net_pice;
                $totalValue = $perbest - ($perbest * $percent);   
                 
                      
                      ?>
                                                                   <td class="{{$bg}}" style="{{$style}}">{{$mem_info->name}}</td>

    <td class="{{$bg}}" style="{{$style}}">{{$total_client_bought_price - $total_client_net_pice}} ج.م</td>

<td class="{{$bg}}" style="{{$style}}">{{$perbest - $totalValue}} ج.م</td>


<!--                      <td>AUTH</td>-->
                   
                  </tr>
                 
                  @endforeach
                 <?php
                   $shared_invoices = App\Models\Invoice::select('*')->where('invoice_shared' , 1)->where('invoice_account_1',Auth::user()->id)->where('invoice_date', date('Y-m-d'))->get();
                   if(count($shared_invoices) == 0){
                   $shared_invoices = App\Models\Invoice::select('*')->where('invoice_shared' , 1)->where('invoice_account_2',Auth::user()->id)->where('invoice_date', date('Y-m-d'))->get();

                   } 
                  
//                 $shared_invoices = $shared_invoices->
                 
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
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                   
                    
                   $mem_info = App\Models\User::select('*')->where('id' ,$invoice->invoice_create_by)->get();
                      $mem_info = $mem_info[0];
                      
                 $value = 5;
                $percent = $value / 100;

                $totalValue = $total_client_bought_price - ($total_client_bought_price * $percent);   
                   
                   ?>
                   <tr>
                     <td class="{{$bg}}" style="{{$style}}">{{$invoice->es_id}}</td>
                     <td class="{{$bg}}" style="{{$style}}">{{$invoice->invoice_travel_date}}</td>
                     <td class="{{$bg}}" style="{{$style}}">
                        <?php
                         $ben_info = App\Models\Supplier::select('*')->where('id' , $invoice->invoice_beneficiaries)->get();
                            if(count($ben_info) == 0){
                                $ben_info = [];
                            } else {
                                $ben_info = $ben_info[0];
                            }
                             ?>
                         {{$ben_info->name}}
                     </td>
                     <td class="{{$bg}}"  style="{{$style}}">
                      <?php
                         $vendors = App\Models\TicketVendor::select('*')->where('ticket_system_id' , $invoice->ticket_system_id)->get();
                           
                             ?>
                         @foreach($vendors as $vendor)
                         <?php
                         $vendor_info = App\Models\Supplier::select('*')->where('id' , $vendor->vendor_id)->get();
                         $vendor_info = $vendor_info[0];
                         ?>
                         {{$vendor_info->name}} /
                         @endforeach
                      </td>
                     <?php
                      $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice->ticket_system_id)->get();
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                      
                      ?>
                      <td class="{{$bg}}" style="{{$style}}">{{$invoice->from_location}} - {{$invoice->to_location}}</td>
                      <td class="{{$bg}}" style="{{$style}}">
                      @foreach($users as $user) 
    
                         {{$user->client_name}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                          
                      </td>
                      

                      <td class="{{$bg}}" style="{{$style}}">
                         ارقام الحجز : @foreach($users as $user) 
                        
                         {{$user->client_booking_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                         
                          <br>
                         
                          ارقام التذاكر : @foreach($users as $user) 
                        
                         {{$user->client_ticket_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                     
                        
                      </td>
                      <?php
                      $mem_info = App\Models\User::select('*')->where('id' ,$invoice->invoice_create_by)->get();
                      $mem_info = $mem_info[0];
                      
              $value = 5;
                $percent = $value / 100;
     $perbest = $total_client_bought_price - $total_client_net_pice;
                $totalValue = $perbest - ($perbest * $percent);   
                 
                      
                      ?>
                                             <td class="{{$bg}}" style="{{$style}}">{{$mem_info->name}}</td>
 
                         <td class="{{$bg}}" style="{{$style}}">{{$total_client_bought_price - $total_client_net_pice}} ج.م</td>

<td class="{{$bg}}" style="{{$style}}">{{$perbest - $totalValue}} ج.م</td>

<!--                      <td>AUTH</td>-->
                   
                  </tr>
                 
       @endforeach
                  <tr>
                   <td>--</td>
                      <td>--</td>
                      <td>--</td>
                      <td>--</td>
                      <td>--</td>
                      <td>--</td>
                      <td>--</td>
                      <td>--</td>
                        
                       <td class="bg-dark" id="subtotal" style="color:white;">0</td>
                       <td class="bg-dark" id="total" style="color:white;">0</td>
                   </tr> 
               </tbody>
               
                 </table>
    
             </div>
          
         </div>
      </div>
   </div>
   <!--end col-->
</div>
  <script>
      $.fn.dataTable.ext.errMode = 'none';
      $(function() {
  $("#subtotal").html(sumColumn(9));
  $("#total").html(sumColumn(10));
          
//          $("#total0").html(calc_get());
  
          
          
});

      function calc_get(){
          
          var val1 = parseInt($("#subtotal").text());
          var val2 = parseInt($("#total").text());
          var val1_total = val1 + val2;
          
           $('#total0').html(val1_total);
      }
      
function sumColumn(index) {
  var total = 0;
  $("td:nth-child(" + index + ")").each(function() {
     total += parseInt($(this).text(), 10) || 0;
    
  });  
  return total + " ج.م ";
}
      
      
      
       let table = new DataTable('#InvoicesTable', {
           ordering: false,
           columnDefs: [
        {
            target: 10,
            visible: false,
        },
               {
            target: 11,
            visible: false,
        },

        {
            target: 13,
            visible: false,
        },
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
