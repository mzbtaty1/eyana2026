@extends('layouts.app')
@section('content')
@section('title' , "تعديل قائمة تسويق")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل قائمة تسويق : {{$marketing_info->id}} </h5>
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
            <form action="{{route('site.marketing_update_save')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$marketing_info->id}}">
               @if($errors->any())
               <div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
               @endif
               <p>
                    البيان<br>
                   جهة الاستلام / الوصول / خط الطيران
            <rtag>(*)</rtag>
                </p>
                <select name="title"
class="form-control @error('title') is-invalid @enderror" style="text-align:right;" required="">
                @foreach($titles as $title)
                    <option value="{{$title->id}}" @if((string) old('title', $marketing_info->title) === (string) $title->id) selected @endif>{{$title->title}}</option>
                    @endforeach
                </select>
                @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                   <p>
                   تاريخ السفر
            <rtag>(*)</rtag>
                </p>
                     <input type="date" name="travel_date" value="{{old('travel_date', $marketing_info->travel_date)}}" placeholder="تاريخ السفر " class="form-control @error('travel_date') is-invalid @enderror" style="text-align:right;" required="">
                     @error('travel_date') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                       <p>
                    موعد الاقلاع
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="time_departure" value="{{old('time_departure', $marketing_info->time_departure)}}" placeholder="موعد الاقلاع" class="form-control @error('time_departure') is-invalid @enderror" style="text-align:right;" required="">
                     @error('time_departure') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                       <p>
                    السعر
            <rtag>(*)</rtag>
                </p>
                     <input type="tel" name="total_price" value="{{old('total_price', $marketing_info->total_price)}}" placeholder="السعر" class="form-control @error('total_price') is-invalid @enderror" style="text-align:right;" required="">
                     @error('total_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                       <p>
                    التكلفة
            <rtag>(*)</rtag>
                </p>
                     <input type="tel" name="cost_price" value="{{old('cost_price', $marketing_info->cost_price)}}" placeholder="التكلفة" class="form-control @error('cost_price') is-invalid @enderror" style="text-align:right;" required="">
                     @error('cost_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                     <p>
                    رقم الحجز
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="booking_id" value="{{old('booking_id', $marketing_info->booking_id)}}" placeholder="" class="form-control @error('booking_id') is-invalid @enderror" style="text-align:right;" required="">
                     @error('booking_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                     <p>
                    رقم الشاشة
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="screen_id" value="{{old('screen_id', $marketing_info->screen_id)}}" placeholder="" class="form-control @error('screen_id') is-invalid @enderror" style="text-align:right;" required="">
                     @error('screen_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                     <p>
                    وقت انتهاء الهولد
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="hold_finish_time" value="{{old('hold_finish_time', $marketing_info->hold_finish_time)}}" placeholder="" class="form-control @error('hold_finish_time') is-invalid @enderror" style="text-align:right;" required="">
                     @error('hold_finish_time') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   تعديل قائمة تسويق  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
