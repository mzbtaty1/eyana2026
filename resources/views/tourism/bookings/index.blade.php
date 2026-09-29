@extends('layouts.app')
@section('content')
@section('title' , 'حجوزات السياحة الداخلية')
@php($money = fn ($v) => \App\Services\InvoiceFullReport::money($v))
@php($badge = ['draft' => 'bg-secondary', 'confirmed' => 'bg-success', 'cancelled' => 'bg-danger'])
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="حجوزات السياحة الداخلية">
            @can('tourism.create')
            <div class="ey-action-group">
               <a href="{{route('site.tourism_bookings_create')}}" class="btn btn-primary"><i class="ri-add-line"></i><span>حجز جديد</span></a>
            </div>
            @endcan
         </x-page-header>
         <div class="card-body">
            @include('components.flash-messages')
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

            <form method="GET" action="{{route('site.tourism_bookings')}}" class="row g-2 align-items-end mb-3">
               <div class="col-md-2"><label class="form-label mb-1">بحث</label><input type="text" class="form-control" name="q" value="{{$f['q']}}" placeholder="رقم الحجز / الاسم / الهاتف"></div>
               <div class="col-md-2">
                  <label class="form-label mb-1">الحالة</label>
                  <select class="form-select" name="status">
                     <option value="">الكل</option>
                     @foreach(\App\Models\TourismBooking::STATUSES as $k => $label)<option value="{{$k}}" @selected($f['status'] === $k)>{{$label}}</option>@endforeach
                  </select>
               </div>
               <div class="col-md-2">
                  <label class="form-label mb-1">البرنامج</label>
                  <select class="form-select" name="program_id">
                     <option value="">الكل</option>
                     @foreach($programs as $p)<option value="{{$p->id}}" @selected($f['program_id'] === $p->id)>{{$p->name}}</option>@endforeach
                  </select>
               </div>
               <div class="col-md-2">
                  <label class="form-label mb-1">العميل</label>
                  <select class="form-select" name="customer_id" id="f_customer">
                     <option value="">الكل</option>
                     @foreach($accounts as $a)<option value="{{$a->id}}" @selected($f['customer_id'] === $a->id)>{{$a->name}}</option>@endforeach
                  </select>
               </div>
               <div class="col-md-2">
                  <label class="form-label mb-1">المورد</label>
                  <select class="form-select" name="supplier_id" id="f_supplier">
                     <option value="">الكل</option>
                     @foreach($accounts as $a)<option value="{{$a->id}}" @selected($f['supplier_id'] === $a->id)>{{$a->name}}</option>@endforeach
                  </select>
               </div>
               @if($employees->isNotEmpty())
               <div class="col-md-2">
                  <label class="form-label mb-1">الموظف</label>
                  <select class="form-select" name="employee_id">
                     <option value="">الكل</option>
                     @foreach($employees as $e)<option value="{{$e->id}}" @selected($f['employee_id'] === $e->id)>{{$e->name}}</option>@endforeach
                  </select>
               </div>
               @endif
               <div class="col-md-2"><label class="form-label mb-1">تاريخ الرحلة من</label><input type="date" class="form-control" name="date_from" value="{{$f['date_from']}}"></div>
               <div class="col-md-2"><label class="form-label mb-1">إلى</label><input type="date" class="form-control" name="date_to" value="{{$f['date_to']}}"></div>
               <div class="col-sm-6 col-md-4 col-xl-2">
                  <div class="ey-filter-actions">
                     <button class="btn btn-primary"><i class="ri-filter-line"></i><span>عرض</span></button>
                     <a href="{{route('site.tourism_bookings')}}" class="btn btn-light"><i class="ri-refresh-line"></i><span>مسح</span></a>
                  </div>
               </div>
            </form>

            <div class="table-responsive">
               <table class="table table-bordered table-striped align-middle text-nowrap" id="tourism-bookings">
                  <thead class="table-light">
                     <tr>
                        <th>رقم الحجز</th><th>العميل</th><th>البرنامج</th><th>تاريخ الرحلة</th><th>الأفراد</th><th>الخدمات</th>
                        <th>البيع</th><th>التكلفة</th><th>الربح</th><th>المتبقي على العميل</th><th>الموظف</th><th>الحالة</th><th>--</th>
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($bookings as $b)
                     @php($s = $summaries[$b->id])
                     @php($est = $b->isDraft())
                     <tr>
                        <td><a href="{{route('site.tourism_bookings_show', $b->id)}}"><strong>{{$b->booking_no}}</strong></a></td>
                        <td>{{$b->customer->name ?? ''}}@if($b->contact_name)<div class="text-muted fs-11">{{$b->contact_name}} {{$b->contact_phone}}</div>@endif</td>
                        <td>{{$b->program->name ?? 'حجز خاص'}}</td>
                        <td>{{$b->start_date?->format('Y-m-d') ?? '—'}}</td>
                        <td>{{$b->adults}} + {{$b->children}}</td>
                        <td>{{$b->items->where('status', 'active')->count()}}</td>
                        {{-- a draft is not on the ledger yet: its items' totals, marked as an estimate --}}
                        <td>{{$money($est ? $b->items->sum('total_sale') : $s['sale'])}}</td>
                        <td>{{$money($est ? $b->items->sum('total_cost') : $s['cost'])}}</td>
                        <td><strong>{{$money($est ? $b->items->sum('total_sale') - $b->items->sum('total_cost') : $s['profit'])}}</strong>@if($est)<div class="text-muted fs-11">تقديري</div>@endif</td>
                        <td>{{$est ? '—' : $money($s['customer_remaining'])}}</td>
                        <td>{{$b->owner->name ?? ''}}</td>
                        <td><span class="badge {{$badge[$b->status] ?? 'bg-light'}} my_badge">{{$b->statusLabel()}}</span></td>
                        <td><div class="ey-row-actions"><a href="{{route('site.tourism_bookings_show', $b->id)}}" class="btn btn-soft-primary btn-sm"><i class="ri-eye-line"></i><span>عرض</span></a></div></td>
                     </tr>
                     @empty
                     <tr><td colspan="13" class="text-center">لا توجد حجوزات</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
            {{ $bookings->links() }}
         </div>
      </div>
   </div>
</div>
<script src="{{asset('assets/dselect.js')}}"></script>
<script>
   dselect(document.querySelector('#f_customer'), { search: true });
   dselect(document.querySelector('#f_supplier'), { search: true });
</script>
@endsection
