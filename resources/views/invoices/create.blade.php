@extends('layouts.app')
@section('content')
@section('title' , "اضافة فاتورة")


@if($errors->any())
<style>
    .aler_error{
        display: none !important;
    }
</style>
<script>
swal("", "{{$errors->first()}}", "info");

</script>
@endif 
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة فاتورة </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.invoices_save')}}" method="POST" autocomplete="off" enctype="multipart/form-data">
               @csrf
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        تاريخ الفاتورة
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_date" value="{{date('Y-m-d')}}" placeholder="تاريخ الفاتورة" class="form-control" style="text-align:right;" required>
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
                     <input type="date" name="return_date" value="" placeholder="تاريخ العودة" class="form-control" style="text-align:right;">
                <br>
               <div class="row">
      <div class="col-6 mb-3">
         <p>
            واجهة الاستلام 
      <span class="text-danger">*</span>
          </p>
            <input type="text" name="from_location" placeholder="واجهة الاستلام" class="form-control" style="text-align:right;" required="">
      </div>
      <div class="col-6">
         <p>
            واجهة الوصول 
         <span class="text-danger">*</span>
          </p>
            <input type="text" name="to_location" placeholder="واجهة الوصول" class="form-control" style="text-align:right;" required="">
      </div>
    </div>
                 <p>
                        خط الطيران
                 <span class="text-danger">*</span>     
                </p>
                     <select class="form-control" name="invoice_airline" id="invoice_airline" style="text-align:right;" required>
                         @foreach($airlines as $airline)
                         <option value="{{$airline->airline_name}}">{{$airline->airline_name}}</option>
                         @endforeach
                     </select>
                <br> 
                
                <p>
                        الجروب
                     </p>
                     <select class="form-control" name="invoice_group_id" id="invoice_group_id" style="text-align:right;">
                     </select>
                <br>
               <p>
                  اسم العميل (المستفيد)
                  <span class="text-danger">*</span>
               </p>
               <select class="form-control" name="invoice_beneficiaries" id="invoice_beneficiaries" style="text-align:right;" required>
                  @foreach($suppliers as $supplier)
                  <option value="{{$supplier->id}}">{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
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
                        <select name="vendor_id" id="vendor_id" class="form-control" required>
                           @foreach($suppliers as $supplier)
                           <option value="{{$supplier->id}}">{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                           @endforeach
                        </select>
                     </td>
                     <td><input type="text" name="vendor_cost" id="vendor_cost" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" placeholder="التكلفة" disabled class="form-control" required="" /></td>
                  </tr>
               </table>
               <p>
                  تصنيف الفاتورة
                  <span class="text-danger">*</span>
               </p>
               <select class="form-control" style="text-align:right;" name="invoice_section" required>
                  <option value="1">فواتير الطيران</option>
                  <option value="2">فواتير تأشيرات</option>
                  <option value="3">فواتير سياحه داخليه</option>
                  <option value="4">فواتير سياحه خارجيه</option>
                  <option value="5">فواتير سياحه دينيه</option>
                  <option value="6">فواتير تأمينات السفر</option>
                  <option value="7">فواتير تحاليل السفر</option>
                  <option value="8">فواتير نقل سياحى</option>
               </select>
               <br>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        ملاحظات
                     </p>
                     <input type="text" name="invoice_comments" placeholder="ملاحظات" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6"> 
                     <p>
                        العملة
                        <span class="text-danger">*</span>
                     </p>
                     <select class="form-control" style="text-align:right;" name="invoice_currency" required>
                        <option value="جنية مصري">جنية مصري</option>
                        <option value="درهم اماراتي">درهم اماراتي</option>
                     </select>
                  </div>
               </div>
               <p>
                  مسودة
               </p>
               <textarea class="form-control" style="text-align:right;" name="invoice_draft" placeholder="مسودة"></textarea>
               <br> 
               <p>
                  ملف / صورة التذكرة
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
                   <span class="text-danger">*</span>
               </p>
               <input type="file" class="form-control" name="myPoster" accept="image/jpeg,image/jpg,image/png,application/pdf" required="">
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
                  <tr>
                     <td>
                        <input type="text" name="ticket_info[0][name]" placeholder="الاسم" class="form-control" required="" />
                     </td>
                     <td>
                        <select class="form-control" name="ticket_info[0][client_type]">
                           <option value="1">رضيع</option>
                           <option value="2">طفل</option>
                           <option value="3">بالغ</option>
                        </select>
                     </td>
                     <td>
                        <input type="number" name="ticket_info[0][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" required="" />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[0][bought_price]" oninput="findTotal2()" oninput="" placeholder="سعر البيع" class="form-control amount2" required="" />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[0][book_id]" placeholder="رقم الحجز" class="form-control" required="" />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[0][tikcet_id]" placeholder="رقم الحجز" class="form-control" required="" />
                     </td>
                     
                       <td>
                        <input type="text" name="ticket_info[0][client_phone]" placeholder="رقم الهاتف" class="form-control"/>
                     </td>
                      
                     <td><button type="button" name="add_create" id="add_create" class="btn btn-success">اضف</button></td>
                  </tr>
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
                    <option value="{{$user->id}}">{{$user->name}}</option>
                    @endforeach
                </select>
                @else
                <input type="hidden" name="added_user" value="{{Auth::user()->id}}">
                @endif
                
               <br> 
                <div class="alert alert-danger aler_error" id="aler_error" style="display: none !important;">
                <center>
                    
                    تنويه : سوف يؤدي ذلك الي حدوث مديونية
                    </center>
                </div>
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
<!--<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>-->

 <script src="{{asset('assets/dselect.js')}}"></script>
 

<script type="text/javascript">
    
    
 var invoice_beneficiaries = document.querySelector('#invoice_beneficiaries');
  dselect(invoice_beneficiaries, {
            search: true
        });
    
     var vendor_id = document.querySelector('#vendor_id');
  dselect(vendor_id, {
            search: true
        });
    
    
  
    
    var i = 0;
   
      
   
   $("#add").click(function(){
   
   
   
       ++i;
   
   
   
   
       $("#dynamicTable").append('<tr><td><select name="vendor['+i+'][vendor_id]" class="form-control">@foreach($my_suppliers as $supplier)<option value="{{$supplier->id}}">{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>@endforeach</select></td><td><input type="text" name="vendor['+i+'][price]" placeholder="التكلفة" required="" class="form-control" /></td><td><button type="button" class="btn btn-danger remove-tr">ازالة</button></td></tr>');
   
   });
   
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
    
    
    function do_calc(total){
        alert(total);
    }
    
</script>


@endsection
