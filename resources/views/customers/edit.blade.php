@extends('layouts.app')
@section('content')
@section('title' , $customer->name)
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">تفاصيل العميل : ({{$customer->name}}) </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.customers_update')}}" method="POST" autocomplete="off">
               @csrf
               <input type="hidden" value="{{$customer->id}}" name="id">
                      @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        اسم العميل
                     </p>
                     <input type="text" name="name" value="{{$customer->name}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6">
                     <p>
                        الايميل
                     </p>
                     <input type="email" name="email" value="{{$customer->email}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
               </div>
               <p>
                  العنوان
               </p>
               <input type="text" name="address" value="{{$customer->address}}" placeholder="" class="form-control" style="text-align:right;" required="">
               <br>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        رقم الهاتف 1
                     </p>
                     <input type="text" name="phone_1" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{$customer->phone_1}}" placeholder="" class="form-control" style="text-align:right;" required="">
                  </div>
                  <div class="col-6">
                     <p>
                        رقم الهاتف 2 (غير الزامي)
                     </p>
                     <input type="text" name="phone_2" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{$customer->phone_2}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
               </div>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        رقم الباسبور
                     </p>
                     <input type="text" name="passport_id" value="{{$customer->passport_id}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6">
                     <p>
                        تاريخ انتهاء الباسبور
                     </p>
                     <input type="date" name="passport_expiration_date" value="{{$customer->passport_expiration_date}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
               </div>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        الرصيد الافتتاحي المدين
                     </p>
                     <input type="text" name="debit_opening_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{$customer->debit_opening_balance}}" placeholder="" class="form-control" style="text-align:right;" required="">
                  </div>
                  <div class="col-6">
                     <p>
                        رصيد افتتاحى الدائن 
                     </p>
                     <input type="text" name="opening_credit_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{$customer->opening_credit_balance}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
               </div>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        الحالة
                     </p>
                     <select name="status" class="form-control" style="text-align:right;" required="">
                     <option value="0" @if($customer->status == 0) selected @endif>موقوف</option>
                     <option value="1" @if($customer->status == 1) selected @endif>مفعل</option>
                     </select>
                  </div>
                  <div class="col-6">
                     <p>
                        النوع
                     </p>
                     <select name="type" class="form-control" style="text-align:right;" required="">
                     <option value="1" @if($customer->type == 1) selected @endif>فرد</option>
                     <option value="2" @if($customer->type == 2) selected @endif>شركة</option>
                     </select>
                  </div>
               </div>
                  <p>
                        نوع الحساب
                     </p>
                     <select name="acc_type" class="form-control" style="text-align:right;" required="">
                     <option value="1" @if($customer->acc_type == 1) selected @endif>عميل</option>
                     <option value="2" @if($customer->acc_type == 2) selected @endif>مورد</option>
                     </select>
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                  حفظ بيانات العميل {{$customer->name}}
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
