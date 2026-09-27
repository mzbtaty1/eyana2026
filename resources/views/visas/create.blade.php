@extends('layouts.app')
@section('content')
@section('title' , "اضافة بيانات تأشيرة جديدة")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <x-page-header title="اضافة بيانات تأشيرة جديدة" />
<div class="card-body">
            <form action="{{route('site.visas_save')}}" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')
                 <p style="text-align: right;"> اسم التأشيرة</p>
               <input type="text" name="visa_name" value="{{old('visa_name')}}" class="form-control @error('visa_name') is-invalid @enderror" style="text-align:right;" required="">
               @error('visa_name') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>
                <p style="text-align: right;"> الحالة</p>
               <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                  <option value="1" @selected(old('status', '1') == '1')>مفعلة (تظهر في فواتير التأشيرات الجديدة)</option>
                  <option value="0" @selected(old('status') === '0')>معطلة</option>
               </select>
               @error('status') <div class="invalid-feedback">{{$message}}</div> @enderror

                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة بيانات تأشيرة جديدة
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
