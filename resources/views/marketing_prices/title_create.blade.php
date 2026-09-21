@extends('layouts.app')
@section('content')
@section('title' , "اضافة بيان جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة بيان جديد </h5>
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
            <form action="{{route('site.marketing_title_store')}}" enctype="multipart/form-data" method="POST" autocomplete="off">
               @csrf
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
               <p>
                    البيان<br>
                   جهة الاستلام / الوصول / خط الطيران
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="title" value="{{old('title')}}" placeholder="جهة الاستلام / الوصول / خط الطيران" class="form-control @error('title') is-invalid @enderror" style="text-align:right;" required="">
                     @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>

                 <p>
                    اللوجو
            <rtag>(*)</rtag>
                </p>
               <input type="file" class="form-control @error('myPoster') is-invalid @enderror" name="myPoster" accept="image/jpeg,image/jpg,image/png" required="">
               @error('myPoster') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>

                 <p>
                    لون خلفية العرض
            <rtag>(*)</rtag>
                </p>
                     <input type="color" name="bk_color" value="{{old('bk_color')}}" class="form-control @error('bk_color') is-invalid @enderror" style="text-align:right;" required="">
                     @error('bk_color') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
                <br>
                
                
                
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
