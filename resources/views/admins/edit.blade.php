@extends('layouts.app')
@section('content')
@section('title' , "حسابي")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اعدادات الموظف : {{$admin_info->name}} </h5>
<!--
               <a href="{{route('site.marketing_title_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة بيان جديد
                 </button>
             </a>
-->
             
             
         </div>
         <div class="card-body">
      
              <form action="{{route('site.admins_save_update')}}" method="POST" autocomplete="off">
               @csrf       
                <input type="hidden" name="admin_id" value="{{$admin_info->id}}">
               <p style="text-align: right;"> اسم الموظف</p>
               <input type="text" name="name" class="form-control" style="text-align:right;" value="{{$admin_info->name}}" required="" autocomplete="off">
               <br>
                <p style="text-align: right;"> البريد الالكتروني </p>
               <input type="email" name="email" class="form-control" style="text-align:right;" value="{{$admin_info->email}}" required="" autocomplete="off">
               <br>
                  <p style="text-align: right;"> العمولة </p>
               <input type="text" name="commission" class="form-control" style="text-align:right;" value="{{$admin_info->commission}}" required="" autocomplete="off">
               <br>
                  <p style="text-align: right;"> الحالة </p>
              
                  <select class="form-select" name="status" required>
                  <option value="0" @if($admin_info->status == 0) selected="" @endif>محظور</option>
                  <option value="1" @if($admin_info->status == 1) selected="" @endif>فعال</option>
                  </select>
                  
               <br> <p style="text-align: right;"> نوع الحساب </p>
              
                  <select class="form-select" name="account_type" required>
                  <option value="1" @if($admin_info->account_type == 1) selected="" @endif>موظف</option>
                  <option value="2" @if($admin_info->account_type == 2) selected="" @endif>محاسب / مسئول</option>
                  </select>
                  
               <br>
                 <p style="text-align: right;"> كلمة السر (اترك كلمة السر فارغه في حالة عدم الرغبة في التغيير) </p>
               <input type="text" name="password" class="form-control" style="text-align:right;" autocomplete="off">

                  
               <br>
                
                
               <div class="d-grid gap-2">
<!--                  <input type="submit" name="send" >-->
                   <button class="btn btn-primary" value="اضافة بيانات موظف 
                     " style="border-radius: 39px;
                     border: 0;"> تعديل البيانات </button>
               </div>
            </form>
             
          </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
