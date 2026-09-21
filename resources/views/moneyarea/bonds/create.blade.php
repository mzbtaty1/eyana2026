@extends('layouts.app')
@section('content')
@section('title' , "اضافة سند جديد")
<style>
 .border_img {
   border-color: #666 !important;
   border-style: solid !important;
   border-width: 2px !important;
   box-shadow: 0 2px 7px 1px rgba(0, 0, 0, .06);
   }   
   .border_img_slct {
   border-color: #237a6e !important;
   border-style: solid !important;
   border-width: 4.5px !important;
   box-shadow: 0 2px 7px 1px rgba(0, 0, 0, .06);
   }
    .type_1{
        display: none;
    }
    .type_2{
        display: none;
    }
    .bank_part{
        display: none;
    }
    .collector_part{
        display: none;
    }
    .bank_part2{
        display: none;
    }
    .collector_part2{
        display: none;
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>


<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- JS for searching -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// .js-example-basic-single declare this class into your select box
$(document).ready(function() {
    $('.js-example-basic-single').select2();
    $('.js-example-basic-single2').select2();
});
</script>

<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة سند جديد </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.bonds_save')}}" method="POST" autocomplete="off" enctype='multipart/form-data'>
               @csrf
               @if($errors->any())
               <div class="alert alert-danger"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
                <input type="hidden" name="type_slctd" id="type_slctd" value="">
              
       
       <center>
                <h5>
                    اختر نوع السند الذي تريد اضافته
                    </h5>
                    
                        <div class="row">
                              <div class="col-sm" onclick="ContentTypeSlct(1)">
                                 <br>
                                 <div class="card border-0 border_img" id="1" onclick="">
                                    <center>
                                       <br>
                                       <img src="{{asset('assets/images/bearer.png')}}" class="dark_img" style="width:43px;">
                                    </center>
                                    <p style="font-size:19px;margin-top:10px;">
                                       سند دفع
                                    </p>
                                 </div>
                              </div>
                              <div class="col-sm" onclick="ContentTypeSlct(2)">
                                 <br>
                                 <div class="card border-0 border_img" id="2" onclick="">
                                    <center>
                                       <br>
                                       <img src="{{asset('assets/images/bearer.png')}}" class="dark_img" style="width:43px;">
                                    </center>
                                    <p style="font-size:19px;margin-top:10px;">
                                       سند قبض
                                    </p>
                                 </div>
                              </div>
                                
                   
                               
                           </div>
           
           <hr>
           
           
               
                <div class="type_1" id="type_1">
                
<!--                
سند دفع
-->
                    <h5>
                    اضافة سند دفع
                    </h5>
                    
                    <div class="row">
      <div class="col-6 mb-3">
         <p>
            من حساب (خزينة) 
         </p>
            <select class="form-select" name="storage_id" id="select_box">
                @foreach($storages as $storage)
          <option value="{{$storage->id}}">{{$storage->name}}</option>
                
                @endforeach
          </select> 
          <?php
          
          $main_info = App\Models\Storage::select('*')->where('name','الخزنة الرئيسية')->get();
          $main_info = $main_info[0];
          $amount_main = $main_info->balance;
           $bonds = App\Models\Bond::select('*')->orderBy('id','DESC')->get();
           $total_bonds = 0;
                   foreach($bonds as $bond){
                       if($bond->type == 1){
                            $total_bonds -= $bond->amount;
                       }else{
                            $total_bonds += $bond->amount;
                       }
                      
                   }
                   
          
          ?>
          <p style="text-align: right;font-size: 15px;margin-top: 10px;">الرصيد الحالي للخزينة : <tag>{{number_format($total_bonds,2)}} ج.م</tag></p>
          <br>
          <p>
          خزينة فرعية (اختياري)
          </p>
          <select class="form-select" name="sub_id" id="select_box">
              <option value="0">--</option>
                @foreach($sub_storages as $storage)
          <option value="{{$storage->id}}">{{$storage->name}}</option>
                
                @endforeach
          </select>
      </div>
      <div class="col-6">
         <p>
            الي حساب
         </p> 
<select class="js-example-basic-single" name="supp_id" id="supp_id_slct" onchange="slctSuppOne()">
     <option value="">-- اختار حساب استقبال التحويل --</option>
                @foreach($suppliers as $supplier)
          <option value="{{$supplier->id}}">{{$supplier->name}}</option>
                
                @endforeach
          </select>
                    <p style="text-align: right;font-size: 15px;margin-top: 10px;display:none;" class="supp_info_1" id="supp_info_1"></p>

                        </div>
    </div>
                 
                    
                    <div class="row">
      <div class="col-6 mb-3">
           <p>
                    المبلغ
                     </p>
                     <input type="tel" name="amount" value="" placeholder="المبلغ (ارقام فقط)" class="form-control" style="text-align:right;" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')">

                        </div>
      <div class="col-6">
         <p>
            طريقة الدفع
         </p>
            
          <select class="form-select" name="money_way" id="chose_moneyway_val" onchange="chose_moneyway()">
          <option value="1">نقدي</option>
          <option value="2">تحويل بنكي / محفظة الالكترونية</option>
          <option value="3">تحصيل مندوب</option>
          </select>
          
      </div>
    </div>
        <br>
                    
             <div id="bank_part" class="bank_part">
                           <p>
                    الحساب البنكي المنفذ للعملية : 
                    </p>
                <select class="form-select" name="bank_id" id="select_box3">
                    @foreach($banks as $bank)
                    <option value="{{$bank->id}}">{{$bank->bank_name}} - (رصيد : {{number_format($bank->bank_balance)}} ج.م)</option>
                    @endforeach
                    </select>
                    <div class="mt-2">
                        <p>عمولة البنك / المحفظة (اختياري)</p>
                        <input type="tel" name="commission" id="commission" value="0" placeholder="0" class="form-control" style="text-align:right;" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')">
                    </div>
                    </div>
                       <div id="collector_part" class="collector_part">
                    
                           
                                <p>
                    المحصل المنفذ للعملية
                    </p>
                <select class="form-select" name="collector_info" id="box_4">
                    @foreach($collectors as $collector)
                    <option value="{{$collector->id}}">{{$collector->name}} / {{$collector->phone}}</option>
                    @endforeach
                    </select>
                           
                           
                       <br>
                           
                           
                       
                    </div>
                  
                      <p>
                           ملاحظات علي العملية
                           </p>
                           
                           <textarea class="form-control" name="transaction_info" rows="5" placeholder="ملاحظاتك علي العملية ........." style="text-align:right;"></textarea>
                           <br> 
                         <p>
                           تاريخ العملية
                           </p>
                           
                           <input type="date" name="crt_date" value="{{date('Y-m-d')}}" class="form-control">
                    <br>
                    
                    
                    <p>
                  ملف / صورة السند
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
               </p>
               <input type="file" class="form-control" name="myPoster" accept="image/jpeg,image/jpg,image/png,application/pdf">
                      
                    
                </div>
             <div class="type_2" id="type_2">
  
                 
                    <h5>
                    اضافة سند قبض
                    </h5>
                   <div class="row">
      <div class="col-6 mb-3">
         <p>
            من حساب
         </p>
         <select class="js-example-basic-single2" name="supp_id2" id="supp_id2_slct" onchange="slctSuppTwo()">
                  <option value="">-- اختار حساب ارسال التحويل --</option>

                @foreach($suppliers as $supplier)
          <option value="{{$supplier->id}}">{{$supplier->name}}</option>
                
                @endforeach
          </select>
         
<p style="text-align: right;font-size: 15px;margin-top: 10px;display:none;" class="supp_info_2" id="supp_info_2"></p>

          <br>
          <p>
          خزينة فرعية (اختياري)
          </p>
          <select class="form-select" name="sub_id2" id="select_box">
              <option value="0">--</option>
                @foreach($sub_storages as $storage)
          <option value="{{$storage->id}}">{{$storage->name}}</option>
                
                @endforeach
          </select>
          
          
      </div>
      <div class="col-6">
         <p>
            الي حساب (خزينة)
         </p>

          
          
             <select class="form-select" name="storage_id2" id="select_boxt2" >
                @foreach($storages as $storage)
          <option value="{{$storage->id}}">{{$storage->name}}</option>
                
                @endforeach
          </select>
           <?php
          
          $main_info = App\Models\Storage::select('*')->where('name','الخزنة الرئيسية')->get();
          $main_info = $main_info[0];
          $amount_main = $main_info->balance;
           $bonds = App\Models\Bond::select('*')->orderBy('id','DESC')->get();
           $total_bonds = 0;
                   foreach($bonds as $bond){
                       if($bond->type == 1){
                            $total_bonds -= $bond->amount;
                       }else{
                            $total_bonds += $bond->amount;
                       }
                      
                   }
                   
          
          ?>
          <p style="text-align: right;font-size: 15px;margin-top: 10px;">الرصيد الحالي للخزينة : <tag>{{number_format($total_bonds,2)}} ج.م</tag></p>
       
          
                        </div>
    </div>
                 
                    
                    <div class="row">
      <div class="col-6 mb-3">
           <p>
                    المبلغ
                     </p>
                     <input type="tel" name="amount2" value="" placeholder="المبلغ (ارقام فقط)" class="form-control" style="text-align:right;" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" >

                        </div>
      <div class="col-6">
         <p>
            طريقة الدفع
         </p>
            
          <select class="form-select" name="money_way2" id="chose_moneyway_val2" onchange="chose_moneyway2()" >
          <option value="1">نقدي</option>
          <option value="2">تحويل بنكي / محفظة الالكترونية</option>
          <option value="3">تحصيل مندوب</option>
          </select>
          
      </div>
    </div>
        <br>
                     
             <div id="bank_part2" class="bank_part2">
                           <p>
                    الحساب البنكي المنفذ للعملية : 
                    </p>
                <select class="form-select" name="bank_id2" id="bank_id2" >
                    @foreach($banks as $bank)
                    <option value="{{$bank->id}}">{{$bank->bank_name}} - (رصيد : {{number_format($bank->bank_balance)}} ج.م)</option>
                    @endforeach
                    </select>
                    </div>
                       <div id="collector_part2" class="collector_part2">
                    
                           
                                <p>
                    المحصل المنفذ للعملية
                    </p>
                <select class="form-select" name="collector_info2" id="collector_info2" >
                    @foreach($collectors as $collector)
                    <option value="{{$collector->id}}">{{$collector->name}} / {{$collector->phone}}</option>
                    @endforeach
                    </select>
                           
                           
                       <br>
                           
                           
                       
                    </div>
                  
                      <p>
                           ملاحظات علي العملية
                           </p>
                           
                           <textarea class="form-control" name="transaction_info2" rows="5" placeholder="ملاحظاتك علي العملية ........." style="text-align:right;"></textarea>
                           <br> 
                         <p>
                           تاريخ العملية
                           </p>
                           
                           <input type="date" name="crt_date2" value="{{date('Y-m-d')}}" class="form-control" >
                    
               
                 
                 <br>
                    <p>
                  ملف / صورة السند
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
               </p>
               <input type="file" class="form-control" name="myPoster2" accept="image/jpeg,image/jpg,image/png,application/pdf">
                      
                 
                </div>
           
                </center>
            
                
                <br>
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة سند جديد   
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
 <!--<script src="https://emposys.khadamaat.org/public/assets/dselect.js"></script>-->
 

<script type="text/javascript">
    
    function slctSuppOne(){
        var sup_one_id = document.getElementById("supp_id_slct").value;
        console.log(sup_one_id);
         
          $(document).ready(function () {

      $.ajax({
         type: "get",
         url: "{{url('')}}/api/supp_info/" + sup_one_id,
         headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
         },
         data: "",
         //   beforeSend: function() { 
         //   $('.message_box').html(
         //   '<img src="Loader.gif" width="25" height="25"/>'
         //   );
         //   },
         success: function (data) {
            //var dataResult = JSON.parse(data);

            //console.log(data.status_code);

            var status_code = data.st_code;
            console.log(status_code);
             
             if(status_code == 200){
//                 alert(data.balance);
                  document.getElementById("supp_info_1").style.display = "block";
                  document.getElementById("supp_info_1").innerHTML = " الرصيد الحالي : " + data.balance + " ج.م ";
             }else{
                 alert("an error in eyana software");
             }
             
//            if (status_code == 901) {
//
//
//               document.getElementById("error_msg").style.display = "block";
//               document.getElementById("LoginBtn").style.display = "block";
//               document.getElementById("disabledBtn").style.display = "none";
//               document.getElementById("error_msg").innerHTML = "برجاء كتابة البريد الالكتروني وكلمة السر";
//            }
//            if (status_code == 404) {
//
//
//               document.getElementById("error_msg").style.display = "block";
//               document.getElementById("LoginBtn").style.display = "block";
//               document.getElementById("disabledBtn").style.display = "none";
//               document.getElementById("error_msg").innerHTML = "خطأ في البريد الالكتروني أو كلمة السر";
//
//
//            }
//            if (status_code == 403) {
//
//
//               document.getElementById("error_msg").style.display = "block";
//               document.getElementById("LoginBtn").style.display = "block";
//               document.getElementById("disabledBtn").style.display = "none";
//               document.getElementById("error_msg").innerHTML = "تم حظر حسابك ، يرجي مراسلة المسئول لفك الحظر";
//
//
//            }
//
//            if (status_code == 200) {
//
//
//               window.location.href = "{{route('site.index')}}";
//
//
//            }


         }
      });
      //   });

   });
        
        
    }
    function slctSuppTwo(){
        var sup_one_id = document.getElementById("supp_id2_slct").value;
        console.log(sup_one_id); 
        
          $(document).ready(function () {

      $.ajax({
         type: "get",
         url: "{{url('')}}/api/supp_info/" + sup_one_id,
         headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
         },
         data: "",
         //   beforeSend: function() { 
         //   $('.message_box').html(
         //   '<img src="Loader.gif" width="25" height="25"/>'
         //   );
         //   },
         success: function (data) {
            //var dataResult = JSON.parse(data);

            //console.log(data.status_code);

            var status_code = data.st_code;
            console.log(status_code);
             
             if(status_code == 200){
//                 alert(data.balance);
                  document.getElementById("supp_info_2").style.display = "block";
                  document.getElementById("supp_info_2").innerHTML = " الرصيد الحالي : " + data.balance + " ج.م ";
             }else{
                 alert("an error in eyana software");
             }
             
//            if (status_code == 901) {
//
//
//               document.getElementById("error_msg").style.display = "block";
//               document.getElementById("LoginBtn").style.display = "block";
//               document.getElementById("disabledBtn").style.display = "none";
//               document.getElementById("error_msg").innerHTML = "برجاء كتابة البريد الالكتروني وكلمة السر";
//            }
//            if (status_code == 404) {
//
//
//               document.getElementById("error_msg").style.display = "block";
//               document.getElementById("LoginBtn").style.display = "block";
//               document.getElementById("disabledBtn").style.display = "none";
//               document.getElementById("error_msg").innerHTML = "خطأ في البريد الالكتروني أو كلمة السر";
//
//
//            }
//            if (status_code == 403) {
//
//
//               document.getElementById("error_msg").style.display = "block";
//               document.getElementById("LoginBtn").style.display = "block";
//               document.getElementById("disabledBtn").style.display = "none";
//               document.getElementById("error_msg").innerHTML = "تم حظر حسابك ، يرجي مراسلة المسئول لفك الحظر";
//
//
//            }
//
//            if (status_code == 200) {
//
//
//               window.location.href = "{{route('site.index')}}";
//
//
//            }


         }
      });
      //   });

   });
         
        
    }
