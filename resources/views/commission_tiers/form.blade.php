@extends('layouts.app')
@section('content')
@section('title' , $table ? 'تعديل جدول شرائح العمولات' : 'اضافة جدول شرائح العمولات')
@php
    $plain = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $rows = old('tiers', $tiers->map(fn ($t) => ['from' => $plain($t->from_amount), 'to' => $plain($t->to_amount), 'rate' => $plain($t->rate)])->all());
    if (!$rows) {
        $rows = [['from' => '1', 'to' => '', 'rate' => '']];
    }
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="{{ $table ? 'تعديل جدول الشرائح: ' . $table->name : 'اضافة جدول شرائح العمولات' }}">
            <a href="{{route('site.commission_tiers')}}" class="btn btn-light">جداول الشرائح</a>
         </x-page-header>
         <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger" role="alert">
               <ul class="mb-0">
                  @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                  @endforeach
               </ul>
            </div>
            @endif

            <form action="{{ $table ? route('site.commission_tiers_update', $table->id) : route('site.commission_tiers_store') }}" method="POST" autocomplete="off" id="tier_table_form">
               @csrf
               <div class="row">
                  <div class="col-md-8 mb-3">
                     <label class="form-label" for="name">اسم الجدول <span class="text-danger">*</span></label>
                     <input type="text" name="name" id="name" value="{{ old('name', optional($table)->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="مثال: العمولة القياسية للموظفين" required>
                  </div>
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="status">الحالة <span class="text-danger">*</span></label>
                     <select name="status" id="status" class="form-select" required>
                        <option value="1" @selected((string) old('status', optional($table)->status ?? 1) === '1')>مفعل</option>
                        <option value="0" @selected((string) old('status', optional($table)->status ?? 1) === '0')>معطل</option>
                     </select>
                  </div>
               </div>

               <h6 style="font-size: 16px;">الشرائح <span class="text-danger">*</span></h6>
               <p class="text-muted fs-12 mb-2">
                  أدخل الشرائح متصلة ومرتبة: كل شريحة تبدأ بعد نهاية السابقة مباشرة (مثال: 1 – 10,000 ثم 10,001 – 50,000).
                  اترك «إلى» فارغاً في الشريحة الأخيرة فقط لتكون بدون حد أعلى. النسبة من 0 إلى 100%.
                  الشريحة تُحدد من إجمالي ربح الموظف خلال الفترة: 10,000 في الشريحة الأولى و 10,001 في الثانية.
               </p>
               <div class="table-responsive">
                  <table class="table table-bordered align-middle" id="tiers_table">
                     <thead>
                        <tr>
                           <th style="width:70px;">#</th>
                           <th>من (إجمالي الربح)</th>
                           <th>إلى (إجمالي الربح)</th>
                           <th>نسبة العمولة %</th>
                           <th style="width:70px;"></th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach(array_values($rows) as $i => $row)
                        <tr>
                           <td class="tier-no">{{ $i + 1 }}</td>
                           <td><input type="number" step="0.01" min="0" name="tiers[{{$i}}][from]" value="{{ $row['from'] ?? '' }}" class="form-control tier-from @error("tiers.$i.from") is-invalid @enderror" required></td>
                           <td><input type="number" step="0.01" min="0" name="tiers[{{$i}}][to]" value="{{ $row['to'] ?? '' }}" class="form-control tier-to @error("tiers.$i.to") is-invalid @enderror" placeholder="بدون حد أعلى"></td>
                           <td><input type="number" step="0.01" min="0" max="100" name="tiers[{{$i}}][rate]" value="{{ $row['rate'] ?? '' }}" class="form-control @error("tiers.$i.rate") is-invalid @enderror" required></td>
                           <td><button type="button" class="btn btn-soft-danger btn-sm remove-tier" title="ازالة"><i class="ri-delete-bin-line"></i></button></td>
                        </tr>
                        @endforeach
                     </tbody>
                  </table>
               </div>
               <button type="button" class="btn btn-success btn-sm mb-3" id="add_tier"><i class="ri-add-line"></i> اضافة شريحة</button>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary"><i class="ri-save-line"></i> حفظ جدول الشرائح</button>
               </div>
            </form>
         </div>
      </div>
   </div>
</div>

<script type="text/javascript">
(function () {
    var body = document.querySelector('#tiers_table tbody');
    var next = {{ count($rows) }};

    function renumber() {
        body.querySelectorAll('tr').forEach(function (tr, i) { tr.querySelector('.tier-no').textContent = i + 1; });
    }

    document.getElementById('add_tier').addEventListener('click', function () {
        var i = next++;
        // suggest the next lower limit: right after the last upper limit
        var lastTo = Array.prototype.slice.call(body.querySelectorAll('.tier-to')).map(function (el) { return el.value; }).pop();
        var from = lastTo !== undefined && lastTo !== '' ? (Math.floor(parseFloat(lastTo)) + 1) : '';
        var tr = document.createElement('tr');
        tr.innerHTML = '<td class="tier-no"></td>'
            + '<td><input type="number" step="0.01" min="0" name="tiers[' + i + '][from]" value="' + from + '" class="form-control tier-from" required></td>'
            + '<td><input type="number" step="0.01" min="0" name="tiers[' + i + '][to]" class="form-control tier-to" placeholder="بدون حد أعلى"></td>'
            + '<td><input type="number" step="0.01" min="0" max="100" name="tiers[' + i + '][rate]" class="form-control" required></td>'
            + '<td><button type="button" class="btn btn-soft-danger btn-sm remove-tier" title="ازالة"><i class="ri-delete-bin-line"></i></button></td>';
        body.appendChild(tr);
        renumber();
    });

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-tier');
        if (!btn) { return; }
        if (body.querySelectorAll('tr').length === 1) { return; } // at least one tier
        btn.closest('tr').remove();
        renumber();
    });
})();
</script>
@endsection
