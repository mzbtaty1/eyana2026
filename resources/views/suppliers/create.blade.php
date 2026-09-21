@extends('layouts.app')
@section('content')
@section('title' , "اضافة مورد جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة مورد جديد </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.suppliers_save')}}" method="POST" autocomplete="off">
               @csrf
         
                
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        اسم المورد
                     </p>
                     <input type="text" name="name" value="{{old('name')}}" placeholder="اسم المورد" class="form-control @error('name') is-invalid @enderror" style="text-align:right;" required="">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-6">
                     <p>
                        الايميل
                     </p>
                     <input type="email" name="email" value="{{old('email')}}" placeholder="الايميل" class="form-control @error('email') is-invalid @enderror" style="text-align:right;">
                     @error('email') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>
               <p>
                  العنوان
               </p>
               <input type="text" name="address" value="{{old('address')}}" placeholder="العنوان" class="form-control @error('address') is-invalid @enderror" style="text-align:right;">
               @error('address') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
               <br>
                <p>
                  الحد الائتماني المسموح
               </p>
               <input type="text" name="limit_balance" value="{{old('limit_balance', '0')}}" placeholder="الحد الائتماني المسموح" class="form-control @error('limit_balance') is-invalid @enderror" style="text-align:right;">
               @error('limit_balance') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
               <br>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        رقم الهاتف 1
                     </p>
                     <input type="text" name="phone_1" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_1')}}" placeholder="رقم الهاتف 1" class="form-control @error('phone_1') is-invalid @enderror" style="text-align:right;" required="">
                     @error('phone_1') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-6">
                     <p>
                        رقم الهاتف 2
                     </p>
                     <input type="text" name="phone_2" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_2')}}" placeholder="رقم الهاتف 2 (غير الزامي)" class="form-control @error('phone_2') is-invalid @enderror" style="text-align:right;">
                     @error('phone_2') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>
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
                     <input type="text" name="debit_opening_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('debit_opening_balance', '0')}}" placeholder="الرصيد الافتتاحي المدين" class="form-control @error('debit_opening_balance') is-invalid @enderror" style="text-align:right;" required="">
                     @error('debit_opening_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-6">
                     <p>
                        رصيد افتتاحى الدائن
                     </p>
                     <input type="text" name="opening_credit_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('opening_credit_balance', '0')}}" placeholder="رصيد افتتاحى الدائن " class="form-control @error('opening_credit_balance') is-invalid @enderror" style="text-align:right;" required>
                     @error('opening_credit_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        الحالة
                     </p>
                     <select name="status" class="form-control @error('status') is-invalid @enderror" style="text-align:right;" required="">
                         <option value="1" @if(old('status')==='1') selected @endif>مفعل</option>
                     <option value="0" @if(old('status')==='0') selected @endif>موقوف</option>

                     </select>
                  </div>
                  <div class="col-6">
                     <p>
                        النوع
                     </p>
                     <select name="type" class="form-control @error('type') is-invalid @enderror" style="text-align:right;" required="">
                     <option value="1" @if(old('type')==='1') selected @endif>فرد</option>
                     <option value="2" @if(old('type')==='2') selected @endif>شركة</option>
                     </select>
                  </div>
                   <div class="col-6">
                     <p>
                                                اظهار كملخص في الصفحة الرئيسية

                     </p>
                     <select name="in_index" class="form-control @error('in_index') is-invalid @enderror" style="text-align:right;" required="">
                     <option value="0" @if(old('in_index')==='0') selected @endif>لا</option>
                     <option value="1" @if(old('in_index')==='1') selected @endif>نعم</option>
                     </select>
                  </div>

                     <div class="col-6">
                     <p>
                                                عرض في كشف الحساب الشركات المخصص

                     </p>
                     <select name="in_stat" class="form-control @error('in_stat') is-invalid @enderror" style="text-align:right;" required="">
                     <option value="0" @if(old('in_stat')==='0') selected @endif>لا</option>
                     <option value="1" @if(old('in_stat')==='1') selected @endif>نعم</option>
                     </select>
                  </div>
                   
               </div>
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة مورد جديد  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
