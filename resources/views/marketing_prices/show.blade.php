@extends('layouts.app')
@section('content')
@section('title' , "تعديل قائمة تسويق")
<x-page-header title="تعديل قائمة تسويق : {{$marketing_info->id}}" />
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <form action="{{route('site.marketing_update_save')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$marketing_info->id}}">
@include('components.flash-messages')
               <div class="mb-3">
                  <label class="form-label">البيان<br>جهة الاستلام / الوصول / خط الطيران <span class="text-danger">*</span></label>
                  <select name="title" class="form-control @error('title') is-invalid @enderror" required="">
                  @foreach($titles as $title)
                      <option value="{{$title->id}}" @if((string) old('title', $marketing_info->title) === (string) $title->id) selected @endif>{{$title->title}}</option>
                      @endforeach
                  </select>
                  @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">تاريخ السفر <span class="text-danger">*</span></label>
                     <input type="date" name="travel_date" value="{{old('travel_date', $marketing_info->travel_date)}}" placeholder="تاريخ السفر " class="form-control @error('travel_date') is-invalid @enderror" required="">
                     @error('travel_date') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">موعد الاقلاع <span class="text-danger">*</span></label>
                     <input type="text" name="time_departure" value="{{old('time_departure', $marketing_info->time_departure)}}" placeholder="موعد الاقلاع" class="form-control @error('time_departure') is-invalid @enderror" required="">
                     @error('time_departure') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">السعر <span class="text-danger">*</span></label>
                     <input type="tel" name="total_price" value="{{old('total_price', $marketing_info->total_price)}}" placeholder="السعر" class="form-control @error('total_price') is-invalid @enderror" required="">
                     @error('total_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">التكلفة <span class="text-danger">*</span></label>
                     <input type="tel" name="cost_price" value="{{old('cost_price', $marketing_info->cost_price)}}" placeholder="التكلفة" class="form-control @error('cost_price') is-invalid @enderror" required="">
                     @error('cost_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الحجز <span class="text-danger">*</span></label>
                     <input type="text" name="booking_id" value="{{old('booking_id', $marketing_info->booking_id)}}" placeholder="" class="form-control @error('booking_id') is-invalid @enderror" required="">
                     @error('booking_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الشاشة <span class="text-danger">*</span></label>
                     <input type="text" name="screen_id" value="{{old('screen_id', $marketing_info->screen_id)}}" placeholder="" class="form-control @error('screen_id') is-invalid @enderror" required="">
                     @error('screen_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">وقت انتهاء الهولد <span class="text-danger">*</span></label>
                     <input type="text" name="hold_finish_time" value="{{old('hold_finish_time', $marketing_info->hold_finish_time)}}" placeholder="" class="form-control @error('hold_finish_time') is-invalid @enderror" required="">
                     @error('hold_finish_time') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

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
