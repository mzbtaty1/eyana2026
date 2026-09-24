@extends('layouts.app')
@section('content')
@section('title' , "تعديل راكب في الفاتورة")

@if($errors->any())
<script>
swal("", "{{$errors->first()}}", "info");
</script>
@endif

<?php
// Column meaning by ledger side (client_bought_price = debit share, client_net_pice = credit share).
$debitLabel = $isRefund ? 'مرتجع لنا من المورد (مدين المورد)' : 'سعر البيع (مدين العميل)';
$creditLabel = $isRefund ? 'مسترد للعميل (دائن العميل)' : 'سعر التكلفة (دائن المورد)';
?>
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">
                تعديل راكب واحد فقط في الفاتورة : <b>{{$invoice_info->es_id}}</b>
                <a href="{{route('site.invoices_edit', $invoice_info->id)}}" class="btn btn-sm btn-outline-secondary" style="float:left;">تعديل الفاتورة بالكامل</a>
            </h5>
         </div>
         <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success border-0">{{session('success')}}</div>
            @endif

            <p class="text-muted">
                اختر الراكب المراد تعديله. سيتم تعديل هذا الراكب فقط، وتتحرك قيمة الفاتورة في كشف الحساب بفرق هذا الراكب فقط، ويبقى باقي الركاب كما هم.
            </p>

            <table class="table table-bordered align-middle">
               <tr>
                  <th></th>
                  <th>الاسم</th>
                  <th>رقم الحجز</th>
                  <th>رقم التكت</th>
                  <th>{{$creditLabel}}</th>
                  <th>{{$debitLabel}}</th>
               </tr>
               @foreach($users as $user)
               <tr @if($selected && $selected->id == $user->id) class="table-primary" @endif>
                  <td>
                     <a href="{{route('site.invoices_edit_passenger', [$invoice_info->id, 'passenger' => $user->id])}}"
                        class="btn btn-sm @if($selected && $selected->id == $user->id) btn-primary @else btn-outline-primary @endif">اختيار</a>
                  </td>
                  <td>{{$user->client_name}}</td>
                  <td>{{$user->client_booking_id}}</td>
                  <td>{{$user->client_ticket_id}}</td>
                  <td>{{number_format($shares[$user->id][1] ?? 0, 2)}}</td>
                  <td>{{number_format($shares[$user->id][0] ?? 0, 2)}}</td>
               </tr>
               @endforeach
            </table>

            @if($selected)
            <form action="{{route('site.invoices_save_passenger')}}" method="POST" autocomplete="off">
               @csrf
               <input type="hidden" name="id" value="{{$invoice_info->id}}">
               <input type="hidden" name="passenger_id" value="{{$selected->id}}">
               <h6 style="font-size: 16px;">تعديل الراكب : <b>{{$selected->client_name}}</b></h6>
               <table class="table table-bordered">
                  <tr>
                     <th>الاسم</th>
                     <th>نوع الراكب</th>
                     <th>{{$creditLabel}}</th>
                     <th>{{$debitLabel}}</th>
                     <th>رقم الحجز</th>
                     <th>رقم التكت</th>
                     <th>رقم الهاتف</th>
                  </tr>
                  <tr>
                     <td><input type="text" name="name" value="{{$selected->client_name}}" class="form-control" required></td>
                     <td>
                        <select class="form-control" name="client_type" required>
                           <option value="1" @if($selected->client_type == 1) selected @endif>رضيع</option>
                           <option value="2" @if($selected->client_type == 2) selected @endif>طفل</option>
                           <option value="3" @if($selected->client_type == 3) selected @endif>بالغ</option>
                        </select>
                     </td>
                     <td><input type="number" step="0.01" min="0" name="net_price" id="net_price" value="{{$shares[$selected->id][1] ?? 0}}" data-old="{{$shares[$selected->id][1] ?? 0}}" class="form-control" oninput="showPassengerDiff()" required></td>
                     <td><input type="number" step="0.01" min="0" name="bought_price" id="bought_price" value="{{$shares[$selected->id][0] ?? 0}}" data-old="{{$shares[$selected->id][0] ?? 0}}" class="form-control" oninput="showPassengerDiff()" required></td>
                     <td><input type="text" name="book_id" value="{{$selected->client_booking_id}}" class="form-control"></td>
                     <td><input type="text" name="tikcet_id" value="{{$selected->client_ticket_id}}" class="form-control"></td>
                     <td><input type="text" name="client_phone" value="{{$selected->client_phone}}" class="form-control"></td>
                  </tr>
               </table>
               <div class="alert alert-info border-0" id="passenger_diff">
                   الفرق المالي لهذا الراكب فقط — {{$creditLabel}}: <b id="diff_credit">0.00</b> ، {{$debitLabel}}: <b id="diff_debit">0.00</b>
               </div>
               <div class="d-grid gap-2">
                  <button class="btn btn-primary"><i class="ri-save-line"></i> حفظ تعديل هذا الراكب فقط</button>
               </div>
            </form>
            @endif
         </div>
      </div>
   </div>
</div>
<script>
function showPassengerDiff() {
    var fmt = function (v) { return (v > 0 ? '+' : '') + v.toFixed(2); };
    var n = document.getElementById('net_price'), b = document.getElementById('bought_price');
    document.getElementById('diff_credit').textContent = fmt((parseFloat(n.value) || 0) - parseFloat(n.dataset.old));
    document.getElementById('diff_debit').textContent = fmt((parseFloat(b.value) || 0) - parseFloat(b.dataset.old));
}
</script>
@endsection
