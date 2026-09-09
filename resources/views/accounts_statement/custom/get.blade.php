@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب شركات (مخصص)")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> بحث كشف حساب شركات (مخصص) </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.accounts_statement_custom_view')}}" method="POST" autocomplete="off">
               @csrf
         
                
               <p>
                    المورد / المستفيد
                     </p>
<select class="form-control" name="report_type" id="report_type" style="text-align:right;" required>
<option value="0">كشف حساب موردين و عملاء</option>
<option value="1">موردين فقط</option>
<option value="2">عملاء فقط</option>
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
                
                <br>
                <div class="form-check">
  <input class="form-check-input" name="check_get_zero" type="checkbox" value="1" id="flexCheckDefault">
  <label class="form-check-label" for="flexCheckDefault">
    اظهار الارصدة الصفرية
  </label>
</div>

                
               <br>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   بحث كشف حساب شركات (مخصص)  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
