@extends('layouts.app')
@section('content')
<?php
   // one account's figures, exactly as on the list (SupplierDirectory::row)
   $account = $row->account;
   $supplierInvoices = route('site.invoices_full_report', ['supplier_id' => $account->id]);
   $customerInvoices = route('site.invoices_full_report', ['customer_id' => $account->id]);
?>
@section('title' , $account->name)
@include('components.flash-messages')

<x-page-header>
   <x-slot:heading>
      {{$account->name}}
      @if($row->system)
      <span class="badge bg-primary fs-12 align-middle" title="حساب نظام: لا يُحذف ولا يُوقف ولا يتغير نوعه"><i class="ri-lock-2-line"></i> حساب نظام</span>
      @endif
   </x-slot:heading>
   <button type="button" class="btn btn-soft-info" id="openStatement">
      <i class="ri-file-list-3-line align-middle"></i> كشف الحساب
   </button>
   <a href="{{route('site.suppliers_edit', $account->id)}}" class="btn btn-soft-secondary">
      <i class="ri-edit-2-line align-middle"></i> تعديل
   </a>
   <a href="{{route('site.suppliers')}}" class="btn btn-light">
      <i class="ri-arrow-go-forward-line align-middle"></i> قائمة الحسابات
   </a>
</x-page-header>

{{-- «كشف الحساب»: the statement screen takes a POST; opened in a new tab --}}
<form id="statementForm" action="{{route('site.accounts_statement_search')}}" method="POST" target="_blank" style="display:none;">
   @csrf
   <input type="hidden" name="invoice_beneficiaries" value="{{$account->id}}">
</form>

<div class="row g-3 mb-3">
   <div class="col-12 col-md-6 col-xl-3">
      <div class="card h-100 mb-0">
         <div class="card-body">
            <p class="text-muted fs-12 mb-2">الرصيد الحالي</p>
            <h3 class="mb-1" id="ovBalance">
               @if($row->balance > 0)
               <span class="text-success">{{ number_format($row->balance, 2) }}</span>
               <span class="badge bg-success-subtle text-success fs-12 align-middle">لنا</span>
               @elseif($row->balance < 0)
               <span class="text-danger">{{ number_format(-$row->balance, 2) }}</span>
               <span class="badge bg-danger-subtle text-danger fs-12 align-middle">علينا</span>
               @else
               <span class="text-muted">0.00</span>
               <span class="badge bg-light text-muted fs-12 align-middle">صفر</span>
               @endif
            </h3>
            <p class="text-muted fs-11 mb-0">من كشف الحساب: إجمالي المدين − إجمالي الدائن</p>
         </div>
      </div>
   </div>
   <div class="col-6 col-xl-3">
      <div class="card h-100 mb-0">
         <div class="card-body">
            <p class="text-muted fs-12 mb-2">إجمالي المدين</p>
            <h4 class="mb-0" id="ovDebit">{{ number_format($row->total_debit, 2) }}</h4>
         </div>
      </div>
   </div>
   <div class="col-6 col-xl-3">
      <div class="card h-100 mb-0">
         <div class="card-body">
            <p class="text-muted fs-12 mb-2">إجمالي الدائن</p>
            <h4 class="mb-0" id="ovCredit">{{ number_format($row->total_credit, 2) }}</h4>
         </div>
      </div>
   </div>
   <div class="col-12 col-md-6 col-xl-3">
      <div class="card h-100 mb-0">
         <div class="card-body">
            <p class="text-muted fs-12 mb-2">آخر حركة</p>
            <h4 class="mb-0" id="ovLast">
               @if($row->last_activity) {{$row->last_activity}} @else <span class="text-muted">لا توجد حركات</span> @endif
            </h4>
         </div>
      </div>
   </div>
</div>

<div class="row g-3">
   <div class="col-12 col-lg-7">
      <div class="card h-100 mb-0">
         <div class="card-body">
            <h6 class="text-muted fs-13 mb-3">الفواتير</h6>
            <div class="d-flex align-items-center gap-2 mb-3">
               <span class="text-muted fs-12">الدور:</span>
               @if($row->role == 'both')
               <span class="badge bg-warning-subtle text-warning my_badge">مورد وعميل</span>
               @elseif($row->role == 'supplier')
               <span class="badge bg-primary-subtle text-primary my_badge">مورد</span>
               @elseif($row->role == 'customer')
               <span class="badge bg-info-subtle text-info my_badge">عميل</span>
               @else
               <span class="text-muted fs-12">بدون فواتير</span>
               @endif
            </div>
            <div class="row g-2">
               <div class="col-6">
                  <div class="border rounded p-3 h-100">
                     <p class="text-muted fs-12 mb-1">فواتير كمورد</p>
                     <h4 class="mb-2" id="ovAsSupplier">{{ number_format($row->invoices_as_supplier) }}</h4>
                     @if($row->invoices_as_supplier)
                     <a href="{{$supplierInvoices}}" class="fs-12">عرض فواتيره كمورد <i class="ri-arrow-left-line align-middle"></i></a>
                     @endif
                  </div>
               </div>
               <div class="col-6">
                  <div class="border rounded p-3 h-100">
                     <p class="text-muted fs-12 mb-1">فواتير كعميل</p>
                     <h4 class="mb-2" id="ovAsCustomer">{{ number_format($row->invoices_as_customer) }}</h4>
                     @if($row->invoices_as_customer)
                     <a href="{{$customerInvoices}}" class="fs-12">عرض فواتيره كعميل <i class="ri-arrow-left-line align-middle"></i></a>
                     @endif
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
   <div class="col-12 col-lg-5">
      <div class="card h-100 mb-0">
         <div class="card-body">
            <h6 class="text-muted fs-13 mb-3">بيانات الحساب</h6>
            <table class="table table-sm table-borderless mb-0 align-middle">
               <tbody>
                  <tr>
                     <td class="text-muted" style="width: 40%;">الهاتف</td>
                     <td>@if($row->phones) {{ implode(' / ', $row->phones) }} @else <span class="text-muted">—</span> @endif</td>
                  </tr>
                  <tr>
                     <td class="text-muted">التصنيف</td>
                     <td>
                        @if($account->type == 1)
                        <span class="badge bg-dark my_badge">فرد</span>
                        @else
                        <span class="badge bg-primary my_badge">شركة</span>
                        @endif
                     </td>
                  </tr>
                  <tr>
                     <td class="text-muted">الحالة</td>
                     <td>
                        @if($account->status == 1)
                        <span class="badge bg-success my_badge">فعال</span>
                        @else
                        <span class="badge bg-danger my_badge">موقوف</span>
                        @endif
                     </td>
                  </tr>
                  @if($account->email)
                  <tr>
                     <td class="text-muted">الايميل</td>
                     <td>{{$account->email}}</td>
                  </tr>
                  @endif
                  @if($account->address)
                  <tr>
                     <td class="text-muted">العنوان</td>
                     <td>{{$account->address}}</td>
                  </tr>
                  @endif
               </tbody>
            </table>
         </div>
      </div>
   </div>
</div>

<script>
document.getElementById('openStatement').addEventListener('click', function () {
    document.getElementById('statementForm').submit();
});
</script>
@endsection
