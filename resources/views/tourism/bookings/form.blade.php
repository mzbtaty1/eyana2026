@extends('layouts.app')
@section('content')
@section('title' , $booking ? 'تعديل حجز ' . $booking->booking_no : 'حجز سياحة داخلية جديد')
@php
   $confirmed = $booking && $booking->isConfirmed();
   $h = fn ($k, $d = null) => old($k, $header[$k] ?? $d);
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header :title="$booking ? 'تعديل حجز ' . $booking->booking_no : 'حجز سياحة داخلية جديد'">
            <div class="ey-action-group">
               <a href="{{ $booking ? route('site.tourism_bookings_show', $booking->id) : route('site.tourism_bookings') }}" class="btn btn-light"><i class="ri-arrow-right-line"></i><span>رجوع</span></a>
            </div>
         </x-page-header>
         <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{$e}}</li>@endforeach</ul></div>
            @endif
            @if($confirmed)
            <div class="alert alert-warning py-2">
               الحجز مؤكد: أي تغيير في المبالغ أو الموردين يسجل كقيود تعديل بتاريخ اليوم في كشوف الحساب، ولا يتم تعديل القيود السابقة.
               لإلغاء خدمة استخدم «إلغاء الخدمة» من صفحة الحجز.
            </div>
            @endif

            @if(!$booking && $programs->isNotEmpty())
            {{-- a booking from a program: its services are copied into the form (an independent copy) --}}
            <form method="GET" action="{{route('site.tourism_bookings_create')}}" class="row g-2 align-items-end border rounded p-2 mb-3 bg-light">
               <div class="col-12"><strong>إنشاء من برنامج</strong> <small class="text-muted">(يتم نسخ خدمات البرنامج إلى الحجز ويمكن تعديلها بعد ذلك)</small></div>
               <div class="col-md-6 col-lg-4">
                  <label class="form-label mb-1">البرنامج</label>
                  <select class="form-select" name="program_id" required>
                     <option value="">-- اختر البرنامج --</option>
                     @foreach($programs as $p)<option value="{{$p->id}}" @selected(optional($program)->id === $p->id)>{{$p->name}}@if($p->days) ({{$p->days}} أيام / {{$p->nights}} ليالي)@endif</option>@endforeach
                  </select>
               </div>
               <div class="col-md-6 col-lg-3"><label class="form-label mb-1">تاريخ البداية</label><input type="date" class="form-control" name="start_date" value="{{$header['start_date'] ?? ''}}"></div>
               <div class="col-6 col-md-4 col-lg-2"><label class="form-label mb-1">بالغين</label><input type="number" min="0" class="form-control" name="adults" value="{{$header['adults'] ?? 1}}"></div>
               <div class="col-6 col-md-4 col-lg-1"><label class="form-label mb-1">أطفال</label><input type="number" min="0" class="form-control" name="children" value="{{$header['children'] ?? 0}}"></div>
               <div class="col-md-4 col-lg-2"><div class="ey-filter-actions"><button class="btn btn-outline-primary"><i class="ri-download-2-line"></i><span>تحميل البرنامج</span></button></div></div>
            </form>
            @endif

            <form method="POST" action="{{ $booking ? route('site.tourism_bookings_update', $booking->id) : route('site.tourism_bookings_store') }}" autocomplete="off" id="tourism-booking-form">
               @csrf
               <input type="hidden" name="program_id" value="{{ $h('program_id') }}">
               @if($program)<div class="mb-2"><span class="badge bg-info my_badge">البرنامج: {{$program->name}}</span></div>@endif

               <div class="row">
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="customer_id">العميل <span class="text-danger">*</span></label>
                     <select class="form-select" name="customer_id" id="customer_id" required>
                        <option value="">-- اختر العميل --</option>
                        @foreach($accounts as $a)<option value="{{$a->id}}" @selected((string) $h('customer_id') === (string) $a->id)>{{$a->name}}</option>@endforeach
                     </select>
                  </div>
                  <div class="col-md-4 mb-3"><label class="form-label">اسم المسئول / العائلة</label><input type="text" class="form-control" name="contact_name" value="{{$h('contact_name')}}"></div>
                  <div class="col-md-4 mb-3"><label class="form-label">هاتف التواصل</label><input type="text" class="form-control" name="contact_phone" value="{{$h('contact_phone')}}"></div>
                  <div class="col-md-2 mb-3"><label class="form-label">بالغين <span class="text-danger">*</span></label><input type="number" min="0" class="form-control" name="adults" value="{{$h('adults', 1)}}" required></div>
                  <div class="col-md-2 mb-3"><label class="form-label">أطفال</label><input type="number" min="0" class="form-control" name="children" value="{{$h('children', 0)}}" required></div>
                  <div class="col-md-2 mb-3"><label class="form-label">تاريخ البداية</label><input type="date" class="form-control" name="start_date" value="{{$h('start_date')}}"></div>
                  <div class="col-md-2 mb-3"><label class="form-label">تاريخ النهاية</label><input type="date" class="form-control" name="end_date" value="{{$h('end_date')}}"></div>
                  @if($employees->isNotEmpty())
                  <div class="col-md-4 mb-3">
                     <label class="form-label">الموظف صاحب الحجز</label>
                     <select class="form-select" name="owner_id">
                        @foreach($employees as $e)<option value="{{$e->id}}" @selected((string) old('owner_id', Auth::id()) === (string) $e->id)>{{$e->name}}</option>@endforeach
                     </select>
                  </div>
                  @endif
                  <div class="col-12 mb-3"><label class="form-label">ملاحظات</label><textarea class="form-control" name="notes" rows="2">{{$h('notes')}}</textarea></div>
               </div>

               <h6 class="mt-2">الخدمات <small class="text-muted">(الفندق / القرية: التكلفة والبيع للغرفة في الليلة، والإجمالي = الغرف × الليالي. باقي الخدمات: الكمية × سعر الوحدة)</small></h6>
               <div class="table-responsive">
                  <table class="table table-bordered align-middle" id="tb-items">
                     <thead class="table-light text-nowrap">
                        <tr>
                           <th>النوع</th><th>الوصف</th><th>المورد</th><th>من / دخول</th><th>إلى / خروج</th><th>ليالي</th><th>غرف</th><th>نوع الغرفة</th>
                           <th>بالغ / طفل</th><th>الكمية</th><th>تكلفة الوحدة</th><th>بيع الوحدة</th><th>إجمالي التكلفة</th><th>إجمالي البيع</th><th>الربح</th><th></th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach(array_values($items) as $i => $it)
                           @include('tourism.bookings._item_row', ['i' => $i, 'it' => $it])
                        @endforeach
                     </tbody>
                     <tfoot class="table-light fw-bold">
                        <tr>
                           <td colspan="12"><div class="ey-action-group"><button type="button" class="btn btn-soft-success" id="tb-add-item"><i class="ri-add-line"></i><span>إضافة خدمة</span></button></div></td>
                           <td id="tb-sum-cost">0</td><td id="tb-sum-sale">0</td><td id="tb-sum-profit">0</td><td></td>
                        </tr>
                     </tfoot>
                  </table>
               </div>
               <div class="alert alert-danger py-2" id="tb-loss" style="display:none">تنويه: إجمالي البيع أقل من إجمالي التكلفة</div>

               <h6 class="mt-3">الأفراد <small class="text-muted">(لكشف الأفراد وكشف التسكين)</small></h6>
               <div class="table-responsive">
                  <table class="table table-bordered align-middle" id="tb-passengers">
                     <thead class="table-light"><tr><th>الاسم</th><th>النوع</th><th>السن</th><th>رقم الهوية / الجواز</th><th>الهاتف</th><th>الغرفة</th><th>ملاحظات</th><th></th></tr></thead>
                     <tbody>
                        @foreach(array_values($passengers) as $i => $p)
                        <tr>
                           <td><input type="text" class="form-control form-control-sm" name="passengers[{{$i}}][name]" value="{{$p['name'] ?? ''}}" required></td>
                           <td><select class="form-select form-select-sm" name="passengers[{{$i}}][type]">@foreach(\App\Models\TourismBookingPassenger::TYPES as $k => $l)<option value="{{$k}}" @selected(($p['type'] ?? 'adult') === $k)>{{$l}}</option>@endforeach</select></td>
                           <td><input type="number" min="0" max="120" class="form-control form-control-sm" name="passengers[{{$i}}][age]" value="{{$p['age'] ?? ''}}"></td>
                           <td><input type="text" class="form-control form-control-sm" name="passengers[{{$i}}][id_number]" value="{{$p['id_number'] ?? ''}}"></td>
                           <td><input type="text" class="form-control form-control-sm" name="passengers[{{$i}}][phone]" value="{{$p['phone'] ?? ''}}"></td>
                           <td><input type="text" class="form-control form-control-sm" name="passengers[{{$i}}][room_ref]" value="{{$p['room_ref'] ?? ''}}" placeholder="مثال: 1"></td>
                           <td><input type="text" class="form-control form-control-sm" name="passengers[{{$i}}][notes]" value="{{$p['notes'] ?? ''}}"></td>
                           <td><div class="ey-row-actions"><button type="button" class="btn btn-soft-danger btn-sm tb-remove-p" title="إزالة"><i class="ri-delete-bin-line"></i></button></div></td>
                        </tr>
                        @endforeach
                     </tbody>
                  </table>
               </div>
               <div class="ey-action-group mb-3">
                  <button type="button" class="btn btn-soft-success" id="tb-add-p"><i class="ri-user-add-line"></i><span>إضافة فرد</span></button>
               </div>

               <div class="d-grid">
                  <button class="btn btn-primary"><i class="ri-save-line"></i><span>{{ $booking ? 'حفظ التعديلات' : 'حفظ الحجز كمسودة' }}</span></button>
               </div>
            </form>
         </div>
      </div>
   </div>
