@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب خزينة")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> بحث كشف حساب خزينة </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.acc_all')}}" method="POST" autocomplete="off">
               @csrf
         
                
               <p>
                    الخزنة
                     </p>
                      <select class="form-control" name="storage_id" id="storage_id" style="text-align:right;" required>
                                                    <option value="0">كشف حساب عام</option>

                  @foreach($storages as $storage)
                  <option value="{{$storage->id}}">{{$storage->name}}</option>
                  @endforeach
               </select>
                  
                <br>
               <center>
                 <p>
                لفرز حساب تاريخ الي تاريخ يرجي ادخال تاريخين وليس تاريخ واحد
                </p>
                </center>
                <div class="row">
               <div class="col-6 mb-3">
                  <p>
                     فرز تاريخ من
                  </p>
                  <input type="date" name="date_from" class="form-control" value="">
               </div>
               <div class="col-6">
                  <p>
                     فرز تاريخ الي
                  </p>
                  <input type="date" name="date_to" class="form-control" value="">
               </div>
            </div>
                
                
                <p>
           نوع العملية    
                </p>
                <select class="form-select" name="transaction_type">
                
                    <option value="">الكل</option>
                    <option value="1">الفواتير</option>
                    <option value="2">السندات (سند بيع / سند قبض)</option>
                </select>
                                <br>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   بحث كشف حساب  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
