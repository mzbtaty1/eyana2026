@extends('layouts.app')
@section('content')
@section('title' , "تعديل بيانات الحساب")
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <x-page-header title="تعديل بيانات الحساب" />
<div class="card-body">

              <form action="{{route('site.password_update')}}" method="POST" autocomplete="off">
               @csrf
               <input type="hidden" name="id" value="{{$password_info->id}}">
@include('components.flash-messages')
                <p style="text-align: right;"> رابط الموقع</p>
               <input type="text" name="url" class="form-control @error('url') is-invalid @enderror" style="text-align:right;" value="{{old('url', $password_info->url)}}" required="" autocomplete="off">
               @error('url') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>

                <p style="text-align: right;"> البريد الالكتروني / اسم الدخول</p>
               <input type="text" name="user" class="form-control @error('user') is-invalid @enderror" style="text-align:right;" value="{{old('user', $password_info->user)}}" required="" autocomplete="off">
               @error('user') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>

                 <p style="text-align: right;"> كلمة السر</p>
               <input type="password" name="pass" class="form-control @error('pass') is-invalid @enderror" style="text-align:right;" value="{{old('pass', $password_info->pass)}}" required="" autocomplete="new-password">
               @error('pass') <div class="invalid-feedback">{{$message}}</div> @enderror
               <br>


               <div class="d-grid gap-2">
                   <button class="btn btn-primary" style="border-radius: 39px; border: 0;"> حفظ التعديلات</button>
               </div>
            </form>

          </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
