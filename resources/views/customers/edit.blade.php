@extends('layouts.app')
@section('content')
@section('title' , $customer->name)
<x-page-header title="تفاصيل العميل : ({{$customer->name}})" />
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <form action="{{route('site.customers_update')}}" method="POST" autocomplete="off">
               @csrf
               <input type="hidden" value="{{$customer->id}}" name="id">
@include('components.flash-messages')

               <h6 class="text-uppercase text-muted fs-13 mb-3">البيانات الأساسية</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">اسم العميل</label>
                     <input type="text" name="name" value="{{old('name', $customer->name)}}" placeholder="" class="form-control @error('name') is-invalid @enderror">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">الايميل</label>
                     <input type="email" name="email" value="{{old('email', $customer->email)}}" placeholder="" class="form-control @error('email') is-invalid @enderror">
                     @error('email') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">العنوان</label>
                     <input type="text" name="address" value="{{old('address', $customer->address)}}" placeholder="" class="form-control @error('address') is-invalid @enderror" required="">
                     @error('address') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">بيانات التواصل</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الهاتف 1</label>
                     <input type="text" name="phone_1" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_1', $customer->phone_1)}}" placeholder="" class="form-control @error('phone_1') is-invalid @enderror" required="">
                     @error('phone_1') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الهاتف 2 (غير الزامي)</label>
                     <input type="text" name="phone_2" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_2', $customer->phone_2)}}" placeholder="" class="form-control @error('phone_2') is-invalid @enderror">
                     @error('phone_2') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الباسبور</label>
                     <input type="text" name="passport_id" value="{{old('passport_id', $customer->passport_id)}}" placeholder="" class="form-control @error('passport_id') is-invalid @enderror">
                     @error('passport_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">تاريخ انتهاء الباسبور</label>
                     <input type="date" name="passport_expiration_date" value="{{old('passport_expiration_date', $customer->passport_expiration_date)}}" placeholder="" class="form-control @error('passport_expiration_date') is-invalid @enderror">
                     @error('passport_expiration_date') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">البيانات المالية</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">الرصيد الافتتاحي المدين</label>
                     <input type="text" name="debit_opening_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('debit_opening_balance', $customer->debit_opening_balance)}}" placeholder="" class="form-control @error('debit_opening_balance') is-invalid @enderror" required="">
                     @error('debit_opening_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رصيد افتتاحى الدائن</label>
                     <input type="text" name="opening_credit_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('opening_credit_balance', $customer->opening_credit_balance)}}" placeholder="" class="form-control @error('opening_credit_balance') is-invalid @enderror">
                     @error('opening_credit_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">الإعدادات</h6>
               <div class="row">
                  <div class="col-md-4 mb-3">
                     <label class="form-label">الحالة</label>
                     <select name="status" class="form-control @error('status') is-invalid @enderror" required="">
                     <option value="0" @if(old('status', $customer->status) == 0) selected @endif>موقوف</option>
                     <option value="1" @if(old('status', $customer->status) == 1) selected @endif>مفعل</option>
                     </select>
                  </div>
                  <div class="col-md-4 mb-3">
                     <label class="form-label">النوع</label>
                     <select name="type" class="form-control @error('type') is-invalid @enderror" required="">
                     <option value="1" @if(old('type', $customer->type) == 1) selected @endif>فرد</option>
                     <option value="2" @if(old('type', $customer->type) == 2) selected @endif>شركة</option>
                     </select>
                  </div>
                  <div class="col-md-4 mb-3">
                     <label class="form-label">نوع الحساب</label>
                     <select name="acc_type" class="form-control @error('acc_type') is-invalid @enderror" required="">
                     <option value="1" @if(old('acc_type', $customer->acc_type) == 1) selected @endif>عميل</option>
                     <option value="2" @if(old('acc_type', $customer->acc_type) == 2) selected @endif>مورد</option>
                     </select>
                  </div>
               </div>

               <div class="d-flex justify-content-end gap-2 mt-3">
                  <a href="{{route('site.customers')}}" class="btn btn-light">إلغاء</a>
                  <button class="btn btn-primary">
                  <i class="ri-save-line align-middle me-1"></i>
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
