@extends('layouts.app')
@section('content')
@section('title', 'الفواتير')

@include('components.flash-messages')

<x-page-header title="قائمة الفواتير">
   <a href="{{route('site.invoices_create')}}">
      <button class="btn btn-primary">
         <i class="ri-file-add-line"></i>
         اضافة فاتورة
      </button>
   </a>
   <a href="{{route('site.shared_invoices_create')}}">
      <button class="btn btn-outline-dark">
         <i class="ri-team-line"></i>
         إضافة فاتورة مشتركة
      </button>
   </a>
</x-page-header>

<style>
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.375rem 0.75rem;
        margin: 2px;
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
        color: #000 !important;
        font-size: 12px;
        transition: all 0.2s ease-in-out;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background-color: #0d6efd;
        color: #fff !important;
        border-color: #0d6efd;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color: #0d6efd;
        color: #fff !important;
        border: 1px solid #0d6efd;
    }

    .dataTables_filter input,
    .dataTables_length select {
        padding: 5px 10px;
        border-radius: 5px;
        border: 1px solid #ced4da;
        font-size: 12px;
    }

    .dataTables_length label,
    .dataTables_filter label {
        font-size: 12px;
        font-weight: normal;
    }

    .dataTables_info {
        font-size: 12px;
        margin-top: 10px;
    }
    
    /* تنسيق الصفوف الملغاة */
    .cancelled-invoice {
        background-color: #ffe6e6 !important;
        color: #d63384;
    }
    
    .cancelled-badge {
        background-color: #dc3545;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .table-striped tbody tr.cancelled-invoice:nth-of-type(odd) {
        background-color: #ffcccc !important;
    }

    /* Operation rows (new invoices: re-issue / refund -- a full cancellation is a full refund). Original
       invoices -- including edited ones -- keep the normal look. */
    #invoicesTable tbody tr.invoice-reissue > td { background-color: #e3f2fd !important; }
    #invoicesTable tbody tr.invoice-refund > td { background-color: #fdecea !important; }
    .op-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
    .op-badge.op-sale { background: #198754; color: #fff; }
    .op-badge.op-edit { background: #f0ad4e; color: #212529; }
    .op-badge.op-reissue { background: #0d6efd; color: #fff; }
    .op-badge.op-refund { background: #dc3545; color: #fff; }
</style>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <div class="row g-2 align-items-end mb-3" id="invoiceFilters">
                    <div class="col-auto">
                        <label class="form-label mb-1" for="flt_from">من تاريخ الفاتورة</label>
                        <input type="date" id="flt_from" class="form-control form-control-sm">
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-1" for="flt_to">إلى تاريخ الفاتورة</label>
                        <input type="date" id="flt_to" class="form-control form-control-sm">
                    </div>
                    <div class="col-auto">
                        <button type="button" id="flt_apply" class="btn btn-primary btn-sm">تطبيق</button>
                        <button type="button" id="flt_clear" class="btn btn-light btn-sm">كل الفواتير</button>
                    </div>
                    <div class="col-auto ms-auto small">
                        <span class="op-badge op-sale">بيع</span>
                        <span class="op-badge op-reissue">إعادة إصدار</span>
                        <span class="op-badge op-refund">مرتجع</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="invoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ES ID</th>
                                <th>حالة الفاتورة</th>
                                <th>تاريخ الفاتورة</th>
                                <th>تاريخ السفر</th>
                                <th>اسم المورد</th>
                                <th>اسم المستفيد</th>
                                <th>المستخدمين</th>
                                <th>الاستلام / الوصول</th>
                                <th>التكلفة</th>
                                <th>البيع</th>
                                <th>الربح</th>
                                <th>الموظف</th>
                                <th>حالة السداد</th>
                                <th>اجراء</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
window.authAccountType = {{ Auth::user()->account_type }};

$(document).ready(function () {
    // Server-side processing: only the visible page is loaded from
    // site.invoices_list_data (search, ordering and paging run in SQL).
    const esc = function (v) { return $('<div>').text(v == null ? '' : String(v)).html(); };
    $('#invoicesTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '{{ route("site.invoices_list_data") }}',
            data: function (d) {
                d.date_from = $('#flt_from').val();
                d.date_to = $('#flt_to').val();
            },
            error: function (xhr) {
                console.error("فشل في تحميل البيانات:", xhr.responseText);
                alert("فشل في تحميل البيانات من الخادم.");
            }
        },
        order: [[2, 'desc']],
        pageLength: 25,
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        columns: [
            {
                data: 'es_id',
                render: function (data, type, row) {
                    if (row.invoice_ticket_file) {
                        const fileUrl = `/public/storage/${String(row.invoice_ticket_file).replace('storage/', '')}`;
                        return `<a href="${fileUrl}" target="_blank">${esc(data)}</a>`;
                    }
                    return esc(data);
                }
            },
            {
                data: 'op', orderable: false,
                render: function (data, type, row) {
                    let html = `<span class="op-badge op-${esc(row.op)}">${esc(row.op_label)}</span>`;
                    if (row.edits > 0) {
                        html += `<br><span class="op-badge op-edit mt-1">معدلة (${row.edits}) - آخر تعديل ${esc(row.last_edit)}</span>`;
                    }
                    if (row.is_shared) {
                        html += '<br><span class="badge bg-dark mt-1">مشتركة</span>';
                        if (row.shared_owner || row.shared_seller) {
                            html += `<br><small>المالك: ${esc(row.shared_owner || '-')}<br>البائع: ${esc(row.shared_seller || '-')}</small>`;
                        }
                    }
                    return html;
                }
            },
            { data: 'invoice_date', render: function (data) { return esc(data || '-'); } },
            { data: 'invoice_travel_date', render: function (data) { return esc(data); } },
            { data: 'vendors', orderable: false, render: function (data) { return esc(data); } },
            { data: 'beneficiary', orderable: false, render: function (data) { return esc(data); } },
            {
                data: 'passengers', orderable: false,
                render: function (data) {
                    return (data || []).map(u => `
                        <strong>${esc(u.name)}</strong><br>
                        حجز: ${esc(u.booking || '-')} | تذكرة: ${esc(u.ticket || '-')}<br>
                    `).join('');
                }
            },
            { data: 'locations', orderable: false, render: function (data) { return esc(data); } },
            { data: 'cost', orderable: false },
            { data: 'sale', orderable: false },
            { data: 'profit', orderable: false },
            { data: 'creator', orderable: false, render: function (data) { return esc(data); } },
            {
                data: 'counter', orderable: false,
                render: function (data, type, row) {
                    const id = row.id;
                    const fmt = v => parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    // payment status: Counter Customer invoices only (computed server-side)
                    let badge = '';
                    const c = row.counter;
                    if (c) {
                        const cls = { unpaid: 'bg-dark', partial: 'bg-secondary', paid: 'bg-success', nothing_due: 'bg-light text-dark', due_to_client: 'bg-warning text-dark' }[c.status] || 'bg-dark';
                        badge = `<span class="badge ${cls} my_badge">${esc(c.label)}</span>`
                            + `<br><small>المسدد: ${fmt(c.paid)}` + (c.refunded > 0 ? `<br>مرتجع: ${fmt(c.refunded)}` : '')
                            + (c.remaining < 0 ? `<br>مستحق للعميل: ${fmt(-c.remaining)}` : `<br>المتبقي: ${fmt(c.remaining)}`) + `</small>`;
                    }
                    let buttons = '';
                    if (window.authAccountType === 2) {
                        if (row.invoice_status === 0) {
                            buttons = `
                                <button class="btn btn-primary mt-1" id="rvd_${id}" style="border-radius: 55px;font-size: 10px;" onclick="do_approved(${id})">تأكيد العملية</button>
                                <button class="btn btn-success mt-1" id="apprvd_${id}" style="border-radius: 55px;font-size: 10px;display:none;">تم التأكيد</button>`;
                        } else {
                            buttons = `<button class="btn btn-success mt-1" style="border-radius: 55px;font-size: 10px;">تم التأكيد</button>`;
                        }
                    }
                    return `<div style="text-align: center;">${badge}<br>${buttons}</div>`;
                }
            },
            {
                data: 'id', orderable: false,
                render: function (data, type, row) {
                    const id = row.id;
                    const es_id = encodeURIComponent(row.es_id);
                    // shared invoices keep their own reissue / refund workflow
                    const reissueUrl = row.is_shared ? `/shared-invoices/reissue/${es_id}` : `/invoices/reissue/${es_id}`;
                    const refundUrl = row.is_shared ? `/shared-invoices/refund/${es_id}` : `/invoices/refund/${es_id}`;
                    let html = `
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ri-more-fill align-middle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a href="/invoices/${id}/show" class="dropdown-item"><i class="ri-eye-fill align-bottom me-2 text-muted"></i> تفاصيل</a></li>`;
                    if (window.authAccountType === 2 || row.invoice_status === 0) {
                        html += `<li><a href="/invoices/${id}/edit" class="dropdown-item"><i class="ri-edit-box-line"></i> تعديل</a></li>`;
                    }
                    if (row.counter) {
                        html += row.counter.remaining > 0.005
                            ? `<li><a href="/invoices/pay-part/${id}" class="dropdown-item"><i class="ri-wallet-3-line"></i> سداد</a></li>`
                            : `<li><span class="dropdown-item disabled"><i class="ri-checkbox-circle-line"></i> ${esc(row.counter.label)}</span></li>`;
                    }
                    html += `
                                <li><a href="${reissueUrl}" class="dropdown-item"><i class="ri-arrow-go-forward-line"></i> اعادة اصدار</a></li>
                                <li><a href="${refundUrl}" class="dropdown-item"><i class="ri-refund-2-line"></i> مرتجع الفاتورة</a></li>
                                <li><a href="/invoices/${es_id}/remove" class="dropdown-item"><i class="ri-delete-bin-line"></i> حذف الفاتورة</a></li>
                            </ul>
                        </div>`;
                    return html;
                }
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.3/i18n/ar.json'
        },
        paging: true,
        searching: true,
        ordering: true,
        responsive: true,
        autoWidth: false,
        searchDelay: 400,
        rowCallback: function (row, data) {
            const cls = { reissue: 'invoice-reissue', refund: 'invoice-refund' }[data.op];
            $(row).removeClass('invoice-reissue invoice-refund');
            if (cls) {
                $(row).addClass(cls);
            }
        }
    });
    $('#flt_apply').on('click', function () { $('#invoicesTable').DataTable().ajax.reload(); });
    $('#flt_clear').on('click', function () { $('#flt_from').val(''); $('#flt_to').val(''); $('#invoicesTable').DataTable().ajax.reload(); });
});

function do_approved(id) {
    var url = "{{url('')}}/invoices/" + id + "/approve";
    $.ajax({
        type: "GET",
        url: url,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: "id=" + id,
        success: function (data) {
            var status_code = data.status_code;

            if (status_code == 200) {
                swal("", "تم تأكيد العملية بنجاح", "success");
                var n1 = "rvd_" + id;
                var n2 = "apprvd_" + id;
                document.getElementById(n1).style.display = "none";
                document.getElementById(n2).style.display = "block";
            } else {
                swal("", "فشل اثناء تأكيد العملية", "error");
            }
        }
    });
}
</script>

@endsection