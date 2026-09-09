@extends('layouts.app')
@section('content')
@section('title' , "تعديل بيانات محصل")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل بيانات محصل </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.collectors_update')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$collector_info->id}}">
                
               <p>
                    اسم المحصل
                <rtag>(*)</rtag>    
                </p>
                     <input type="text" name="name" value="{{$collector_info->name}}" placeholder="اسم المحصل" class="form-control" style="text-align:right;" required="">
                  
                <br>
                    <p>
                    رقم الهاتف
                     </p>
                     <input type="text" name="phone" value="{{$collector_info->phone}}" placeholder="رقم الهاتف" class="form-control" style="text-align:right;">
                  
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
