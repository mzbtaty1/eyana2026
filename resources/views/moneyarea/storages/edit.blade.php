@extends('layouts.app')
@section('content')
@section('title' , "تعديل بيانات خزنة")
<div class="row">
   <div class="col-lg-12">
   
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل بيانات خزنة </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.storages_update')}}" method="POST" autocomplete="off">
               @csrf
         
                <input type="hidden" name="id" value="{{$storage_info->id}}">
               <p>
                    اسم الخزنة
                <rtag>(*)</rtag>    
                </p>
                     <input type="text" name="name" value="{{$storage_info->name}}" placeholder="اسم الخزنة" class="form-control" style="text-align:right;" required="" disabled>
                  
                <br>
                <p>
                    النوع
                <rtag>(*)</rtag>    
                </p>
                    <select class="form-select" name="type" required>
                <option value="1" @if($storage_info->type == 1) selected="" @endif>نقدي</option>
                <option value="2" @if($storage_info->type == 2) selected="" @endif>حساب بنكي</option>
                </select>
                  
                <br>
                <p>
                    الحساب البنكي                   
                </p>
     <select class="form-select" name="bank_id">
         <option value="" @if($storage_info->bank_id == null) selected="" @endif>--</option>
                @foreach($banks as $bank)
         <option value="{{$bank->id}}" @if($storage_info->bank_id == $bank->id) selected="" @endif>{{$bank->bank_name}}</option>
         @endforeach
                </select>                  
                <br>
                <p>
                    رقم الحساب
                </p>
                     <input type="text" name="bank_number" value="{{$storage_info->bank_number}}" value="" placeholder="رقم الحساب" class="form-control" style="text-align:right;">
                  
                <br>
                <p>
                    الرصيد
                <rtag>(*)</rtag>    
                </p>
                     <input type="text" name="balance" value="{{$storage_info->balance}}" placeholder="الرصيد" class="form-control" style="text-align:right;" required="">
                  
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   تعديل بيانات خزنة  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
