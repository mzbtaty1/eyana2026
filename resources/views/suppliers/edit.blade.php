@extends('layouts.app')
@section('content')
@section('title' , $supplier->name)
<x-page-header title="تفاصيل المورد : ({{$supplier->name}})" />
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <form action="{{route('site.suppliers_update')}}" method="POST" autocomplete="off">
               @csrf
               <input type="hidden" value="{{$supplier->id}}" name="id">
@include('components.flash-messages')

               <h6 class="text-uppercase text-muted fs-13 mb-3">البيانات الأساسية</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">اسم المورد</label>
                     <input type="text" name="name" value="{{old('name', $supplier->name)}}" placeholder="" class="form-control @error('name') is-invalid @enderror" required="">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">الايميل</label>
                     <input type="email" name="email" value="{{old('email', $supplier->email)}}" placeholder="" class="form-control @error('email') is-invalid @enderror">
                     @error('email') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">العنوان</label>
                     <input type="text" name="address" value="{{old('address', $supplier->address)}}" placeholder="" class="form-control @error('address') is-invalid @enderror">
                     @error('address') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">بيانات التواصل</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الهاتف 1</label>
                     <input type="text" name="phone_1" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_1', $supplier->phone_1)}}" placeholder="" class="form-control @error('phone_1') is-invalid @enderror" required="">
                     @error('phone_1') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الهاتف 2 (غير الزامي)</label>
                     <input type="text" name="phone_2" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('phone_2', $supplier->phone_2)}}" placeholder="" class="form-control @error('phone_2') is-invalid @enderror">
                     @error('phone_2') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>
<!--
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        رقم الباسبور
                     </p>
                     <input type="text" name="passport_id" value="{{$supplier->passport_id}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
                  <div class="col-6">
                     <p>
                        تاريخ انتهاء الباسبور
                     </p>
                     <input type="date" name="passport_expiration_date" value="{{$supplier->passport_expiration_date}}" placeholder="" class="form-control" style="text-align:right;">
                  </div>
               </div>
-->

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">البيانات المالية</h6>
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">الرصيد الافتتاحي المدين</label>
                     <input type="text" name="debit_opening_balance" inputmode="decimal" value="{{old('debit_opening_balance', $supplier->debit_opening_balance)}}" placeholder="" class="form-control js-money @error('debit_opening_balance') is-invalid @enderror" required="">
                     @error('debit_opening_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رصيد افتتاحى الدائن</label>
                     <input type="text" name="opening_credit_balance" inputmode="decimal" value="{{old('opening_credit_balance', $supplier->opening_credit_balance)}}" placeholder="" class="form-control js-money @error('opening_credit_balance') is-invalid @enderror" required>
                     @error('opening_credit_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

               <h6 class="text-uppercase text-muted fs-13 mb-3 mt-2">الإعدادات</h6>
               <div class="row">
                  <div class="col-md-3 mb-3">
                     <label class="form-label">الحالة</label>
                     <select name="status" class="form-control @error('status') is-invalid @enderror" required="">
                     <option value="0" @if(old('status', $supplier->status) == 0) selected @endif>موقوف</option>
                     <option value="1" @if(old('status', $supplier->status) == 1) selected @endif>مفعل</option>
                     </select>
                  </div>
                  <div class="col-md-3 mb-3">
                     <label class="form-label">النوع</label>
                     <select name="type" class="form-control @error('type') is-invalid @enderror" required="">
                     <option value="1" @if(old('type', $supplier->type) == 1) selected @endif>فرد</option>
                     <option value="2" @if(old('type', $supplier->type) == 2) selected @endif>شركة</option>
                     </select>
                  </div>
                  <div class="col-md-3 mb-3">
                     <label class="form-label">نوع الحساب</label>
                     <select name="acc_type" class="form-control @error('acc_type') is-invalid @enderror" required="">
                     <option value="1" @if(old('acc_type', $supplier->acc_type) == 1) selected @endif>عميل</option>
                     <option value="2" @if(old('acc_type', $supplier->acc_type) == 2) selected @endif>مورد</option>
                     </select>
                  </div>
                  <div class="col-md-3 mb-3">
                     <label class="form-label">اظهار كملخص في الصفحة الرئيسية</label>
                     <select name="in_index" class="form-control @error('in_index') is-invalid @enderror" required="">
                     <option value="0" @if(old('in_index', $supplier->in_index) == 0) selected @endif>لا</option>
                     <option value="1" @if(old('in_index', $supplier->in_index) == 1) selected @endif>نعم</option>
                     </select>
                  </div>
                  <div class="col-md-3 mb-3">
                     <label class="form-label">عرض في كشف الحساب الشركات المخصص</label>
                     <select name="in_stat" class="form-control @error('in_stat') is-invalid @enderror" required="">
                     <option value="0" @if(old('in_stat', $supplier->in_stat) == 0) selected @endif>لا</option>
                     <option value="1" @if(old('in_stat', $supplier->in_stat) == 1) selected @endif>نعم</option>
                     </select>
                  </div>
               </div>

               <div class="d-flex justify-content-end gap-2 mt-3">
                  <a href="{{route('site.suppliers')}}" class="btn btn-light">إلغاء</a>
                  <button class="btn btn-primary">
                  <i class="ri-save-line align-middle me-1"></i>
                  حفظ بيانات المورد {{$supplier->name}}
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@include('suppliers._money_inputs')
@endsection
