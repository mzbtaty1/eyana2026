@extends('layouts.app')
@section('content')
@section('title' , "سداد الفاتورة")
<style>
    .aler_error{
        display: none !important;
    }
</style>

@if($errors->any())

<script>
swal("", "{{$errors->first()}}", "info");

</script>
@endif 
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> سداد الفاتورة : <b>{{$invoice_info->es_id}}</b> — {{$client->name ?? ''}} </h5>
         </div>
         <div class="card-body">
                 <table class="table table-bordered" id="dynamicTableTwo">
                  <tr>
                     <th>الاسم</th>
                     <th>نوع الراكب</th>
                     <th>سعر التكلفة</th>
                     <th>سعر البيع</th>
                     <th>رقم الحجز</th>
                     <th>رقم التكت</th>
                  </tr>
                   <?php $x = 0; ?>
                   @foreach($users as $user)
                  <tr>
                     <td>
                        <input type="text" value="{{$user->client_name}}" name="ticket_info[{{$x}}][name]" placeholder="الاسم" class="form-control" disabled>
                     </td>
                     <td>
                        <select class="form-control" name="ticket_info[{{$x}}][client_type]" disabled>
                           <option value="1" @if($user->client_type == 1) selected @endif>رضيع</option>
                           <option value="2" @if($user->client_type == 2) selected @endif>طفل</option>
                           <option value="3" @if($user->client_type == 3) selected @endif>بالغ</option>
                        </select>
                     </td>
                     <td>
                        <input type="number" name="ticket_info[{{$x}}][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" value="{{$user->client_net_pice}}" disabled="" />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[{{$x}}][bought_price]" oninput="findTotal2()" oninput="" placeholder="سعر البيع" class="form-control amount2" value="{{$user->client_bought_price}}" disabled="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_booking_id}}" name="ticket_info[{{$x}}][book_id]" placeholder="رقم الحجز" class="form-control" disabled="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_ticket_id}}" name="ticket_info[{{$x}}][tikcet_id]" placeholder="رقم الحجز" class="form-control" disabled="" />
                     </td>
                     
                    
                    
                  </tr>
                   <?php $x++; ?>
                   @endforeach
               </table>
               <br>
            @if(!$summary)
                <div class="alert alert-warning border-0" style="font-size: 16px;">
                    <i class="ri-information-line"></i>
                    تسجيل السداد متاح لفواتير عميل الكونتر فقط.
                </div>
            @else
            <?php $fmt = fn ($v) => number_format((float) $v, 2); ?>
            <table class="table table-bordered" style="max-width: 640px;">
                <tr><th>رقم الفاتورة</th><td>{{$invoice_info->es_id}}</td></tr>
                <tr><th>العميل</th><td>{{$client->name ?? '-'}}</td></tr>
                <tr><th>قيمة الفاتورة</th><td>{{$fmt($summary['total'])}} ج.م</td></tr>
                <tr><th>المسدد</th><td>{{$fmt($summary['paid'])}} ج.م</td></tr>
                @if($summary['refunded'] > 0)
                <tr><th>مسترد للعميل (مرتجعات)</th><td>{{$fmt($summary['refunded'])}} ج.م</td></tr>
                @endif
                @if($summary['paid_out'] > 0)
                <tr><th>مردود للعميل (سندات دفع)</th><td>{{$fmt($summary['paid_out'])}} ج.م</td></tr>
                @endif
                @if($summary['remaining'] < 0)
                <tr class="table-warning"><th>مستحق للعميل</th><td>{{$fmt(-$summary['remaining'])}} ج.م</td></tr>
                @else
                <tr class="table-primary"><th>المتبقي</th><td><b>{{$fmt($summary['remaining'])}} ج.م</b></td></tr>
                @endif
                <tr><th>حالة السداد</th><td>{{$summary['label']}}</td></tr>
            </table>

            @if($summary['due_to_client'] > 0.005)
            {{-- Refund payout: an ordinary payment voucher («سند دفع», BondsController::save)
                 linked to this invoice; limited to the amount due to the client. --}}
            <form action="{{route('site.bonds_save')}}" onsubmit="return validatePayout()" name="payoutForm" method="POST" autocomplete="off" style="max-width: 640px;">
               @csrf
                <input type="hidden" name="type_slctd" value="1">
                <input type="hidden" name="invoice_id" value="{{$invoice_info->id}}">
                <input type="hidden" name="supp_id" value="{{$invoice_info->invoice_beneficiaries}}">
                <input type="hidden" name="commission" value="0">
                <input type="hidden" name="sub_id" value="0">
                <input type="hidden" name="crt_date" value="{{date('Y-m-d')}}">
                <h6 class="mb-2">رد المبلغ للعميل</h6>
                <p class="mb-1"><b>المبلغ المردود</b> <small class="text-muted">(حتى {{$fmt($summary['due_to_client'])}} ج.م)</small></p>
                <input type="number" step="0.01" min="0.01" max="{{$summary['due_to_client']}}" name="amount" id="payout_amount" class="form-control" value="{{$summary['due_to_client']}}" style="text-align: right;direction: rtl;" required>
                <br>
                <p class="mb-1"><b>من الخزنة</b></p>
                <select name="storage_id" class="form-select" required>
                    @foreach($storages as $storage)
                    <option value="{{$storage->id}}">{{$storage->name}}</option>
                    @endforeach
                </select>
                <br>
                <p class="mb-1"><b>طريقة الرد</b></p>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="money_way" id="out_cash" value="1" checked onchange="onOutWayChange()">
                    <label class="form-check-label" for="out_cash">خزنة (نقدي)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="money_way" id="out_bank" value="2" onchange="onOutWayChange()">
                    <label class="form-check-label" for="out_bank">بنك</label>
                </div>
                <div id="out_bank_box" class="mt-2" style="display:none;">
                    <select name="bank_id" id="out_bank_id" class="form-select">
                        <option value="">-- اختر البنك --</option>
                        @foreach($banks as $bank)
                        <option value="{{$bank->id}}">{{$bank->bank_name}}</option>
                        @endforeach
                    </select>
                </div>
                <br>
                <input type="text" name="transaction_info" class="form-control" value="رد مبلغ مستحق لفاتورة {{$invoice_info->es_id}}">
                <br>
                <div class="d-grid gap-2">
                    <button class="btn btn-warning"><i class="ri-refund-2-line"></i> تسجيل رد المبلغ للعميل (سند دفع)</button>
                </div>
            </form>
            @elseif($summary['remaining'] <= 0.005)
                <div class="alert alert-success border-0" style="font-size: 16px;">
                    <i class="ri-information-line"></i>
                    لا يوجد مبلغ متبقي على فاتورة {{$invoice_info->es_id}}.
                </div>
            @else
            <form action="{{route('site.pay_part_save')}}" onsubmit="return validateForm()" name="myForm" method="POST" autocomplete="off" style="max-width: 640px;">
               @csrf
                <input type="hidden" name="id" value="{{$invoice_info->id}}">
                <p class="mb-1"><b>مبلغ السداد</b> <small class="text-muted">(حتى {{$fmt($summary['remaining'])}} ج.م)</small></p>
                <input type="number" step="0.01" min="0.01" max="{{$summary['remaining']}}" name="money_pay" id="money_pay" class="form-control" placeholder="المبلغ : (*)" style="text-align: right;direction: rtl;" required>
                <br>
                <p class="mb-1"><b>طريقة السداد</b></p>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="money_way" id="way_cash" value="1" checked onchange="onWayChange()">
                    <label class="form-check-label" for="way_cash">خزنة (نقدي)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="money_way" id="way_bank" value="2" onchange="onWayChange()">
                    <label class="form-check-label" for="way_bank">بنك</label>
                </div>
                <div id="bank_box" class="mt-2" style="display:none;">
                    <select name="bank_id" id="bank_id" class="form-select">
                        <option value="">-- اختر البنك --</option>
                        @foreach($banks as $bank)
                        <option value="{{$bank->id}}">{{$bank->bank_name}}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">السداد البنكي يُسجل في البنك المختار وفي الخزنة، مثل سند القبض البنكي.</small>
                </div>
                <br>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary"><i class="ri-save-line"></i> تسجيل السداد</button>
                </div>
            </form>
            @endif
            @endif
         </div>
      </div>
   </div>
   <!--end col-->
