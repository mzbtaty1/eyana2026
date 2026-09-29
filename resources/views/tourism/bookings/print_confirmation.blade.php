@extends('layouts.print')
@section('title', 'تأكيد حجز ' . $booking->booking_no)
@section('extra-style')
    table.tb-print { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.tb-print th, table.tb-print td { border: 1px solid var(--ey-border); padding: 5px 6px; font-size: 11px; }
    table.tb-print th { background: var(--ey-brand-light); }
    table.tb-print td.n { direction: ltr; text-align: right; white-space: nowrap; }
    .tb-info { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; font-size: 12px; margin-bottom: 12px; }
@endsection
@section('content')
@php
    $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
    $active = $booking->items->where('status', 'active');
    $sale = $booking->isDraft() ? $active->sum('total_sale') : $summary['sale'];
@endphp
<x-print-header title="تأكيد حجز سياحة داخلية">
    <span><span class="ey-print-filter-label">رقم الحجز:</span> <b>{{ $booking->booking_no }}</b></span>
    <span><span class="ey-print-filter-label">الحالة:</span> <b>{{ $booking->statusLabel() }}</b></span>
</x-print-header>

<div class="tb-info">
    <div>العميل: <b>{{ $booking->customer->name ?? '' }}</b></div>
    <div>المسئول / الهاتف: <b>{{ $booking->contact_name }} {{ $booking->contact_phone }}</b></div>
    <div>البرنامج: <b>{{ $booking->program->name ?? 'حجز خاص' }}</b></div>
    <div>الأفراد: <b>{{ $booking->adults }} بالغ + {{ $booking->children }} طفل</b></div>
    <div>المدة: <b>{{ $booking->start_date?->format('Y-m-d') ?? '—' }} : {{ $booking->end_date?->format('Y-m-d') ?? '—' }}</b></div>
    <div>الموظف: <b>{{ $booking->owner->name ?? '' }}</b></div>
</div>

<table class="tb-print">
    <thead><tr><th>الخدمة</th><th>التاريخ</th><th>التفاصيل</th><th>الإجمالي</th></tr></thead>
    <tbody>
        @foreach($active as $it)
        <tr>
            <td>{{ \App\Models\TourismBookingItem::typeLabel($it->service_type) }}: {{ $it->description }}</td>
            <td>{{ $it->dateText() }}</td>
            <td>{{ $it->detailsText() ?: $it->quantityText() }}</td>
            <td class="n">{{ $money($it->total_sale) }}</td>
        </tr>
        @endforeach
        @foreach($booking->items->where('status', 'cancelled')->filter(fn ($i) => (float) $i->cancel_fee > 0) as $it)
        <tr><td colspan="3">رسوم إلغاء: {{ $it->title() }}</td><td class="n">{{ $money($it->cancel_fee) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="3">الإجمالي</th><td class="n"><b>{{ $money($sale) }}</b></td></tr>
        @unless($booking->isDraft())
        <tr><th colspan="3">المدفوع</th><td class="n">{{ $money($summary['customer_paid']) }}</td></tr>
        <tr><th colspan="3">{{ $summary['customer_remaining'] < 0 ? 'مستحق للعميل' : 'المتبقي' }}</th><td class="n"><b>{{ $money(abs($summary['customer_remaining'])) }}</b></td></tr>
        @endunless
    </tfoot>
</table>

@if($booking->passengers->isNotEmpty())
<table class="tb-print">
    <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>السن</th><th>الغرفة</th></tr></thead>
    <tbody>
        @foreach($booking->passengers as $n => $p)
        <tr><td>{{ $n + 1 }}</td><td>{{ $p->name }}</td><td>{{ \App\Models\TourismBookingPassenger::TYPES[$p->type] ?? '' }}</td><td>{{ $p->age }}</td><td>{{ $p->room_ref }}</td></tr>
        @endforeach
    </tbody>
</table>
@endif
@if($booking->notes)<p style="font-size:12px">ملاحظات: {{ $booking->notes }}</p>@endif

<x-print-footer :note="'تأكيد حجز ' . $booking->booking_no" />
@endsection
