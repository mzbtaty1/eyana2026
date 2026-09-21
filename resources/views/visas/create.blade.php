@extends('layouts.app')
@section('content')
@section('title' , "اضافة بيانات تأشيرة جديدة")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة بيانات تأشيرة جديدة </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.visas_save')}}" method="POST" autocomplete="off">
               @csrf
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
                 <p style="text-align: right;"> اسم التأشيرة</p>
               <input type="text" name="visa_name" value="{{old('visa_name')}}" class="form-control @error('visa_name') is-invalid @enderror" style="text-align:right;" required="">
               @error('visa_name') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>
                <p style="text-align: right;"> سعر التأشيرة</p>
               <input type="text" name="visa_price" value="{{old('visa_price')}}" class="form-control @error('visa_price') is-invalid @enderror" style="text-align:right;" required="">
               @error('visa_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                <p style="text-align: right;"> سعر التنفيذ التأشيرة</p>
               <input type="text" name="visa_ext_price" value="{{old('visa_ext_price')}}" class="form-control @error('visa_ext_price') is-invalid @enderror" style="text-align:right;" required="">
               @error('visa_ext_price') <div class="invalid-feedback">{{$message}}</div> @enderror
<!--               <br>-->

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
