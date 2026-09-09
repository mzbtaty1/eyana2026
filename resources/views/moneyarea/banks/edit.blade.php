@extends('layouts.app')
@section('content')
@section('title' , "تعديل بيانات البنك")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل بيانات البنك : {{$bank_info->bank_name}}</h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.banks_update')}}" method="POST" autocomplete="off">
               @csrf
         <input type="hidden" name="id" value="{{$bank_info->id}}">
                
               <p>
                    اسم البنك
                <rtag>(*)</rtag>    
                </p>
                     <input type="text" name="bank_name" value="{{$bank_info->bank_name}}" placeholder="اسم البنك" class="form-control" style="text-align:right;" required="">
                  
                <br>
                 <p>
                    رصيد البنك
                <rtag>(*)</rtag>    
                </p>
                     <input type="text" name="bank_balance" value="{{$bank_info->bank_balance}}" placeholder="رصيد البنك" class="form-control" style="text-align:right;" required="">
                  
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   تعديل بيانات البنك  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
