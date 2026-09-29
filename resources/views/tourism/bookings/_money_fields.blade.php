{{-- Treasury / bank / amount / date fields of a voucher form ($storages, $banks, $max: suggested amount). --}}
<div class="col-6 col-md-4 col-xl-2"><label class="form-label mb-1">المبلغ</label><input type="number" step="0.01" min="0.01" class="form-control" name="amount" value="{{ $max > 0 ? number_format($max, 2, '.', '') : '' }}" required></div>
<div class="col-6 col-md-4 col-xl-2">
   <label class="form-label mb-1">الخزنة</label>
   <select class="form-select" name="storage_id" required>@foreach($storages as $s)<option value="{{$s->id}}">{{$s->name}}</option>@endforeach</select>
</div>
<div class="col-6 col-md-4 col-xl-2">
   <label class="form-label mb-1">طريقة الدفع</label>
   <select class="form-select tb-money-way" name="money_way" required><option value="1">نقدي</option><option value="2">تحويل بنكي / محفظة</option></select>
</div>
<div class="col-6 col-md-4 col-xl-2 tb-bank" style="display:none">
   <label class="form-label mb-1">البنك</label>
   <select class="form-select" name="bank_id">@foreach($banks as $bk)<option value="{{$bk->id}}">{{$bk->bank_name}}</option>@endforeach</select>
</div>
<div class="col-6 col-md-4 col-xl-2"><label class="form-label mb-1">التاريخ</label><input type="date" class="form-control" name="date" value="{{date('Y-m-d')}}" required></div>
<div class="col-6 col-md-4 col-xl-2"><label class="form-label mb-1">مرجع / ملاحظة</label><input type="text" class="form-control" name="reference" maxlength="200"></div>
