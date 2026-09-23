@extends('layouts.app')
@section('title' , 'الرئيسية')
@section('content')
<div class="h-100">
   <div class="row mb-3 pb-1">
      <div class="col-12">
         <div class="d-flex align-items-lg-center flex-lg-row flex-column">
            <div class="flex-grow-1">
               <h4 class="fs-20 fw-bold mb-1">
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

   @php
      // View-layer aggregates only -- both derived from data the controller
      // already fetched and passed ($suppliersBalances, $allSuppliers), no
      // new queries. Mirrors the exact same over-limit condition the alert
      // table below already uses, so the count always matches what it shows.
      $totalSuppliersBalance = collect($suppliersBalances)->sum();
      $overLimitCount = $allSuppliers->filter(function ($supplier) use ($suppliersBalances) {
         $balance = $suppliersBalances[$supplier->id] ?? 0;
         return $supplier->limit_balance > 0 && $balance > $supplier->limit_balance;
      })->count();
   @endphp
   <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3">
      <div class="col">
         <div class="card card-animate mb-0 h-100 border-start border-3 border-success">
            <div class="card-body">
               <div class="d-flex align-items-center">
                  <div class="flex-grow-1 overflow-hidden">
                     <p class="text-uppercase fw-medium text-muted text-truncate mb-0">الفواتير</p>
                  </div>
               </div>
               <div class="d-flex align-items-end justify-content-between mt-4">
                  <div>
                     <h3 class="fs-24 fw-bold mb-0">{{ $invoices }}</h3>
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

      <div class="col">
         <div class="card card-animate mb-0 h-100 border-start border-3 border-info">
            <div class="card-body">
               <div class="d-flex align-items-center">
                  <div class="flex-grow-1 overflow-hidden">
                     <p class="text-uppercase fw-medium text-muted text-truncate mb-0">السندات</p>
                  </div>
               </div>
               <div class="d-flex align-items-end justify-content-between mt-4">
                  <div>
                     <h3 class="fs-24 fw-bold mb-0">{{ $bonds }}</h3>
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

      <div class="col">
         <div class="card card-animate mb-0 h-100 border-start border-3 border-primary">
            <div class="card-body">
               <div class="d-flex align-items-center">
                  <div class="flex-grow-1 overflow-hidden">
                     <p class="text-uppercase fw-medium text-muted text-truncate mb-0">اجمالي أرصدة الموردين</p>
                  </div>
               </div>
               <div class="d-flex align-items-end justify-content-between mt-4">
                  <div>
                     <h3 class="fs-24 fw-bold mb-0">{{ number_format($totalSuppliersBalance, 2) }}</h3>
                  </div>
                  <div class="avatar-sm flex-shrink-0">
                     <span class="avatar-title bg-primary-subtle rounded fs-3">
                        <i class="ri-exchange-funds-line text-primary"></i>
                     </span>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <div class="col">
         <div class="card card-animate mb-0 h-100 border-start border-3 border-danger">
            <div class="card-body">
               <div class="d-flex align-items-center">
                  <div class="flex-grow-1 overflow-hidden">
                     <p class="text-uppercase fw-medium text-muted text-truncate mb-0">موردين تجاوزوا الحد الائتماني</p>
                  </div>
               </div>
               <div class="d-flex align-items-end justify-content-between mt-4">
                  <div>
                     <h3 class="fs-24 fw-bold mb-0">{{ $overLimitCount }}</h3>
                  </div>
                  <div class="avatar-sm flex-shrink-0">
                     <span class="avatar-title bg-danger-subtle rounded fs-3">
                        <i class="ri-alert-line text-danger"></i>
                     </span>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div class="row g-3 mt-1">
      <div class="col-lg-6">
         <div class="card mb-0 h-100">
            <div class="card-header d-flex align-items-center gap-2">
               <i class="ri-file-list-3-line fs-16 text-primary"></i>
               <h5 class="card-title mb-0 flex-grow-1">ملخص حسابات الموردين المختارة للعرض</h5>
            </div>
            <div class="card-body">
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
      </div>

      <div class="col-lg-6">
         <div class="card mb-0 h-100">
            <div class="card-header d-flex align-items-center gap-2">
               <i class="ri-alert-line fs-16 text-danger"></i>
               <h5 class="card-title mb-0 flex-grow-1">تنبية ارصده الموردين</h5>
            </div>
            <div class="card-body">
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
                        $balance = $suppliersBalances[$supplier->id] ?? 0;
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
</div>

<script>
   let table = new DataTable('#InvoicesTable', {
       // This table has only 3 columns (#, اسم الحساب, رصيد). The columnDefs
       // below used to target column indices 10/11/13, which don't exist here
       // (leftover from a much wider table elsewhere in the app) -- DataTables
       // built a malformed column object for those out-of-range targets,
       // throwing "col.fnGetData is not a function" on every dashboard load.
       ordering: false,
       responsive: true,
       layout: {
           topStart: {
               buttons: ['colvis']
           }
       },
   });
</script>
@endsection
