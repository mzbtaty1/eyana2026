@extends('layouts.print')
@section('title', 'إيصال صرف عمولة')
@section('extra-style')
    table.cp-print { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.cp-print th, table.cp-print td { border: 1px solid var(--ey-border); padding: 5px 7px; font-size: 11px; text-align: right; }
    table.cp-print th { background: var(--ey-brand-light); width: 30%; }
@endsection
@section('content')
@php
    $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
    $c = $period->calculation;
@endphp

<x-print-header title="إيصال صرف عمولة موظف"></x-print-header>

<table class="cp-print">
    <tr><th>الموظف</th><td>{{ $period->employee->name ?? '#' . $period->employee_id }}</td></tr>
    <tr><th>الفترة</th><td>{{ $period->label() }}</td></tr>
    <tr><th>المبلغ المصروف</th><td><b>{{ $money($payout->amount) }}</b></td></tr>
    <tr><th>تاريخ الصرف</th><td>{{ $payout->paid_date->format('Y-m-d') }}</td></tr>
    <tr><th>سند الدفع</th><td>{{ $payout->bond->es_id ?? '#' . $payout->bond_id }} ({{ $payout->money_way === 2 ? 'بنك / محفظة' : 'نقدي' }})</td></tr>
    <tr><th>بواسطة</th><td>{{ $payout->creator->name ?? '' }}</td></tr>
    @if($payout->reference)<tr><th>المرجع</th><td>{{ $payout->reference }}</td></tr>@endif
    <tr><th>الحالة</th><td>{{ $payout->isReversed() ? 'معكوس في ' . $payout->reversed_at->format('Y-m-d') : 'مصروف' }}</td></tr>
</table>

<table class="cp-print">
    <tr><th>إجمالي المبيعات</th><td>{{ $money($c['sales']) }}</td></tr>
    <tr><th>إجمالي التكلفة</th><td>{{ $money($c['cost']) }}</td></tr>
    <tr><th>صافي المرتجعات</th><td>{{ $money($c['refund_net']) }}</td></tr>
    <tr><th>إجمالي الربح</th><td>{{ $money($c['profit']) }}</td></tr>
    <tr><th>طريقة العمولة</th><td>{{ $c['method_label'] }}{{ $c['tier_table'] ? ' - ' . $c['tier_table'] : '' }}</td></tr>
    <tr><th>نسبة العمولة</th><td>{{ \App\Services\EmployeeCommissionReport::percent((float) $c['rate']) }}%</td></tr>
    <tr><th>العمولة المعتمدة للفترة</th><td>{{ $money($period->commission_amount) }}</td></tr>
    <tr><th>إجمالي المصروف / المتبقي</th><td>{{ $money($period->paid()) }} / {{ $money($period->remaining()) }}</td></tr>
</table>

<x-print-footer note="صرف عمولات الموظفين" />
@endsection
