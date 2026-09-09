@extends('layouts.print')
@section('content')
<?php
//dd($stpp);
if($stpp = 1){
    $title_1 = "كشف حساب";
}else{
    $title_1 = "كشف حساب";
}
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
    
    <img src="{{asset('assets/images/flymix_colored.png')}}"  style="  width: 154px;">
        <h5 style="  float: right;  text-align: right;  line-height: 31px;">
            
             @if(isset($id)) 
             تقرير تذاكر الموظف : {{$user_info->name}}
              @else
                تقرير تذاكر عام
                @endif
                @if(isset($date_from) && isset($date_to))
                <br>
                - من {{$date_from}} الي {{$date_to}}
                @endif
                
                @if(isset($invoice_section))
                <br>
                <?php
                          if($invoice_section == 1){
                              $type = "فواتير الطيران";
                          }elseif($invoice_section == 2){
                              $type = "فواتير تأشيرات";
                          }elseif($invoice_section == 3){
                              $type = "فواتير سياحه داخليه";
                          }elseif($invoice_section == 4){
                              $type = "فواتير سياحه خارجيه";
                          }elseif($invoice_section == 5){
                              $type = "فواتير سياحه دينيه";
                          }elseif($invoice_section == 6){
                              $type = "فواتير تأمينات السفر";
                          }elseif($invoice_section == 7){
                              $type = "فواتير تحاليل السفر";
                          }elseif($invoice_section == 8){
                              $type = "فواتير نقل سياحى";
                          }
                          ?>
                نوع البحث : {{$type}}
                @endif
                
                @if(isset($invoice_airline))
                    <br> 
                خط الطيران : {{$invoice_airline}}
                @endif
                       @if(isset($invoice_beneficiaries))
                    <br> 
                المستفيد  : {{$invoice_beneficiaries}}
                @endif
                
                   @if(isset($vendor_id))
                    <br> 
                المورد  : {{$vendor_id}}
                @endif
                
                
        </h5>
        <hr>
       
        <div class="t">
               <table id="customers"  dir="rtl">
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
                   
            if(isset($vendor_id)){
                
                $getVendor = App\Models\TicketVendor::select('*')->where('ticket_system_id',$invoice->ticket_system_id)->get();
                $getVendor = $getVendor[0];
                
                if($getVendor->vendor_id == $vendor_id){
                    $show = 1;
                }else{
                    $show = 0;
                }
                
            }else{
                $show = 1;
            }    
                   
//                   dd($show); 
                   ?>
                  @if($show == 1)
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
                      
                      
                $value = $mem_info->commission;
                      $value = (int) $value;
                $percent = $value / 100;

                      $perbest = $total_client_bought_price - $total_client_net_pice;
                $totalValue = $perbest - ($perbest * $percent);   
//                 dd($value);
                      
                      ?>
                      <td class="{{$bg}}" style="{{$style}}">{{$mem_info->name}}</td>
                       <td class="{{$bg}}" style="{{$style}}">{{$total_client_bought_price - $total_client_net_pice}} ج.م</td>

                                             <td class="{{$bg}}" style="{{$style}}">{{$perbest - $totalValue}} ج.م</td>

<!--                      <td>AUTH</td>-->
                   
                  </tr>
                  
                   @endif
                  @endforeach
                   <?php
                if($stpp == 1){
                   $shared_invoices = App\Models\Invoice::select('*')->where('invoice_shared' , 1)->where('invoice_account_1',$id)->get();
                   if(count($shared_invoices) == 0){
                   $shared_invoices = App\Models\Invoice::select('*')->where('invoice_shared' , 1)->where('invoice_account_2',$id)->get();

                   }    
                }else{
                      $shared_invoices = App\Models\Invoice::select('*')->where('invoice_shared' , 1)->get();
                   
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
                   
                       <td>الاجمالي</td>
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
      <script src="https://cuoratech.com/public/assets/jquery-3.7.0.slim.min.js?v=6655"></script> 

  <script>
            window.onload = setTimeout(function(){
    
window.print();
}, 1000);
       function printdiv(){
          var url = "";
//    alert(url);
           var printWindow = window.open( url, 'Print', 'left=200, top=200, width=950, height=500, toolbar=0, resizable=0');
       printWindow.addEventListener('load', function(){
//           printWindow.print();
//           printWindow.close();
       }, true);
          
      }
      
      
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
