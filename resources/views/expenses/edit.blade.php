@extends('layouts.app')
@section('content')
@section('title' , $supplier->name)
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <x-page-header title="تفاصيل المصروف : ({{$supplier->name}})" />
<div class="card-body">
            <form action="{{route('site.expenses_update')}}" method="POST" autocomplete="off">
               @csrf
               <input type="hidden" value="{{$supplier->id}}" name="id">
@include('components.flash-messages')
               <div class="row">
                  <div class="">
                     <p>
                        اسم المصروف
                     </p>
                     <input type="text" name="name" value="{{old('name', $supplier->name)}}" placeholder="" class="form-control @error('name') is-invalid @enderror" style="text-align:right;" required="">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>

               </div>


               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        الرصيد الافتتاحي المدين
                     </p>
                     <input type="text" name="debit_opening_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('debit_opening_balance', $supplier->debit_opening_balance)}}" placeholder="" class="form-control @error('debit_opening_balance') is-invalid @enderror" style="text-align:right;" required="">
                     @error('debit_opening_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-6">
                     <p>
                        رصيد افتتاحى الدائن
                     </p>
                     <input type="text" name="opening_credit_balance" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="{{old('opening_credit_balance', $supplier->opening_credit_balance)}}" placeholder="" class="form-control @error('opening_credit_balance') is-invalid @enderror" style="text-align:right;">
                     @error('opening_credit_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>
             
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                  حفظ بيانات المصروف {{$supplier->name}}
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
