@extends('layouts.app')
@section('content')
@section('title' , 'الموردين و العملاء')
@include('components.flash-messages')
<x-page-header title="الموردين و العملاء">
   <a href="{{route('site.suppliers_create')}}">
      <button class="btn btn-primary">
         <i class="ri-file-add-line"></i>
         اضافة مورد
      </button>
   </a>
</x-page-header>

<style>
   /* fixed system account (Counter Customer): tinted row, accent edge, always first */
   #suppliersTable tr.ey-system-row > td {
      box-shadow: inset 0 0 0 9999px rgba(var(--vz-primary-rgb), .11);
      border-top: 1px solid rgba(var(--vz-primary-rgb), .35);
      border-bottom: 1px solid rgba(var(--vz-primary-rgb), .35);
   }
   #suppliersTable tr.ey-system-row > td.ey-name {
      border-inline-start: 3px solid rgb(var(--vz-primary-rgb));
      font-weight: 600;
   }
   #suppliersTable .ey-actions { display: flex; gap: 4px; flex-wrap: nowrap; }
   #suppliersTable .ey-actions .btn { font-size: 15px; }
</style>

<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <p class="text-muted fs-12 mb-3">
               الرصيد من كشف حساب كل حساب (مدين − دائن):
               <span class="badge bg-success-subtle text-success">لنا</span> الحساب مدين لنا ،
               <span class="badge bg-danger-subtle text-danger">علينا</span> نحن مدينون له.
               <span class="badge bg-primary"><i class="ri-lock-2-line"></i> حساب نظام</span> يظهر دائماً في أول القائمة.
               @if($expenseAccounts)
               لا تظهر هنا حسابات المصروفات ({{$expenseAccounts}}) — <a href="{{route('site.expenses')}}">صفحة المصروفات</a>.
               @endif
            </p>

            {{-- filters: all client-side over the rows below (no extra queries); kept in the URL --}}
            <div class="row g-2 align-items-end mb-3" id="supFilters">
               <div class="col-12 col-md-3">
                  <label class="form-label fs-12 mb-1" for="f_q">بحث بالاسم أو الهاتف</label>
                  <input type="search" id="f_q" class="form-control form-control-sm" placeholder="اكتب الاسم أو رقم الهاتف" autocomplete="off">
               </div>
               <div class="col-6 col-md">
                  <label class="form-label fs-12 mb-1" for="f_role">الدور</label>
                  <select id="f_role" class="form-select form-select-sm">
                     <option value="">الكل</option>
                     <option value="supplier_any">كل الموردين</option>
                     <option value="customer_any">كل العملاء</option>
                     <option value="both">مورد وعميل</option>
                     <option value="supplier">مورد فقط</option>
                     <option value="customer">عميل فقط</option>
                     <option value="none">بدون فواتير</option>
                  </select>
               </div>
               <div class="col-6 col-md">
                  <label class="form-label fs-12 mb-1" for="f_bal">الرصيد</label>
                  <select id="f_bal" class="form-select form-select-sm">
                     <option value="">الكل</option>
                     <option value="pos">لنا</option>
                     <option value="neg">علينا</option>
                     <option value="nonzero">غير صفري</option>
                     <option value="zero">صفر</option>
                  </select>
               </div>
               <div class="col-6 col-md">
                  <label class="form-label fs-12 mb-1" for="f_status">الحالة</label>
                  <select id="f_status" class="form-select form-select-sm">
                     <option value="">الكل</option>
                     <option value="1">فعال</option>
                     <option value="0">موقوف</option>
                  </select>
               </div>
               <div class="col-6 col-md">
                  <label class="form-label fs-12 mb-1" for="f_act">النشاط</label>
                  <select id="f_act" class="form-select form-select-sm">
                     <option value="">الكل</option>
                     <option value="yes">عليه حركات</option>
                     <option value="no">بدون حركات</option>
                  </select>
               </div>
               <div class="col-6 col-md">
                  <label class="form-label fs-12 mb-1" for="f_from">آخر حركة من</label>
                  <input type="date" id="f_from" class="form-control form-control-sm">
               </div>
               <div class="col-6 col-md">
                  <label class="form-label fs-12 mb-1" for="f_to">آخر حركة إلى</label>
                  <input type="date" id="f_to" class="form-control form-control-sm">
               </div>
               <div class="col-12 col-md-auto">
                  <button type="button" id="f_reset" class="btn btn-sm btn-light w-100">
                     <i class="ri-refresh-line align-middle"></i> إعادة ضبط
                  </button>
               </div>
            </div>

            {{-- «كشف الحساب»: the statement screen takes a POST; one shared form, opened in a new tab --}}
            <form id="statementForm" action="{{route('site.accounts_statement_search')}}" method="POST" target="_blank" style="display:none;">
               @csrf
               <input type="hidden" name="invoice_beneficiaries" id="statementAccount">
            </form>

            <div class="table-responsive">
            <table id="suppliersTable" class="table table-bordered nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th style="display:none;">pin</th>
                     <th>الاسم</th>
                     <th>الهاتف</th>
                     <th>الدور</th>
                     <th>فواتير كمورد</th>
                     <th>فواتير كعميل</th>
                     <th>الرصيد</th>
                     <th>آخر حركة</th>
                     <th>التصنيف</th>
                     <th>إجراءات</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($rows as $row)
                  <?php
                     $supplier = $row->account;
                     $sign = $row->balance > 0 ? 'pos' : ($row->balance < 0 ? 'neg' : 'zero');
                     $supplierInvoices = route('site.invoices_full_report', ['supplier_id' => $supplier->id]);
                     $customerInvoices = route('site.invoices_full_report', ['customer_id' => $supplier->id]);
                  ?>
                  <tr @class(['ey-system-row' => $row->system])
                      data-id="{{$supplier->id}}"
                      data-system="{{$row->system ? 1 : 0}}"
                      data-role="{{$row->role ?? 'none'}}"
                      data-sign="{{$sign}}"
                      data-status="{{$supplier->status == 1 ? 1 : 0}}"
                      data-last="{{$row->last_activity ?? ''}}"
                      data-search="{{ mb_strtolower($supplier->name . ' ' . implode(' ', $row->phones)) }}">
                     <td style="display:none;">{{$row->system ? 0 : 1}}</td>
                     <td class="ey-name">
                        {{$supplier->name}}
                        @if($row->system)
                        <span class="badge bg-primary ms-1" title="حساب نظام: لا يُحذف ولا يتغير نوعه"><i class="ri-lock-2-line"></i> حساب نظام</span>
                        @endif
                     </td>
                     <td>
                        @if($row->phones)
                        {{ implode(' / ', $row->phones) }}
                        @else
                        <span class="text-muted">—</span>
                        @endif
                     </td>
                     <td>
                        @if($row->role == 'both')
                        <span class="badge bg-warning-subtle text-warning my_badge">مورد وعميل</span>
                        @elseif($row->role == 'supplier')
                        <span class="badge bg-primary-subtle text-primary my_badge">مورد</span>
                        @elseif($row->role == 'customer')
                        <span class="badge bg-info-subtle text-info my_badge">عميل</span>
                        @else
                        <span class="text-muted fs-12">بدون فواتير</span>
                        @endif
                     </td>
                     <td data-order="{{$row->invoices_as_supplier}}">
                        @if($row->invoices_as_supplier)
                        <a href="{{$supplierInvoices}}" title="فواتيره كمورد">{{ number_format($row->invoices_as_supplier) }}</a>
                        @else <span class="text-muted">0</span> @endif
                     </td>
                     <td data-order="{{$row->invoices_as_customer}}">
                        @if($row->invoices_as_customer)
                        <a href="{{$customerInvoices}}" title="فواتيره كعميل">{{ number_format($row->invoices_as_customer) }}</a>
                        @else <span class="text-muted">0</span> @endif
                     </td>
                     <td data-order="{{$row->balance}}">
                        @if($row->balance > 0)
                        <span class="fw-semibold text-success">{{ number_format($row->balance, 2) }}</span>
                        <span class="badge bg-success-subtle text-success">لنا</span>
                        @elseif($row->balance < 0)
                        <span class="fw-semibold text-danger">{{ number_format(-$row->balance, 2) }}</span>
                        <span class="badge bg-danger-subtle text-danger">علينا</span>
                        @else
                        <span class="text-muted">0.00</span>
                        <span class="badge bg-light text-muted">صفر</span>
                        @endif
                     </td>
                     <td data-order="{{$row->last_activity ?? ''}}">
                        @if($row->last_activity) {{$row->last_activity}} @else <span class="text-muted">—</span> @endif
                     </td>
                     <td>
                        @if($supplier->type == 1)
                        <span class="badge bg-dark my_badge">فرد</span>
                        @else
                        <span class="badge bg-primary my_badge">شركة</span>
                        @endif
                        @if($supplier->status == 1)
                        <span class="badge bg-success my_badge">فعال</span>
                        @else
                        <span class="badge bg-danger my_badge">موقوف</span>
                        @endif
                     </td>
                     <td>
                        <div class="ey-actions">
                           <button type="button" class="btn btn-soft-info btn-sm js-statement" data-id="{{$supplier->id}}" title="كشف الحساب" aria-label="كشف حساب {{$supplier->name}}">
                              <i class="ri-file-list-3-line align-middle"></i>
                           </button>

                           @if($row->role == 'both')
                           <div class="btn-group">
                              <button type="button" class="btn btn-soft-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="الفواتير" aria-label="فواتير {{$supplier->name}}">
                                 <i class="ri-bill-line align-middle"></i>
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end">
                                 <li><a class="dropdown-item" href="{{$supplierInvoices}}">فواتيره كمورد ({{ number_format($row->invoices_as_supplier) }})</a></li>
                                 <li><a class="dropdown-item" href="{{$customerInvoices}}">فواتيره كعميل ({{ number_format($row->invoices_as_customer) }})</a></li>
                              </ul>
                           </div>
                           @elseif($row->role == 'supplier' || $row->role == 'customer')
                           <a href="{{$row->role == 'supplier' ? $supplierInvoices : $customerInvoices}}" class="btn btn-soft-primary btn-sm" title="الفواتير" aria-label="فواتير {{$supplier->name}}">
                              <i class="ri-bill-line align-middle"></i>
                           </a>
                           @endif

                           @if($row->balance > 0 && ! $row->system)
                           <button type="button" class="btn btn-soft-success btn-sm js-remind" data-name="{{$supplier->name}}" data-amount="{{ number_format($row->balance, 2) }}" title="نسخ رسالة تذكير بالسداد" aria-label="تذكير {{$supplier->name}} بالسداد">
                              <i class="ri-message-3-line align-middle"></i>
                           </button>
                           @endif

                           <a href="{{route('site.suppliers_edit' , $supplier->id)}}" class="btn btn-soft-secondary btn-sm" title="تعديل" aria-label="تعديل {{$supplier->name}}">
                              <i class="ri-edit-2-line align-middle"></i>
                           </a>

                           @if($row->system)
                           <button type="button" class="btn btn-soft-dark btn-sm" disabled title="حساب نظام لا يُحذف" aria-label="حساب نظام لا يُحذف">
                              <i class="ri-lock-2-line align-middle"></i>
                           </button>
                           @else
                           <form id="delete-form-{{$supplier->id}}" action="{{route('site.suppliers_delete', $supplier->id)}}" method="POST" style="display:none;">
                              @csrf
                           </form>
                           <button class="btn btn-soft-danger btn-sm" type="button" title="حذف" aria-label="حذف {{$supplier->name}}" onclick="confirmDeleteForm('delete-form-{{$supplier->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك الحساب نهائياً. لا يمكن حذف حساب له فواتير أو سندات أو حركات في كشف الحساب')">
                              <i class="ri-delete-bin-line align-middle"></i>
                           </button>
                           @endif
                        </div>
                     </td>
                  </tr>
                  @endforeach
               </tbody>
            </table>
            </div>
         </div>
      </div>
   </div>
   <!--end col-->
