@extends('layouts.app')
@section('content')
@section('title' , "اضافة قائمة اسعار تسويق جديدة")
<x-page-header title="اضافة قائمة اسعار تسويق جديدة">
   <a href="{{route('site.marketing_titles')}}">
   <button class="btn btn-primary">
       <i class="ri-eye-line"></i>
       كل البيانات (العناوين)
       </button>
   </a>
</x-page-header>
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <form action="{{route('site.marketing_prices_store')}}" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')
               <div class="mb-3">
                  <label class="form-label">البيان<br>جهة الاستلام / الوصول / خط الطيران <span class="text-danger">*</span></label>
                  <select name="title" class="form-control @error('title') is-invalid @enderror" required="">
                  @foreach($titles as $title)
                      <option value="{{$title->id}}" @if((string) old('title') === (string) $title->id) selected @endif>{{$title->title}}</option>
                      @endforeach
                  </select>
                  @error('title') <div class="invalid-feedback">{{$message}}</div> @enderror
               </div>

               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label">تاريخ السفر <span class="text-danger">*</span></label>
                     <input type="date" name="travel_date" value="{{old('travel_date')}}" placeholder="تاريخ السفر " class="form-control @error('travel_date') is-invalid @enderror" required="">
                     @error('travel_date') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">موعد الاقلاع <span class="text-danger">*</span></label>
                     <input type="text" name="time_departure" value="{{old('time_departure')}}" placeholder="موعد الاقلاع" class="form-control @error('time_departure') is-invalid @enderror" required="">
                     @error('time_departure') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">السعر <span class="text-danger">*</span></label>
                     <input type="tel" name="total_price" value="{{old('total_price')}}" placeholder="السعر" class="form-control @error('total_price') is-invalid @enderror" required="">
                     @error('total_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">التكلفة <span class="text-danger">*</span></label>
                     <input type="tel" name="cost_price" value="{{old('cost_price')}}" placeholder="التكلفة" class="form-control @error('cost_price') is-invalid @enderror" required="">
                     @error('cost_price') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الحجز <span class="text-danger">*</span></label>
                     <input type="text" name="booking_id" value="{{old('booking_id')}}" placeholder="" class="form-control @error('booking_id') is-invalid @enderror" required="">
                     @error('booking_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">رقم الشاشة <span class="text-danger">*</span></label>
                     <input type="text" name="screen_id" value="{{old('screen_id')}}" placeholder="" class="form-control @error('screen_id') is-invalid @enderror" required="">
                     @error('screen_id') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">وقت انتهاء الهولد <span class="text-danger">*</span></label>
                     <div class="d-flex gap-2">
                        <input type="date" name="hold_finish_time" value="{{old('hold_finish_time')}}" placeholder="" class="form-control @error('hold_finish_time') is-invalid @enderror" required="">
                        <input type="time" name="hold_finish_time2" value="{{old('hold_finish_time2')}}" placeholder="" class="form-control @error('hold_finish_time2') is-invalid @enderror" required="">
                     </div>
                     @error('hold_finish_time') <div class="invalid-feedback d-block">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">عدد الامكان <span class="text-danger">*</span></label>
                     <input type="tel" name="places_available" value="{{old('places_available')}}" placeholder="" class="form-control @error('places_available') is-invalid @enderror" required="">
                     @error('places_available') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label">اسم الموظف <span class="text-danger">*</span></label>
                     <input type="text" name="added_by" value="{{old('added_by', Auth::user()->name)}}" placeholder="" class="form-control @error('added_by') is-invalid @enderror" required="">
                     @error('added_by') <div class="invalid-feedback">{{$message}}</div> @enderror
                  </div>
               </div>

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
