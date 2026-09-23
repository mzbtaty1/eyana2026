@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب")
{{--
    This page previously loaded a second, independent Select2 setup (its own
    jQuery, CDN, and .select2() call) on top of dselect() being called on the
    same field -- two competing widgets on one select, which is why the
    dropdown didn't reliably open/search. dselect.js (loaded below, local
    copy, already proven working on invoices/create.blade.php and others) is
    the chosen implementation here; Select2 provided no capability dselect
    doesn't already have, so it's removed rather than reconciled.
--}}

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
<select name="invoice_beneficiaries" id="accounts_statement_beneficiary" style="text-align:right;" required>
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
    
    
 var invoice_beneficiaries = document.querySelector('#accounts_statement_beneficiary');
  dselect(invoice_beneficiaries, {
            search: true
        });
</script>
@endsection
