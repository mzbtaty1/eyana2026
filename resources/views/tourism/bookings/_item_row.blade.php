{{--
    One service row of the booking form ($i index -- "__I__" in the template --, $it values,
    $accounts, $confirmed: the booking is confirmed). The «التفاصيل» cell shows only the fields
    of the row's service type (TourismBookingItem::FIELDS): groups marked data-types; a group of
    another type is hidden and its inputs disabled, so they are never sent (the server clears
    them anyway). A cancelled service is shown read-only (its values are sent back unchanged and
    ignored by the server); a confirmed booking's own services have no remove button (they are
    cancelled from the booking page instead).
--}}
@php
   $status = $it['status'] ?? 'active';
   $locked = $status === 'cancelled';
   $existing = !empty($it['id']);
   $type = $it['service_type'] ?? 'hotel';
   $f = \App\Models\TourismBookingItem::fields($type);
   $n = "items[$i]";
   $show = fn (array $types) => in_array($type, $types, true);
@endphp
<tr class="tb-item {{$locked ? 'table-secondary' : ''}}" data-locked="{{$locked ? 1 : 0}}">
   <td style="min-width:130px">
      <input type="hidden" name="{{$n}}[id]" value="{{$it['id'] ?? ''}}">
      <input type="hidden" name="{{$n}}[source_program_item_id]" value="{{$it['source_program_item_id'] ?? ''}}">
      <input type="hidden" name="{{$n}}[status]" value="{{$status}}">{{-- display only (kept after a failed validation); the server never reads it --}}
      <select class="form-select form-select-sm tb-type" name="{{$n}}[service_type]" @if($locked) readonly tabindex="-1" style="pointer-events:none" @endif>
         @foreach(\App\Models\TourismBookingItem::TYPES as $k => $label)<option value="{{$k}}" @selected($type === $k)>{{$label}}</option>@endforeach
      </select>
      @if($locked)<span class="badge bg-danger my_badge mt-1">ملغاة</span>@endif
   </td>
   <td style="min-width:190px">
      <input type="text" class="form-control form-control-sm" name="{{$n}}[description]" value="{{$it['description'] ?? ''}}" placeholder="الوصف (اسم الفندق / السيارة / الرحلة ...)" required @readonly($locked)>
      <input type="text" class="form-control form-control-sm mt-1" name="{{$n}}[notes]" value="{{$it['notes'] ?? ''}}" placeholder="ملاحظات" @readonly($locked)>
   </td>
   <td style="min-width:190px">
      <select class="form-select form-select-sm tb-supplier" name="{{$n}}[supplier_id]" required @if($locked) readonly tabindex="-1" style="pointer-events:none" @endif>
         <option value="">-- المورد --</option>
         @foreach($accounts as $a)<option value="{{$a->id}}" @selected((string) ($it['supplier_id'] ?? '') === (string) $a->id)>{{$a->name}}</option>@endforeach
      </select>
   </td>
   <td class="tb-details" style="min-width:300px">
      {{-- hotel / village --}}
      <div class="tb-f @unless($show(['hotel'])) d-none @endunless" data-types="hotel">
         <div class="row g-1">
            <div class="col-6"><label class="form-label fs-11 mb-0">الدخول</label><input type="date" class="form-control form-control-sm tb-in" name="{{$n}}[start_date]" value="{{$it['start_date'] ?? ''}}" @readonly($locked) @disabled(!$show(['hotel']))></div>
            <div class="col-6"><label class="form-label fs-11 mb-0">الخروج</label><input type="date" class="form-control form-control-sm tb-out" name="{{$n}}[end_date]" value="{{$it['end_date'] ?? ''}}" @readonly($locked) @disabled(!$show(['hotel']))></div>
            <div class="col-4"><label class="form-label fs-11 mb-0">ليالي</label><input type="number" class="form-control form-control-sm tb-nights" name="{{$n}}[nights]" value="{{$it['nights'] ?? ''}}" readonly tabindex="-1" @disabled(!$show(['hotel']))></div>
            <div class="col-4"><label class="form-label fs-11 mb-0">غرف</label><input type="number" min="1" class="form-control form-control-sm tb-rooms" name="{{$n}}[rooms]" value="{{$it['rooms'] ?? ''}}" @readonly($locked) @disabled(!$show(['hotel']))></div>
            <div class="col-4"><label class="form-label fs-11 mb-0">نوع الغرفة</label><input type="text" class="form-control form-control-sm" name="{{$n}}[room_type]" value="{{$it['room_type'] ?? ''}}" @readonly($locked) @disabled(!$show(['hotel']))></div>
            <div class="col-6"><label class="form-label fs-11 mb-0">بالغين</label><input type="number" min="0" class="form-control form-control-sm" name="{{$n}}[adults]" value="{{$it['adults'] ?? ''}}" @readonly($locked) @disabled(!$show(['hotel']))></div>
            <div class="col-6"><label class="form-label fs-11 mb-0">أطفال</label><input type="number" min="0" class="form-control form-control-sm" name="{{$n}}[children]" value="{{$it['children'] ?? ''}}" @readonly($locked) @disabled(!$show(['hotel']))></div>
         </div>
      </div>
      {{-- transport / activity / day use: one service date --}}
      <div class="tb-f @unless($show(\App\Models\TourismBookingItem::DATED)) d-none @endunless" data-types="{{implode(',', \App\Models\TourismBookingItem::DATED)}}">
         <label class="form-label fs-11 mb-0">تاريخ الخدمة</label>
         <input type="date" class="form-control form-control-sm" name="{{$n}}[start_date]" value="{{$it['start_date'] ?? ''}}" @readonly($locked) @disabled(!$show(\App\Models\TourismBookingItem::DATED))>
      </div>
      {{-- transport: route --}}
      <div class="tb-f mt-1 @unless($show(['transport'])) d-none @endunless" data-types="transport">
         <div class="row g-1">
            <div class="col-6"><label class="form-label fs-11 mb-0">من</label><input type="text" class="form-control form-control-sm" name="{{$n}}[route_from]" value="{{$it['route_from'] ?? ''}}" @readonly($locked) @disabled(!$show(['transport']))></div>
            <div class="col-6"><label class="form-label fs-11 mb-0">إلى</label><input type="text" class="form-control form-control-sm" name="{{$n}}[route_to]" value="{{$it['route_to'] ?? ''}}" @readonly($locked) @disabled(!$show(['transport']))></div>
         </div>
      </div>
      <div class="tb-f text-muted fs-12 @unless($show(['package', 'other'])) d-none @endunless" data-types="package,other">لا توجد تفاصيل إضافية لهذا النوع</div>
   </td>
   <td style="min-width:110px">
      <input type="number" step="0.01" min="0" class="form-control form-control-sm tb-qty" name="{{$n}}[quantity]" value="{{$it['quantity'] ?? 1}}" @readonly($locked || $type === 'hotel')>
      <div class="text-muted fs-11 tb-qty-hint">{{$f['quantity']}}</div>
   </td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm tb-cost" name="{{$n}}[unit_cost]" value="{{$it['unit_cost'] ?? ''}}" required @readonly($locked)></td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm tb-price" name="{{$n}}[unit_price]" value="{{$it['unit_price'] ?? ''}}" required @readonly($locked)></td>
   <td class="tb-total-cost text-nowrap">0</td>
   <td class="tb-total-sale text-nowrap">0</td>
   <td class="tb-profit text-nowrap fw-bold">0</td>
   <td>
      @if(!$locked && !($confirmed && $existing))
      <div class="ey-row-actions"><button type="button" class="btn btn-soft-danger btn-sm tb-remove" title="إزالة"><i class="ri-delete-bin-line"></i></button></div>
      @endif
   </td>
</tr>
