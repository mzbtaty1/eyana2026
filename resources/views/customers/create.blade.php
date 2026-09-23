@extends('layouts.app')
@section('content')
@section('title' , "اضافة عميل جديد")
<x-page-header title="اضافة عميل جديد" />
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <form action="{{route('site.customers_save')}}" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')

               <h6 class="text-uppercase text-muted fs-13 mb-3">البيانات الأساسية</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">اسم العميل</label>
                     <input type="text" name="name" value="{{old('name')}}" placeholder="اسم العميل" class="form-control @error('name') is-invalid @enderror" required="">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">الايميل</label>
                     <input type="email" name="email" value="{{old('email')}}" placeholder="الايميل" class="form-control @error('email') is-invalid @enderror">
                     @error('email') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">العنوان</label>
                     <input type="text" name="address" value="{{old('address')}}" placeholder="العنوان" class="form-control @error('address') is-invalid @enderror">
                     @error('address') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">بيانات التواصل</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الهاتف 1</label>
                     <input type="text" name="phone_1" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_1')}}" placeholder="رقم الهاتف 1" class="form-control @error('phone_1') is-invalid @enderror" required="">
                     @error('phone_1') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الهاتف 2</label>
                     <input type="text" name="phone_2" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_2')}}" placeholder="رقم الهاتف 2 (غير الزامي)" class="form-control @error('phone_2') is-invalid @enderror">
                     @error('phone_2') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الباسبور</label>
                     <input type="text" name="passport_id" value="{{old('passport_id')}}" placeholder="رقم الباسبور" class="form-control @error('passport_id') is-invalid @enderror">
                     @error('passport_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">تاريخ انتهاء الباسبور</label>
                     <input type="date" name="passport_expiration_date" value="{{old('passport_expiration_date')}}" placeholder="تاريخ انتهاء الباسبور" class="form-control @error('passport_expiration_date') is-invalid @enderror">
                     @error('passport_expiration_date') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">البيانات المالية</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">الرصيد الافتتاحي المدين</label>
                     <input type="text" name="debit_opening_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('debit_opening_balance', '0')}}" placeholder="الرصيد الافتتاحي المدين" class="form-control @error('debit_opening_balance') is-invalid @enderror" required="">
                     @error('debit_opening_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رصيد افتتاحى الدائن</label>
                     <input type="text" name="opening_credit_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('opening_credit_balance', '0')}}" placeholder="رصيد افتتاحى الدائن " class="form-control @error('opening_credit_balance') is-invalid @enderror">
                     @error('opening_credit_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">الإعدادات</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">الحالة</label>
                     <select name="status" class="form-control @error('status') is-invalid @enderror" required="">
                         <option value="1" @if(old('status')==='1') selected @endif>مفعل</option>
                     <option value="0" @if(old('status')==='0') selected @endif>موقوف</option>

                     </select>
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">النوع</label>
                     <select name="type" class="form-control @error('type') is-invalid @enderror" required="">
                     <option value="1" @if(old('type')==='1') selected @endif>فرد</option>
                     <option value="2" @if(old('type')==='2') selected @endif>شركة</option>
                     </select>
                  </div>
               </div>

               <div class="d-flex justify-content-end gap-2 mt-3">
                  <a href="{{route('site.customers')}}" class="btn btn-light">إلغاء</a>
                  <button class="btn btn-primary">
                  <i class="ri-save-line align-middle me-1"></i>
                   اضافة عميل جديد
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