</div>

<template id="tb-item-tpl">@include('tourism.bookings._item_row', ['i' => '__I__', 'it' => ['quantity' => 1]])</template>
<template id="tb-p-tpl">
   <tr>
      <td><input type="text" class="form-control form-control-sm" name="passengers[__I__][name]" required></td>
      <td><select class="form-select form-select-sm" name="passengers[__I__][type]">@foreach(\App\Models\TourismBookingPassenger::TYPES as $k => $l)<option value="{{$k}}">{{$l}}</option>@endforeach</select></td>
      <td><input type="number" min="0" max="120" class="form-control form-control-sm" name="passengers[__I__][age]"></td>
      <td><input type="text" class="form-control form-control-sm" name="passengers[__I__][id_number]"></td>
      <td><input type="text" class="form-control form-control-sm" name="passengers[__I__][phone]"></td>
      <td><input type="text" class="form-control form-control-sm" name="passengers[__I__][room_ref]" placeholder="مثال: 1"></td>
      <td><input type="text" class="form-control form-control-sm" name="passengers[__I__][notes]"></td>
      <td><div class="ey-row-actions"><button type="button" class="btn btn-soft-danger btn-sm tb-remove-p" title="إزالة"><i class="ri-delete-bin-line"></i></button></div></td>
   </tr>
