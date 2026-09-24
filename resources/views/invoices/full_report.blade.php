@extends('layouts.app')
@section('content')
@section('title', 'التقرير التفصيلي للفواتير')
@include('components.flash-messages')

{{-- Detailed invoice report: one row per ticket. Every figure comes from App\Services\InvoiceFullReport
     (same rules as «كل الفواتير»); the table, totals, groups, Excel and print all use the same rows. --}}
<x-page-header title="التقرير التفصيلي للفواتير">
   <div class="ey-action-group">
      <a href="#" id="fr_excel" class="btn btn-success"><i class="ri-file-excel-2-line"></i><span>Excel</span></a>
      <a href="#" id="fr_print" target="_blank" class="btn btn-outline-dark"><i class="ri-printer-line"></i><span>طباعة</span></a>
   </div>
</x-page-header>

<style>
    #frTable tbody tr.fr-reissue > td { background-color: #e3f2fd !important; }
    #frTable tbody tr.fr-refund > td { background-color: #fdecea !important; }
    #frTable td, #frTable th, #frGroups td, #frGroups th { font-size: 12px; white-space: nowrap; }
    #frTable tfoot td, #frGroups tfoot td { font-weight: 700; background: #eef3f8; }
    .op-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
    .op-badge.op-sale { background: #198754; color: #fff; }
    .op-badge.op-edit { background: #f0ad4e; color: #212529; }
    .op-badge.op-reissue { background: #0d6efd; color: #fff; }
    .op-badge.op-refund { background: #dc3545; color: #fff; }
    .fr-neg { color: #dc3545; }
    .fr-kpi .card-body { padding: .75rem 1rem; }
    .fr-kpi .fr-kpi-label { font-size: 12px; color: #6c7a89; }
    .fr-kpi .fr-kpi-value { font-size: 18px; font-weight: 700; direction: ltr; text-align: right; }
    .fr-latin { direction: ltr; unicode-bidi: embed; }
</style>

{{-- filters (kept in the URL, so a report can be bookmarked / shared) --}}
<div class="card">
   <div class="card-body">
      <form method="GET" action="{{ route('site.invoices_full_report') }}" autocomplete="off">
         <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3 col-xl-2">
               <label class="form-label mb-1" for="f_from">من تاريخ الفاتورة</label>
               <input type="date" id="f_from" name="date_from" class="form-control form-control-sm" value="{{ $f['date_from'] }}">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
               <label class="form-label mb-1" for="f_to">إلى تاريخ الفاتورة</label>
               <input type="date" id="f_to" name="date_to" class="form-control form-control-sm" value="{{ $f['date_to'] }}">
            </div>
            <div class="col-12 col-md-6 col-xl-4">
               <label class="form-label mb-1" for="f_q">رقم الفاتورة / PNR / رقم التذكرة</label>
               <input type="text" id="f_q" name="q" class="form-control form-control-sm" value="{{ $f['q'] }}" placeholder="مثال: FLY-A13134">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
               <label class="form-label mb-1" for="f_op">نوع العملية</label>
               <select id="f_op" name="op" class="form-select form-select-sm">
                  <option value="">الكل</option>
                  @foreach(\App\Services\InvoiceFullReport::OPS as $k => $label)
                     <option value="{{ $k }}" @selected($f['op'] === $k)>{{ $label }}</option>
                  @endforeach
               </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
               <label class="form-label mb-1" for="f_type">نوع الفاتورة</label>
               <select id="f_type" name="type" class="form-select form-select-sm">
                  <option value="">الكل</option>
                  @foreach(\App\Services\InvoiceFullReport::TYPES as $k => $label)
                     <option value="{{ $k }}" @selected($f['type'] === $k)>{{ $label }}</option>
                  @endforeach
               </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
               <label class="form-label mb-1" for="f_customer">العميل</label>
               <select id="f_customer" name="customer_id" class="form-select form-select-sm fr-search">
                  <option value="">الكل</option>
                  @foreach($customers as $c)
                     <option value="{{ $c->id }}" @selected($f['customer_id'] === (int) $c->id)>{{ $c->name }}</option>
                  @endforeach
               </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
               <label class="form-label mb-1" for="f_supplier">المورد</label>
               <select id="f_supplier" name="supplier_id" class="form-select form-select-sm fr-search">
                  <option value="">الكل</option>
                  @foreach($suppliers as $s)
                     <option value="{{ $s->id }}" @selected($f['supplier_id'] === (int) $s->id)>{{ $s->name }}</option>
                  @endforeach
               </select>
            </div>
            @if($isAdmin)
            <div class="col-6 col-md-3 col-xl-2">
               <label class="form-label mb-1" for="f_emp">الموظف</label>
               <select id="f_emp" name="employee_id" class="form-select form-select-sm">
                  <option value="">الكل</option>
                  @foreach($employees as $e)
                     <option value="{{ $e->id }}" @selected($f['employee_id'] === (int) $e->id)>{{ $e->name }}</option>
                  @endforeach
               </select>
            </div>
            @endif
            <div class="col-6 col-md-3 col-xl-2">
               <label class="form-label mb-1" for="f_airline">شركة الطيران</label>
               <select id="f_airline" name="airline" class="form-select form-select-sm fr-search">
                  <option value="">الكل</option>
                  @foreach($airlines as $a)
                     <option value="{{ $a }}" @selected($f['airline'] === $a)>{{ $a }}</option>
                  @endforeach
               </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
               <label class="form-label mb-1" for="f_section">القسم</label>
               <select id="f_section" name="section" class="form-select form-select-sm">
                  <option value="">الكل</option>
                  @foreach(\App\Services\InvoiceFullReport::SECTIONS as $k => $label)
                     <option value="{{ $k }}" @selected($f['section'] === $k)>{{ $label }}</option>
                  @endforeach
               </select>
            </div>
            <div class="col-12 col-md-6 col-xl-2 d-flex gap-2">
               <button class="btn btn-primary btn-sm text-nowrap flex-fill"><i class="ri-search-line"></i> عرض التقرير</button>
               <a href="{{ route('site.invoices_full_report') }}" class="btn btn-light btn-sm text-nowrap">مسح الفلاتر</a>
            </div>
         </div>
      </form>
   </div>
</div>

{{-- totals of all rows matching the filters and the table search --}}
<div class="row g-2 mb-3" id="frKpis">
   @foreach([
      'tickets' => 'عدد التذاكر', 'sale' => 'إجمالي البيع', 'purchase' => 'إجمالي الشراء', 'gross_profit' => 'إجمالي الربح',
      'commission' => 'إجمالي العمولة', 'refund_net' => 'صافي المرتجعات', 'net_profit' => 'صافي الربح',
   ] as $k => $label)
   <div class="col-6 col-md-4 col-xl">
      <div class="card fr-kpi mb-0 h-100">
         <div class="card-body">
            <div class="fr-kpi-label">{{ $label }}</div>
            <div class="fr-kpi-value" data-kpi="{{ $k }}">—</div>
            @if($k === 'tickets')
               <div class="fr-kpi-label"><span data-kpi="invoices">—</span> فاتورة · <span data-kpi="refund_tickets">—</span> مرتجع</div>
            @elseif($k === 'refund_net')
               <div class="fr-kpi-label">من المورد <span data-kpi="supplier_return" class="fr-latin">—</span> · للعميل <span data-kpi="client_refund" class="fr-latin">—</span></div>
            @endif
         </div>
      </div>
   </div>
   @endforeach
</div>

<div class="card">
   <div class="card-body">
      <ul class="nav nav-tabs mb-3" role="tablist">
         <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#frTabRows" role="tab">التفاصيل</a></li>
         <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#frTabGroups" role="tab" id="frGroupsTab">التجميعات</a></li>
      </ul>
      <div class="tab-content">
         <div class="tab-pane active" id="frTabRows" role="tabpanel">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
               <div class="dropdown">
                  <button type="button" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                     <i class="ri-layout-column-line"></i> <span>الأعمدة</span>
                  </button>
                  <ul class="dropdown-menu p-2" id="colChooser" style="min-width: 210px; max-height: 60vh; overflow-y: auto;"></ul>
               </div>
               <div class="ms-auto small">
                  <span class="op-badge op-sale">بيع</span>
                  <span class="op-badge op-reissue">إعادة إصدار</span>
                  <span class="op-badge op-refund">مرتجع</span>
                  <span class="op-badge op-edit">✎ بها تعديل</span>
               </div>
            </div>
            <div class="table-responsive">
               <table id="frTable" class="table table-bordered table-striped align-middle" style="width:100%">
                  <thead>
                     <tr>
                        <th>رقم الفاتورة</th>
                        <th>نوع العملية</th>
                        <th>نوع الفاتورة</th>
                        <th>تاريخ الفاتورة</th>
                        <th>تاريخ السفر</th>
                        <th>الراكب</th>
                        <th>PNR</th>
                        <th>رقم التذكرة</th>
                        <th>الرحلة</th>
                        <th>شركة الطيران</th>
                        <th>العميل</th>
                        <th>المورد</th>
                        <th>الموظف</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>مرتجع من المورد</th>
                        <th>مسترد للعميل</th>
                        <th>الربح / الخسارة</th>
                        <th>العمولة</th>
                     </tr>
                  </thead>
                  <tfoot>
                     <tr>
                        <td colspan="13">إجمالي كل النتائج (وليس الصفحة فقط)</td>
                        <td data-kpi="purchase"></td>
                        <td data-kpi="sale"></td>
                        <td data-kpi="supplier_return"></td>
                        <td data-kpi="client_refund"></td>
                        <td data-kpi="net_profit"></td>
                        <td data-kpi="commission"></td>
                     </tr>
                  </tfoot>
               </table>
            </div>
         </div>

         <div class="tab-pane" id="frTabGroups" role="tabpanel">
            <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
               <div>
                  <label class="form-label mb-1" for="frBy">تجميع حسب</label>
                  <select id="frBy" class="form-select form-select-sm">
                     @foreach(\App\Services\InvoiceFullReport::GROUPS as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                     @endforeach
                  </select>
               </div>
               <a href="#" id="frGroupPrint" target="_blank" class="btn btn-outline-dark btn-sm"><i class="ri-printer-line"></i> طباعة التجميع</a>
            </div>
            <div class="alert alert-info py-2 small d-none" id="frEmpNote">
               <i class="ri-information-line"></i>
               الفاتورة المشتركة تظهر عند كل موظف من الموظفين المشتركين فيها (البيع والربح كاملين، والعمولة بنسبة كل موظف)، لذلك قد يزيد مجموع صفوف الموظفين عن الإجمالي العام.
            </div>
            <div class="table-responsive">
               <table id="frGroups" class="table table-bordered table-striped align-middle">
                  <thead>
                     <tr>
                        <th id="frGroupHead">الموظف</th>
                        <th>الفواتير</th>
                        <th>التذاكر</th>
                        <th>مرتجعات</th>
                        <th>إجمالي البيع</th>
                        <th>إجمالي الشراء</th>
                        <th>إجمالي الربح</th>
                        <th>صافي المرتجعات</th>
                        <th>صافي الربح</th>
                        <th>العمولة</th>
                     </tr>
                  </thead>
                  <tbody><tr><td colspan="10" class="text-center">—</td></tr></tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>

<script>
$(document).ready(function () {
    const filters = @json($report->query());
    const urls = {
        data: @json(route('site.invoices_full_report_data')),
        groups: @json(route('site.invoices_full_report_groups')),
        excel: @json(route('site.invoices_full_report_excel')),
        print: @json(route('site.invoices_full_report_print')),
    };
    const esc = function (v) { return $('<div>').text(v == null ? '' : String(v)).html(); };
    const money = function (v) {
        const n = Number(v || 0);
        const s = n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return n < 0 ? '<span class="fr-neg">' + s + '</span>' : s;
    };
    const moneyCol = function (v, type) { return type === 'display' ? money(v) : v; };
    const textCol = function (v, type) { return type === 'display' ? esc(v) : v; };
    const latinCol = function (v, type) { return type === 'display' ? '<span class="fr-latin">' + esc(v) + '</span>' : v; };
    let search = '';

    // query string: current filters (+ the table search)
    const qs = function (extra) {
        const p = new URLSearchParams(filters);
        if (search) { p.set('search', search); }
        Object.entries(extra || {}).forEach(function ([k, v]) { p.set(k, v); });
        return p.toString();
    };
    const refreshLinks = function () {
        $('#fr_excel').attr('href', urls.excel + '?' + qs());
        $('#fr_print').attr('href', urls.print + '?' + qs());
        $('#frGroupPrint').attr('href', urls.print + '?' + qs({ by: $('#frBy').val() }));
    };
    const showSummary = function (s) {
        $('[data-kpi]').each(function () {
            const k = $(this).data('kpi');
            if (s[k] === undefined) { return; }
            $(this).html(['tickets', 'invoices', 'refund_tickets'].includes(k) ? Number(s[k]).toLocaleString('en-US') : money(s[k]));
        });
    };

    // DataTables' own ajax needs $.ajax, which the layout's slim jQuery build does not have: load with fetch.
    const toParams = function (obj, prefix, p) {
        p = p || new URLSearchParams();
        Object.entries(obj).forEach(function ([k, v]) {
            const key = prefix ? prefix + '[' + k + ']' : k;
            if (v !== null && typeof v === 'object') { toParams(v, key, p); } else if (v !== undefined) { p.append(key, v); }
        });
        return p;
    };
    const table = $('#frTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: function (d, callback) {
            fetch(urls.data + '?' + toParams(Object.assign({}, d, filters)).toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
                .then(function (json) { showSummary(json.summary); callback(json); })
                .catch(function (e) {
                    console.error('full report:', e);
                    callback({ draw: d.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                });
        },
        order: [],
        columns: [
            { data: 'es_id', render: function (v, type, r) {
                if (type !== 'display') { return v; }
                return '<a href="{{ url('invoices') }}/' + r.id + '/show" target="_blank" class="fr-latin">' + esc(v) + '</a>';
            } },
            { data: 'op_label', render: function (v, type, r) {
                if (type !== 'display') { return v; }
                let html = '<span class="op-badge op-' + esc(r.op) + '">' + esc(v) + '</span>';
                if (r.edits > 0) { html += ' <span class="op-badge op-edit" title="آخر تعديل ' + esc(r.last_edit) + '">✎ ' + r.edits + '</span>'; }
                return html;
            } },
            { data: 'type_label', render: textCol },
            { data: 'invoice_date', render: textCol },
            { data: 'travel_date', render: textCol },
            { data: 'passenger', render: latinCol },
            { data: 'pnr', render: latinCol },
            { data: 'ticket', render: latinCol },
            { data: 'route', render: textCol },
            { data: 'airline', render: textCol },
            { data: 'customer', render: textCol },
            { data: 'supplier', render: textCol },
            { data: 'employee', render: function (v, type, r) {
                return type === 'display' ? esc(v) + ' <small class="text-muted">(' + esc(r.rate_label) + ')</small>' : v;
            } },
            { data: 'purchase', render: moneyCol, className: 'text-end' },
            { data: 'sale', render: moneyCol, className: 'text-end' },
            { data: 'supplier_return', render: moneyCol, className: 'text-end' },
            { data: 'client_refund', render: moneyCol, className: 'text-end' },
            { data: 'profit', render: moneyCol, className: 'text-end' },
            { data: 'commission', render: moneyCol, className: 'text-end' },
        ],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 250, 500],
        searchDelay: 400,
        autoWidth: false,
        // inline: language.url is loaded with $.ajax too
        language: {
            processing: 'جاري التحميل...', search: 'بحث:', lengthMenu: 'عرض _MENU_ صف',
            info: 'عرض _START_ إلى _END_ من _TOTAL_ صف', infoEmpty: 'لا توجد نتائج', infoFiltered: '',
            emptyTable: 'لا توجد نتائج', zeroRecords: 'لا توجد نتائج',
            paginate: { first: 'الأول', previous: 'السابق', next: 'التالي', last: 'الأخير' },
        },
        rowCallback: function (row, r) {
            $(row).removeClass('fr-reissue fr-refund');
            if (r.op === 'reissue') { $(row).addClass('fr-reissue'); }
            if (r.op === 'refund') { $(row).addClass('fr-refund'); }
        },
    });
    table.on('search.dt', function () { search = table.search(); refreshLinks(); if ($('#frGroupsTab').hasClass('active')) { loadGroups(); } });

    // show / hide columns
    table.columns().every(function () {
        const col = this;
        const item = $('<li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer;">'
            + '<input type="checkbox" class="form-check-input m-0"> <span></span></label></li>');
        item.find('span').text($(col.header()).text());
        item.find('input').prop('checked', col.visible()).on('change', function () { col.visible(this.checked); });
        $('#colChooser').append(item);
    });

    // grouped totals
    const loadGroups = function () {
        const by = $('#frBy').val();
        $('#frGroupHead').text($('#frBy option:selected').text());
        $('#frEmpNote').toggleClass('d-none', by !== 'employee');
        $('#frGroups tbody').html('<tr><td colspan="10" class="text-center">جاري التحميل...</td></tr>');
        $('#frGroups tfoot').remove();
        refreshLinks();
        fetch(urls.groups + '?' + qs({ by: by }), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.groups.length) {
                    $('#frGroups tbody').html('<tr><td colspan="10" class="text-center">لا توجد نتائج</td></tr>');
                    return;
                }
                $('#frGroups tbody').html(json.groups.map(function (g) {
                    return '<tr><td>' + esc(g.label) + (g.shared_tickets ? ' <small class="text-muted">(' + g.shared_tickets + ' تذكرة مشتركة)</small>' : '') + '</td>'
                        + '<td>' + g.invoices + '</td><td>' + g.tickets + '</td><td>' + g.refund_tickets + '</td>'
                        + ['sale', 'purchase', 'gross_profit', 'refund_net', 'net_profit', 'commission'].map(function (k) { return '<td class="text-end">' + money(g[k]) + '</td>'; }).join('')
                        + '</tr>';
                }).join(''));
            })
            .catch(function (e) {
                console.error('full report groups:', e);
                $('#frGroups tbody').html('<tr><td colspan="10" class="text-center text-danger">تعذر تحميل التجميع</td></tr>');
            });
    };
    $('#frGroupsTab').on('shown.bs.tab', loadGroups);
    $('#frBy').on('change', loadGroups);

    refreshLinks();

    if (typeof dselect === 'function') {
        document.querySelectorAll('select.fr-search').forEach(function (el) { dselect(el, { search: true }); });
    }
});
</script>
@endsection
