@extends('layouts.app')
@section('content')
@section('title' , "تفاصيل الفاتورة")
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
            <h5 class="card-title mb-0"> تعديل الفاتورة : <b>{{$invoice_info->es_id}}</b>
                <a href="{{route('site.invoices_edit_passenger', $invoice_info->id)}}" class="btn btn-sm btn-outline-primary" style="float:left;">تعديل راكب واحد فقط</a>
            </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.invoices_save_update')}}" method="POST" autocomplete="off" enctype="multipart/form-data">
                <?php
                
                 $result = substr($invoice_info->es_id, 0, 6);
                ?>
               @csrf
                 @if($result == "FLY-RD")
                  <input type="hidden" name="is_rfund" value="1">
                @else
                 <input type="hidden" name="is_rfund" value="0">
                @endif
                <input type="hidden" name="id" value="{{$invoice_info->id}}">
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        تاريخ الفاتورة
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_date" value="{{$invoice_info->invoice_date}}" placeholder="تاريخ الفاتورة" class="form-control" style="text-align:right;" readonly>
                     <small class="text-muted">تاريخ الفاتورة الأصلي لا يتغير؛ أي تعديل مالي يُسجل بتاريخ اليوم.</small>
                  </div>
                  <div class="col-6">
                    <p>
                        تاريخ السفر
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_travel_date" value="{{$invoice_info->invoice_travel_date}}" placeholder="تاريخ السفر" class="form-control" style="text-align:right;" required>
                  </div>
               </div>
                    <p>
                        تاريخ العودة
                     </p>
                     <input type="date" name="return_date" value="{{$invoice_info->return_date}}" placeholder="تاريخ العودة" class="form-control" style="text-align:right;">
                <br>
                
               <div class="row">
      <div class="col-6 mb-3">
         <p>
            واجهة الاستلام 
      <span class="text-danger">*</span>
          </p>
            <input type="text" name="from_location" value="{{$invoice_info->from_location}}" placeholder="واجهة الاستلام" class="form-control" style="text-align:right;" required="">
      </div>
      <div class="col-6">
         <p>
            واجهة الوصول 
         <span class="text-danger">*</span>
          </p>
            <input type="text" name="to_location" value="{{$invoice_info->to_location}}" placeholder="واجهة الوصول" class="form-control" style="text-align:right;" required="">
      </div>
    </div>
                 <p>
                        خط الطيران
                 <span class="text-danger">*</span>     
                </p>
                     <select class="form-control" name="invoice_airline" id="invoice_airline" style="text-align:right;" required>
                         @foreach($airlines as $airline)
                         <option value="{{$airline->airline_name}}" @if($invoice_info->invoice_airline == $airline->airline_name) selected @endif>{{$airline->airline_name}}</option>
                         @endforeach
                     </select>
                <br> 
                
                <p>
                        الجروب
                     </p>
                     <select class="form-control" name="invoice_group_id" id="invoice_group_id" style="text-align:right;">
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
               <select class="form-control" name="invoice_beneficiaries" id="invoice_group_id" style="text-align:right;" required>
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
                          $total_client_net_pice += (float) $user->client_net_pice;
                          $total_client_bought_price += (float) $user->client_bought_price;
                      }
                         
                         
                             ?>
                         
                        <select name="vendor_id" class="form-control" required>
                           @foreach($suppliers as $supplier)
                           <option value="{{$supplier->id}}" @if($supplier->id == $ticket_vendor->vendor_id) selected @endif>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                           @endforeach
                        </select>
                     </td>
                      <input type="hidden" name="vendor_cost" value="{{$total_client_net_pice}}">
                     <td><input type="text" name="vendor_cost_nn" id="vendor_cost" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{$total_client_net_pice}}" placeholder="التكلفة" disabled class="form-control" required="" /></td>
                  </tr>
               </table>
               <p>
                  تصنيف الفاتورة
                  <span class="text-danger">*</span>
               </p>
               <select class="form-control" style="text-align:right;" name="invoice_section" required>
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
                     <input type="text" name="invoice_comments" value="{{$invoice_info->invoice_comments}}" placeholder="ملاحظات" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6">
                     <p>
                        العملة
                        <span class="text-danger">*</span>
                     </p>
                     <select class="form-control" style="text-align:right;" name="invoice_currency" required>
                       
                         <option value="جنية مصري" @if($invoice_info->invoice_currency == "جنية مصري") selected @endif>جنية مصري</option>
                         <option value="درهم اماراتي" @if($invoice_info->invoice_currency == "درهم اماراتي") selected @endif>درهم اماراتي</option>
                         
                     </select>
                  </div>
               </div>
               <p>
                  مسودة
               </p>
               <textarea class="form-control" style="text-align:right;" name="invoice_draft" placeholder="مسودة">{{$invoice_info->invoice_draft}}</textarea>
               <br> 
               <p>
                  ملف / صورة التذكرة
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
                <br>
                   لا تقم بتغيير الملف في حالة عدم الرغبة في التغيير
                   
               </p>
               <input type="file" class="form-control" name="myPoster" accept="image/jpeg,image/jpg,image/png,application/pdf">
               <br>
               <h6 style="font-size: 16px;">
                  معلومات الفاتوره
                  <span class="text-danger">*</span>
               </h6>
               <table class="table table-bordered" id="dynamicTableTwo">
                  <tr>
                     <th>الاسم</th>
                     <th>نوع الراكب</th>
                     @if($result == "FLY-RD")
                     <th>مسترد للعميل (دائن العميل)</th>
                     <th>مرتجع لنا من المورد (مدين المورد)</th>
                     @else
                     <th>سعر التكلفة</th>
                     <th>سعر البيع</th>
                     @endif
                     <th>رقم الحجز</th>
                     <th>رقم التكت</th>
                     <th>رقم الهاتف</th>
                  </tr>
                   <?php 
                   $x = 0;
                   ?>
                   @foreach($users as $user)
                  <tr>
                     <td>
                        <input type="hidden" name="ticket_info[{{$x}}][id]" value="{{$user->id}}">
                        <input type="text" value="{{$user->client_name}}" name="ticket_info[{{$x}}][name]" placeholder="الاسم" class="form-control">
                     </td>
                     <td>
                        <select class="form-control" name="ticket_info[{{$x}}][client_type]">
                           <option value="1" @if($user->client_type == 1) selected @endif>رضيع</option>
                           <option value="2" @if($user->client_type == 2) selected @endif>طفل</option>
                           <option value="3" @if($user->client_type == 3) selected @endif>بالغ</option>
                        </select>
                     </td>
                     <td>
                        <input type="number" name="ticket_info[{{$x}}][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" value="{{$shares[$user->id][1] ?? $user->client_net_pice}}" step="0.01" /> 
                     </td>
                     <td>
                        <input type="text" name="ticket_info[{{$x}}][bought_price]" oninput="findTotal2()" oninput="" placeholder="سعر البيع" class="form-control amount2" value="{{$shares[$user->id][0] ?? $user->client_bought_price}}" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_booking_id}}" name="ticket_info[{{$x}}][book_id]" placeholder="رقم الحجز" class="form-control" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_ticket_id}}" name="ticket_info[{{$x}}][tikcet_id]" placeholder="رقم الحجز" class="form-control" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_phone}}" name="ticket_info[{{$x}}][client_phone]" placeholder="رقم الهاتف" class="form-control" />
                     </td>
                    
                     <td>
                         @if($x == 0)
