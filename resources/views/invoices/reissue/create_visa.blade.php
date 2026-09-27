@extends('layouts.app')
@section('content')
@section('title' , "اعادة اصدار فاتورة تأشيرة")
{{--
    Visa Invoice re-issue form (section 2), opened by invoices_reissue_create() instead of the
    flight re-issue form. Saved by InvoicesController@reissueVisaSave through the existing
    invoices_reissue_save() (new FLY-RS invoice dated today, same ledger rows). No travel date,
    route or PNR; the visa type and applicants (name, passport, application / visa number) are
    carried over, cost and sale are entered fresh.
--}}
@php
    $ben = App\Models\Supplier::find($invoice_info->invoice_beneficiaries);
    $currentVisa = $invoice_info->invoice_airline;
    $applicants = old('applicants', $users->map(fn ($u) => ['name' => $u->client_name, 'passport' => $u->client_passport_id, 'visa_number' => $u->client_ticket_id])->all() ?: [[]]);
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> اعادة اصدار فاتورة تأشيرة : <b>{{$invoice_info->es_id}}</b></h5>
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

            <form action="{{route('site.invoices_reissue_visa_save')}}" method="POST" autocomplete="off" enctype="multipart/form-data" id="visa_reissue_form">
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
                     <label class="form-label" for="invoice_date">تاريخ اعادة الاصدار</label>
                     <input type="date" id="invoice_date" value="{{date('Y-m-d')}}" class="form-control" style="text-align:right;" readonly>
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

               <h6 style="font-size: 16px;">
                  مقدمو الطلب <span class="text-danger">*</span>
                  <br>
                  <small class="text-muted" style="font-size: 13px;">البيانات منقولة من الفاتورة الأصلية؛ أدخل تكلفة وسعر بيع اعادة الاصدار.</small>
               </h6>
               <div class="table-responsive">
                  <table class="table table-bordered" id="applicants_table">
                     <thead>
                        <tr>
                           <th>اسم مقدم الطلب</th>
                           <th>رقم جواز السفر</th>
                           <th>رقم الطلب / التأشيرة</th>
                           <th>سعر التكلفة</th>
                           <th>سعر البيع</th>
                           <th></th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach(array_values($applicants) as $i => $a)
                        <tr>
                           <td><input type="text" name="applicants[{{$i}}][name]" value="{{$a['name'] ?? ''}}" placeholder="اسم مقدم الطلب" class="form-control" required></td>
                           <td><input type="text" name="applicants[{{$i}}][passport]" value="{{$a['passport'] ?? ''}}" placeholder="رقم جواز السفر" class="form-control" required></td>
                           <td><input type="text" name="applicants[{{$i}}][visa_number]" value="{{$a['visa_number'] ?? ''}}" placeholder="رقم الطلب / التأشيرة" class="form-control"></td>
                           <td><input type="number" step="0.01" min="0" name="applicants[{{$i}}][cost]" value="{{$a['cost'] ?? ''}}" placeholder="سعر التكلفة" class="form-control visa-cost" required></td>
                           <td><input type="number" step="0.01" min="0" name="applicants[{{$i}}][sale]" value="{{$a['sale'] ?? ''}}" placeholder="سعر البيع" class="form-control visa-sale" required></td>
                           <td>
                              @if($i == 0)
                              <button type="button" id="add_applicant" class="btn btn-success">اضف</button>
                              @else
                              <button type="button" class="btn btn-danger remove-applicant">ازالة</button>
                              @endif
                           </td>
                        </tr>
                        @endforeach
                     </tbody>
                  </table>
               </div>

               <div class="row">
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="total_cost">اجمالي التكلفة</label>
                     <input type="text" id="total_cost" class="form-control" style="text-align:right;" disabled>
                  </div>
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="total_sale">اجمالي البيع</label>
                     <input type="text" id="total_sale" class="form-control" style="text-align:right;" disabled>
                  </div>
                  <div class="col-md-4 mb-3">
                     <label class="form-label" for="total_profit">الربح</label>
                     <input type="text" id="total_profit" class="form-control" style="text-align:right;" disabled>
                  </div>
               </div>
               <div class="alert alert-danger" id="visa_loss_alert" style="display: none;">
                  <center>تنويه : سوف يؤدي ذلك الي حدوث مديونية</center>
               </div>

               <div class="mb-3">
                  <label class="form-label" for="invoice_comments">ملاحظات</label>
                  <input type="text" name="invoice_comments" id="invoice_comments" value="{{old('invoice_comments', $invoice_info->invoice_comments)}}" placeholder="ملاحظات" class="form-control" style="text-align:right;">
               </div>

               <div class="mb-3">
                  <label class="form-label" for="myPoster">مرفق (اختياري) (المسموح : JPG , PNG , JPEG , WEBP , PDF فقط)</label>
                  <p class="mb-1 text-muted small">بدون مرفق جديد يبقى مرفق الفاتورة الأصلية (إن وجد).</p>
                  <input type="file" class="form-control" name="myPoster" id="myPoster" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf">
               </div>

               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                  اعادة اصدار فاتورة التأشيرة
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

    var applicantIndex = {{ count($applicants) }};

    $("#add_applicant").click(function () {
        var i = applicantIndex++;
        $("#applicants_table tbody").append('<tr>'
            + '<td><input type="text" name="applicants[' + i + '][name]" placeholder="اسم مقدم الطلب" class="form-control" required></td>'
            + '<td><input type="text" name="applicants[' + i + '][passport]" placeholder="رقم جواز السفر" class="form-control" required></td>'
            + '<td><input type="text" name="applicants[' + i + '][visa_number]" placeholder="رقم الطلب / التأشيرة" class="form-control"></td>'
            + '<td><input type="number" step="0.01" min="0" name="applicants[' + i + '][cost]" placeholder="سعر التكلفة" class="form-control visa-cost" required></td>'
            + '<td><input type="number" step="0.01" min="0" name="applicants[' + i + '][sale]" placeholder="سعر البيع" class="form-control visa-sale" required></td>'
            + '<td><button type="button" class="btn btn-danger remove-applicant">ازالة</button></td>'
            + '</tr>');
    });

    $(document).on('click', '.remove-applicant', function () {
        $(this).closest('tr').remove();
        visaTotals();
    });

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
