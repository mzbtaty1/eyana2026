@extends('layouts.app')
@section('content')
@section('title' , 'صرف عمولة موظف')
@php
   $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
   $pct = fn ($v) => \App\Services\EmployeeCommissionReport::percent((float) $v) . '%';
   $snap = $period?->calculation;
   $differs = $period && \App\Services\CommissionPayouts::differs($current['commission'], $period->commission_amount);
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="صرف عمولة: {{$employee->name}} ({{$from}} : {{$to}})">
            <a href="{{route('site.commission_payouts')}}" class="btn btn-light btn-sm"><i class="ri-arrow-go-back-line"></i> كل الفترات</a>
         </x-page-header>
         <div class="card-body">
            @include('components.flash-messages')
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

            <div class="table-responsive mb-3">
               <table class="table table-bordered align-middle text-nowrap" id="commission-calculation">
                  <thead class="table-light">
                     <tr>
                        <th></th><th>إجمالي المبيعات</th><th>إجمالي التكلفة</th><th>صافي المرتجعات</th><th>إجمالي الربح</th>
                        <th>طريقة العمولة</th><th>جدول الشرائح</th><th>الشريحة المطبقة</th><th>نسبة العمولة</th><th>قيمة العمولة</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach(array_filter(['العمولة الحالية' => $current, 'العمولة المعتمدة عند فتح الفترة' => $snap]) as $label => $c)
                     <tr @if($differs) class="table-warning" @endif>
                        <th>{{$label}}</th>
                        <td>{{$money($c['sales'])}}</td>
                        <td>{{$money($c['cost'])}}</td>
                        <td>{{$money($c['refund_net'])}}</td>
                        <td>{{$money($c['profit'])}}</td>
                        <td>{{$c['method_label']}}</td>
                        <td>{{$c['tier_table'] ?? '--'}}</td>
                        <td>@if($c['tier']){{$money($c['tier']['from'])}} - {{$c['tier']['to'] === null ? 'فأكثر' : $money($c['tier']['to'])}}@else -- @endif</td>
                        <td>{{$pct($c['rate'])}}</td>
                        <td><strong>{{$money($c['commission'])}}</strong></td>
                     </tr>
                     @endforeach
                  </tbody>
               </table>
            </div>

            @if($differs)
            <div class="alert alert-warning" id="commission-difference">
               <i class="ri-error-warning-line"></i>
               العمولة الحالية ({{$money($current['commission'])}}) تختلف عن العمولة المعتمدة للفترة ({{$money($period->commission_amount)}})،
               الفرق {{$money($current['commission'] - (float) $period->commission_amount)}}. لا يتم إنشاء أي تسوية أو صرف إضافي أو استرداد تلقائيا.
               @if($period->paid() > 0) الصرف متوقف لهذه الفترة حتى تتم مراجعة الفرق. @endif
            </div>
            @endif

            @if($beforeStart)
            <div class="alert alert-danger">صرف العمولات من خلال النظام يبدأ من {{\App\Services\CommissionPayouts::START_DATE}}، ولا يمكن فتح فترة قبل ذلك.</div>
            @elseif(!$period)
               @if($overlap)
               <div class="alert alert-danger">توجد فترة أخرى لنفس الموظف متداخلة مع هذه الفترة ({{$overlap->label()}}).</div>
               @elseif($current['commission'] <= 0)
               <div class="alert alert-info">لا توجد عمولة مستحقة للموظف في هذه الفترة.</div>
               @else
               <form method="POST" action="{{route('site.commission_payouts_store')}}">
                  @csrf
                  <input type="hidden" name="action" value="open">
                  <input type="hidden" name="employee_id" value="{{$employee->id}}">
                  <input type="hidden" name="from" value="{{$from}}">
                  <input type="hidden" name="to" value="{{$to}}">
                  <button class="btn btn-primary"><i class="ri-lock-unlock-line"></i> فتح الفترة واعتماد العمولة ({{$money($current['commission'])}})</button>
               </form>
               @endif
            @else
               <div class="row g-2 mb-3">
                  <div class="col-md-3"><div class="border rounded p-2">العمولة المعتمدة<br><strong>{{$money($period->commission_amount)}}</strong></div></div>
                  <div class="col-md-3"><div class="border rounded p-2">المصروف<br><strong>{{$money($period->paid())}}</strong></div></div>
                  <div class="col-md-3"><div class="border rounded p-2">المتبقي<br><strong id="commission-remaining">{{$money($period->remaining())}}</strong></div></div>
                  <div class="col-md-3"><div class="border rounded p-2">الحالة<br><strong>{{$period->isClosed() ? 'مغلقة (مصروفة بالكامل)' : 'مفتوحة'}}</strong></div></div>
               </div>

               @if($differs && $period->paid() <= 0)
               <form method="POST" action="{{route('site.commission_payouts_store')}}" class="mb-3">
                  @csrf
                  <input type="hidden" name="action" value="refresh">
                  <input type="hidden" name="period_id" value="{{$period->id}}">
                  <button class="btn btn-outline-warning"><i class="ri-refresh-line"></i> اعتماد العمولة الحالية ({{$money($current['commission'])}})</button>
               </form>
               @endif

               @php($completed = \App\Services\CommissionPayouts::completed($period))
               @if(!$completed && !$period->isClosed())
               <div class="alert alert-info" id="commission-not-ended">
                  <i class="ri-time-line"></i> الفترة لم تنته بعد: لا يمكن صرف العمولة إلا بعد {{$period->period_to->format('Y-m-d')}}.
               </div>
               @endif

               @if($completed && !$period->isClosed() && !$differs && $period->remaining() > 0)
               <form method="POST" action="{{route('site.commission_payouts_store')}}" class="row g-3 align-items-end border rounded p-3 mb-3" id="commission-pay-form">
                  @csrf
                  <input type="hidden" name="action" value="pay">
                  <input type="hidden" name="period_id" value="{{$period->id}}">
                  <input type="hidden" name="request_token" value="{{$token}}">
                  <div class="col-md-2">
                     <label class="form-label">المبلغ</label>
                     <input type="number" step="0.01" min="0.01" max="{{$period->remaining()}}" class="form-control" name="amount" value="{{old('amount', $period->remaining())}}" required>
                  </div>
                  <div class="col-md-2">
                     <label class="form-label">تاريخ الصرف</label>
                     <input type="date" class="form-control" name="paid_date" value="{{old('paid_date', date('Y-m-d'))}}" required>
                  </div>
                  <div class="col-md-2">
                     <label class="form-label">الخزنة</label>
                     <select class="form-select" name="storage_id" required>
                        @foreach($storages as $s)<option value="{{$s->id}}">{{$s->name}}</option>@endforeach
                     </select>
                  </div>
                  <div class="col-md-2">
                     <label class="form-label">طريقة الدفع</label>
                     <select class="form-select" name="money_way" id="money_way">
                        <option value="1">نقدي</option>
                        <option value="2">بنك / محفظة</option>
                     </select>
                  </div>
                  <div class="col-md-2" id="bank-box" style="display:none;">
                     <label class="form-label">البنك</label>
                     <select class="form-select" name="bank_id">
                        @foreach($banks as $b)<option value="{{$b->id}}">{{$b->bank_name}}</option>@endforeach
                     </select>
                  </div>
                  <div class="col-md-2">
                     <label class="form-label">مرجع / ملاحظة</label>
                     <input type="text" class="form-control" name="reference" maxlength="200" value="{{old('reference')}}">
                  </div>
                  <div class="col-12">
                     <small class="text-muted d-block mb-2">يسجل سند دفع من الخزنة لحساب «رواتب وسلف وعمولة موظفين». يمكن الصرف على دفعات؛ تغلق الفترة تلقائيا عند صرف المتبقي بالكامل.</small>
                     <button class="btn btn-success"><i class="ri-hand-coin-line"></i> صرف</button>
                  </div>
               </form>
               <script>
               (function () {
                  var way = document.getElementById('money_way');
                  var sync = function () { document.getElementById('bank-box').style.display = way.value === '2' ? '' : 'none'; };
                  way.addEventListener('change', sync); sync();
               })();
               </script>
               @endif

               <h6 class="mt-4">عمليات الصرف</h6>
               <div class="table-responsive">
                  <table class="table table-bordered table-striped align-middle text-nowrap" id="commission-payouts">
                     <thead class="table-light">
                        <tr><th>التاريخ</th><th>المبلغ</th><th>السند</th><th>طريقة الدفع</th><th>بواسطة</th><th>المرجع</th><th>الحالة</th><th>--</th></tr>
                     </thead>
                     <tbody>
                        @forelse($period->payouts as $po)
                        <tr @if($po->isReversed()) class="text-muted" @endif>
                           <td>{{$po->paid_date->format('Y-m-d')}}</td>
                           <td>{{$money($po->amount)}}</td>
                           <td>{{$po->bond->es_id ?? '#' . $po->bond_id}}</td>
                           <td>{{$po->money_way === 2 ? 'بنك / محفظة' : 'نقدي'}}</td>
                           <td>{{$po->creator->name ?? ''}}</td>
                           <td>{{$po->reference}}</td>
                           <td>@if($po->isReversed())<span class="badge bg-secondary my_badge">معكوس</span>@else<span class="badge bg-success my_badge">مصروف</span>@endif</td>
                           <td class="d-flex gap-1">
                              <a href="{{route('site.commission_payouts_receipt', $po->id)}}" target="_blank" class="btn btn-soft-secondary btn-sm"><i class="ri-printer-line"></i></a>
                              @if(!$po->isReversed())
                              <form method="POST" action="{{route('site.commission_payouts_reverse', $po->id)}}" onsubmit="return confirm('عكس عملية الصرف وإعادة فتح الفترة؟');">
                                 @csrf
                                 <button class="btn btn-soft-danger btn-sm"><i class="ri-arrow-go-back-line"></i> عكس</button>
                              </form>
                              @endif
                           </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center">لا توجد عمليات صرف</td></tr>
                        @endforelse
                     </tbody>
                  </table>
               </div>
            @endif
         </div>
      </div>
   </div>
</div>
@endsection
