@extends('layouts.app')
@section('content')
@section('title' , "تعديل بيانات خزنة")
<div class="row">
   <div class="col-lg-12">
   
      <div class="card">
         <x-page-header title="تعديل بيانات خزنة" />
<div class="card-body">
            <form action="{{route('site.storages_update')}}" method="POST" autocomplete="off">
               @csrf
                <input type="hidden" name="id" value="{{$storage_info->id}}">
@include('components.flash-messages')
               <p>
                    اسم الخزنة
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="name" value="{{$storage_info->name}}" placeholder="اسم الخزنة" class="form-control" style="text-align:right;" required="" disabled>

                <br>
                <p>
                    النوع
                <span class="text-danger">*</span>
                </p>
                    <select class="form-select @error('type') is-invalid @enderror" name="type" required>
                <option value="1" @if(old('type', $storage_info->type) == 1) selected="" @endif>نقدي</option>
                <option value="2" @if(old('type', $storage_info->type) == 2) selected="" @endif>حساب بنكي</option>
                </select>
                @error('type') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                <p>
                    الحساب البنكي
                </p>
     <select class="form-select @error('bank_id') is-invalid @enderror" name="bank_id">
         <option value="" @if(old('bank_id', $storage_info->bank_id) == null) selected="" @endif>--</option>
                @foreach($banks as $bank)
         <option value="{{$bank->id}}" @if(old('bank_id', $storage_info->bank_id) == $bank->id) selected="" @endif>{{$bank->bank_name}}</option>
         @endforeach
                </select>
                @error('bank_id') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
                <br>
                <p>
                    رقم الحساب
                </p>
                     <input type="text" name="bank_number" value="{{old('bank_number', $storage_info->bank_number)}}" placeholder="رقم الحساب" class="form-control @error('bank_number') is-invalid @enderror" style="text-align:right;">
                     @error('bank_number') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                <p>
                    الرصيد
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="balance" value="{{old('balance', $storage_info->balance)}}" placeholder="الرصيد" class="form-control @error('balance') is-invalid @enderror" style="text-align:right;" required="">
                     @error('balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   تعديل بيانات خزنة  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
