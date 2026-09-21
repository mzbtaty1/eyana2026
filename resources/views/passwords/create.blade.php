@extends('layouts.app')
@section('content')
@section('title' , "اضافة حساب جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة حساب جديد </h5>
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
      
              <form action="{{route('site.password_save')}}" method="POST" autocomplete="off">
               @csrf
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
                <p style="text-align: right;"> رابط الموقع</p>
               <input type="text" name="url" class="form-control @error('url') is-invalid @enderror" style="text-align:right;" value="{{old('url')}}" required="" autocomplete="off">
               @error('url') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>

                <p style="text-align: right;"> البريد الالكتروني / اسم الدخول</p>
               <input type="text" name="user" class="form-control @error('user') is-invalid @enderror" style="text-align:right;" value="{{old('user')}}" required="" autocomplete="off">
               @error('user') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>

                 <p style="text-align: right;"> كلمة السر</p>
               <input type="password" name="pass" class="form-control @error('pass') is-invalid @enderror" style="text-align:right;" value="{{old('pass')}}" required="" autocomplete="new-password">
               @error('pass') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>
                
                
               <div class="d-grid gap-2">
<!--                  <input type="submit" name="send" >-->
                   <button class="btn btn-primary" value="اضافة بيانات جديدة 
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