// var supp_id_slct = document.querySelector('#supp_id_slct');
//  dselect(supp_id_slct, {
//        search: true 
//        });
//    
// var supp_id2_slct = document.querySelector('#supp_id2_slct');
//dselect(supp_id2_slct, {
//            search: true
//        });
//    
    function chose_moneyway(){
         var chose_moneyway_val = document.getElementById("chose_moneyway_val").value;
        
        
        if(chose_moneyway_val == 2){
            document.getElementById("bank_part").style.display = "block"; 
            document.getElementById("collector_part").style.display = "none"; 
            
        }else if(chose_moneyway_val == 3){ 
            
            document.getElementById("bank_part").style.display = "none"; 
            document.getElementById("collector_part").style.display = "block"; 

            
        }else{
            document.getElementById("bank_part").style.display = "none"; 
            document.getElementById("collector_part").style.display = "none"; 

        }
        
    }
    function chose_moneyway2(){
         var chose_moneyway_val2 = document.getElementById("chose_moneyway_val2").value;
        
        
        if(chose_moneyway_val2 == 2){
            document.getElementById("bank_part2").style.display = "block"; 
            document.getElementById("collector_part2").style.display = "none"; 
            
        }else if(chose_moneyway_val2 == 3){
            
            document.getElementById("bank_part2").style.display = "none"; 
            document.getElementById("collector_part2").style.display = "block"; 

            
        }else{
            document.getElementById("bank_part2").style.display = "none"; 
            document.getElementById("collector_part2").style.display = "none"; 

        }
        
    }
       function ContentTypeSlct(way){
      var myElement = document.getElementById("type_slctd").value;
       if(myElement == ""){
            document.getElementById("type_slctd").value = way;
           var element = document.getElementById(way);
           element.classList.remove("border_img");
           element.classList.add("border_img_slct");
       }else{
           
         var myElement = document.getElementById("type_slctd").value;   
            var element = document.getElementById(myElement);
           element.classList.remove("border_img_slct");
           element.classList.add("border_img");
           
            document.getElementById("type_slctd").value = way;
           
               var element = document.getElementById(way);
           element.classList.remove("border_img");
           element.classList.add("border_img_slct");
           
       }
           
           
           
           if(way == 1){
       document.getElementById("type_1").style.display = "block"; 
            document.getElementById("type_2").style.display = "none"; 

       }else if(way == 2){
       document.getElementById("type_1").style.display = "none"; 
            document.getElementById("type_2").style.display = "block"; 
       }
           
           
       }
</script>
@endsection
