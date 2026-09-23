@extends('layouts.app')
@section('content')
@section('title' , "اضافة حساب جديد")
<x-page-header title="اضافة حساب جديد" />
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">

              <form action="{{route('site.password_save')}}" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')
               <div class="mb-3">
                  <label class="form-label">رابط الموقع</label>
                  <input type="text" name="url" class="form-control @error('url') is-invalid @enderror" value="{{old('url')}}" required="" autocomplete="off">
                  @error('url') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="mb-3">
                  <label class="form-label">البريد الالكتروني / اسم الدخول</label>
                  <input type="text" name="user" class="form-control @error('user') is-invalid @enderror" value="{{old('user')}}" required="" autocomplete="off">
                  @error('user') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="mb-3">
                  <label class="form-label">كلمة السر</label>
                  <input type="password" name="pass" class="form-control @error('pass') is-invalid @enderror" value="{{old('pass')}}" required="" autocomplete="new-password">
                  @error('pass') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="d-grid gap-2">
                   <button class="btn btn-primary"> اضافة بيانات جديدة</button>
               </div>
            </form>

          </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
