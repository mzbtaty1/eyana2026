@extends('layouts.app')
@section('content')
@section('title' , "تعديل التأشيرة")
<?php $visa_info = $visa; ?>
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <x-page-header title="تعديل التأشيرة: {{$visa_info->visa_name}}">
            <a href="{{route('site.visas')}}" class="btn btn-light">قائمة التأشيرات</a>
         </x-page-header>
<div class="card-body">
            <form action="{{route('site.visas_update')}}" method="POST" autocomplete="off">
               @csrf
             <input type="hidden" name="id" value="{{$visa_info->id}}">
@include('components.flash-messages')
               @if($used)
               <div class="alert alert-info py-2 fs-13" role="note">
                  <i class="ri-information-line align-middle"></i>
                  هذه التأشيرة مستخدمة في {{ number_format($used) }} فاتورة: لا يمكن تغيير اسمها أو حذفها، ويمكن تعطيلها.
               </div>
               @endif
                <p style="text-align: right;"> اسم التأشيرة</p>
               <input type="text" name="visa_name" class="form-control @error('visa_name') is-invalid @enderror" value="{{old('visa_name', $visa_info->visa_name)}}" style="text-align:right;" required="" @if($used) readonly @endif>
               @error('visa_name') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>
                <p style="text-align: right;"> الحالة</p>
               <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                  <option value="1" @selected(old('status', (string) $visa_info->status) == '1')>مفعلة (تظهر في فواتير التأشيرات الجديدة)</option>
                  <option value="0" @selected(old('status', (string) $visa_info->status) == '0')>معطلة</option>
               </select>
               @error('status') <div class="invalid-feedback">{{$message}}</div> @enderror

                <br>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
تعديل بيانات التأشيرة {{$visa_info->visa_name}}
                   </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
