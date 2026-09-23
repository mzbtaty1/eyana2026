@extends('layouts.app')
@section('content')
@section('title' , "تعديل عنوان")
<x-page-header title="تعديل عنوان" />
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <form action="{{route('site.marketing_save_update')}}" enctype="multipart/form-data" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$title_info->id}}">
@include('components.flash-messages')
               <div class="mb-3">
                  <label class="form-label">البيان<br>جهة الاستلام / الوصول / خط الطيران <span class="text-danger">*</span></label>
                  <input type="text" name="title" value="{{old('title', $title_info->title)}}" placeholder="جهة الاستلام / الوصول / خط الطيران" class="form-control @error('title') is-invalid @enderror" required="">
                  @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="mb-3">
                  <label class="form-label">اللوجو (المسموح : JPG , PNG , JPEG فقط)<br>لا تقم بتغيير الملف في حالة عدم الرغبة في التغيير</label>
                  <input type="file" class="form-control @error('myPoster') is-invalid @enderror" name="myPoster" accept="image/jpeg,image/jpg,image/png">
                  @error('myPoster') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="mb-3">
                  <label class="form-label">لون خلفية العرض <span class="text-danger">*</span></label>
                  <input type="color" name="bk_color" value="{{old('bk_color', $title_info->bk_color)}}" class="form-control @error('bk_color') is-invalid @enderror" required="">
                  @error('bk_color') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
               </div>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   تعديل عنوان
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
