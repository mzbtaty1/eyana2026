@extends('layouts.app')
@section('content')
@section('title' , "اضافة اشعار جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة اشعار جديد </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.alerts_save')}}" method="POST" autocomplete="off">
               @csrf
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
           <p>
                نص الاشعار
                </p>
                <textarea class="form-control @error('alert_txt') is-invalid @enderror" name="alert_txt" placeholder="ادخل نص الاشعار" rows="5" required>{{old('alert_txt')}}</textarea>
                @error('alert_txt') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة اشعار جديد  
                  </button>
               </div>
            </form>
         </div> 
      </div>
   </div>
   <!--end col-->
</div>
@endsection
