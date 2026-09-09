@extends('layouts.app')
@section('content')
@section('title' , "اضافة مصروف جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة مصروف جديد </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.expenses_save')}}" method="POST" autocomplete="off">
               @csrf
         
                
               <div class="row">
                  <div class="">
                     <p>
                        اسم المصروف
                     </p>
                     <input type="text" name="name" value="" placeholder="اسم المصروف" class="form-control" style="text-align:right;" required="">
                  </div>
<!--
                  <div class="col-6">
                     <p>
                        الايميل
                     </p>
                     <input type="email" name="email" value="" placeholder="الايميل" class="form-control" style="text-align:right;">
                  </div>
-->
               </div>
<!--
               <p>
                  العنوان
               </p>
               <input type="text" name="address" value="" placeholder="العنوان" class="form-control" style="text-align:right;">
-->
               <br>
<!--
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        رقم الهاتف 1
                     </p>
                     <input type="text" name="phone_1" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="" placeholder="رقم الهاتف 1" class="form-control" style="text-align:right;" required="">
                  </div>
                  <div class="col-6">
                     <p>
                        رقم الهاتف 2 
                     </p>
                     <input type="text" name="phone_2" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="" placeholder="رقم الهاتف 2 (غير الزامي)" class="form-control" style="text-align:right;">
                  </div>
               </div>
-->
<!--
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        رقم الباسبور
                     </p>
                     <input type="text" name="passport_id" value="" placeholder="رقم الباسبور" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6">
                     <p>
                        تاريخ انتهاء الباسبور
                     </p>
                     <input type="date" name="passport_expiration_date" value="" placeholder="تاريخ انتهاء الباسبور" class="form-control" style="text-align:right;">
                  </div>
               </div>
-->
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        الرصيد الافتتاحي المدين
                     </p>
                     <input type="text" name="debit_opening_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="0" placeholder="الرصيد الافتتاحي المدين" class="form-control" style="text-align:right;" required="">
                  </div>
                  <div class="col-6">
                     <p>
                        رصيد افتتاحى الدائن 
                     </p>
                     <input type="text" name="opening_credit_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="0" placeholder="رصيد افتتاحى الدائن " class="form-control" style="text-align:right;">
                  </div>
               </div>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        الحالة
                     </p>
                     <select name="status" class="form-control" style="text-align:right;" required="">
                         <option value="1">مفعل</option>
                     <option value="0">موقوف</option>
                     
                     </select>
                  </div>
                  <div class="col-6">
                     <p>
                        النوع
                     </p>
                     <select name="type" class="form-control" style="text-align:right;" required="">
                     <option value="1">فرد</option>
                     <option value="2">شركة</option>
                     </select>
                  </div>
               </div>
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة مصروف جديد  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
