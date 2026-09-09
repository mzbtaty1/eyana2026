@extends('layouts.app')
@section('title' , 'الرئيسية')
@section('content')
<div class="h-100">
   <div class="row mb-3 pb-1">
      <div class="col-12">
         <div class="d-flex align-items-lg-center flex-lg-row flex-column">
            <div class="flex-grow-1">
               <h4 class="fs-16 mb-1">
                  مرحباً بك يا : {{ Auth::user()->name }}!
               </h4>
               <p class="text-muted mb-0">نقدم لك تقارير عن التذاكر والارباح وملخص لكل ذلك</p>
            </div>
            <div class="mt-3 mt-lg-0">
               <form action="javascript:void(0);">
                  <div class="row g-3 mb-0 align-items-center">
                     <div class="col-auto">
                        <a href="{{ route('site.invoices_create') }}">
                           <button type="button" class="btn btn-soft-success material-shadow-none">
                              <i class="ri-add-circle-line align-middle me-1"></i> اضافة فاتورة
                           </button>
                        </a>
                     </div>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </div>

   @if(count($alerts) !== 0)
   <div class="alert alert-primary">
      <b>يوجد تنبيهات من الادارة : </b>
      <ul>
         @foreach($alerts as $alert)
         <li>{{ $alert->alert_txt }}</li>
         @endforeach
      </ul>
   </div>
   @endif

   <div class="row">
      <div class="col-sm">
         <div class="card card-animate">
            <div class="card-body">
               <div class="d-flex align-items-center">
                  <div class="flex-grow-1 overflow-hidden">
                     <p class="text-uppercase fw-medium text-muted text-truncate mb-0">الفواتير</p>
                  </div>
               </div>
               <div class="d-flex align-items-end justify-content-between mt-4">
                  <div>
                     <h4 class="fs-22 fw-semibold ff-secondary mb-4">{{ $invoices }} فواتير</h4>
                  </div>
                  <div class="avatar-sm flex-shrink-0">
                     <span class="avatar-title bg-success-subtle rounded fs-3">
                        <i class="ri-bill-line text-success"></i>
                     </span>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <div class="col-sm">
         <div class="card card-animate">
            <div class="card-body">
               <div class="d-flex align-items-center">
                  <div class="flex-grow-1 overflow-hidden">
                     <p class="text-uppercase fw-medium text-muted text-truncate mb-0">السندات</p>
                  </div>
               </div>
               <div class="d-flex align-items-end justify-content-between mt-4">
                  <div>
                     <h4 class="fs-22 fw-semibold ff-secondary mb-4">{{ $bonds }} سندات</h4>
                  </div>
                  <div class="avatar-sm flex-shrink-0">
                     <span class="avatar-title bg-info-subtle rounded fs-3">
                        <i class="ri-wallet-2-line text-info"></i>
                     </span>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div class="row">
      <div class="col-sm">
         <div class="card" style="padding: 11px;">
            <br>
            <h5 class="container">ملخص حسابات الموردين المختارة للعرض</h5>
            <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th style="text-align: right;">#</th>
                     <th style="text-align: right;">اسم الحساب</th>
                     <th style="text-align: right;" class="bg-danger text-white">رصيد</th>
                  </tr>
               </thead>
               <tbody>
                 @php $x = 1; @endphp
@foreach($suppliers as $supplier)
    <tr>
        <td style="text-align:right;">{{ $x }}</td>
        <td style="text-align:right;">{{ $supplier->name }}</td>
        <td style="text-align:right;">
            {{ number_format($suppliersBalances[$supplier->id] ?? 0, 2) }}
        </td>
    </tr>
    @php $x++; @endphp
@endforeach

               </tbody>
            </table>
         </div>
      </div>

      <div class="col-sm">
         <div class="card" style="padding: 11px;">
            <br>
            <h5 class="container">تنبية ارصده الموردين</h5>
            <table class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th style="text-align: right;">#</th>
                     <th style="text-align: right;">اسم الحساب</th>
                     <th style="text-align: right;">رصيد</th>
                  </tr>
               </thead>
               <tbody>
                  @php $x = 1; @endphp
                  @foreach($allSuppliers as $supplier)
                  @php
                     $trsnactions = \App\Models\AccountStatement::where('supp_client_id', $supplier->id)
                         ->where('is_storage', '!=', 1)
                         ->get();

                     $total_credit = $trsnactions->sum('credit_balance');
                     $total_debit = $trsnactions->sum('debit_balance');
                     $balance = $total_debit - $total_credit;
                     $show_alert = $supplier->limit_balance > 0 && $balance > $supplier->limit_balance;
                  @endphp

                  @if($show_alert)
                  <tr>
                     <td style="text-align:right;" class="bg-danger text-white">{{ $x }}</td>
                     <td style="text-align:right;" class="bg-danger text-white">
                        {{ $supplier->name }} <br>
                        الحد المسموح : {{ number_format($supplier->limit_balance, 2) }} ج.م
                     </td>
                     <td style="text-align:right;" class="bg-danger text-white">{{ number_format($balance, 2) }}</td>
                  </tr>
                  @endif
                  @php $x++; @endphp
                  @endforeach
               </tbody>
            </table>
         </div>
      </div>
   </div>
</div>

<script>
   let table = new DataTable('#InvoicesTable', {
       ordering: false,
       columnDefs: [
           { target: 10, visible: false },
           { target: 11, visible: false },
           { target: 13, visible: false },
       ],
       responsive: true,
       layout: {
           topStart: {
               buttons: ['colvis']
           }
       },
   });
</script>
@endsection
