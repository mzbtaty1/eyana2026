@extends('layouts.app')
@section('content')
@section('title' , "تعديل فاتورة تأشيرة")
{{--
    Visa Invoice edit form (section 2), opened by edit_invoice() instead of the flight form.
    Saved by InvoicesController@saveVisaUpdate through the existing save_update() (same
    passenger-by-passenger ledger adjustments). Applicants cannot be added or removed here,
    as in the flight edit form.
--}}
@php
    $ben = App\Models\Supplier::find($invoice_info->invoice_beneficiaries);
    $file = App\Models\Invoice::ticketFileOrNull($invoice_info->invoice_ticket_file);
    $currentVisa = $invoice_info->invoice_airline;
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> تعديل فاتورة تأشيرة : <b>{{$invoice_info->es_id}}</b>
               @if($isRefund) <span class="badge bg-warning text-dark">استرجاع</span> @endif
            </h5>
         </div>
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

            <form action="{{route('site.invoices_visa_save_update')}}" method="POST" autocomplete="off" enctype="multipart/form-data" id="visa_invoice_form">
               @csrf
               <input type="hidden" name="id" value="{{$invoice_info->id}}">
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label" for="visa_name">نوع التأشيرة <span class="text-danger">*</span></label>
                     <select class="form-control" name="visa_name" id="visa_name" style="text-align:right;" required>
                        @unless($visaNames->contains($currentVisa))
                        <option value="{{$currentVisa}}" @selected(old('visa_name', $currentVisa) == $currentVisa)>{{$currentVisa}} (الحالي)</option>
                        @endunless
                        @foreach($visaNames as $name)
                        <option value="{{$name}}" @selected(old('visa_name', $currentVisa) == $name)>{{$name}}</option>
                        @endforeach
                     </select>
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label" for="invoice_date">تاريخ الفاتورة</label>
                     <input type="date" id="invoice_date" value="{{$invoice_info->invoice_date}}" class="form-control" style="text-align:right;" readonly>
                  </div>
               </div>

               <div class="mb-3">
                  <label class="form-label" for="invoice_beneficiaries">اسم العميل (المستفيد) <span class="text-danger">*</span></label>
                  @if($isCounter)
                  {{-- Counter Customer invoice: the customer stays the Counter Customer --}}
                  <input type="hidden" name="invoice_beneficiaries" value="{{$invoice_info->invoice_beneficiaries}}">
                  <input type="text" class="form-control" id="invoice_beneficiaries" value="{{$ben->name ?? ''}}" style="text-align:right;" readonly>
                  @else
                  <select class="form-control" name="invoice_beneficiaries" id="invoice_beneficiaries" style="text-align:right;" required>
                     @foreach($suppliers as $supplier)
                     <option value="{{$supplier->id}}" @selected(old('invoice_beneficiaries', $invoice_info->invoice_beneficiaries) == $supplier->id)>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                     @endforeach
                  </select>
                  @endif
               </div>

               <div class="mb-3">
                  <label class="form-label" for="vendor_id">اسم المورد (المنفذ) <span class="text-danger">*</span></label>
                  <select name="vendor_id" id="vendor_id" class="form-control" required>
                     @foreach($suppliers as $supplier)
                     <option value="{{$supplier->id}}" @selected(old('vendor_id', $vendorId) == $supplier->id)>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                     @endforeach
                  </select>
               </div>

               <h6 style="font-size: 16px;">مقدمو الطلب <span class="text-danger">*</span></h6>
               <div class="table-responsive">
                  <table class="table table-bordered" id="applicants_table">
                     <thead>
                        <tr>
                           <th>اسم مقدم الطلب</th>
                           <th>رقم جواز السفر</th>
                           <th>رقم الطلب / التأشيرة</th>
                           <th>{{ $isRefund ? 'مسترد للعميل (دائن العميل)' : 'سعر التكلفة' }}</th>
                           <th>{{ $isRefund ? 'مرتجع لنا من المورد (مدين المورد)' : 'سعر البيع' }}</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach($users as $i => $user)
                        <tr>
                           <td>
                              <input type="hidden" name="applicants[{{$i}}][id]" value="{{$user->id}}">
                              <input type="text" name="applicants[{{$i}}][name]" value="{{old("applicants.$i.name", $user->client_name)}}" placeholder="اسم مقدم الطلب" class="form-control" required>
                           </td>
                           <td><input type="text" name="applicants[{{$i}}][passport]" value="{{old("applicants.$i.passport", $user->client_passport_id)}}" placeholder="رقم جواز السفر" class="form-control" required></td>
                           <td><input type="text" name="applicants[{{$i}}][visa_number]" value="{{old("applicants.$i.visa_number", $user->client_ticket_id)}}" placeholder="رقم الطلب / التأشيرة" class="form-control"></td>
                           <td><input type="number" step="0.01" min="0" name="applicants[{{$i}}][cost]" value="{{old("applicants.$i.cost", $shares[$user->id][1] ?? $user->client_net_pice)}}" class="form-control visa-cost" required></td>
                           <td><input type="number" step="0.01" min="0" name="applicants[{{$i}}][sale]" value="{{old("applicants.$i.sale", $shares[$user->id][0] ?? $user->client_bought_price)}}" class="form-control visa-sale" required></td>
                        </tr>
                        @endforeach
                     </tbody>
                  </table>
               </div>

               <div class="row">
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="total_cost">{{ $isRefund ? 'مسترد للعميل (المجموع)' : 'اجمالي التكلفة' }}</label>
                     <input type="text" id="total_cost" class="form-control" style="text-align:right;" disabled>
                  </div>
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="total_sale">{{ $isRefund ? 'مرتجع لنا من المورد (المجموع)' : 'اجمالي البيع' }}</label>
                     <input type="text" id="total_sale" class="form-control" style="text-align:right;" disabled>
                  </div>
                  @unless($isRefund)
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="total_profit">الربح</label>
                     <input type="text" id="total_profit" class="form-control" style="text-align:right;" disabled>
                  </div>
                  @endunless
               </div>
               @unless($isRefund)
               <div class="alert alert-danger" id="visa_loss_alert" style="display: none;">
                  <center>تنويه : سوف يؤدي ذلك الي حدوث مديونية</center>
               </div>
               @endunless

               <div class="mb-3">
                  <label class="form-label" for="invoice_comments">ملاحظات</label>
                  <input type="text" name="invoice_comments" id="invoice_comments" value="{{old('invoice_comments', $invoice_info->invoice_comments)}}" placeholder="ملاحظات" class="form-control" style="text-align:right;">
               </div>

               <div class="mb-3">
                  <label class="form-label" for="myPoster">مرفق (اختياري) (المسموح : JPG , PNG , JPEG , WEBP , PDF فقط)</label>
                  <p class="mb-1" id="current_attachment">
                     @if($file)
                     المرفق الحالي: <a href="{{asset($file)}}" target="_blank">عرض المرفق</a> — اترك الحقل فارغا للإبقاء عليه
                     @else
                     لا يوجد مرفق
                     @endif
                  </p>
                  <input type="file" class="form-control" name="myPoster" id="myPoster" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf">
               </div>

               @if(Auth::user()->account_type == 2)
               <div class="mb-3">
                  <label class="form-label" for="added_user">الحساب الذي ترغب في اضافة الفاتورة فيه من الموظفين</label>
                  <select class="form-select" name="added_user" id="added_user">
                     @foreach(App\Models\User::all() as $u)
                     <option value="{{$u->id}}" @selected(old('added_user', $invoice_info->invoice_create_by) == $u->id)>{{$u->name}}</option>
                     @endforeach
                  </select>
               </div>
               @endif

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                  حفظ تعديل فاتورة التأشيرة
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
</div>

<script src="{{asset('assets/dselect.js')}}"></script>
<script type="text/javascript">
@unless($isCounter)
    dselect(document.querySelector('#invoice_beneficiaries'), { search: true });
@endunless
    dselect(document.querySelector('#vendor_id'), { search: true });

    function sumOf(selector) {
        var tot = 0;
        $(selector).each(function () { tot += parseFloat(this.value) || 0; });
        return Math.round(tot * 100) / 100;
    }

    function visaTotals() {
        var cost = sumOf('.visa-cost'), sale = sumOf('.visa-sale');
        $('#total_cost').val(cost);
        $('#total_sale').val(sale);
        $('#total_profit').val(Math.round((sale - cost) * 100) / 100);
        $('#visa_loss_alert').toggle(sale < cost);
    }

    $(document).on('input', '.visa-cost, .visa-sale', visaTotals);
    visaTotals();
</script>
@endsection