</template>

<script src="{{asset('assets/dselect.js')}}"></script>
<script>
(function () {
   var itemIndex = {{ count($items) + 100 }}, pIndex = {{ count($passengers) + 100 }};
   var num = function (v) { return parseFloat(v) || 0; };
   var r2 = function (v) { return Math.round(v * 100) / 100; };
   var fmt = function (v) { return r2(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };

   dselect(document.querySelector('#customer_id'), { search: true });
   document.querySelectorAll('#tb-items .tb-item[data-locked="0"] .tb-supplier').forEach(function (s) { dselect(s, { search: true }); });

   // one row: hotel -> nights from the dates, quantity = rooms x nights (the server calculates the same)
   function calcRow(tr) {
      var hotel = tr.querySelector('.tb-type').value === 'hotel';
      var qty = tr.querySelector('.tb-qty'), nights = tr.querySelector('.tb-nights'), rooms = tr.querySelector('.tb-rooms');
      if (hotel) {
         var s = tr.querySelector('.tb-start').value, e = tr.querySelector('.tb-end').value;
         if (s && e) { nights.value = Math.max(0, Math.round((new Date(e) - new Date(s)) / 86400000)); }
         qty.value = num(rooms.value) * num(nights.value);
      }
      qty.readOnly = hotel || tr.dataset.locked === '1';
      nights.readOnly = hotel || tr.dataset.locked === '1';
      rooms.required = hotel;
      var cost = r2(num(qty.value) * num(tr.querySelector('.tb-cost').value));
      var sale = r2(num(qty.value) * num(tr.querySelector('.tb-price').value));
      tr.querySelector('.tb-total-cost').textContent = fmt(cost);
      tr.querySelector('.tb-total-sale').textContent = fmt(sale);
      tr.querySelector('.tb-profit').textContent = fmt(sale - cost);
      return [cost, sale, tr.dataset.locked === '1'];
   }
   function calcAll() {
      var c = 0, s = 0;
      document.querySelectorAll('#tb-items .tb-item').forEach(function (tr) {
         var v = calcRow(tr);
         if (!v[2]) { c += v[0]; s += v[1]; }   // cancelled services are not part of the active totals
      });
      document.getElementById('tb-sum-cost').textContent = fmt(c);
      document.getElementById('tb-sum-sale').textContent = fmt(s);
      document.getElementById('tb-sum-profit').textContent = fmt(s - c);
      document.getElementById('tb-loss').style.display = s < c ? '' : 'none';
   }
   document.getElementById('tb-items').addEventListener('input', calcAll);
   document.getElementById('tb-items').addEventListener('change', calcAll);
   document.getElementById('tb-add-item').addEventListener('click', function () {
      var html = document.getElementById('tb-item-tpl').innerHTML.replace(/__I__/g, itemIndex++);
      var body = document.querySelector('#tb-items tbody');
      body.insertAdjacentHTML('beforeend', html);
      dselect(body.lastElementChild.querySelector('.tb-supplier'), { search: true });
      calcAll();
   });
   document.getElementById('tb-items').addEventListener('click', function (e) {
      var b = e.target.closest('.tb-remove');
      if (b) { b.closest('tr').remove(); calcAll(); }
   });
   document.getElementById('tb-add-p').addEventListener('click', function () {
      document.querySelector('#tb-passengers tbody').insertAdjacentHTML('beforeend', document.getElementById('tb-p-tpl').innerHTML.replace(/__I__/g, pIndex++));
   });
   document.getElementById('tb-passengers').addEventListener('click', function (e) {
      var b = e.target.closest('.tb-remove-p');
      if (b) { b.closest('tr').remove(); }
   });
   calcAll();
})();
</script>
@endsection
