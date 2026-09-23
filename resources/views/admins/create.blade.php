@extends('layouts.app')
@section('content')
@section('title' , "اضفافة حساب جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="card-title mb-0"> اضافة حساب جديد </h5>
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
      
              <form action="{{route('site.admins_save')}}" method="POST" autocomplete="off">
               @csrf       
               <p style="text-align: right;"> اسم الموظف</p>
               <input type="text" name="name" class="form-control" style="text-align:right;" value="" required="" autocomplete="off">
               <br> 
                <p style="text-align: right;"> البريد الالكتروني </p>
               <input type="text" name="email" class="form-control" style="text-align:right;" value="" required="" autocomplete="off">
               <br>
                  <p style="text-align: right;"> العمولة </p>
               <input type="text" name="commission" class="form-control" style="text-align:right;" value="" required="" autocomplete="off">
               <br>
                  <p style="text-align: right;"> الحالة </p>
              
                  <select class="form-select" name="status" required>
                       <option value="1">فعال</option>
                  <option value="0">محظور</option>
                 
                  </select>
                  
               <br> <p style="text-align: right;"> نوع الحساب </p>
              
                  <select class="form-select" name="account_type" required>
                  <option value="1">موظف</option>
                  <option value="2">محاسب / مسئول</option>
                  </select>
                  
               <br>
                   <p style="text-align: right;"> كلمة السر</p>
               <input type="text" name="password" placeholder="كلمة السر" class="form-control" style="text-align:right;" autocomplete="off" required>

                  
               <br>
                
                
               <div class="d-grid gap-2">
<!--                  <input type="submit" name="send" >-->
                   <button class="btn btn-primary" value="اضافة بيانات موظف 
                     " style="border-radius: 39px;
                     border: 0;"> اضافة بيانات جديدة</button>
               </div>
            </form>
             
          </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
