@extends('layouts.app')
@section('content')
@section('title' , "اضافة محصل")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <x-page-header title="اضافة محصل" />
<div class="card-body">
            <form action="{{route('site.collectors_save')}}" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')
               <p>
                    اسم المحصل
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="name" value="{{old('name')}}" placeholder="اسم المحصل" class="form-control @error('name') is-invalid @enderror" style="text-align:right;" required="">
                     @error('name') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                    <p>
                    رقم الهاتف
                     </p>
                     <input type="text" name="phone" value="{{old('phone')}}" placeholder="رقم الهاتف" class="form-control @error('phone') is-invalid @enderror" style="text-align:right;">
                     @error('phone') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة محصل  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