</div>
<script type="text/javascript">
function onOutWayChange() {
    document.getElementById('out_bank_box').style.display = document.getElementById('out_bank').checked ? 'block' : 'none';
}
function validatePayout() {
    var due = {{ $summary ? $summary['due_to_client'] : 0 }};
    var x = parseFloat(document.forms["payoutForm"]["amount"].value);
    if (isNaN(x) || x <= 0) { swal("", "برجاء إدخال مبلغ صحيح أكبر من صفر", "error"); return false; }
    if (x > due + 0.005) { swal("", "قيمة الرد أكبر من المبلغ المستحق للعميل على الفاتورة.", "error"); return false; }
    if (document.getElementById('out_bank').checked && !document.getElementById('out_bank_id').value) { swal("", "برجاء اختيار البنك", "error"); return false; }
    return true;
}
function onWayChange() {
    document.getElementById('bank_box').style.display = document.getElementById('way_bank').checked ? 'block' : 'none';
}
function validateForm() {
    var remaining = {{ $summary ? max(0, $summary['remaining']) : 0 }};
    var x = parseFloat(document.forms["myForm"]["money_pay"].value);
    if (isNaN(x) || x <= 0) {
        swal("", "برجاء إدخال مبلغ سداد صحيح أكبر من صفر", "error");
        return false;
    }
    if (x > remaining + 0.005) {
        swal("", "قيمة السداد أكبر من المبلغ المتبقي.", "error");
        return false;
    }
    if (document.getElementById('way_bank').checked && !document.getElementById('bank_id').value) {
        swal("", "برجاء اختيار البنك", "error");
        return false;
    }
    return true;
}
</script>
@endsection
