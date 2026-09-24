@extends('layouts.app')
@section('content')
@section('title' , "اعادة اصادر / تعديل فاتورة")
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
            <h5 class="card-title mb-0"> اعادة اصادر / تعديل فاتورة : <b>{{$invoice_info->es_id}}</b> </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.invoices_reissue_save')}}" method="POST" autocomplete="off" enctype="multipart/form-data">
               @csrf
                <input type="hidden" name="id" value="{{$invoice_info->id}}">
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        تاريخ الفاتورة
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_date" value="{{date('Y-m-d')}}" placeholder="تاريخ الفاتورة" class="form-control" style="text-align:right;" readonly>
                     <small class="text-muted">إعادة الإصدار عملية جديدة بتاريخ اليوم؛ الفاتورة الأصلية لا تتغير.</small>
                  </div>
                  <div class="col-6">
                    <p>
                        تاريخ السفر
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_travel_date" value="" placeholder="تاريخ السفر" class="form-control" style="text-align:right;" required>
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
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                         
                         
                             ?>
                         
                        <select name="vendor_id" class="form-control" required>
                           @foreach($suppliers as $supplier)
                           <option value="{{$supplier->id}}" @if($supplier->id == $ticket_vendor->vendor_id) selected @endif>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                           @endforeach
                        </select>
                     </td>
                     <td><input type="text" name="vendor_cost" id="vendor_cost" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="" placeholder="التكلفة" disabled class="form-control" required="" /></td>
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
<br>
                   <tag style="font-size: 14px;">رقم الهاتف اختياري</tag>

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
                  <tr id="remove{{$user->id}}">
                     <td>
                        <input type="text" value="{{$user->client_name}}" name="ticket_info[{{$x}}][name]" placeholder="الاسم" class="form-control" required>
                     </td>
                     <td>
                        <select class="form-control" name="ticket_info[{{$x}}][client_type]" required>
                           <option value="1" @if($user->client_type == 1) selected @endif>رضيع</option>
                           <option value="2" @if($user->client_type == 2) selected @endif>طفل</option>
                           <option value="3" @if($user->client_type == 3) selected @endif>بالغ</option>
                        </select>
                     </td>
                     <td>
                        <input type="number" name="ticket_info[{{$x}}][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" value="" required="" />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[{{$x}}][bought_price]" oninput="findTotal2()" oninput="" placeholder="سعر البيع" class="form-control amount2" value="" required="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_booking_id}}" name="ticket_info[{{$x}}][book_id]" placeholder="رقم الحجز" class="form-control" required="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_ticket_id}}" name="ticket_info[{{$x}}][tikcet_id]" placeholder="رقم الحجز" class="form-control" required="" />
                     </td>
                    <td>
                        <input type="text" value="{{$user->client_phone}}" name="ticket_info[{{$x}}][client_phone]" placeholder="رقم الهاتف" class="form-control" required="" />
                     </td>
                    
                    
                     <td>
                         @if($x == 0)
                         <button type="button" name="add_create" id="add_create" class="btn btn-success">اضف</button>
                      @endif
                         @if($x !== 0)
                         <a href="#remove{{$user->id}}" onclick="RemoveClient({{$user->id}})">
                         <button type="button" name="" id="" class="btn btn-danger"><i class="ri-delete-bin-line"></i></button>
                         </a>
                         @endif
                      </td>
                  </tr>
                   <?php $x++; ?>
                   @endforeach
               </table>
               <br>
                   <div class="row">
      <div class="col-6 mb-3">
          <p>
          اجمالي التكلفة
          </p>
          <input type="number" id="vendor_cost2" class="form-control" style="text-align:right;" disabled>
      </div>
      <div class="col-6">
          <p>
          اجمالي البيع
          </p>
            <input type="text" id="bought_price_total" class="form-control" onchange="alert(4);" style="text-align:right;" disabled>
      </div>
    </div>
                
<!--
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
-->
                
                
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
<script>
function RemoveClient(id){ 
    
    var name = "remove" + id;
//    alert(name);
    
    const element = document.getElementById(name);
element.remove();
    
//    var ticket_id = "{{$invoice_info->es_id}}";
//      swal({
//     title: "هل انت متأكد؟",
//     text: "سيتم حذف ذلك العميل وازالة كل البيانات المرتبطه به",
//     icon: "warning",
//     buttons: true,
//     dangerMode: true,
//   })
//   .then((willDelete) => {
//     if (willDelete) {
//   //       var url = "http://teacher.cuoratech.com/aladmin_srp/sections/" + id + "/remove";
//   var url = "{{url('')}}/invoices/remove_client/" + ticket_id + "/" + id;
////                     alert(url);
//         window.location.href = url;
//         
//     } else {
//       swal("تم الغاء عملية الحذف بنجاح");
//     }
//   });

    
}
</script>
<script type="text/javascript">
    

     
 var i = {{count($users) - 1}};
 var i2 = 0;
   
      
   
   $("#add_create").click(function(){
   
   
   
       ++i;
   
   
   
   
       $("#dynamicTableTwo").append('<tr><td><input type="text" name="ticket_info['+i+'][name]" placeholder="الاسم" class="form-control" required="" /></td><td><select class="form-control" name="ticket_info['+i+'][client_type]" required=""><option value="1">رضيع</option><option value="2">طفل</optio><option value="3">بالغ</option></select></td><td><input type="number" name="ticket_info['+i+'][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" required="" /></td>           <td><input type="text" name="ticket_info['+i+'][bought_price]" placeholder="سعر البيع" oninput="findTotal2()" class="form-control amount2" required="" /></td>           <td><input type="text" name="ticket_info['+i+'][book_id]" placeholder="رقم الحجز" class="form-control" required="" /></td><td><input type="text" name="ticket_info['+i+'][tikcet_id]" placeholder="رقم التكت" class="form-control" required="" /></td><td><input type="text" name="ticket_info['+i+'][client_phone]" placeholder="رقم الهاتف" class="form-control"></td><td><button type="button" class="btn btn-danger remove-tr">ازالة</button></td></tr>');
   
   });
   
   
   
   $(document).on('click', '.remove-tr', function(){  
   
        $(this).parents('tr').remove();
   
   });  
       
       function findTotal(){
    var arr = document.getElementsByClassName('amount');
    var tot=0;
    for(var i=0;i<arr.length;i++){
        if(parseFloat(arr[i].value))
            tot += parseFloat(arr[i].value);
    }
    document.getElementById('vendor_cost').value = tot;
    document.getElementById('vendor_cost2').value = tot;
}
    function findTotal2(){
    var arr = document.getElementsByClassName('amount2');
    var tot=0;
    for(var i=0;i<arr.length;i++){
        if(parseFloat(arr[i].value))
            tot += parseFloat(arr[i].value);
    }
        
        var val22 = document.getElementById("vendor_cost2").value;
      
        if(val22 == ""){
            
        }else{
            console.log(val22);
         document.getElementById('bought_price_total').value = tot;   
            
            if(val22 > tot){
//               aler_error
                 document.getElementById("aler_error").style.display = "block"; 
            }else{
                
            document.getElementById("aler_error").style.display = "none";     
                
            }
            
            
        }
        
    
} 
    
</script>



@endsection
