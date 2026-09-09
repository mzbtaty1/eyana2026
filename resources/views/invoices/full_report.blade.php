@extends('layouts.app')
@section('content')
@section('title' , "بحث تقرير الفواتير")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> بحث تقرير الفواتير </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.invoices_full_report_get')}}" method="POST" autocomplete="off">
               @csrf
         
               
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
           نوع الفواتير    
                </p>
                <select class="form-select" name="invoice_section">
                
                                      <option value="">الكل</option>
                                      <option value="1">فواتير الطيران</option>
                  <option value="2">فواتير تأشيرات</option>
                  <option value="3">فواتير سياحه داخليه</option>
                  <option value="4">فواتير سياحه خارجيه</option>
                  <option value="5">فواتير سياحه دينيه</option>
                  <option value="6">فواتير تأمينات السفر</option>
                  <option value="7">فواتير تحاليل السفر</option>
                  <option value="8">فواتير نقل سياحى</option>
                </select>
                                <br>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   بحث تقرير الفواتير  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
