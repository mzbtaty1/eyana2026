@extends('layouts.app')
@section('content')
@section('title' , 'كشف العمولات')
@php
   $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
   $rows = $dashboard->rows();
   $t = $dashboard->totals();
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="كشف العمولات: {{$employee->name ?? ''}}"></x-page-header>
         <div class="card-body">
            @if($employees->isNotEmpty())
            <form method="GET" action="{{route('site.commission_payouts_mine')}}" class="row g-2 align-items-end mb-3">
               <div class="col-md-4">
                  <label class="form-label mb-1">الموظف</label>
                  <select class="form-select" name="employee_id">
                     @foreach($employees as $e)<option value="{{$e->id}}" @selected($employee && $employee->id === $e->id)>{{$e->name}}</option>@endforeach
                  </select>
               </div>
               <div class="col-md-2"><button class="btn btn-primary">عرض</button></div>
            </form>
            @endif

            <div class="row g-2 mb-3">
               @foreach(['commission' => 'إجمالي العمولة المعتمدة', 'paid' => 'إجمالي المصروف', 'remaining' => 'إجمالي المتبقي'] as $k => $label)
               <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">{{$label}}</small><br><strong data-total="{{$k}}">{{$money($t[$k])}}</strong></div></div>
               @endforeach
            </div>

            @forelse($rows as $r)
            @php($p = $r['period'])
            <div class="border rounded p-3 mb-3" data-period="{{$p->id}}">
               <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                  <h6 class="mb-0">الفترة {{$r['from']}} : {{$r['to']}}</h6>
                  <span class="badge {{$r['status'] === 'closed' ? 'bg-success' : ($r['paid'] > 0 ? 'bg-warning' : 'bg-info')}} my_badge">{{$r['status_label']}}</span>
               </div>
               <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle text-nowrap mb-2">
                     <thead class="table-light"><tr><th>إجمالي الربح</th><th>طريقة العمولة</th><th>نسبة العمولة</th><th>العمولة</th><th>المصروف</th><th>المتبقي</th></tr></thead>
                     <tbody>
                        <tr>
                           <td>{{$money($r['profit'])}}</td>
                           <td>{{$p->calculation['method_label'] ?? ''}}{{ !empty($p->calculation['tier_table']) ? ' - ' . $p->calculation['tier_table'] : '' }}</td>
                           <td>{{\App\Services\EmployeeCommissionReport::percent((float) ($p->calculation['rate'] ?? 0))}}%</td>
                           <td><strong>{{$money($r['commission'])}}</strong></td>
                           <td>{{$money($r['paid'])}}</td>
                           <td>{{$money($r['remaining'])}}</td>
                        </tr>
                     </tbody>
                  </table>
               </div>
               @if($r['differs'])
               <div class="alert alert-warning py-2 small mb-2">
                  عمولة وقت الدفع: {{$money($r['commission'])}} · العمولة الحالية: {{$money($r['current'])}} · الفرق: {{$money($r['difference'])}} (يراجع من الإدارة)
               </div>
               @endif
               @if($p->payouts->isNotEmpty())
               <table class="table table-sm table-striped align-middle text-nowrap mb-0">
                  <thead><tr><th>تاريخ الصرف</th><th>المبلغ</th><th>السند</th><th>الحالة</th></tr></thead>
                  <tbody>
                     @foreach($p->payouts as $po)
                     <tr @if($po->isReversed()) class="text-muted" @endif>
                        <td>{{$po->paid_date->format('Y-m-d')}}</td>
                        <td>{{$money($po->amount)}}</td>
                        <td>{{$po->bond->es_id ?? ''}}</td>
                        <td>@if($po->isReversed())معكوس في {{$po->reversed_at->format('Y-m-d')}}@else مصروف @endif</td>
                     </tr>
                     @endforeach
                  </tbody>
               </table>
               @else
               <small class="text-muted">لم يتم صرف مبالغ لهذه الفترة بعد.</small>
               @endif
            </div>
            @empty
            <div class="alert alert-info">لا توجد فترات عمولة.</div>
            @endforelse
         </div>
      </div>
   </div>
</div>
@endsection
