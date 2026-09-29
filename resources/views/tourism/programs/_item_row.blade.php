{{--
    One service row of the program form ($i index -- "__I__" in the template --, $it values, $accounts).
    Only the fields of the row's type are shown (TourismBookingItem::FIELDS, groups .tb-f): a hotel has
    nights / rooms / room type and is priced per room-night (its quantity = rooms x nights); transport
    has a route; the other types a quantity and a pricing (per unit / per person).
--}}
@php
   $type = $it['service_type'] ?? 'hotel';
   $n = "items[$i]";
   $others = implode(',', array_diff(array_keys(\App\Models\TourismBookingItem::TYPES), ['hotel']));
@endphp
<tr class="tp-item">
   <td style="min-width:130px">
      <select class="form-select form-select-sm tb-type" name="{{$n}}[service_type]">
         @foreach(\App\Models\TourismBookingItem::TYPES as $k => $label)<option value="{{$k}}" @selected($type === $k)>{{$label}}</option>@endforeach
      </select>
   </td>
   <td style="min-width:190px">
      <input type="text" class="form-control form-control-sm" name="{{$n}}[description]" value="{{$it['description'] ?? ''}}" required>
      <input type="text" class="form-control form-control-sm mt-1" name="{{$n}}[notes]" value="{{$it['notes'] ?? ''}}" placeholder="ملاحظات">
   </td>
   <td style="min-width:190px">
      <select class="form-select form-select-sm tp-supplier" name="{{$n}}[supplier_id]">
         <option value="">-- بدون --</option>
         @foreach($accounts as $a)<option value="{{$a->id}}" @selected((string) ($it['supplier_id'] ?? '') === (string) $a->id)>{{$a->name}}</option>@endforeach
      </select>
   </td>
   <td style="min-width:280px">
      <div class="row g-1">
         <div class="col-4"><label class="form-label fs-11 mb-0">اليوم</label><input type="number" min="1" class="form-control form-control-sm" name="{{$n}}[day_no]" value="{{$it['day_no'] ?? ''}}"></div>
         <div class="col-8 tb-f @if($type !== 'hotel') d-none @endif" data-types="hotel">
            <div class="row g-1">
               <div class="col-4"><label class="form-label fs-11 mb-0">ليالي</label><input type="number" min="1" class="form-control form-control-sm" name="{{$n}}[nights]" value="{{$it['nights'] ?? ''}}" @disabled($type !== 'hotel')></div>
               <div class="col-4"><label class="form-label fs-11 mb-0">غرف</label><input type="number" min="1" class="form-control form-control-sm" name="{{$n}}[rooms]" value="{{$it['rooms'] ?? ''}}" @disabled($type !== 'hotel')></div>
               <div class="col-4"><label class="form-label fs-11 mb-0">نوع الغرفة</label><input type="text" class="form-control form-control-sm" name="{{$n}}[room_type]" value="{{$it['room_type'] ?? ''}}" @disabled($type !== 'hotel')></div>
            </div>
         </div>
         <div class="col-8 tb-f @if($type !== 'transport') d-none @endif" data-types="transport">
            <div class="row g-1">
               <div class="col-6"><label class="form-label fs-11 mb-0">من</label><input type="text" class="form-control form-control-sm" name="{{$n}}[route_from]" value="{{$it['route_from'] ?? ''}}" @disabled($type !== 'transport')></div>
               <div class="col-6"><label class="form-label fs-11 mb-0">إلى</label><input type="text" class="form-control form-control-sm" name="{{$n}}[route_to]" value="{{$it['route_to'] ?? ''}}" @disabled($type !== 'transport')></div>
            </div>
         </div>
      </div>
   </td>
   <td style="min-width:200px">
      <div class="tb-f @if($type === 'hotel') d-none @endif" data-types="{{$others}}">
         <div class="row g-1">
            <div class="col-6">
               <label class="form-label fs-11 mb-0">التسعير</label>
               <select class="form-select form-select-sm" name="{{$n}}[pricing]" @disabled($type === 'hotel')>
                  @foreach(\App\Models\TourismProgramItem::PRICING as $k => $label)<option value="{{$k}}" @selected(($it['pricing'] ?? 'per_unit') === $k)>{{$label}}</option>@endforeach
               </select>
            </div>
            <div class="col-6"><label class="form-label fs-11 mb-0">الكمية</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="{{$n}}[quantity]" value="{{$it['quantity'] ?? 1}}" @disabled($type === 'hotel')></div>
         </div>
         <div class="text-muted fs-11 tb-qty-hint">{{\App\Models\TourismBookingItem::fields($type)['quantity']}}</div>
      </div>
      <div class="tb-f text-muted fs-12 @if($type !== 'hotel') d-none @endif" data-types="hotel">
         <input type="hidden" name="{{$n}}[pricing]" value="per_unit" @disabled($type !== 'hotel')>
         الكمية = الغرف × الليالي (السعر للغرفة في الليلة)
      </div>
   </td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="{{$n}}[unit_cost]" value="{{$it['unit_cost'] ?? ''}}" required></td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="{{$n}}[unit_price]" value="{{$it['unit_price'] ?? ''}}" required></td>
   <td><div class="ey-row-actions"><button type="button" class="btn btn-soft-danger btn-sm tp-remove" title="إزالة"><i class="ri-delete-bin-line"></i></button></div></td>
</tr>
