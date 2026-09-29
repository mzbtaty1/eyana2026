@extends('layouts.print')
@section('title', 'كشف أفراد ' . $booking->booking_no)
@section('extra-style')
    table.tb-print { width: 100%; border-collapse: collapse; }
    table.tb-print th, table.tb-print td { border: 1px solid var(--ey-border); padding: 5px 6px; font-size: 11px; }
    table.tb-print th { background: var(--ey-brand-light); }
@endsection
@section('content')
<x-print-header title="كشف الأفراد">
    <span><span class="ey-print-filter-label">رقم الحجز:</span> <b>{{ $booking->booking_no }}</b></span>
    <span><span class="ey-print-filter-label">العميل:</span> <b>{{ $booking->customer->name ?? '' }}</b></span>
    <span><span class="ey-print-filter-label">البرنامج:</span> <b>{{ $booking->program->name ?? 'حجز خاص' }}</b></span>
    <span><span class="ey-print-filter-label">المدة:</span> <b>{{ $booking->start_date?->format('Y-m-d') ?? '—' }} : {{ $booking->end_date?->format('Y-m-d') ?? '—' }}</b></span>
</x-print-header>

<table class="tb-print">
    <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>السن</th><th>رقم الهوية / الجواز</th><th>الهاتف</th><th>الغرفة</th><th>ملاحظات</th></tr></thead>
    <tbody>
        @forelse($booking->passengers as $n => $p)
        <tr><td>{{ $n + 1 }}</td><td>{{ $p->name }}</td><td>{{ \App\Models\TourismBookingPassenger::TYPES[$p->type] ?? '' }}</td><td>{{ $p->age }}</td><td>{{ $p->id_number }}</td><td>{{ $p->phone }}</td><td>{{ $p->room_ref }}</td><td>{{ $p->notes }}</td></tr>
        @empty
        <tr><td colspan="8" style="text-align:center">لم يتم إدخال الأفراد ({{ $booking->adults }} بالغ + {{ $booking->children }} طفل)</td></tr>
        @endforelse
    </tbody>
</table>

<x-print-footer :note="'كشف أفراد ' . $booking->booking_no" />
@endsection
