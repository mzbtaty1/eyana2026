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
                
               <p>
                    البيان<br>
                   جهة الاستلام / الوصول / خط الطيران
            <rtag>(*)</rtag>   
                </p>
                <select name="title"
class="form-control" style="text-align:right;" required="">
                @foreach($titles as $title)
                    <option value="{{$title->id}}" @if($marketing_info->title == $title->id) selected @endif>{{$title->title}}</option>
                    @endforeach
                </select>
                  
                <br>
                   <p>
                   تاريخ السفر 
            <rtag>(*)</rtag>   
                </p>
                     <input type="date" name="travel_date" value="{{$marketing_info->travel_date}}" placeholder="تاريخ السفر " class="form-control" style="text-align:right;" required="">
                  
                <br>
                       <p>
                    موعد الاقلاع
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="time_departure" value="{{$marketing_info->time_departure}}" placeholder="موعد الاقلاع" class="form-control" style="text-align:right;" required="">
                  
                <br>
                       <p>
                    السعر
            <rtag>(*)</rtag>   
                </p>
                     <input type="tel" name="total_price" value="{{$marketing_info->total_price}}" placeholder="السعر" class="form-control" style="text-align:right;" required="">
                  
                <br>
                       <p>
                    التكلفة
            <rtag>(*)</rtag>   
                </p>
                     <input type="tel" name="cost_price" value="{{$marketing_info->cost_price}}" placeholder="التكلفة" class="form-control" style="text-align:right;" required="">
                  
                <br>
                     <p>
                    رقم الحجز
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="booking_id" value="{{$marketing_info->booking_id}}" placeholder="" class="form-control" style="text-align:right;" required="">
                  
                <br>
                     <p>
                    رقم الشاشة
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="screen_id" value="{{$marketing_info->screen_id}}" placeholder="" class="form-control" style="text-align:right;" required="">
                  
                <br>
                     <p>
                    وقت انتهاء الهولد
            <rtag>(*)</rtag>   
                </p>
                     <input type="text" name="hold_finish_time" value="{{$marketing_info->hold_finish_time}}" placeholder="" class="form-control" style="text-align:right;" required="">
                  
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
