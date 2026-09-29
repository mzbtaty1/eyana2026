@extends('layouts.print')
@section('title', 'أمر خدمة ' . $booking->booking_no)
@section('extra-style')
    table.tb-print { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.tb-print th, table.tb-print td { border: 1px solid var(--ey-border); padding: 5px 6px; font-size: 11px; }
    table.tb-print th { background: var(--ey-brand-light); }
    table.tb-print td.n { direction: ltr; text-align: right; white-space: nowrap; }
    .tb-info { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; font-size: 12px; margin-bottom: 12px; }
@endsection
@section('content')
{{-- The supplier's copy (hotel voucher / service order): its active services and the people -- no selling prices. --}}
@php
    $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
    $items = $booking->items->where('supplier_id', $supplier->id)->where('status', 'active');
@endphp
<x-print-header title="أمر خدمة / فاوتشر">
    <span><span class="ey-print-filter-label">رقم الحجز:</span> <b>{{ $booking->booking_no }}</b></span>
    <span><span class="ey-print-filter-label">إلى:</span> <b>{{ $supplier->name }}</b></span>
</x-print-header>

<div class="tb-info">
    <div>اسم الحجز: <b>{{ $booking->contact_name ?: ($booking->customer->name ?? '') }}</b></div>
    <div>الهاتف: <b>{{ $booking->contact_phone }}</b></div>
    <div>الأفراد: <b>{{ $booking->adults }} بالغ + {{ $booking->children }} طفل</b></div>
    <div>الموظف: <b>{{ $booking->owner->name ?? '' }}</b></div>
</div>

<table class="tb-print">
    <thead><tr><th>الخدمة</th><th>التاريخ</th><th>التفاصيل</th><th>الكمية</th><th>التكلفة</th></tr></thead>
    <tbody>
        @forelse($items as $it)
        <tr>
            <td>{{ \App\Models\TourismBookingItem::typeLabel($it->service_type) }}: {{ $it->description }}@if($it->notes)<br><small>{{ $it->notes }}</small>@endif</td>
            <td>{{ $it->dateText() }}</td>
            <td>{{ $it->detailsText() }}</td>
            <td>{{ $it->quantityText() }}</td>
            <td class="n">{{ $money($it->total_cost) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center">لا توجد خدمات فعالة لهذا المورد</td></tr>
        @endforelse
    </tbody>
    <tfoot><tr><th colspan="4">الإجمالي</th><td class="n"><b>{{ $money($items->sum('total_cost')) }}</b></td></tr></tfoot>
</table>

@if($booking->passengers->isNotEmpty())
<table class="tb-print">
    <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>السن</th><th>رقم الهوية / الجواز</th><th>الغرفة</th></tr></thead>
    <tbody>
        @foreach($booking->passengers as $n => $p)
        <tr><td>{{ $n + 1 }}</td><td>{{ $p->name }}</td><td>{{ \App\Models\TourismBookingPassenger::TYPES[$p->type] ?? '' }}</td><td>{{ $p->age }}</td><td>{{ $p->id_number }}</td><td>{{ $p->room_ref }}</td></tr>
        @endforeach
    </tbody>
</table>
@endif

<x-print-footer :note="'أمر خدمة ' . $booking->booking_no" />
@endsection