</div>

<script>
$(function () {
    var F = { q: '#f_q', role: '#f_role', bal: '#f_bal', status: '#f_status', act: '#f_act', from: '#f_from', to: '#f_to' };
    var val = function (k) { return ($(F[k]).val() || '').trim(); };
    // Arabic-Indic / Persian digits typed in the search box match the stored 0-9 phones
    var latinDigits = function (s) {
        return s.replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
                .replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); });
    };

    // restore the filters from the URL (e.g. coming back from an edit page)
    var params = new URLSearchParams(location.search);
    Object.keys(F).forEach(function (k) { if (params.has(k)) { $(F[k]).val(params.get(k)); } });

    DataTable.ext.search.push(function (settings, data, index) {
        if (settings.nTable.id !== 'suppliersTable') { return true; }
        var d = settings.aoData[index].nTr.dataset;
        if (d.system === '1') { return true; }   // system account (Counter Customer): never filtered out

        var q = latinDigits(val('q')).toLowerCase();
        if (q && d.search.indexOf(q) === -1) { return false; }

        var role = val('role');
        if (role === 'supplier_any' && d.role !== 'supplier' && d.role !== 'both') { return false; }
        if (role === 'customer_any' && d.role !== 'customer' && d.role !== 'both') { return false; }
        if (['both', 'supplier', 'customer', 'none'].indexOf(role) !== -1 && d.role !== role) { return false; }

        var bal = val('bal');
        if (bal === 'nonzero' ? d.sign === 'zero' : (bal && d.sign !== bal)) { return false; }

        if (val('status') !== '' && d.status !== val('status')) { return false; }

        if (val('act') === 'yes' && !d.last) { return false; }
        if (val('act') === 'no' && d.last) { return false; }

        if (val('from') && (!d.last || d.last < val('from'))) { return false; }
        if (val('to') && (!d.last || d.last > val('to'))) { return false; }
        return true;
    });

    var table = new DataTable('#suppliersTable', {
        pageLength: 25,
        order: [[1, 'asc']],
        orderFixed: { pre: [[0, 'asc']] },   // system account first, whatever the sort
        layout: { topEnd: null },            // search: our own box above (it never hides the system account)
        columnDefs: [
            { targets: 0, visible: false, searchable: false },
            { targets: -1, orderable: false, searchable: false },
        ],
        // same Arabic texts as the detailed invoice report (inline, no language file request)
        language: {
            lengthMenu: 'عرض _MENU_ صف',
            info: 'عرض _START_ إلى _END_ من _TOTAL_ حساب', infoEmpty: 'لا توجد نتائج',
            infoFiltered: '(من أصل _MAX_)', emptyTable: 'لا توجد حسابات', zeroRecords: 'لا توجد نتائج',
            paginate: { first: 'الأول', previous: 'السابق', next: 'التالي', last: 'الأخير' },
        },
    });

    var apply = function () {
        var p = new URLSearchParams();
        Object.keys(F).forEach(function (k) { if (val(k) !== '') { p.set(k, val(k)); } });
        history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p.toString() : ''));
        table.draw();
    };
    $('#f_q').on('input', apply);
    $('#supFilters select, #supFilters input[type=date]').on('change', apply);
    $('#f_reset').on('click', function () {
        Object.keys(F).forEach(function (k) { $(F[k]).val(''); });
        apply();
    });

    // «كشف الحساب»: POST the account to the statement screen in a new tab
    $('#suppliersTable').on('click', '.js-statement', function () {
        $('#statementAccount').val(this.dataset.id);
        document.getElementById('statementForm').submit();
    });

    // «تذكير بالسداد»: copy the same reminder text as the balances report (no alert box)
    $('#suppliersTable').on('click', '.js-remind', function () {
        var btn = this, rtl = '‫', ltr = '‪', end = '‬';
        var text = rtl + 'السادة/ ' + btn.dataset.name + ' 💫🌸 برجاء العلم ان رصيدكم الحالي '
            + ltr + btn.dataset.amount + end + ' ج.م '
            + 'لذا لطفاً برجاء سداد المبالغ و ارسال الايصال - مع تحيات إدارة شركة إيانا تورز سوهاج' + end;
        var done = function () {
            var icon = btn.querySelector('i'), title = btn.title;
            icon.className = 'ri-check-line align-middle';
            btn.title = 'تم نسخ رسالة التذكير';
            setTimeout(function () { icon.className = 'ri-message-3-line align-middle'; btn.title = title; }, 2000);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            done();
        }
    });

    if (Object.keys(F).some(function (k) { return val(k) !== ''; })) { table.draw(); }
});
</script>
@endsection
