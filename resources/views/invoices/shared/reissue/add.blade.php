@extends('layouts.app')
@section('content')
@section('title' , "اعادة اصادر / تعديل فاتورة")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اعادة اصادر / تعديل فاتورة </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.invoices_reissue')}}" method="POST" autocomplete="off">
               @csrf
         
                
               <p>
                    معرف الفاتورة <br>
                   مثال : FLY-A1,FLY-A2
                     </p>
                     <select class="form-select" name="es_id" id="select_box" required>
                
                         @foreach($invoices as $invoice)
                         <option value="{{$invoice->es_id}}">{{$invoice->es_id}}</option>
                         @endforeach
                         
                </select>
                  
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   اعادة اصادر / تعديل فاتورة  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
