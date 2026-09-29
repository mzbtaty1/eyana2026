@extends('layouts.app')
@section('content')
@section('title' , 'تقرير السياحة الداخلية')
@php
   $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
   $f = $report->f;
   $rows = $report->rows();
   $t = $report->totals();
   $groups = $report->groups();
   $query = array_filter($f, fn ($v) => $v !== null);
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="تقرير السياحة الداخلية">
            <div class="ey-action-group">
               <a href="{{route('site.tourism_report_excel', $query)}}" class="btn btn-outline-success"><i class="ri-file-excel-2-line"></i><span>Excel</span></a>
               <a href="{{route('site.tourism_report_print', $query)}}" target="_blank" class="btn btn-outline-dark"><i class="ri-printer-line"></i><span>طباعة</span></a>
            </div>
         </x-page-header>
         <div class="card-body">
            <form method="GET" action="{{route('site.tourism_report')}}" class="row g-2 align-items-end mb-3">
               <div class="col-md-2"><label class="form-label mb-1">تاريخ الحركة من</label><input type="date" class="form-control" name="date_from" value="{{$f['date_from']}}"></div>
               <div class="col-md-2"><label class="form-label mb-1">إلى</label><input type="date" class="form-control" name="date_to" value="{{$f['date_to']}}"></div>
               <div class="col-md-2"><label class="form-label mb-1">تاريخ الرحلة من</label><input type="date" class="form-control" name="travel_from" value="{{$f['travel_from']}}"></div>
               <div class="col-md-2"><label class="form-label mb-1">إلى</label><input type="date" class="form-control" name="travel_to" value="{{$f['travel_to']}}"></div>
               <div class="col-md-2">
                  <label class="form-label mb-1">الحالة</label>
                  <select class="form-select" name="status"><option value="">الكل</option>@foreach(\App\Models\TourismBooking::STATUSES as $k => $l)<option value="{{$k}}" @selected($f['status'] === $k)>{{$l}}</option>@endforeach</select>
               </div>
               <div class="col-md-2">
                  <label class="form-label mb-1">البرنامج</label>
                  <select class="form-select" name="program_id"><option value="">الكل</option>@foreach($programs as $p)<option value="{{$p->id}}" @selected($f['program_id'] === $p->id)>{{$p->name}}</option>@endforeach</select>
               </div>
               <div class="col-md-2">
                  <label class="form-label mb-1">العميل</label>
                  <select class="form-select" name="customer_id" id="r_customer"><option value="">الكل</option>@foreach($accounts as $a)<option value="{{$a->id}}" @selected($f['customer_id'] === $a->id)>{{$a->name}}</option>@endforeach</select>
               </div>
               <div class="col-md-2">
                  <label class="form-label mb-1">المورد</label>
                  <select class="form-select" name="supplier_id" id="r_supplier"><option value="">الكل</option>@foreach($accounts as $a)<option value="{{$a->id}}" @selected($f['supplier_id'] === $a->id)>{{$a->name}}</option>@endforeach</select>
               </div>
               <div class="col-md-2">
                  <label class="form-label mb-1">نوع الخدمة</label>
                  <select class="form-select" name="service_type"><option value="">الكل</option>@foreach(\App\Models\TourismBookingItem::TYPES as $k => $l)<option value="{{$k}}" @selected($f['service_type'] === $k)>{{$l}}</option>@endforeach</select>
               </div>
               @if($employees->isNotEmpty())
               <div class="col-md-2">
                  <label class="form-label mb-1">الموظف</label>
                  <select class="form-select" name="employee_id"><option value="">الكل</option>@foreach($employees as $e)<option value="{{$e->id}}" @selected($f['employee_id'] === $e->id)>{{$e->name}}</option>@endforeach</select>
               </div>
               @endif
               <div class="col-md-2">
                  <label class="form-label mb-1">التجميع حسب</label>
                  <select class="form-select" name="group_by">@foreach(\App\Services\TourismReport::GROUPS as $k => $l)<option value="{{$k}}" @selected($f['group_by'] === $k)>{{$l}}</option>@endforeach</select>
               </div>
               <div class="col-sm-6 col-md-4 col-xl-2">
                  <div class="ey-filter-actions">
                     <button class="btn btn-primary"><i class="ri-filter-line"></i><span>عرض</span></button>
                     <a href="{{route('site.tourism_report')}}" class="btn btn-light"><i class="ri-refresh-line"></i><span>مسح</span></a>
                  </div>
               </div>
            </form>
            <p class="text-muted small">المبيعات والتكلفة والربح من قيود الحجوزات في كشوف الحساب بتاريخ الحركة (نفس تواريخ العمولة)، والمتبقي على العميل وللموردين هو الرصيد الحالي لكل حجز.</p>

            <div class="row g-2 mb-3">
               @foreach(['bookings' => 'عدد الحجوزات', 'sale' => 'المبيعات', 'cost' => 'التكلفة', 'profit' => 'الربح', 'receivable' => 'المتبقي على العملاء', 'payable' => 'المتبقي للموردين'] as $k => $label)
               <div class="col-6 col-md"><div class="border rounded p-2 h-100"><small class="text-muted">{{$label}}</small><br><strong>{{ $k === 'bookings' ? $t[$k] : $money($t[$k]) }}</strong></div></div>
               @endforeach
            </div>

            <h6>حسب {{\App\Services\TourismReport::GROUPS[$f['group_by']]}}</h6>
            <div class="table-responsive mb-3">
               <table class="table table-bordered table-sm align-middle">
                  <thead class="table-light"><tr><th>{{\App\Services\TourismReport::GROUPS[$f['group_by']]}}</th><th>الحجوزات</th><th>المبيعات</th><th>التكلفة</th><th>الربح</th></tr></thead>
                  <tbody>
                     @forelse($groups as $g)
                     <tr><td>{{$g['label']}}</td><td>{{$g['bookings']}}</td><td>{{$money($g['sale'])}}</td><td>{{$money($g['cost'])}}</td><td><strong>{{$money($g['profit'])}}</strong></td></tr>
                     @empty
                     <tr><td colspan="5" class="text-center">لا توجد بيانات</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>

            <h6>الحجوزات</h6>
            <div class="table-responsive">
               <table class="table table-bordered table-striped align-middle text-nowrap">
                  <thead class="table-light"><tr><th>رقم الحجز</th><th>تاريخ الرحلة</th><th>العميل</th><th>البرنامج</th><th>الموظف</th><th>الحالة</th><th>الأفراد</th><th>المبيعات</th><th>التكلفة</th><th>الربح</th><th>المدفوع</th><th>المتبقي على العميل</th><th>المتبقي للموردين</th></tr></thead>
                  <tbody>
                     @forelse($rows as $r)
                     <tr>
                        <td><a href="{{route('site.tourism_bookings_show', $r['booking']->id)}}">{{$r['booking_no']}}</a></td>
                        <td>{{$r['start_date'] ?? '—'}}</td><td>{{$r['customer']}}</td><td>{{$r['program']}}</td><td>{{$r['employee']}}</td><td>{{$r['status']}}</td><td>{{$r['people']}}</td>
                        <td>{{$money($r['sale'])}}</td><td>{{$money($r['cost'])}}</td><td><strong>{{$money($r['profit'])}}</strong></td>
                        <td>{{$money($r['customer_paid'])}}</td><td>{{$money($r['receivable'])}}</td><td>{{$money($r['payable'])}}</td>
                     </tr>
                     @empty
                     <tr><td colspan="13" class="text-center">لا توجد حجوزات</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
<script src="{{asset('assets/dselect.js')}}"></script>
<script>
   dselect(document.querySelector('#r_customer'), { search: true });
   dselect(document.querySelector('#r_supplier'), { search: true });
</script>
@endsection
