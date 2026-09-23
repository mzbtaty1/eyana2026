@extends('layouts.app')
@section('content')
@section('title' , "تعديل الاشعار")
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <x-page-header title="تعديل الاشعار" />
<div class="card-body">
            <form action="{{route('site.alerts_update')}}" method="POST" autocomplete="off">
               @csrf
               <input type="hidden" name="id" value="{{$alert->id}}">
@include('components.flash-messages')
               <p>
                    نص الاشعار
                </p>
                <textarea class="form-control @error('alert_txt') is-invalid @enderror" name="alert_txt" placeholder="ادخل نص الاشعار" rows="5" required>{{old('alert_txt', $alert->alert_txt)}}</textarea>
                @error('alert_txt') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   حفظ التعديلات
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
