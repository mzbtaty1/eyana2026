@extends('layouts.app')
@section('content')
@section('title' , 'الفواتير المشتركة')
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
            <h5 class="card-title mb-0">قائمة الفواتير المشتركة</h5>
             <a href="{{route('site.shared_invoices_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة فاتورة مشتركة
                 </button>
             </a>
         </div>
         <div class="card-body">
             <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100% !important;">
               <thead> 
                  <tr>
                     <th data-ordering="false">#</th>
                     <th data-ordering="false">تاريخ الفاتورة</th>
                     <th data-ordering="false">تاريخ السفر</th>
                     <th data-ordering="false">المستفيد</th>
                     <th data-ordering="false">المورد</th>
                     <th data-ordering="false">التكلفة</th>
                     <th data-ordering="false">البيع</th>
                     <th data-ordering="false">الاستلام / الوصول</th>
                     <th data-ordering="false">بيانات الراكب</th>
                     <th data-ordering="false">بيانات الحجز</th>
                     <th data-ordering="false">الربح</th>
                     <th data-ordering="false">نوع الفاتورة</th>
                     <th data-ordering="false">حالة الفاتورة</th>
                                            <th data-ordering="false">موظف الفاتورة</th>

                     <th data-ordering="false">وصف الفاتورة</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody> 
                  @foreach($invoices as $invoice)
                   <?php
                   
                    $result = substr($invoice->es_id, 0, 6);
                     if($result == "FLY-RD"){
                          $bg = "bg-success";
                       $style = "color:white;";
                     
                   }elseif($result == "FLY-RS"){
                          $bg = "bg-primary";
                       $style = "color:white;";
                     
                   }else{
                        $bg = "";
                       $style = "";
                   }
                   
                   ?>
                  <tr>
                     <td class="{{$bg}}" style="{{$style}}"><a href="{{asset($invoice->invoice_ticket_file)}}">
                         {{$invoice->es_id}}
                         </a></td> 
                     <td class="{{$bg}}" style="{{$style}}">{{$invoice->invoice_date}}</td>
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
                     <td class="{{$bg}}" style="{{$style}}">
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
                       @if($result == "FLY-RD")
                      <?php
                        $mostarad = App\Models\AccountStatement::select('*')->where('es_id',$invoice->es_id)
                               ->where('credit_balance',0)->get();
                           $mostarad = $mostarad[0];
                           
                            $mortaga = App\Models\AccountStatement::select('*')->where('es_id',$invoice->es_id)
                               ->where('debit_balance',0)->get();
                           $mortaga = $mortaga[0];
                           
                      
                      ?>
                      <td class="{{$bg}}" style="{{$style}}">{{$mostarad->debit_balance}}</td>
                      <td class="{{$bg}}" style="{{$style}}">{{$mortaga->credit_balance}}</td>
                      @else
                      <td class="{{$bg}}" style="{{$style}}">{{$total_client_net_pice}}</td>
                      <td class="{{$bg}}" style="{{$style}}">{{$total_client_bought_price}}</td>
                      @endif
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
                        <td class="{{$bg}}" style="{{$style}}">
                          @if($result == "FLY-RD")
                          {{$mortaga->credit_balance - $mostarad->debit_balance}}

                          @else
                          {{$total_client_bought_price - $total_client_net_pice}}
                      @endif
                      </td>
                      <td class="{{$bg}}" style="{{$style}}">
                          <?php
                          if($invoice->invoice_section == 1){
                              $type = "فواتير الطيران";
                          }elseif($invoice->invoice_section == 2){
                              $type = "فواتير تأشيرات";
                          }elseif($invoice->invoice_section == 3){
                              $type = "فواتير سياحه داخليه";
                          }elseif($invoice->invoice_section == 4){
                              $type = "فواتير سياحه خارجيه";
                          }elseif($invoice->invoice_section == 5){
                              $type = "فواتير سياحه دينيه";
                          }elseif($invoice->invoice_section == 6){
                              $type = "فواتير تأمينات السفر";
                          }elseif($invoice->invoice_section == 7){
                              $type = "فواتير تحاليل السفر";
                          }elseif($invoice->invoice_section == 8){
                              $type = "فواتير نقل سياحى";
                          }
                          ?>
                      {{$type}}
                      </td>
                      <td class="{{$bg}}" style="{{$style}}">
                      @if($invoice->invoice_status == 0)
                    <span class="badge bg-warning my_badge">لم يتم التأكد</span>
                          @else
                          <span class="badge bg-primary my_badge">تم التأكيد</span>
                          @endif
                          @if($invoice->invoice_money_pay == 0) 
                          
                           <span class="badge bg-dark my_badge">لم يتم السداد</span>
                          @else
                          
                          @if($invoice->invoice_money_pay < $total_client_bought_price)
                                 <span class="badge bg-secondary my_badge">سداد جزئي</span>  
                          @else
                    <span class="badge bg-success my_badge">تم السداد</span>  
                           @endif                                                          
                          
                          @endif
                          
                      </td>
                 
                 
                         
                 <td class="{{$bg}}" style="{{$style}}">
                 <?php
                     $mem_info1 = App\Models\User::select('*')->where('id',$invoice->invoice_account_1)->get();
                     $mem_info1 = $mem_info1[0];
                     
                       $mem_info2 = App\Models\User::select('*')->where('id',$invoice->invoice_account_2)->get();
                     $mem_info2 = $mem_info2[0];
                     ?>
                     {{$mem_info1->name}} / {{$mem_info2->name}}
                 </td>
                 
                      <td class="{{$bg}}" style="{{$style}}">
                          حجز الرحلة {{$invoice->es_id}}
                          اسماء : @foreach($users as $user) 
    
                         {{$user->client_name}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                         
                         
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
                         
                         
                          ارقام الجواز : @foreach($users as $user) 
                        
                         {{$user->client_passport_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                          
             </td>
                      <td class="{{$bg}}" style="{{$style}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="ri-more-fill align-middle"></i>
                                                        </button>

                          <ul class="dropdown-menu dropdown-menu-end">
   <li>
      <a href="{{route('site.invoices_show' , $invoice->id)}}" class="dropdown-item"><i class="ri-eye-fill align-bottom me-2 text-muted"></i> تفاصيل</a>
   </li>
                              
                              @if(Auth::user()->account_type == 2)                              
<li>
      <a href="{{route('site.invoices_edit' , $invoice->id)}}" class="dropdown-item">
          <i class="ri-edit-box-line"></i>
                        تعديل          
</a>
   </li>
@else
       @if($invoice->invoice_status == 0) 
                              
     <li>
      <a href="{{route('site.invoices_edit' , $invoice->id)}}" class="dropdown-item">
          <i class="ri-edit-box-line"></i>
                        تعديل          
</a>
   </li>
                              
            @endif                  
            @endif 
                              
    @if($invoice->invoice_money_pay < $total_client_bought_price)                          
     <li>
      <a href="{{route('site.invoices_pay_part' , $invoice->id)}}" class="dropdown-item"><i class="ri-wallet-3-line"></i> سداد الفاتورة</a>
   </li>
    @endif                          
                              <li>
      <a href="{{route('site.invoices_confirm' , $invoice->id)}}" class="dropdown-item">
         <i class="ri-checkbox-line"></i>
                                  اعتماد
                                  </a>      
                              <li>
      <a href="{{route('site.shared_invoices_reissue_create' , $invoice->es_id)}}" class="dropdown-item">
      <i class="ri-arrow-go-forward-line"></i>
                                    اعادة اصدار
                                  </a>
   </li>
                               <li> 
      <a href="{{route('site.shared_invoices_refund' , $invoice->es_id)}}" class="dropdown-item">
     <i class="ri-refund-2-line"></i>
                                   الغاء الفاتورة
                                  </a>
   </li>
              <li> 
      <a href="{{route('site.invoices_remove' , $invoice->es_id)}}" class="dropdown-item">
     <i class="ri-refund-2-line"></i>
                                   حذف الفاتورة
                                  </a>
   </li>
 
                              
</ul>

             </td>
                  </tr>
                  @endforeach
               </tbody>
            </table>
    
             </div>
          
         </div>
      </div>
   </div>
   <!--end col-->
</div>
  <script>
       let table = new DataTable('#InvoicesTable', {
           ordering: false,
    
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
