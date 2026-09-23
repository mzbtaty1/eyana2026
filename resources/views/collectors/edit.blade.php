@extends('layouts.app')
@section('content')
@section('title' , "تعديل بيانات محصل")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <x-page-header title="تعديل بيانات محصل" />
<div class="card-body">
            <form action="{{route('site.collectors_update')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$collector_info->id}}">
@include('components.flash-messages')
               <p>
                    اسم المحصل
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="name" value="{{old('name', $collector_info->name)}}" placeholder="اسم المحصل" class="form-control @error('name') is-invalid @enderror" style="text-align:right;" required="">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                    <p>
                    رقم الهاتف
                     </p>
                     <input type="text" name="phone" value="{{old('phone', $collector_info->phone)}}" placeholder="رقم الهاتف" class="form-control @error('phone') is-invalid @enderror" style="text-align:right;">
                     @error('phone') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   تعديل بيانات محصل
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
