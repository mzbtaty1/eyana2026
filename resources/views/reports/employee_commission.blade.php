@extends('layouts.app')
@section('content')
@section('title' , 'تقرير عمولات الموظفين')
@php($money = fn ($v) => \App\Services\InvoiceFullReport::money($v))
@php($num = fn ($v) => \App\Services\InvoiceFullReport::num($v))
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="تقرير عمولات الموظفين"></x-page-header>
         <div class="card-body">
            @include('components.flash-messages')
            <form method="GET" action="{{route('site.employee_commission_report')}}" class="row g-3 align-items-end mb-4">
               @if($all)
               <div class="col-md-4">
                  <label class="form-label" for="employee_id">الموظف</label>
                  <select class="form-select" name="employee_id" id="employee_id">
                     <option value="">كل الموظفين</option>
                     @foreach($employees as $e)
                     <option value="{{$e->id}}" @selected((string) $employee_id === (string) $e->id)>{{$e->name}}</option>
                     @endforeach
                  </select>
               </div>
               @endif
               <div class="col-md-3">
                  <label class="form-label" for="date_from">من تاريخ</label>
                  <input type="date" class="form-control" name="date_from" id="date_from" value="{{$date_from}}" required>
               </div>
               <div class="col-md-3">
                  <label class="form-label" for="date_to">إلى تاريخ</label>
                  <input type="date" class="form-control" name="date_to" id="date_to" value="{{$date_to}}" required>
               </div>
               <div class="col-md-2 d-grid">
                  <button class="btn btn-primary"><i class="ri-search-line align-middle"></i> عرض التقرير</button>
               </div>
            </form>

            @if($report)
            @php($results = $report->results())
            <p class="text-muted">
               الفترة (تاريخ العملية): {{$report->from}} : {{$report->to}}
               -- الربح = المبيعات - التكلفة + صافي المرتجعات. العمولة على إجمالي ربح الموظف في الفترة.
               الفاتورة المشتركة تقسم بين الموظفين حسب نسب المشاركة.
            </p>
            @if(count($results) === 0)
            <div class="alert alert-info">لا توجد عمليات في هذه الفترة.</div>
            @else
            <div class="table-responsive">
               <table class="table table-bordered table-striped align-middle text-nowrap" id="commission-report">
                  <thead class="table-light">
                     <tr>
                        <th>الموظف</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th>إجمالي المبيعات</th>
                        <th>إجمالي التكلفة</th>
                        <th>صافي المرتجعات</th>
                        <th>إجمالي الربح</th>
                        <th>طريقة العمولة</th>
                        <th>جدول الشرائح</th>
                        <th>الشريحة المطبقة</th>
                        <th>نسبة العمولة</th>
                        <th>قيمة العمولة</th>
                        <th>صافي ربح الشركة بعد العمولة</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach($results as $r)
                     <tr>
                        <td>{{$r['employee']}}</td>
                        <td>{{$report->from}}</td>
                        <td>{{$report->to}}</td>
                        <td>{{$money($r['sales'])}}</td>
                        <td>{{$money($r['cost'])}}</td>
                        <td>{{$money($r['refund_net'])}}</td>
                        <td><strong>{{$money($r['profit'])}}</strong></td>
                        <td>{{$r['method_label']}}</td>
                        <td>
                           {{$r['tier_table'] ?? '--'}}
                           @if($r['tier_table_active'] === false)
                           <span class="badge bg-warning my_badge">معطل</span>
                           @endif
                        </td>
                        <td>
                           @if($r['tier'])
                           {{$money($r['tier']['from'])}} - {{$r['tier']['to'] === null ? 'فأكثر' : $money($r['tier']['to'])}}
                           @else
                           --
                           @endif
                        </td>
                        <td>{{$num($r['rate'])}}%</td>
                        <td><strong>{{$money($r['commission'])}}</strong></td>
                        <td>{{$money($r['net_company_profit'])}}</td>
                     </tr>
                     @endforeach
                  </tbody>
                  @if(count($results) > 1)
                  @php($t = $report->totals())
                  <tfoot class="table-light fw-bold">
                     <tr>
                        <td colspan="3">الإجمالي</td>
                        <td>{{$money($t['sales'])}}</td>
                        <td>{{$money($t['cost'])}}</td>
                        <td>{{$money($t['refund_net'])}}</td>
                        <td>{{$money($t['profit'])}}</td>
                        <td colspan="4"></td>
                        <td>{{$money($t['commission'])}}</td>
                        <td>{{$money($t['net_company_profit'])}}</td>
                     </tr>
                  </tfoot>
                  @endif
               </table>
            </div>
            @endif
            @endif
         </div>
      </div>
   </div>
</div>
@endsection
