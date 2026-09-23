@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب")
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>


<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- JS for searching -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// .js-example-basic-single declare this class into your select box
$(document).ready(function() {
    $('.js-example-basic-single').select2();
});
</script>

<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> بحث كشف حساب </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.accounts_statement_search')}}" method="POST" autocomplete="off">
               @csrf
         
                
               <p>
                    المورد / المستفيد
                     </p>
<select class="js-example-basic-single" name="invoice_beneficiaries" id="invoice_group_id" style="text-align:right;" required>
                          <option value="0">كشف حساب عام</option>
                  @foreach($suppliers as $supplier)
                  <option value="{{$supplier->id}}">{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @elseif($supplier->acc_type == 2) مورد @else مصروفات @endif</option>
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
 <script src="{{asset('assets/dselect.js')}}"></script>
 

<script type="text/javascript">
    
    
 var invoice_beneficiaries = document.querySelector('#invoice_group_id');
  dselect(invoice_beneficiaries, {
            search: true
        });
</script>
@endsection
