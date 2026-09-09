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
         
                
               <p>
                    البيان<br>
                   جهة الاستلام / الوصول / خط الطيران
            <rtag>(*)</rtag>   
                </p>
                <select name="title"
class="form-control" style="text-align:right;" required="">
                @foreach($titles as $title)
                    <option value="{{$title->id}}">{{$title->title}}</option>
                    @endforeach
                </select>
                  
                <br>
                   <p>
                   تاريخ السفر 
            <rtag>(*)</rtag>   
                </p>
                     <input type="date" name="travel_date" value="" placeholder="تاريخ السفر " class="form-control" style="text-align:right;" required="">
                  
                <br>
                       <p>
                    موعد الاقلاع
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="time_departure" value="" placeholder="موعد الاقلاع" class="form-control" style="text-align:right;" required="">
                  
                <br>
                       <p>
                    السعر
            <rtag>(*)</rtag>   
                </p>
                     <input type="tel" name="total_price" value="" placeholder="السعر" class="form-control" style="text-align:right;" required="">
                  
                <br>
                       <p>
                    التكلفة
            <rtag>(*)</rtag>   
                </p>
                     <input type="tel" name="cost_price" value="" placeholder="التكلفة" class="form-control" style="text-align:right;" required="">
                  
                <br>
                     <p>
                    رقم الحجز
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="booking_id" value="" placeholder="" class="form-control" style="text-align:right;" required="">
                  
                <br>
                     <p>
                    رقم الشاشة
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="screen_id" value="" placeholder="" class="form-control" style="text-align:right;" required="">
                  
                <br>
                     <p>
                    وقت انتهاء الهولد
            <rtag>(*)</rtag>   
                </p>
                     <input type="date" name="hold_finish_time" value="" placeholder="" class="form-control" style="text-align:right;" required="">
                     <input type="time" name="hold_finish_time2" value="" placeholder="" class="form-control" style="text-align:right;" required="">
                   <br>
                     <p>
                    عدد الامكان
            <rtag>(*)</rtag>   
                </p>
                     <input type="tel" name="places_available" value="" placeholder="" class="form-control" style="text-align:right;" required="">
                  <br>
                     <p>
                    اسم الموظف
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="added_by" value="{{Auth::user()->name}}" placeholder="" class="form-control" style="text-align:right;" required="">
                  
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
