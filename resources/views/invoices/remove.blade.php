@extends('layouts.app')
@section('content')
@section('title' , "حذف الفاتورة")
<style>
    .aler_error{
        display: none !important;
    }
</style>

@if($errors->any())

<script>
swal("", "{{$errors->first()}}", "info");

</script>
@endif 
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> حذف الفاتورة : <b>{{$invoice_info->es_id}}</b> </h5>
         </div>
         <div class="card-body">
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        تاريخ الفاتورة
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_date" value="{{$invoice_info->invoice_date}}" disabled="" placeholder="تاريخ الفاتورة" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6">
                    <p>
                        تاريخ السفر
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_travel_date" value="{{$invoice_info->invoice_travel_date}}" disabled="" placeholder="تاريخ السفر" class="form-control" style="text-align:right;" required>
                  </div>
               </div>
                 <p>
                        تاريخ العودة
                     </p>
                     <input type="date" name="return_date" value="{{$invoice_info->return_date}}" placeholder="تاريخ العودة" class="form-control" style="text-align:right;" disabled>
                <br>
                
               <div class="row">
      <div class="col-6 mb-3">
         <p>
            واجهة الاستلام 
      <span class="text-danger">*</span>
          </p>
            <input type="text" name="from_location" value="{{$invoice_info->from_location}}" disabled="" placeholder="واجهة الاستلام" class="form-control" style="text-align:right;" required="">
      </div>
      <div class="col-6">
         <p>
            واجهة الوصول 
         <span class="text-danger">*</span>
          </p>
            <input type="text" name="to_location" value="{{$invoice_info->to_location}}" disabled="" placeholder="واجهة الوصول" class="form-control" style="text-align:right;" required="">
      </div>
    </div>
                
                 <p>
                        خط الطيران
                 <span class="text-danger">*</span>     
                </p>
                     <select class="form-control" name="invoice_airline" id="invoice_airline" disabled="" style="text-align:right;" required>
                         @foreach($airlines as $airline)
                         <option value="{{$airline->airline_name}}" @if($invoice_info->invoice_airline == $airline->airline_name) selected @endif>{{$airline->airline_name}}</option>
                         @endforeach
                     </select>
                <br> 
                
                <p>
                        الجروب
                     </p>
                     <select class="form-control" name="invoice_group_id" id="invoice_group_id" disabled="" style="text-align:right;">
                     </select>
                <br>
               
                <?php
                         $ben_info = App\Models\Supplier::select('*')->where('id' , $invoice_info->invoice_beneficiaries)->get();
                            if(count($ben_info) == 0){
                                $ben_info = [];
                            } else {
                                $ben_info = $ben_info[0];
                            }
                             ?>
               <p>
                  اسم العميل (المستفيد)
                  <span class="text-danger">*</span>
               </p>
               <select class="form-control" name="invoice_beneficiaries" id="invoice_group_id" style="text-align:right;" disabled="" required>
                  @foreach($suppliers as $supplier)
                  <option value="{{$supplier->id}}" @if($supplier->id == $ben_info->id) selected @endif>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                  @endforeach
               </select>
               <br>
               <table class="table table-bordered">
                  <tr>
                     <th>
                        اسم المورد (المنفذ)  
                        <span class="text-danger">*</span>
                     </th>
                     <th>
                        التكلفة  
                       
                     </th>
                  </tr>
                  <tr>
                     <td>
                        <!--                <input type="text" name="vendor[0][name]" placeholder="Enter your Name" class="form-control" />-->
                        <!--                    <select name="vendor[0][vendor_id]" class="form-control">-->
                         
                         <?php
                         
                         $ticket_vendor = App\Models\TicketVendor::select('*')->where('ticket_system_id' , $invoice_info->ticket_system_id)->get();
                         if(count($ticket_vendor) == 0){
                                $ticket_vendor = [];
                            } else {
                                $ticket_vendor = $ticket_vendor[0];
                            }
                         
                      $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice_info->ticket_system_id)->get();
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                         
                         
                             ?>
                         
                        <select name="vendor_id" class="form-control" disabled="" required>
                           @foreach($suppliers as $supplier)
                           <option value="{{$supplier->id}}" @if($supplier->id == $ticket_vendor->vendor_id) selected @endif>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                           @endforeach
                        </select>
                     </td>
                     <td><input type="text" name="vendor_cost" id="vendor_cost" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{$total_client_net_pice}}" placeholder="التكلفة" disabled class="form-control" required="" /></td>
                  </tr>
               </table>
               <p>
                  تصنيف الفاتورة
                  <span class="text-danger">*</span>
               </p>
               <select class="form-control" style="text-align:right;" name="invoice_section" disabled="" required>
                  <option value="1" @if($invoice_info->invoice_section == 1) selected @endif>فواتير الطيران</option>
                  <option value="2" @if($invoice_info->invoice_section == 2) selected @endif>فواتير تأشيرات</option>
                  <option value="3" @if($invoice_info->invoice_section == 3) selected @endif>فواتير سياحه داخليه</option>
                  <option value="4" @if($invoice_info->invoice_section == 4) selected @endif>فواتير سياحه خارجيه</option>
                  <option value="5" @if($invoice_info->invoice_section == 5) selected @endif>فواتير سياحه دينيه</option>
                  <option value="6" @if($invoice_info->invoice_section == 6) selected @endif>فواتير تأمينات السفر</option>
                  <option value="7" @if($invoice_info->invoice_section == 7) selected @endif>فواتير تحاليل السفر</option>
                  <option value="8" @if($invoice_info->invoice_section == 8) selected @endif>فواتير نقل سياحى</option>
               </select>
               <br>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        ملاحظات
                     </p>
                     <input type="text" disabled="" name="invoice_comments" value="{{$invoice_info->invoice_comments}}" placeholder="ملاحظات" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6">
                     <p>
                        العملة
                        <span class="text-danger">*</span>
                     </p>
                     <select class="form-control" style="text-align:right;" name="invoice_currency" disabled="" required>
                       
                         <option value="جنية مصري" @if($invoice_info->invoice_currency == "جنية مصري") selected @endif>جنية مصري</option>
                         <option value="درهم اماراتي" @if($invoice_info->invoice_currency == "درهم اماراتي") selected @endif>درهم اماراتي</option>
                         
                     </select>
                  </div>
               </div>
               <p>
                  مسودة
               </p>
               <textarea class="form-control" disabled="" style="text-align:right;" name="invoice_draft" placeholder="مسودة">{{$invoice_info->invoice_draft}}</textarea>
               <br> 
               <p>
                  ملف / صورة التذكرة
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
                <br>
                   لا تقم بتغيير الملف في حالة عدم الرغبة في التغيير
                   
               </p>
               <input type="file" disabled="" class="form-control" name="myPoster" accept="image/jpeg,image/jpg,image/png,application/pdf">
               <br>
               <h6 style="font-size: 16px;">
                  معلومات الفاتوره
                  <span class="text-danger">*</span>
               </h6>
               <table class="table table-bordered" id="dynamicTableTwo">
                  <tr>
                     <th>الاسم</th>
                     <th>نوع الراكب</th>
                     <th>سعر التكلفة</th>
                     <th>سعر البيع</th>
                     <th>رقم الحجز</th>
                     <th>رقم التكت</th>
                     <th>رقم الهاتف</th>
                  </tr>
                   <?php $x = 0; ?>
                   @foreach($users as $user)
                  <tr>
                     <td>
                        <input type="text" value="{{$user->client_name}}" name="ticket_info[{{$x}}][name]" placeholder="الاسم" class="form-control" disabled>
                     </td>
                     <td>
                        <select class="form-control" name="ticket_info[{{$x}}][client_type]" disabled>
                           <option value="1" @if($user->client_type == 1) selected @endif>رضيع</option>
                           <option value="2" @if($user->client_type == 2) selected @endif>طفل</option>
                           <option value="3" @if($user->client_type == 3) selected @endif>بالغ</option>
                        </select>
                     </td>
                     <td>
                        <input type="number" name="ticket_info[{{$x}}][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" value="{{$user->client_net_pice}}" disabled="" />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[{{$x}}][bought_price]" oninput="findTotal2()" oninput="" placeholder="سعر البيع" class="form-control amount2" value="{{$user->client_bought_price}}" disabled="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_booking_id}}" name="ticket_info[{{$x}}][book_id]" placeholder="رقم الحجز" class="form-control" disabled="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_ticket_id}}" name="ticket_info[{{$x}}][tikcet_id]" placeholder="رقم الحجز" class="form-control" disabled="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_phone}}" name="ticket_info[{{$x}}][client_phone]" placeholder="رقم الهاتف" class="form-control" disabled="" />
                     </td>
                    
                     
                  </tr>
                   <?php $x++; ?>
                   @endforeach
               </table>
               <br>
                  <div class="row">
      <div class="col-sm">
          <h4>
          اجمالي التكلفة الحالية : {{$total_client_net_pice}} {{$invoice_info->invoice_currency}}
          </h4>
      </div>
      <div class="col-sm">
          <h4>
          اجمالي البيع الحالي : {{$total_client_bought_price}} {{$invoice_info->invoice_currency}} 
          </h4>
      </div>
    </div>
                
                
               <br> 
                @if($total_client_bought_price < $total_client_net_pice)
                <div class="alert alert-danger border-0">
                <center>
                    
                    تنوية : هناك مديونية بسبب زيادة سعر التكلفة عن سعر البيع وتبلغ قيمتها {{$total_client_bought_price - $total_client_net_pice}} {{$invoice_info->invoice_currency}}  
                    </center>
                </div>
             @endif
               <br> 
               <div class="d-grid gap-2">
