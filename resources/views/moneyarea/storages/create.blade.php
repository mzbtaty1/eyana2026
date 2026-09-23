@extends('layouts.app')
@section('content')
@section('title' , "اضافة خزنة جديدة")
<div class="row">
   <div class="col-lg-12">
   
      <div class="card">
         <x-page-header title="اضافة خزنة جديدة" />
<div class="card-body">
            <form action="{{route('site.storages_save')}}" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')
               <p>
                    اسم الخزنة
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="name" value="{{old('name')}}" placeholder="اسم الخزنة" class="form-control @error('name') is-invalid @enderror" style="text-align:right;" required="">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                <p>
                    النوع
                <span class="text-danger">*</span>
                </p>
                    <select class="form-select @error('type') is-invalid @enderror" name="type" required>
                <option value="1" @if(old('type')==='1') selected @endif>نقدي</option>
                <option value="2" @if(old('type')==='2') selected @endif>حساب بنكي</option>
                </select>
                @error('type') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                <p>
                    الحساب البنكي
                </p>
     <select class="form-select @error('bank_id') is-invalid @enderror" name="bank_id">
         <option value="" @if(old('bank_id')==='') selected @endif>--</option>
                @foreach($banks as $bank)
         <option value="{{$bank->id}}" @if((string) old('bank_id') === (string) $bank->id) selected @endif>{{$bank->bank_name}}</option>
         @endforeach
                </select>
                @error('bank_id') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
                <br>
                <p>
                    رقم الحساب
                </p>
                     <input type="text" name="bank_number" value="{{old('bank_number')}}" placeholder="رقم الحساب" class="form-control @error('bank_number') is-invalid @enderror" style="text-align:right;">
                     @error('bank_number') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                <p>
                    الرصيد
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="balance" value="{{old('balance', '0')}}" placeholder="الرصيد" class="form-control @error('balance') is-invalid @enderror" style="text-align:right;" required="">
                     @error('balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة خزنة جديدة  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
