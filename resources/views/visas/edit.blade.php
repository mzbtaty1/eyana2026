@extends('layouts.app')
@section('content')
@section('title' , "تعديل خط الطيران")
<?php $visa_info = $visa; ?>
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل خط الطيران </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.visas_update')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$visa->id}}">
                
             <input type="hidden" name="id" value="{{$visa_info->id}}">
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
                <p style="text-align: right;"> اسم التأشيرة</p>
               <input type="text" name="visa_name" class="form-control @error('visa_name') is-invalid @enderror" value="{{old('visa_name', $visa_info->visa_name)}}" style="text-align:right;" required="">
               @error('visa_name') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>
                <p style="text-align: right;"> سعر التأشيرة</p>
               <input type="text" name="visa_price" class="form-control @error('visa_price') is-invalid @enderror" value="{{old('visa_price', $visa_info->visa_price)}}" style="text-align:right;" required="">
               @error('visa_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                <p style="text-align: right;"> سعر التنفيذ للتأشيرة</p>
               <input type="text" name="visa_ext_price" class="form-control @error('visa_ext_price') is-invalid @enderror" value="{{old('visa_ext_price', $visa_info->visa_ext_price)}}" style="text-align:right;" required="">
               @error('visa_ext_price') <div class="invalid-feedback">{{$message}}</div> @enderror

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
