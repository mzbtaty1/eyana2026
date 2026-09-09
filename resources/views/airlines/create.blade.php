@extends('layouts.app')
@section('content')
@section('title' , "اضافة خط طيران جديد")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اضافة خط طيران جديد </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.airlines_save')}}" method="POST" autocomplete="off">
               @csrf
         
                
               <p>
                    اسم خط الطيران
                     </p>
                     <input type="text" name="airline_name" value="" placeholder="اسم خط الطيران" class="form-control" style="text-align:right;" required="">
                  
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اضافة خط طيران جديد  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
