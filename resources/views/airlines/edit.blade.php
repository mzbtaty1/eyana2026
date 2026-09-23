@extends('layouts.app')
@section('content')
@section('title' , "تعديل خط الطيران")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <x-page-header title="تعديل خط الطيران" />
<div class="card-body">
            <form action="{{route('site.airlines_update')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$airline->id}}">
@include('components.flash-messages')
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
