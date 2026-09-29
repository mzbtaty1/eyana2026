{{--
    Operation lists (rooming list for hotels / villages, transport list): the active services of
    confirmed bookings, by service date, supplier and program (a group departure = program +
    date). ?print=1 renders the same list on the print layout.
--}}
@extends($print ? 'layouts.print' : 'layouts.app')
@section('title', $title)
@if($print)
@section('extra-style')
    @page { size: A4 landscape; }
    table.tb-print { width: 100%; border-collapse: collapse; }
    table.tb-print th, table.tb-print td { border: 1px solid var(--ey-border); padding: 4px 5px; font-size: 10px; vertical-align: top; }
    table.tb-print th { background: var(--ey-brand-light); }
@endsection
@endif
@section('content')
@php
   $hotel = $type === 'hotel';
   $supplierName = !empty($f['supplier_id']) ? optional($accounts->firstWhere('id', (int) $f['supplier_id']))->name : null;
   $programName = !empty($f['program_id']) ? optional($programs->firstWhere('id', (int) $f['program_id']))->name : null;
@endphp
@if($print)
<x-print-header :title="$title" :dateFrom="$f['date_from'] ?? null" :dateTo="$f['date_to'] ?? null">
    @if($supplierName)<span><span class="ey-print-filter-label">المورد:</span> <b>{{ $supplierName }}</b></span>@endif
    @if($programName)<span><span class="ey-print-filter-label">البرنامج:</span> <b>{{ $programName }}</b></span>@endif
</x-print-header>
@else
<div class="row"><div class="col-lg-12"><div class="card">
   <x-page-header :title="$title">
      <div class="ey-action-group">
         <a href="{{route('site.tourism_operations', ['type' => $type] + $f + ['print' => 1])}}" target="_blank" class="btn btn-outline-dark"><i class="ri-printer-line"></i><span>طباعة</span></a>
      </div>
   </x-page-header>
   <div class="card-body">
      <form method="GET" action="{{route('site.tourism_operations', $type)}}" class="row g-2 align-items-end mb-3">
         <div class="col-md-2"><label class="form-label mb-1">{{ $hotel ? 'الدخول من' : 'التاريخ من' }}</label><input type="date" class="form-control" name="date_from" value="{{$f['date_from'] ?? ''}}"></div>
         <div class="col-md-2"><label class="form-label mb-1">إلى</label><input type="date" class="form-control" name="date_to" value="{{$f['date_to'] ?? ''}}"></div>
         <div class="col-md-3">
            <label class="form-label mb-1">المورد</label>
            <select class="form-select" name="supplier_id" id="o_supplier"><option value="">الكل</option>@foreach($accounts as $a)<option value="{{$a->id}}" @selected((string) ($f['supplier_id'] ?? '') === (string) $a->id)>{{$a->name}}</option>@endforeach</select>
         </div>
         <div class="col-md-3">
            <label class="form-label mb-1">البرنامج</label>
            <select class="form-select" name="program_id"><option value="">الكل</option>@foreach($programs as $p)<option value="{{$p->id}}" @selected((string) ($f['program_id'] ?? '') === (string) $p->id)>{{$p->name}}</option>@endforeach</select>
         </div>
         <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="ey-filter-actions">
               <button class="btn btn-primary"><i class="ri-filter-line"></i><span>عرض</span></button>
               <a href="{{route('site.tourism_operations', $type)}}" class="btn btn-light"><i class="ri-refresh-line"></i><span>مسح</span></a>
            </div>
         </div>
      </form>
      <div class="table-responsive">
@endif
      <table class="{{ $print ? 'tb-print' : 'table table-bordered table-striped align-middle' }}">
         <thead class="table-light">
            <tr>
               <th>{{ $hotel ? 'الفندق / القرية' : 'المورد' }}</th><th>الخدمة</th><th>{{ $hotel ? 'دخول' : 'التاريخ' }}</th>@if($hotel)<th>خروج</th><th>ليالي</th><th>غرف</th><th>نوع الغرفة</th>@else<th>من ← إلى</th><th>العدد</th>@endif
               <th>الحجز</th><th>الاسم / الهاتف</th><th>بالغ / طفل</th><th>الأفراد</th>
            </tr>
         </thead>
         <tbody>
            @forelse($items as $it)
            @php($b = $it->booking)
            <tr>
               <td>{{ $it->supplier->name ?? '' }}</td>
               <td>{{ $it->description }}@if($it->notes)<br><small>{{ $it->notes }}</small>@endif</td>
               <td>{{ $it->start_date?->format('Y-m-d') }}</td>
               @if($hotel)<td>{{ $it->end_date?->format('Y-m-d') }}</td><td>{{ $it->nights }}</td><td>{{ $it->rooms }}</td><td>{{ $it->room_type }}</td>@else<td>{{ $it->detailsText() }}</td><td>{{ $it->quantityText() }}</td>@endif
               <td>{{ $b->booking_no }}@if($b->program)<br><small>{{ $b->program->name }}</small>@endif</td>
               <td>{{ $b->contact_name ?: ($b->customer->name ?? '') }}<br><small>{{ $b->contact_phone }}</small></td>
               <td>{{ $it->adults ?? $b->adults }} / {{ $it->children ?? $b->children }}</td>
               <td>
                  @foreach($b->passengers as $p)
                  <div>{{ $p->name }}@if($p->room_ref) <small>(غرفة {{ $p->room_ref }})</small>@endif</div>
                  @endforeach
               </td>
            </tr>
            @empty
            <tr><td colspan="{{ $hotel ? 11 : 9 }}" style="text-align:center">لا توجد خدمات</td></tr>
            @endforelse
         </tbody>
      </table>
@if($print)
<x-print-footer :note="$title" />
@else
      </div>
   </div>
</div></div></div>
<script src="{{asset('assets/dselect.js')}}"></script>
<script>dselect(document.querySelector('#o_supplier'), { search: true });</script>
@endif
@endsection
