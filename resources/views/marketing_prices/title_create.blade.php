@extends('layouts.app')
@section('content')
@section('title' , "اضافة بيان جديد")
<x-page-header title="اضافة بيان جديد" />
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <form action="{{route('site.marketing_title_store')}}" enctype="multipart/form-data" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')
               <div class="mb-3">
                  <label class="form-label">البيان<br>جهة الاستلام / الوصول / خط الطيران <span class="text-danger">*</span></label>
                  <input type="text" name="title" value="{{old('title')}}" placeholder="جهة الاستلام / الوصول / خط الطيران" class="form-control @error('title') is-invalid @enderror" required="">
                  @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="mb-3">
                  <label class="form-label">اللوجو <span class="text-danger">*</span></label>
                  <input type="file" class="form-control @error('myPoster') is-invalid @enderror" name="myPoster" accept="image/jpeg,image/jpg,image/png" required="">
                  @error('myPoster') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="mb-3">
                  <label class="form-label">لون خلفية العرض <span class="text-danger">*</span></label>
                  <input type="color" name="bk_color" value="{{old('bk_color')}}" class="form-control @error('bk_color') is-invalid @enderror" required="">
                  @error('bk_color') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
               </div>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة بيان جديد
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
