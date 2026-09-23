{{-- Shared A4 print header for the Eyana reporting system. Props: title, entity, dateFrom, dateTo. Slot: optional extra filter chips. --}}
@props([
    'title' => null,
    'entity' => null,
    'dateFrom' => null,
    'dateTo' => null,
])
<div class="ey-print-header">
    {{-- logo-dark.png is an unused Velzon starter-template asset; flymix_colored.png
         is the actual Eyana brand logo, already used app-wide (see layouts/app.blade.php
         navbar-brand-box). --}}
    <img src="{{ asset('assets/images/flymix_colored.png') }}" alt="Eyana">
    <div class="ey-print-title">
        <h4>{{ $title }}</h4>
        @isset($heading)
            <div>{{ $heading }}</div>
        @endisset
    </div>
    <div class="ey-print-meta">
        تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}
    </div>
</div>

@if($entity || $dateFrom || $dateTo || trim($slot ?? '') !== '')
<div class="ey-print-filters">
    @if($entity)
        <span><span class="ey-print-filter-label">الحساب/الجهة:</span> <b>{{ $entity }}</b></span>
    @endif
    @if($dateFrom || $dateTo)
        <span><span class="ey-print-filter-label">الفترة:</span> <b>{{ $dateFrom ?: '—' }} : {{ $dateTo ?: '—' }}</b></span>
    @endif
    {{ $slot ?? '' }}
</div>
@endif
