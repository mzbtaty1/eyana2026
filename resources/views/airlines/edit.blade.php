@extends('layouts.app')
@section('content')
@section('title' , "تعديل خط الطيران")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل خط الطيران </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.airlines_update')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$airline->id}}">
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
               <p>
                    اسم خط الطيران
                     </p>
                     <input type="text" name="airline_name" value="{{old('airline_name', $airline->airline_name)}}" placeholder="اسم خط الطيران" class="form-control @error('airline_name') is-invalid @enderror" style="text-align:right;" required="">
                     @error('airline_name') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
تعديل خط الطيران
                   </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
