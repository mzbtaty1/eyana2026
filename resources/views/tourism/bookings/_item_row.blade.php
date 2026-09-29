{{--
    One service row of the booking form ($i index -- "__I__" in the template --, $it values,
    $accounts, $confirmed: the booking is confirmed). A cancelled service is shown read-only
    (its values are sent back unchanged and ignored by the server); a confirmed booking's own
    services have no remove button (they are cancelled from the booking page instead).
--}}
@php
   $status = $it['status'] ?? 'active';
   $locked = $status === 'cancelled';
   $existing = !empty($it['id']);
   $type = $it['service_type'] ?? 'hotel';
@endphp
<tr class="tb-item {{$locked ? 'table-secondary' : ''}}" data-locked="{{$locked ? 1 : 0}}">
   <td style="min-width:130px">
      <input type="hidden" name="items[{{$i}}][id]" value="{{$it['id'] ?? ''}}">
      <input type="hidden" name="items[{{$i}}][source_program_item_id]" value="{{$it['source_program_item_id'] ?? ''}}">
      <input type="hidden" name="items[{{$i}}][status]" value="{{$status}}">{{-- display only (kept after a failed validation); the server never reads it --}}
      <select class="form-select form-select-sm tb-type" name="items[{{$i}}][service_type]" @if($locked) readonly tabindex="-1" style="pointer-events:none" @endif>
         @foreach(\App\Models\TourismBookingItem::TYPES as $k => $label)<option value="{{$k}}" @selected($type === $k)>{{$label}}</option>@endforeach
      </select>
      @if($locked)<span class="badge bg-danger my_badge mt-1">ملغاة</span>@endif
   </td>
   <td style="min-width:200px">
      <input type="text" class="form-control form-control-sm" name="items[{{$i}}][description]" value="{{$it['description'] ?? ''}}" placeholder="الوصف (اسم الفندق / الرحلة ...)" required @readonly($locked)>
      <input type="text" class="form-control form-control-sm mt-1" name="items[{{$i}}][notes]" value="{{$it['notes'] ?? ''}}" placeholder="ملاحظات" @readonly($locked)>
   </td>
   <td style="min-width:200px">
      <select class="form-select form-select-sm tb-supplier" name="items[{{$i}}][supplier_id]" required @if($locked) readonly tabindex="-1" style="pointer-events:none" @endif>
         <option value="">-- المورد --</option>
         @foreach($accounts as $a)<option value="{{$a->id}}" @selected((string) ($it['supplier_id'] ?? '') === (string) $a->id)>{{$a->name}}</option>@endforeach
      </select>
   </td>
   <td style="min-width:130px"><input type="date" class="form-control form-control-sm tb-start" name="items[{{$i}}][start_date]" value="{{$it['start_date'] ?? ''}}" @readonly($locked)></td>
   <td style="min-width:130px"><input type="date" class="form-control form-control-sm tb-end" name="items[{{$i}}][end_date]" value="{{$it['end_date'] ?? ''}}" @readonly($locked)></td>
   <td style="min-width:70px"><input type="number" min="0" class="form-control form-control-sm tb-nights" name="items[{{$i}}][nights]" value="{{$it['nights'] ?? ''}}" @readonly($locked)></td>
   <td style="min-width:70px"><input type="number" min="1" class="form-control form-control-sm tb-rooms" name="items[{{$i}}][rooms]" value="{{$it['rooms'] ?? ''}}" @readonly($locked)></td>
   <td style="min-width:110px"><input type="text" class="form-control form-control-sm tb-room-type" name="items[{{$i}}][room_type]" value="{{$it['room_type'] ?? ''}}" placeholder="نوع الغرفة" @readonly($locked)></td>
   <td style="min-width:120px">
      <div class="d-flex gap-1">
         <input type="number" min="0" class="form-control form-control-sm" name="items[{{$i}}][adults]" value="{{$it['adults'] ?? ''}}" placeholder="بالغ" title="بالغين" @readonly($locked)>
         <input type="number" min="0" class="form-control form-control-sm" name="items[{{$i}}][children]" value="{{$it['children'] ?? ''}}" placeholder="طفل" title="أطفال" @readonly($locked)>
      </div>
   </td>
   <td style="min-width:80px"><input type="number" step="0.01" min="0" class="form-control form-control-sm tb-qty" name="items[{{$i}}][quantity]" value="{{$it['quantity'] ?? 1}}" @readonly($locked)></td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm tb-cost" name="items[{{$i}}][unit_cost]" value="{{$it['unit_cost'] ?? ''}}" required @readonly($locked)></td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm tb-price" name="items[{{$i}}][unit_price]" value="{{$it['unit_price'] ?? ''}}" required @readonly($locked)></td>
   <td class="tb-total-cost text-nowrap">0</td>
   <td class="tb-total-sale text-nowrap">0</td>
   <td class="tb-profit text-nowrap fw-bold">0</td>
   <td>
      @if(!$locked && !($confirmed && $existing))
      <div class="ey-row-actions"><button type="button" class="btn btn-soft-danger btn-sm tb-remove" title="إزالة"><i class="ri-delete-bin-line"></i></button></div>
      @endif
   </td>
</tr>
