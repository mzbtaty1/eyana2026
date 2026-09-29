@extends('layouts.print')
@section('title', 'تقرير السياحة الداخلية')
@section('extra-style')
    @page { size: A4 landscape; }
    table.tb-print { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.tb-print th, table.tb-print td { border: 1px solid var(--ey-border); padding: 4px 5px; font-size: 10px; }
    table.tb-print th { background: var(--ey-brand-light); }
    table.tb-print td.n { direction: ltr; text-align: right; white-space: nowrap; }
    table.tb-print tfoot td { font-weight: 700; background: var(--ey-brand-light); }
@endsection
@section('content')
@php
    $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
    $t = $report->totals();
    $label = \App\Services\TourismReport::GROUPS[$report->f['group_by']];
@endphp
<x-print-header title="تقرير السياحة الداخلية">
    @foreach($report->filterLabels() as $k => $v)
        <span><span class="ey-print-filter-label">{{ $k }}:</span> <b>{{ $v }}</b></span>
    @endforeach
</x-print-header>

<table class="tb-print">
    <thead><tr><th>{{ $label }}</th><th>الحجوزات</th><th>المبيعات</th><th>التكلفة</th><th>الربح</th></tr></thead>
    <tbody>
        @foreach($report->groups() as $g)
        <tr><td>{{ $g['label'] }}</td><td class="n">{{ $g['bookings'] }}</td><td class="n">{{ $money($g['sale']) }}</td><td class="n">{{ $money($g['cost']) }}</td><td class="n">{{ $money($g['profit']) }}</td></tr>
        @endforeach
    </tbody>
</table>

<table class="tb-print">
    <thead><tr><th>رقم الحجز</th><th>تاريخ الرحلة</th><th>العميل</th><th>البرنامج</th><th>الموظف</th><th>الحالة</th><th>الأفراد</th><th>المبيعات</th><th>التكلفة</th><th>الربح</th><th>المدفوع</th><th>المتبقي على العميل</th><th>المتبقي للموردين</th></tr></thead>
    <tbody>
        @forelse($report->rows() as $r)
        <tr>
            <td>{{ $r['booking_no'] }}</td><td>{{ $r['start_date'] ?? '—' }}</td><td>{{ $r['customer'] }}</td><td>{{ $r['program'] }}</td><td>{{ $r['employee'] }}</td><td>{{ $r['status'] }}</td><td class="n">{{ $r['people'] }}</td>
            <td class="n">{{ $money($r['sale']) }}</td><td class="n">{{ $money($r['cost']) }}</td><td class="n">{{ $money($r['profit']) }}</td>
            <td class="n">{{ $money($r['customer_paid']) }}</td><td class="n">{{ $money($r['receivable']) }}</td><td class="n">{{ $money($r['payable']) }}</td>
        </tr>
        @empty
        <tr><td colspan="13" style="text-align:center;">لا توجد حجوزات</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6">الإجمالي ({{ $t['bookings'] }} حجز)</td><td></td>
            <td class="n">{{ $money($t['sale']) }}</td><td class="n">{{ $money($t['cost']) }}</td><td class="n">{{ $money($t['profit']) }}</td>
            <td class="n">{{ $money($t['customer_paid']) }}</td><td class="n">{{ $money($t['receivable']) }}</td><td class="n">{{ $money($t['payable']) }}</td>
        </tr>
    </tfoot>
</table>

<x-print-footer note="تقرير السياحة الداخلية" />
@endsection
