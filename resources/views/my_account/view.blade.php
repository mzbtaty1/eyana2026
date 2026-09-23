@extends('layouts.app')
@section('content')
@section('title' , "حسابي")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0"> اعدادات حسابي </h5>
<!--
               <a href="{{route('site.marketing_title_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة بيان جديد
                 </button>
             </a>
-->
             
             
         </div>
         <div class="card-body">
      
              <form action="{{route('site.my_account_saves')}}" method="POST" autocomplete="off">
               @csrf       
                <input type="hidden" name="admin_id" value="{{Auth::user()->id}}">
               <p style="text-align: right;"> اسم الموظف</p>
               <input type="text" name="name" class="form-control" style="text-align:right;" value="{{Auth::user()->name}}" required="" autocomplete="off">
               <br>
                <p style="text-align: right;"> البريد الالكتروني </p>
               <input type="email" name="email" class="form-control" style="text-align:right;" value="{{Auth::user()->email}}" required="" autocomplete="off">
               <br>
                 <p style="text-align: right;"> كلمة السر (اترك كلمة السر فارغه في حالة عدم الرغبة في التغيير) </p>
               <input type="text" name="password" class="form-control" style="text-align:right;" autocomplete="off">

                  
               <br>
                
                
               <div class="d-grid gap-2">
<!--                  <input type="submit" name="send" >-->
                   <button class="btn btn-primary" value="اضافة بيانات موظف 
                     " style="border-radius: 39px;
                     border: 0;"> تعديل بياناتي </button>
               </div>
            </form>
             
          </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
