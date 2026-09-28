@extends('layouts.app')
@section('content')
@section('title' , 'لوحة عمولات الموظفين')
@php
   $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
   $f = $dashboard->f;
   $rows = $dashboard->rows();
   $t = $dashboard->totals();
   $query = array_filter($f, fn ($v) => $v !== null);
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="لوحة عمولات الموظفين">
            <a href="{{route('site.commission_payouts_print', $query)}}" target="_blank" class="btn btn-outline-dark btn-sm"><i class="ri-printer-line"></i> طباعة</a>
            <a href="{{route('site.commission_payouts_excel', $query)}}" class="btn btn-outline-success btn-sm"><i class="ri-file-excel-2-line"></i> Excel</a>
         </x-page-header>
         <div class="card-body">
            @include('components.flash-messages')
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

            {{-- filters --}}
            <form method="GET" action="{{route('site.commission_payouts')}}" class="row g-2 align-items-end mb-3" id="commission-filters">
               <div class="col-md-3">
                  <label class="form-label mb-1">الموظف</label>
                  <select class="form-select" name="employee_id">
                     <option value="">كل الموظفين</option>
                     @foreach($employees as $e)<option value="{{$e->id}}" @selected((string) $f['employee_id'] === (string) $e->id)>{{$e->name}}</option>@endforeach
                  </select>
               </div>
               <div class="col-md-2"><label class="form-label mb-1">الفترات من</label><input type="date" class="form-control" name="from" value="{{$f['from']}}"></div>
               <div class="col-md-2"><label class="form-label mb-1">إلى</label><input type="date" class="form-control" name="to" value="{{$f['to']}}"></div>
               <div class="col-md-2">
                  <label class="form-label mb-1">الحالة</label>
                  <select class="form-select" name="status">
                     <option value="">الكل</option>
                     @foreach(\App\Services\CommissionDashboard::STATUSES as $k => $label)<option value="{{$k}}" @selected($f['status'] === $k)>{{$label}}</option>@endforeach
                  </select>
               </div>
               <div class="col-md-3 d-flex gap-2">
                  <button class="btn btn-primary"><i class="ri-filter-line"></i> عرض</button>
                  <a href="{{route('site.commission_payouts')}}" class="btn btn-light">مسح</a>
               </div>
            </form>

            <div class="row g-2 mb-3">
               @foreach(['commission' => 'العمولة وقت فتح الفترات', 'current' => 'العمولة الحالية', 'paid' => 'المصروف', 'remaining' => 'المتبقي', 'difference' => 'الفرق'] as $k => $label)
               <div class="col-6 col-md"><div class="border rounded p-2 h-100"><small class="text-muted">{{$label}}</small><br><strong data-total="{{$k}}">{{$money($t[$k])}}</strong></div></div>
               @endforeach
            </div>

            <div class="table-responsive">
               <table class="table table-bordered table-striped align-middle text-nowrap" id="commission-periods">
                  <thead class="table-light">
                     <tr>
                        <th>الموظف</th><th>الفترة</th><th>العمولة وقت فتح الفترة</th><th>العمولة الحالية</th><th>الفرق</th>
                        <th>المصروف</th><th>المتبقي</th><th>الحالة</th><th>آخر صرف</th><th>--</th>
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($rows as $r)
                     <tr @if($r['differs']) class="table-warning" @endif>
                        <td>{{$r['employee']}}</td>
                        <td>{{$r['from']}} : {{$r['to']}}</td>
                        <td>{{$money($r['commission'])}}</td>
                        <td>{{$money($r['current'])}}</td>
                        <td>@if($r['differs'])<strong class="text-danger">{{$money($r['difference'])}}</strong>@else — @endif</td>
                        <td>{{$money($r['paid'])}}</td>
                        <td><strong>{{$money($r['remaining'])}}</strong></td>
                        <td>
                           <span class="badge {{$r['status'] === 'closed' ? 'bg-success' : ($r['paid'] > 0 ? 'bg-warning' : 'bg-info')}} my_badge">{{$r['status_label']}}</span>
                           @if($r['reversals'])<span class="badge bg-secondary my_badge">{{$r['reversals']}} عكس</span>@endif
                        </td>
                        <td>{{$r['last_paid_date'] ?? '—'}}</td>
                        <td>
                           <a href="{{route('site.commission_payouts_preview', ['employee_id' => $r['period']->employee_id, 'from' => $r['from'], 'to' => $r['to']])}}" class="btn btn-soft-secondary btn-sm">
                              <i class="ri-eye-line align-middle"></i> التفاصيل
                           </a>
                        </td>
                     </tr>
                     @empty
                     <tr><td colspan="10" class="text-center">لا توجد فترات عمولة</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>

            <hr class="my-4">
            {{-- a new / existing period: the Step D commission for an employee and dates --}}
            <h6>فتح / صرف فترة عمولة</h6>
            <form method="GET" action="{{route('site.commission_payouts_preview')}}" class="row g-3 align-items-end">
               <div class="col-md-4">
                  <label class="form-label" for="employee_id">الموظف</label>
                  <select class="form-select" name="employee_id" id="employee_id" required>
                     <option value="">-- اختر الموظف --</option>
                     @foreach($employees as $e)<option value="{{$e->id}}">{{$e->name}}</option>@endforeach
                  </select>
               </div>
               <div class="col-md-3"><label class="form-label" for="from">من تاريخ</label><input type="date" class="form-control" name="from" id="from" min="{{\App\Services\CommissionPayouts::START_DATE}}" required></div>
               <div class="col-md-3"><label class="form-label" for="to">إلى تاريخ</label><input type="date" class="form-control" name="to" id="to" required></div>
               <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="ri-calculator-line align-middle"></i> حساب العمولة</button></div>
            </form>
            <p class="text-muted small mt-2">صرف العمولات من خلال النظام يبدأ من {{\App\Services\CommissionPayouts::START_DATE}}، والصرف متاح بعد انتهاء الفترة. العمولة تحسب دائما من تقرير عمولات الموظفين؛ أي فرق بين العمولة الحالية والعمولة وقت فتح الفترة يظهر ولا يعدل تلقائيا.</p>
         </div>
      </div>
   </div>
</div>
@endsection
