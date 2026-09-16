@extends('layouts.app')
@section('content')
@section('title' , "اضافة سند جديد")
<?php

$bond = $check_bond;
?>
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
        @if($check_bond->type == 2)  
        display: none;
          @endif
    }
  
          
    .type_2{
        @if($check_bond->type == 1)  
        display: none;
        @endif
    }
    
    .bank_part{
        @if($bond->money_way == 2)
        display: block;
        @else
            display:none;
        @endif
    }
    .collector_part{
            @if($bond->money_way == 3)
        display: block;
        @else
            display:none;
        @endif
    }
    .bank_part2{
    
         @if($bond->money_way == 2)
        display: block;
        @else
            display:none;
        @endif
    }
    .collector_part2{
        
           @if($bond->money_way == 3)
        display: block;
        @else
            display:none;
        @endif
        
    }
</style>
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل سند القبض : {{$check_bond->es_id}} </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.bonds_save_update')}}" method="POST" autocomplete="off" enctype='multipart/form-data'>
               @csrf
         
                <input type="hidden" name="bond_id" id="bond_id" value="{{$bond->id}}">
                <input type="hidden" name="type_slctd" id="type_slctd" value="{{$bond->type}}">
              
       
       <center>
                <h5> <?php
                         if($bond->type == 1){
                             $type = "سند دفع";
                         }else{
                             $type = "سند قبض";
                         }
    
                         ?>
                    
                    نوع السند الحالي هو : <b>{{$type}}</b> , في حالة العدم الرغبة في التغيير برجاء عدم الضغط علي اي من النوعين الاتيين
                    </h5>
                    
                        <div class="row">
                              <div class="col-sm" onclick="ContentTypeSlct(1)">
                                 <br>
                                 <div class="card border-0 border_img @if($bond->type == 1) border_img_slct @endif" id="1" onclick="">
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
                                 <div class="card border-0 border_img @if($bond->type == 2) border_img_slct @endif" id="2" onclick="">
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
                    تعديل سند دفع
                    </h5>
                    
                    <div class="row">
      <div class="col-6 mb-3">
         <p>
            من حساب (خزينة) 
         </p>
            <select class="form-select" name="storage_id" id="select_box">
                @foreach($storages as $storage)
          <option value="{{$storage->id}}" @if($bond->from_account == $storage->id) selected="" @endif>{{$storage->name}}</option>
                
                @endforeach
          </select> 
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
<select class="form-select" name="supp_id" id="">
                @foreach($suppliers as $supplier)
          <option value="{{$supplier->id}}" @if($bond->to_account == $supplier->id) selected="" @endif>{{$supplier->name}}</option>
                
                @endforeach
          </select>
                        </div>
    </div>
                 
                    
                    <div class="row">
      <div class="col-6 mb-3">
           <p>
                    المبلغ
                     </p>
                     <input type="tel" name="amount" value="{{$bond->amount}}" placeholder="المبلغ (ارقام فقط)" class="form-control" style="text-align:right;" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')">

                        </div>
      <div class="col-6">
         <p>
            طريقة الدفع
         </p>
            
          <select class="form-select" name="money_way" id="chose_moneyway_val" onchange="chose_moneyway()">
          <option value="1" @if($bond->money_way == 1) selected="" @endif>نقدي</option>
          <option value="2" @if($bond->money_way == 2) selected="" @endif>تحويل بنكي / محفظة الالكترونية</option>
          <option value="3" @if($bond->money_way == 3) selected="" @endif>تحصيل مندوب</option>
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
                    <option value="{{$bank->id}}" @if($bond->bank_id == $bank->id) selected="" @endif>{{$bank->bank_name}}</option>
                    @endforeach
                    </select>
                    <div class="mt-2">
                        <p>عمولة البنك / المحفظة (اختياري)</p>
                        <input type="tel" name="commission" id="commission" value="{{ $bond->commission ?? 0 }}" placeholder="0" class="form-control" style="text-align:right;" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')">
                    </div>
                    </div>
                       <div id="collector_part" class="collector_part">
                    
                           
                                <p>
                    المحصل المنفذ للعملية
                    </p>
                <select class="form-select" name="collector_info" id="box_4">
                    @foreach($collectors as $collector)
                    <option value="{{$collector->id}}" @if($bond->collector_info == $collector->id) selected="" @endif>{{$collector->name}} / {{$collector->phone}}</option>
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
                           
                           <input type="date" name="crt_date" value="{{$bond->crt_date}}" class="form-control" >
                    <br>
                    
                    
                    <p>
                  ملف / صورة السند
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
               <br>
                        اترك الخانة فارغة في حالة عدم الرغبة في التغيير
                        
                 </p> 
               <input type="file" class="form-control" name="myPoster" accept="image/jpeg,image/jpg,image/png,application/pdf">
                      
                    
                </div>
             <div class="type_2" id="type_2">
  
                 
                    <h5>
                     تعديل سند قبض
                    </h5>
                   <div class="row">
      <div class="col-6 mb-3">
         <p>
            من حساب
         </p>
         <select class="form-select" name="supp_id2" id="select_box2_t2" >
                @foreach($suppliers as $supplier)
          <option value="{{$supplier->id}}" @if($bond->from_account == $supplier->id) selected="" @endif>{{$supplier->name}}</option>
                
                @endforeach
          </select>
          
          
          <br>
          <p>
          خزينة فرعية (اختياري)
          </p>
          <select class="form-select" name="sub_id2" id="select_box">
              <option value="0">--</option>
                @foreach($sub_storages as $storage)
          <option value="{{$storage->id}}" @if($bond->sub_id == $storage->id) selected="" @endif>{{$storage->name}}</option>
                
                @endforeach
          </select>
          
          
      </div>
      <div class="col-6">
         <p>
            الي حساب (خزينة)
         </p>

          
          
             <select class="form-select" name="storage_id2" id="select_boxt2" >
                @foreach($storages as $storage)
          <option value="{{$storage->id}}" @if($bond->to_account == $storage->id) selected="" @endif>{{$storage->name}}</option>
                
                @endforeach
          </select>
                        </div>
    </div>
                 
                    
                    <div class="row">
      <div class="col-6 mb-3">
           <p>
                    المبلغ
                     </p>
                     <input type="tel" name="amount2" value="{{$bond->amount}}" placeholder="المبلغ (ارقام فقط)" class="form-control" style="text-align:right;" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" >

                        </div>
      <div class="col-6">
         <p>
            طريقة الدفع
         </p>
            
          <select class="form-select" name="money_way2" id="chose_moneyway_val2" onchange="chose_moneyway2()" >
          <option value="1" @if($bond->money_way == 1) selected="" @endif>نقدي</option>
          <option value="2" @if($bond->money_way == 2) selected="" @endif>تحويل بنكي / محفظة الالكترونية</option>
          <option value="3" @if($bond->money_way == 3) selected="" @endif>تحصيل مندوب</option>
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
                    <option value="{{$bank->id}}" @if($bond->bank_id == $bank->id) selected="" @endif>{{$bank->bank_name}}</option>
                    @endforeach
                    </select>
                    </div>
                       <div id="collector_part2" class="collector_part2">
                    
                           
                                <p>
                    المحصل المنفذ للعملية
                    </p>
                <select class="form-select" name="collector_info2" id="collector_info2" >
                    @foreach($collectors as $collector)
                    <option value="{{$collector->id}}" @if($bond->collector_info == $collector->id) selected="" @endif>{{$collector->name}} / {{$collector->phone}}</option>
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
                           
                           <input type="date" name="crt_date2" value="{{$bond->crt_date}}" class="form-control" >
                    
               
                  
                 <br>
                    <p>
                  ملف / صورة السند
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
               <br>
                        اترك الخانة فارغة في حالة عدم الرغبة في التغيير
                        
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
<script>
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
