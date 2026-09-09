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
         
                
                 <p style="text-align: right;"> اسم التأشيرة</p>
               <input type="text" name="visa_name" class="form-control" style="text-align:right;" required="">
               <br>
                <p style="text-align: right;"> سعر التأشيرة</p>
               <input type="text" name="visa_price" class="form-control" style="text-align:right;" required="">
                <br>
                <p style="text-align: right;"> سعر التنفيذ التأشيرة</p>
               <input type="text" name="visa_ext_price" class="form-control" style="text-align:right;" required="">
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
