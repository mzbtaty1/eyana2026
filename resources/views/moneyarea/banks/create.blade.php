@extends('layouts.app')
@section('content')
@section('title' , "اضافة بنك جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <x-page-header title="اضافة بنك جديد" />
<div class="card-body">
            <form action="{{route('site.banks_save')}}" method="POST" autocomplete="off">
               @csrf
@include('components.flash-messages')

               <p>
                    اسم البنك
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="bank_name" value="{{old('bank_name')}}" placeholder="اسم البنك" class="form-control @error('bank_name') is-invalid @enderror" style="text-align:right;" required="">
                     @error('bank_name') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>

                 <p>
                    رصيد البنك
                <span class="text-danger">*</span>
                </p>
                     <input type="text" name="bank_balance" value="{{old('bank_balance')}}" placeholder="رصيد البنك" class="form-control @error('bank_balance') is-invalid @enderror" style="text-align:right;" required="">
                     @error('bank_balance') <div class="invalid-feedback">{{$message}}</div> @enderror
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة بنك جديد  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
