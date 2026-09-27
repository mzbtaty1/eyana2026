@extends('layouts.app')
@section('content')
@section('title' , !empty($counterCustomer) ? "اضافة فاتورة تأشيرة - عميل كونتر" : "اضافة فاتورة تأشيرة")
{{--
    Visa Invoice form (Visa step 2, invoice_section 2). Visa-specific fields only; saved by
    InvoicesController@storeVisa through the same store() as flight invoices. The visa type
    is a name only: cost and sale are entered per applicant by the employee.
--}}
@php($applicants = old('applicants', [[]]))
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
               <h5 class="card-title mb-0"> اضافة فاتورة تأشيرة{{ !empty($counterCustomer) ? ' - عميل كونتر' : '' }} </h5>
               <div class="btn-group">
                  <a href="{{route('site.invoices_create_visa')}}" class="btn btn-sm {{ empty($counterCustomer) ? 'btn-primary active' : 'btn-outline-primary' }}" @if(empty($counterCustomer)) aria-current="page" @endif><i class="ri-file-add-line"></i> <span>إضافة فاتورة</span></a>
                  <a href="{{route('site.shared_invoices_create')}}" class="btn btn-outline-dark btn-sm"><i class="ri-team-line"></i> <span>إضافة فاتورة مشتركة</span></a>
                  <a href="{{route('site.invoices_create_counter_visa')}}" class="btn btn-sm {{ !empty($counterCustomer) ? 'btn-success active' : 'btn-outline-success' }}" @if(!empty($counterCustomer)) aria-current="page" @endif><i class="ri-store-2-line"></i> <span>عميل كونتر</span></a>
               </div>
            </div>
         </div>
         <div class="card-body">
            @include('invoices.partials.type_chooser', ['invoiceKind' => 'visa'])

            @if($errors->any())
            <div class="alert alert-danger" role="alert">
               <ul class="mb-0">
                  @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                  @endforeach
               </ul>
            </div>
            @endif

            @if($visas->isEmpty())
            <div class="alert alert-warning" role="alert">
               لا توجد أنواع تأشيرات مفعلة. يجب إضافة أو تفعيل نوع تأشيرة من
               @if(Auth::user()->account_type == 2)<a href="{{route('site.visas')}}">إدارة التأشيرات</a>@else إدارة التأشيرات (المدير) @endif
               قبل إنشاء فاتورة تأشيرة.
            </div>
            @endif

            <form action="{{route('site.invoices_visa_save')}}" method="POST" autocomplete="off" enctype="multipart/form-data" id="visa_invoice_form">
               @csrf
               @if(!empty($counterCustomer))
               <input type="hidden" name="counter" value="1">
               @endif
               <div class="row">
                  <div class="col-md-6 mb-3">
                     <label class="form-label" for="visa_id">نوع التأشيرة <span class="text-danger">*</span></label>
                     <select class="form-control" name="visa_id" id="visa_id" style="text-align:right;" required>
                        <option value="">اختر نوع التأشيرة</option>
                        @foreach($visas as $visa)
                        <option value="{{$visa->id}}" @selected(old('visa_id') == $visa->id)>{{$visa->visa_name}}</option>
                        @endforeach
                     </select>
                  </div>
                  <div class="col-md-6 mb-3">
                     <label class="form-label" for="invoice_date">تاريخ الفاتورة <span class="text-danger">*</span></label>
                     <input type="date" name="invoice_date" id="invoice_date" value="{{old('invoice_date', date('Y-m-d'))}}" class="form-control" style="text-align:right;" required>
                  </div>
               </div>

               <div class="mb-3">
                  <label class="form-label" for="invoice_beneficiaries">اسم العميل (المستفيد) <span class="text-danger">*</span></label>
                  @if(!empty($counterCustomer))
                  {{-- Counter Customer invoice: the customer is fixed, no search / selection --}}
                  <input type="hidden" name="invoice_beneficiaries" value="{{$counterCustomer->id}}">
                  <input type="text" class="form-control" id="invoice_beneficiaries" value="{{$counterCustomer->name}}" style="text-align:right;" readonly>
                  @else
                  <select class="form-control" name="invoice_beneficiaries" id="invoice_beneficiaries" style="text-align:right;" required>
                     @foreach($suppliers as $supplier)
                     <option value="{{$supplier->id}}" @selected(old('invoice_beneficiaries') == $supplier->id)>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                     @endforeach
                  </select>
                  @endif
               </div>

               <div class="mb-3">
                  <label class="form-label" for="vendor_id">اسم المورد (المنفذ) <span class="text-danger">*</span></label>
                  <select name="vendor_id" id="vendor_id" class="form-control" required>
                     @foreach($suppliers as $supplier)
                     <option value="{{$supplier->id}}" @selected(old('vendor_id') == $supplier->id)>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                     @endforeach
                  </select>
               </div>

               <h6 style="font-size: 16px;">
                  مقدمو الطلب
                  <span class="text-danger">*</span>
                  <br>
                  <small class="text-muted" style="font-size: 13px;">رقم الطلب / التأشيرة اختياري. التكلفة والبيع يدخلهما الموظف لكل مقدم طلب.</small>
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
                  <input type="text" name="invoice_comments" id="invoice_comments" value="{{old('invoice_comments')}}" placeholder="ملاحظات" class="form-control" style="text-align:right;">
               </div>

               <div class="mb-3">
                  <label class="form-label" for="myPoster">مرفق (اختياري) (المسموح : JPG , PNG , JPEG , WEBP , PDF فقط)</label>
                  <input type="file" class="form-control" name="myPoster" id="myPoster" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf">
               </div>

               @if(Auth::user()->account_type == 2)
               <div class="mb-3">
                  <label class="form-label" for="added_user">الحساب الذي ترغب في اضافة الفاتورة فيه من الموظفين</label>
                  <select class="form-select" name="added_user" id="added_user">
                     @foreach(App\Models\User::all() as $user)
                     <option value="{{$user->id}}" @selected(old('added_user', Auth::user()->id) == $user->id)>{{$user->name}}</option>
                     @endforeach
                  </select>
               </div>
               @endif

               <div class="d-grid gap-2">
                  <button class="btn btn-primary" @disabled($visas->isEmpty())>
                  <i class="ri-save-line"></i>
                  حفظ فاتورة التأشيرة
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
</div>

<script src="{{asset('assets/dselect.js')}}"></script>
<script type="text/javascript">
@if(empty($counterCustomer))
    dselect(document.querySelector('#invoice_beneficiaries'), { search: true });
@endif
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
