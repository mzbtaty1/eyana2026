@extends('layouts.app')
@section('content')
@section('title' , 'سجل صرف عمولاتي')
@php($money = fn ($v) => \App\Services\InvoiceFullReport::money($v))
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="سجل صرف عمولاتي"></x-page-header>
         <div class="card-body">
            <div class="table-responsive">
               <table class="table table-bordered table-striped align-middle text-nowrap" id="my-commission-periods">
                  <thead class="table-light">
                     <tr><th>الفترة</th><th>العمولة المعتمدة</th><th>المصروف</th><th>المتبقي</th><th>الحالة</th><th>عمليات الصرف</th></tr>
                  </thead>
                  <tbody>
                     @forelse($periods as $p)
                     <tr>
                        <td>{{$p->label()}}</td>
                        <td>{{$money($p->commission_amount)}}</td>
                        <td>{{$money($p->paid())}}</td>
                        <td>{{$money($p->remaining())}}</td>
                        <td>{{$p->isClosed() ? 'مغلقة (مصروفة بالكامل)' : 'مفتوحة'}}</td>
                        <td>
                           @foreach($p->payouts as $po)
                           <div>{{$po->paid_date->format('Y-m-d')}}: {{$money($po->amount)}} ({{$po->bond->es_id ?? ''}})@if($po->isReversed()) - معكوس @endif</div>
                           @endforeach
                        </td>
                     </tr>
                     @empty
                     <tr><td colspan="6" class="text-center">لا توجد فترات عمولة</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
@endsection