<!--                         <button type="button" name="add_create" id="add_create" class="btn btn-success">اضف</button>-->
                      @endif
                      </td>
                  </tr>
                   <?php $x++; ?>
                   @endforeach
               </table>
               <br>
                @if($result == "FLY-RD")
                <?php
                // Refund amounts are passenger-level: each ledger row = the sum of the
                // passengers' amounts above (saved passenger by passenger).
                $refund_credit_total = array_sum(array_map(fn ($sh) => $sh[1], $shares));
                $refund_debit_total = array_sum(array_map(fn ($sh) => $sh[0], $shares));
                ?>
                  <div class="row">
      <div class="col-6 mb-3">
          <p>
          مسترد للعميل (مجموع الركاب)
          </p>
          <input type="number" value="{{$refund_credit_total}}" class="form-control" style="text-align:right;" readonly>
      </div>
      <div class="col-6">
          <p>
          مرتجع لنا من المورد (مجموع الركاب)
          </p>
            <input type="number" value="{{$refund_debit_total}}" class="form-control" style="text-align:right;" readonly>
      </div>
    </div>
                @else
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
            @endif    
                
               <br> 
                @if($total_client_bought_price < $total_client_net_pice)
                <div class="alert alert-danger border-0">
                <center>
                    
                    تنوية : هناك مديونية بسبب زيادة سعر التكلفة عن سعر البيع وتبلغ قيمتها {{$total_client_bought_price - $total_client_net_pice}} {{$invoice_info->invoice_currency}}  
                    </center>
                </div>
             @endif
              @if(Auth::user()->account_type == 2)
                <br>
                <p>
                الحساب الذي ترغب في اضافة التذكرة فيه من الموظفين
                </p>
                <select class="form-select" name="added_user">
                <?php
                    $users = App\Models\User::all();
                    ?>
                    @foreach($users as $user)
                    <option value="{{$user->id}}" @if($invoice_info->invoice_create_by == $user->id) selected="" @endif>{{$user->name}}</option>
                    @endforeach
                </select>
                @else
                <input type="hidden" name="added_user" value="{{Auth::user()->id}}">
                @endif
                
             
               <br> 
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                  اضافة فاتورة  
                  </button>
                   

                   
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script type="text/javascript">
    
 var i = {{count($users) - 1}};
 var i2 = 0;
   
      
   
   $("#add_create").click(function(){
   
   
   
       ++i;
   
   
   
   
       $("#dynamicTableTwo").append('<tr><td><input type="text" name="ticket_info['+i+'][name]" placeholder="الاسم" class="form-control" required="" /></td><td><select class="form-control" name="ticket_info['+i+'][client_type]" required=""><option value="1">رضيع</option><option value="2">طفل</optio><option value="3">بالغ</option></select></td><td><input type="number" name="ticket_info['+i+'][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" required="" /></td>           <td><input type="text" name="ticket_info['+i+'][bought_price]" placeholder="سعر البيع" oninput="findTotal2()" class="form-control amount2" required="" /></td>           <td><input type="text" name="ticket_info['+i+'][book_id]" placeholder="رقم الحجز" class="form-control" required="" /></td><td><input type="text" name="ticket_info['+i+'][tikcet_id]" placeholder="رقم الحجز" class="form-control" required="" /></td><td><input type="text" name="ticket_info['+i+'][client_phone]" placeholder="رقم الهاتف" class="form-control" required="" /></td><td><button type="button" class="btn btn-danger remove-tr">ازالة</button></td></tr>');
   
   });
   
   
   
   $(document).on('click', '.remove-tr', function(){  
   
        $(this).parents('tr').remove();
   
   });  
   
    
    
</script>
@endsection
