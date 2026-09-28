@extends('layouts.print')
@section('title', 'تقرير عمولات الموظفين')
@section('extra-style')
    @page { size: A4 landscape; }
    table.cp-print { width: 100%; border-collapse: collapse; }
    table.cp-print th, table.cp-print td { border: 1px solid var(--ey-border); padding: 4px 5px; font-size: 10px; }
    table.cp-print th { background: var(--ey-brand-light); }
    table.cp-print td.n { direction: ltr; text-align: right; white-space: nowrap; }
    table.cp-print tfoot td { font-weight: 700; background: var(--ey-brand-light); }
@endsection
@section('content')
@php
    $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
    $t = $dashboard->totals();
@endphp

<x-print-header title="تقرير عمولات الموظفين">
    @foreach($dashboard->filterLabels() as $label => $value)
        <span><span class="ey-print-filter-label">{{ $label }}:</span> <b>{{ $value }}</b></span>
    @endforeach
</x-print-header>

<table class="cp-print">
    <thead>
        <tr>
            <th>الموظف</th><th>الفترة</th><th>الربح وقت فتح الفترة</th><th>العمولة وقت فتح الفترة</th><th>العمولة الحالية</th><th>الفرق</th>
            <th>المصروف</th><th>المتبقي</th><th>الحالة</th><th>آخر صرف</th>
        </tr>
    </thead>
    <tbody>
        @forelse($dashboard->rows() as $r)
            <tr>
                <td>{{ $r['employee'] }}</td>
                <td>{{ $r['from'] }} : {{ $r['to'] }}</td>
                <td class="n">{{ $money($r['profit']) }}</td>
                <td class="n">{{ $money($r['commission']) }}</td>
                <td class="n">{{ $money($r['current']) }}</td>
                <td class="n">{{ $r['differs'] ? $money($r['difference']) : '—' }}</td>
                <td class="n">{{ $money($r['paid']) }}</td>
                <td class="n">{{ $money($r['remaining']) }}</td>
                <td>{{ $r['status_label'] }}</td>
                <td>{{ $r['last_paid_date'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center;">لا توجد فترات عمولة</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">الإجمالي</td>
            <td class="n">{{ $money($t['commission']) }}</td>
            <td class="n">{{ $money($t['current']) }}</td>
            <td class="n">{{ $money($t['difference']) }}</td>
            <td class="n">{{ $money($t['paid']) }}</td>
            <td class="n">{{ $money($t['remaining']) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

<x-print-footer note="صرف عمولات الموظفين" />
@endsection
