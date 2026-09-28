{{--
    «اعدادات العمولة» (Employees step C) on the admin employee create / edit forms.
    $user: the employee (null on create); $tier_tables: the ACTIVE tier tables;
    $current_tier_table: the assigned table (may be inactive -- shown, never offered).
    Configuration only: the tiers themselves are managed in «شرائح العمولات».
--}}
@php
    $method = old('commission_method', $user->commission_method ?? \App\Models\User::COMMISSION_FIXED);
    $fixed = old('commission', $user ? \App\Models\User::normalizeCommission($user->commission) : '');
    $selected = (string) old('commission_tier_table_id', $user->commission_tier_table_id ?? '');
    $current_tier_table = $current_tier_table ?? null;
    $inactive_current = $current_tier_table && !$current_tier_table->isActive();
@endphp
<div class="border rounded p-3 mb-3" id="commission-settings">
    <h6 class="mb-3" style="text-align: right;"><i class="ri-percent-line align-middle"></i> اعدادات العمولة</h6>

    <p style="text-align: right;" class="mb-1"> طريقة احتساب العمولة </p>
    <div class="d-flex gap-4 mb-3">
        @foreach(\App\Models\User::COMMISSION_METHODS as $value => $label)
        <div class="form-check">
            <input class="form-check-input" type="radio" name="commission_method" id="commission_method_{{$value}}" value="{{$value}}" @checked($method === $value)>
            <label class="form-check-label" for="commission_method_{{$value}}">{{$label}}</label>
        </div>
        @endforeach
    </div>

    <div id="commission-fixed-box">
        <p style="text-align: right;" class="mb-1"> نسبة العمولة الثابتة </p>
        <div class="input-group" style="max-width: 260px;">
            <input type="text" name="commission" inputmode="decimal" class="form-control" style="text-align:right;" value="{{$fixed}}" autocomplete="off">
            <span class="input-group-text">%</span>
        </div>
        <small class="text-muted">من 0 إلى 100 -- تطبق على إجمالي ربح الموظف</small>
    </div>

    <div id="commission-tiered-box">
        <p style="text-align: right;" class="mb-1"> جدول شرائح العمولات </p>
        @if($inactive_current)
        <div class="alert alert-warning py-2">
            الجدول المعين حاليا «{{$current_tier_table->name}}» معطل. اختر جدولا فعالا قبل الحفظ.
        </div>
        @endif
        <select class="form-select" name="commission_tier_table_id" style="max-width: 420px;">
            <option value="">-- اختر جدول الشرائح --</option>
            @if($inactive_current)
            <option value="" disabled @selected($selected === (string) $current_tier_table->id)>{{$current_tier_table->name}} (معطل)</option>
            @endif
            @foreach($tier_tables as $t)
            <option value="{{$t->id}}" @selected($selected === (string) $t->id)>{{$t->name}}</option>
            @endforeach
        </select>
        @if($tier_tables->isEmpty())
        <small class="text-danger d-block">لا توجد جداول شرائح فعالة.</small>
        @endif
        <small class="text-muted d-block">تفاصيل الشرائح من: اعدادات البرنامج ← <a href="{{route('site.commission_tiers')}}" target="_blank">شرائح العمولات</a></small>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('commission-settings');
    function sync() {
        var checked = box.querySelector('input[name="commission_method"]:checked');
        var tiered = checked && checked.value === '{{\App\Models\User::COMMISSION_TIERED}}';
        document.getElementById('commission-fixed-box').style.display = tiered ? 'none' : '';
        document.getElementById('commission-tiered-box').style.display = tiered ? '' : 'none';
        box.querySelector('input[name="commission"]').required = !tiered;
        box.querySelector('select[name="commission_tier_table_id"]').required = tiered;
    }
    box.querySelectorAll('input[name="commission_method"]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
