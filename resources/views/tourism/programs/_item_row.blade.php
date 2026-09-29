{{-- One service row of the program form ($i index -- "__I__" in the template --, $it values, $accounts). --}}
<tr>
   <td style="min-width:130px">
      <select class="form-select form-select-sm" name="items[{{$i}}][service_type]">
         @foreach(\App\Models\TourismBookingItem::TYPES as $k => $label)<option value="{{$k}}" @selected(($it['service_type'] ?? 'hotel') === $k)>{{$label}}</option>@endforeach
      </select>
   </td>
   <td style="min-width:200px">
      <input type="text" class="form-control form-control-sm" name="items[{{$i}}][description]" value="{{$it['description'] ?? ''}}" required>
      <input type="text" class="form-control form-control-sm mt-1" name="items[{{$i}}][notes]" value="{{$it['notes'] ?? ''}}" placeholder="ملاحظات">
   </td>
   <td style="min-width:200px">
      <select class="form-select form-select-sm tp-supplier" name="items[{{$i}}][supplier_id]">
         <option value="">-- بدون --</option>
         @foreach($accounts as $a)<option value="{{$a->id}}" @selected((string) ($it['supplier_id'] ?? '') === (string) $a->id)>{{$a->name}}</option>@endforeach
      </select>
   </td>
   <td style="min-width:70px"><input type="number" min="1" class="form-control form-control-sm" name="items[{{$i}}][day_no]" value="{{$it['day_no'] ?? ''}}"></td>
   <td style="min-width:70px"><input type="number" min="1" class="form-control form-control-sm" name="items[{{$i}}][nights]" value="{{$it['nights'] ?? ''}}"></td>
   <td style="min-width:70px"><input type="number" min="1" class="form-control form-control-sm" name="items[{{$i}}][rooms]" value="{{$it['rooms'] ?? ''}}"></td>
   <td style="min-width:110px"><input type="text" class="form-control form-control-sm" name="items[{{$i}}][room_type]" value="{{$it['room_type'] ?? ''}}"></td>
   <td style="min-width:100px">
      <select class="form-select form-select-sm" name="items[{{$i}}][pricing]">
         @foreach(\App\Models\TourismProgramItem::PRICING as $k => $label)<option value="{{$k}}" @selected(($it['pricing'] ?? 'per_unit') === $k)>{{$label}}</option>@endforeach
      </select>
   </td>
   <td style="min-width:80px"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{$i}}][quantity]" value="{{$it['quantity'] ?? 1}}"></td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{$i}}][unit_cost]" value="{{$it['unit_cost'] ?? ''}}" required></td>
   <td style="min-width:100px"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{$i}}][unit_price]" value="{{$it['unit_price'] ?? ''}}" required></td>
   <td><div class="ey-row-actions"><button type="button" class="btn btn-soft-danger btn-sm tp-remove" title="إزالة"><i class="ri-delete-bin-line"></i></button></div></td>
</tr>
