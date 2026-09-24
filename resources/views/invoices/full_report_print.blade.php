@extends('layouts.print')
@section('title', 'التقرير التفصيلي للفواتير')
@section('extra-style')
    @page { size: A4 landscape; }
    @media screen { main { max-width: 297mm; min-height: 210mm; } }
    table.fr-print { width: 100%; border-collapse: collapse; }
    table.fr-print th, table.fr-print td { border: 1px solid var(--ey-border); padding: 3px 4px; font-size: 9px; vertical-align: middle; }
    table.fr-print th { background: var(--ey-brand-light); }
    table.fr-print td.n { direction: ltr; text-align: right; white-space: nowrap; }
    table.fr-print tr.fr-refund td { background: #fdecea; }
    table.fr-print tr.fr-reissue td { background: #e3f2fd; }
    table.fr-print tfoot td { font-weight: 700; background: var(--ey-brand-light); }
    .fr-kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 12px; }
    .fr-kpis div { border: 1px solid var(--ey-border); padding: 5px 7px; font-size: 10px; }
    .fr-kpis b { display: block; font-size: 12px; direction: ltr; text-align: right; }
    .fr-latin { direction: ltr; unicode-bidi: embed; }
@endsection
@section('content')
@php
    $m = fn ($v) => \App\Services\InvoiceFullReport::money($v);
@endphp

<x-print-header :title="$by ? 'التقرير التفصيلي للفواتير - حسب ' . \App\Services\InvoiceFullReport::GROUPS[$by] : 'التقرير التفصيلي للفواتير'">
    @foreach($filters as $label => $value)
        <span><span class="ey-print-filter-label">{{ $label }}:</span> <b>{{ $value }}</b></span>
    @endforeach
</x-print-header>

<div class="fr-kpis">
    <div>عدد الفواتير<b>{{ $summary['invoices'] }}</b></div>
    <div>عدد التذاكر<b>{{ $summary['tickets'] }}</b></div>
    <div>تذاكر مرتجعة<b>{{ $summary['refund_tickets'] }}</b></div>
    <div>إجمالي البيع<b>{{ $m($summary['sale']) }}</b></div>
    <div>إجمالي الشراء<b>{{ $m($summary['purchase']) }}</b></div>
    <div>إجمالي الربح<b>{{ $m($summary['gross_profit']) }}</b></div>
    <div>مرتجع من الموردين<b>{{ $m($summary['supplier_return']) }}</b></div>
    <div>مسترد للعملاء<b>{{ $m($summary['client_refund']) }}</b></div>
    <div>صافي المرتجعات<b>{{ $m($summary['refund_net']) }}</b></div>
    <div>صافي الربح<b>{{ $m($summary['net_profit']) }}</b></div>
    <div>إجمالي العمولة<b>{{ $m($summary['commission']) }}</b></div>
</div>

@if($by)
    @if($by === 'employee')
        <p style="font-size:10px;">الفاتورة المشتركة تظهر عند كل موظف من الموظفين المشتركين فيها (البيع والربح كاملين، والعمولة بنسبة كل موظف).</p>
    @endif
    <table class="fr-print">
        <thead>
            <tr>
                <th>{{ \App\Services\InvoiceFullReport::GROUPS[$by] }}</th>
                <th>الفواتير</th><th>التذاكر</th><th>مرتجعات</th><th>إجمالي البيع</th><th>إجمالي الشراء</th>
                <th>إجمالي الربح</th><th>صافي المرتجعات</th><th>صافي الربح</th><th>العمولة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $g)
                <tr>
                    <td>{{ $g['label'] }}</td>
                    <td class="n">{{ $g['invoices'] }}</td><td class="n">{{ $g['tickets'] }}</td><td class="n">{{ $g['refund_tickets'] }}</td>
                    <td class="n">{{ $m($g['sale']) }}</td><td class="n">{{ $m($g['purchase']) }}</td><td class="n">{{ $m($g['gross_profit']) }}</td>
                    <td class="n">{{ $m($g['refund_net']) }}</td><td class="n">{{ $m($g['net_profit']) }}</td><td class="n">{{ $m($g['commission']) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;">لا توجد نتائج</td></tr>
            @endforelse
        </tbody>
    </table>
@else
    <table class="fr-print">
        <thead>
            <tr>
                <th>رقم الفاتورة</th><th>العملية</th><th>نوع الفاتورة</th><th>تاريخ الفاتورة</th><th>تاريخ السفر</th>
                <th>الراكب</th><th>PNR</th><th>التذكرة</th><th>الرحلة</th><th>الطيران</th><th>العميل</th><th>المورد</th><th>الموظف</th>
                <th>الشراء</th><th>البيع</th><th>مرتجع من المورد</th><th>مسترد للعميل</th><th>الربح / الخسارة</th><th>العمولة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr class="{{ $r['op'] === 'refund' ? 'fr-refund' : ($r['op'] === 'reissue' ? 'fr-reissue' : '') }}">
                    <td class="fr-latin">{{ $r['es_id'] }}</td>
                    <td>{{ $r['op_label'] }}@if($r['edits']) ✎{{ $r['edits'] }}@endif</td>
                    <td>{{ $r['type_label'] }}</td>
                    <td>{{ $r['invoice_date'] }}</td>
                    <td>{{ $r['travel_date'] }}</td>
                    <td class="fr-latin">{{ $r['passenger'] }}</td>
                    <td class="fr-latin">{{ $r['pnr'] }}</td>
                    <td class="fr-latin">{{ $r['ticket'] }}</td>
                    <td>{{ $r['route'] }}</td>
                    <td>{{ $r['airline'] }}</td>
                    <td>{{ $r['customer'] }}</td>
                    <td>{{ $r['supplier'] }}</td>
                    <td>{{ $r['employee'] }} ({{ $r['rate_label'] }})</td>
                    <td class="n">{{ $m($r['purchase']) }}</td>
                    <td class="n">{{ $m($r['sale']) }}</td>
                    <td class="n">{{ $m($r['supplier_return']) }}</td>
                    <td class="n">{{ $m($r['client_refund']) }}</td>
                    <td class="n">{{ $m($r['profit']) }}</td>
                    <td class="n">{{ $m($r['commission']) }}</td>
                </tr>
            @empty
                <tr><td colspan="19" style="text-align:center;">لا توجد نتائج</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="13">الإجمالي</td>
                <td class="n">{{ $m($summary['purchase']) }}</td>
                <td class="n">{{ $m($summary['sale']) }}</td>
                <td class="n">{{ $m($summary['supplier_return']) }}</td>
                <td class="n">{{ $m($summary['client_refund']) }}</td>
                <td class="n">{{ $m($summary['net_profit']) }}</td>
                <td class="n">{{ $m($summary['commission']) }}</td>
            </tr>
        </tfoot>
    </table>
@endif

<x-print-footer note="التقرير التفصيلي للفواتير" />
@endsection
