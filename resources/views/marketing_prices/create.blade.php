@extends('layouts.app')
@section('content')
@section('title' , "اضافة قائمة اسعار تسويق جديدة")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة قائمة اسعار تسويق جديدة </h5>
<!--
               <a href="{{route('site.marketing_title_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة بيان جديد
                 </button>
             </a>
-->
             <a href="{{route('site.marketing_titles')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-eye-line"></i>
                 كل البيانات (العناوين)
                 </button>
             </a>
         </div>
         <div class="card-body">
            <form action="{{route('site.marketing_prices_store')}}" method="POST" autocomplete="off">
               @csrf
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
                    <option value="{{$title->id}}" @if((string) old('title') === (string) $title->id) selected @endif>{{$title->title}}</option>
                    @endforeach
                </select>
                @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                   <p>
                   تاريخ السفر
            <rtag>(*)</rtag>
                </p>
                     <input type="date" name="travel_date" value="{{old('travel_date')}}" placeholder="تاريخ السفر " class="form-control @error('travel_date') is-invalid @enderror" style="text-align:right;" required="">
                     @error('travel_date') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                       <p>
                    موعد الاقلاع
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="time_departure" value="{{old('time_departure')}}" placeholder="موعد الاقلاع" class="form-control @error('time_departure') is-invalid @enderror" style="text-align:right;" required="">
                     @error('time_departure') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                       <p>
                    السعر
            <rtag>(*)</rtag>
                </p>
                     <input type="tel" name="total_price" value="{{old('total_price')}}" placeholder="السعر" class="form-control @error('total_price') is-invalid @enderror" style="text-align:right;" required="">
                     @error('total_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                       <p>
                    التكلفة
            <rtag>(*)</rtag>
                </p>
                     <input type="tel" name="cost_price" value="{{old('cost_price')}}" placeholder="التكلفة" class="form-control @error('cost_price') is-invalid @enderror" style="text-align:right;" required="">
                     @error('cost_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                     <p>
                    رقم الحجز
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="booking_id" value="{{old('booking_id')}}" placeholder="" class="form-control @error('booking_id') is-invalid @enderror" style="text-align:right;" required="">
                     @error('booking_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                     <p>
                    رقم الشاشة
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="screen_id" value="{{old('screen_id')}}" placeholder="" class="form-control @error('screen_id') is-invalid @enderror" style="text-align:right;" required="">
                     @error('screen_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                     <p>
                    وقت انتهاء الهولد
            <rtag>(*)</rtag>
                </p>
                     <input type="date" name="hold_finish_time" value="{{old('hold_finish_time')}}" placeholder="" class="form-control @error('hold_finish_time') is-invalid @enderror" style="text-align:right;" required="">
                     <input type="time" name="hold_finish_time2" value="{{old('hold_finish_time2')}}" placeholder="" class="form-control @error('hold_finish_time2') is-invalid @enderror" style="text-align:right;" required="">
                     @error('hold_finish_time') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
                   <br>
                     <p>
                    عدد الامكان
            <rtag>(*)</rtag>
                </p>
                     <input type="tel" name="places_available" value="{{old('places_available')}}" placeholder="" class="form-control @error('places_available') is-invalid @enderror" style="text-align:right;" required="">
                     @error('places_available') <div class="invalid-feedback">{{$message}}</div> @enderror
                  <br>
                     <p>
                    اسم الموظف
            <rtag>(*)</rtag>
                </p>
                     <input type="text" name="added_by" value="{{old('added_by', Auth::user()->name)}}" placeholder="" class="form-control @error('added_by') is-invalid @enderror" style="text-align:right;" required="">
                     @error('added_by') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة قائمة اسعار تسويق جديدة  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
