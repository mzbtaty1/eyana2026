@extends('layouts.app')
@section('content')
@section('title' , "اضافة خزنة فرعية جديدة")
<div class="row">
   <div class="col-lg-12">
   
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة خزنة فرعية جديدة </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.sub_storages_save')}}" method="POST" autocomplete="off">
               @csrf
         
                
               <p>
                    الخزينة الرئيسية
                <rtag>(*)</rtag>    
                </p>
 <select class="form-select" name="main_storage" required>
               @foreach($storages as $storage)
     <option value="{{$storage->id}}">{{$storage->name}}</option>
     @endforeach
                </select>
                
                <br>
                <p>
                    اسم الخزنة
                <rtag>(*)</rtag>    
                </p>
                     <input type="text" name="name" value="" placeholder="اسم الخزنة" class="form-control" style="text-align:right;" required="">
                  
                <br>
                <p>
                    النوع
                <rtag>(*)</rtag>    
                </p>
                    <select class="form-select" name="type" required>
                <option value="1">نقدي</option>
                <option value="2">حساب بنكي</option>
                </select>
                  
                <br>
                <p>
                    الحساب البنكي                   
                </p>
     <select class="form-select" name="bank_id">
         <option value="">--</option>
                @foreach($banks as $bank)
         <option value="{{$bank->id}}">{{$bank->bank_name}}</option>
         @endforeach
                </select>                  
                <br>
                <p>
                    رقم الحساب
                </p>
                     <input type="text" name="bank_number" value="" placeholder="رقم الحساب" class="form-control" style="text-align:right;">
                  
                <br>
                <p>
                    الرصيد
                <rtag>(*)</rtag>    
                </p>
                     <input type="text" name="balance" value="0" placeholder="الرصيد" class="form-control" style="text-align:right;" required="">
                  
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة خزنة فرعية جديدة  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