<!--
                
-->
                   
  @if(!empty($counterDeletion) && $counterDeletion['blocker'])
                  <div class="alert alert-danger border-0" role="alert">
                     <i class="ri-error-warning-line"></i> {{ $counterDeletion['blocker'] }}
                  </div>
  @elseif(!empty($counterDeletion) && $counterDeletion['bonds']->isNotEmpty())
                  <div class="alert alert-warning border-0" role="alert">
                     <b><i class="ri-information-line"></i> على هذه الفاتورة حركات مالية مرتبطة، سيتم عكسها تلقائياً مع الحذف (الخزنة / البنك وكشف حساب العميل):</b>
                     <ul class="mb-0 mt-1">
                        @foreach($counterDeletion['bonds'] as $bond)
                        <li>{{ (int) $bond->type === 2 ? 'سداد' : 'رد مبلغ للعميل' }} {{ $bond->es_id }} : {{ number_format((float) $bond->amount, 2) }} ج.م - {{ (int) $bond->money_way === 2 ? 'بنك' : 'نقدي' }} ({{ $bond->crt_date }})</li>
                        @endforeach
                     </ul>
                  </div>
  @endif
  <div class="d-grid gap-2">
                  <button class="btn btn-danger" onclick="Removecustomer('{{$invoice_info->es_id}}')" @if(!empty($counterDeletion) && $counterDeletion['blocker']) disabled @endif>
                  <i class="ri-delete-bin-line"></i>
                  حذف فاتورة  
                  </button>
                   

                   
               </div>
                   
               </div>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
<script type="text/javascript">
    
    
    
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
   var url = "{{url('')}}/invoices/" + id + "/remove/send";
//                     alert(url);
         window.location.href = url;
         
     } else {
       swal("تم الغاء عملية الحذف بنجاح");
     }
   });
    
}
    
   
    
    
</script>
@endsection
