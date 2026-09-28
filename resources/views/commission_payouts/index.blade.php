@extends('layouts.app')
@section('content')
@section('title' , 'صرف عمولات الموظفين')
@php($money = fn ($v) => \App\Services\InvoiceFullReport::money($v))
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="صرف عمولات الموظفين"></x-page-header>
         <div class="card-body">
            @include('components.flash-messages')
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

            {{-- a new / existing period: the Step D commission for an employee and dates --}}
            <form method="GET" action="{{route('site.commission_payouts_preview')}}" class="row g-3 align-items-end mb-4">
               <div class="col-md-4">
                  <label class="form-label" for="employee_id">الموظف</label>
                  <select class="form-select" name="employee_id" id="employee_id" required>
                     <option value="">-- اختر الموظف --</option>
                     @foreach($employees as $e)
                     <option value="{{$e->id}}" @selected((string) $employee_id === (string) $e->id)>{{$e->name}}</option>
                     @endforeach
                  </select>
               </div>
               <div class="col-md-3">
                  <label class="form-label" for="from">من تاريخ</label>
                  <input type="date" class="form-control" name="from" id="from" min="{{\App\Services\CommissionPayouts::START_DATE}}" required>
               </div>
               <div class="col-md-3">
                  <label class="form-label" for="to">إلى تاريخ</label>
                  <input type="date" class="form-control" name="to" id="to" required>
               </div>
               <div class="col-md-2 d-grid">
                  <button class="btn btn-primary"><i class="ri-calculator-line align-middle"></i> حساب العمولة</button>
               </div>
            </form>
            <p class="text-muted small">صرف العمولات من خلال النظام يبدأ من {{\App\Services\CommissionPayouts::START_DATE}}. العمولة تحسب دائما من تقرير عمولات الموظفين (إعدادات عمولة الموظف على إجمالي ربحه في الفترة).</p>

            <div class="table-responsive">
               <table class="table table-bordered table-striped align-middle text-nowrap" id="commission-periods">
                  <thead class="table-light">
                     <tr>
                        <th>الموظف</th>
                        <th>الفترة</th>
                        <th>العمولة المعتمدة</th>
                        <th>المصروف</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                        <th>--</th>
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($periods as $p)
                     <tr>
                        <td>{{$p->employee->name ?? '#' . $p->employee_id}}</td>
                        <td>{{$p->label()}}</td>
                        <td>{{$money($p->commission_amount)}}</td>
                        <td>{{$money($p->paid())}}</td>
                        <td><strong>{{$money($p->remaining())}}</strong></td>
                        <td>
                           @if($p->isClosed())
                           <span class="badge bg-success my_badge">مغلقة (مصروفة بالكامل)</span>
                           @elseif($p->paid() > 0)
                           <span class="badge bg-warning my_badge">مفتوحة (صرف جزئي)</span>
                           @else
                           <span class="badge bg-info my_badge">مفتوحة</span>
                           @endif
                        </td>
                        <td>
                           <a href="{{route('site.commission_payouts_preview', ['employee_id' => $p->employee_id, 'from' => $p->period_from->format('Y-m-d'), 'to' => $p->period_to->format('Y-m-d')])}}" class="btn btn-soft-secondary btn-sm">
                              <i class="ri-eye-line align-middle"></i> عرض
                           </a>
                        </td>
                     </tr>
                     @empty
                     <tr><td colspan="7" class="text-center">لا توجد فترات عمولة بعد</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
@endsection
