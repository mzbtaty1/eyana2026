@extends('layouts.app')
@section('content')
@section('title', 'الفواتير')

@include('components.flash-messages')

<style>
    /* أنماط التحميل */
    #loadingOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    .loading-spinner {
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* أنماط الجدول */
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
    
    /* أنماط إضافية */
    .booking-info {
        font-weight: bold;
    }
    .booking-number {
        color: #4e73df;
        margin-left: 5px;
    }
    .ticket-number {
        color: #1cc88a;
        margin-left: 5px;
    }
    .status-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }
    .badge-sm {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
</style>

<!-- علامة التحميل -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
    <span style="margin-right: 10px;">جاري تحميل البيانات...</span>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0">قائمة الفواتير</h5>
                <a href="{{route('site.invoices_create')}}">
                    <button class="btn btn-primary">
                        <i class="ri-file-add-line"></i>
                        اضافة فاتورة
                    </button>
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="invoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ES ID</th>
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
                                <th>حالة الفاتورة</th>
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
        // إظهار علامة التحميل
        $('#loadingOverlay').show();
        
        $.ajax({
            url: '{{ route("site.invoices_json") }}',
            method: 'GET',
            success: function (response) {
                if (!response || !response.data || !Array.isArray(response.data)) {
                    alert('البيانات غير صحيحة أو فارغة');
                    $('#loadingOverlay').hide();
                    return;
                }
        
                let data = response.data;
        
                $('#invoicesTable').DataTable({
                    data: data,
                    columns: [
                        {
                            data: 'es_id',
                            title: 'ES ID',
                            render: function(data, type, row) {
                                if (!row.invoice_ticket_file) return data;
                                const fileUrl = `/public/storage/${row.invoice_ticket_file.replace('storage/', '')}`;
                                return `<a href="${fileUrl}" target="_blank">${data}</a>`;
                            }
                        },
                        { 
                            data: 'invoice_date', 
                            title: 'تاريخ الفاتورة' 
                        },
                        { 
                            data: 'invoice_travel_date', 
                            title: 'تاريخ السفر' 
                        },
                        {
                            data: null,
                            title: 'اسم المورد',
                            render: function (data, type, row) {
                                if (!row.ticket_vendors || !Array.isArray(row.ticket_vendors)) return '';
                                return row.ticket_vendors
                                    .filter(v => v?.supplier?.name)
                                    .map(v => v.supplier.name)
                                    .join(" / ");
                            }
                        },
                        {
                            data: null,
                            title: 'اسم المستفيد',
                            render: function (data, type, row) {
                                return row?.beneficiaries?.name || '';
                            }
                        },
                        {
                            data: 'custom_users',
                            title: 'المستخدمين',
                            render: function (data, type, row) {
                                if (!row.users || !Array.isArray(row.users)) return '';
                                return row.users.map(u => `
                                    <div class="booking-info">
                                        <strong>${u.client_name}</strong><br>
                                        <span>رقم حجز: <span class="booking-number">${u.client_booking_id || '-'}</span></span>
                                        <span style="margin-right: 10px;">رقم تذكرة: <span class="ticket-number">${u.client_ticket_id || '-'}</span></span>
                                    </div>
                                `).join('');
                            }
                        },
                        {
                            data: 'combined_location',
                            title: 'الاستلام / الوصول',
                            render: function (data, type, row) {
                                const from = row.from_location || '';
                                const to = row.to_location || '';
                                return from || to ? `${from} / ${to}` : '';
                            }
                        },
                        {
                            data: 'combined_client_net_pice',
                            title: 'التكلفة',
                            render: function (data, type, row) {
                                return (row.users || []).reduce((sum, user) => {
                                    return sum + parseFloat(user.client_net_pice || 0);
                                }, 0).toFixed(2);
                            }
                        },
                        {
                            data: 'combined_client_net_pice',
                            title: 'البيع',
                            render: function (data, type, row) {
                                return (row.users || []).reduce((sum, user) => {
                                    return sum + parseFloat(user.client_bought_price || 0);
                                }, 0).toFixed(2);
                            }
                        },
                        {
                            data: 'profit_or_loss',
                            title: 'الربح',
                            render: function (data, type, row) {
                                const net = (row.users || []).reduce((sum, user) => {
                                    return sum + parseFloat(user.client_net_pice || 0);
                                }, 0);
                            
                                const bought = (row.users || []).reduce((sum, user) => {
                                    return sum + parseFloat(user.client_bought_price || 0);
                                }, 0);
                            
                                const diff = bought - net;
                                const sign = diff > 0 ? '' : '';
                                return `${sign}${diff.toFixed(2)}`;
                            }
                        },
                        {
                            data: 'creator_name',
                            title: 'الموظف',
                            render: function (data, type, row) {
                                return row.creator && row.creator.name ? row.creator.name : '-';
                            }
                        },
                        {
                            data: 'payment_and_action',
                            title: 'حالة الفاتورة',
                            render: function (data, type, row) {
                                const id = row.id;
                                const moneyPay = parseFloat(row.invoice_money_pay || 0);
                                const totalBought = (row.users || []).reduce((sum, user) => {
                                    return sum + parseFloat(user.client_bought_price || 0);
                                }, 0);
                                const invoiceStatus = parseInt(row.invoice_status || 0);
                                const isAccountType2 = window.authAccountType === 2;

                                let badge = '';
                                if (moneyPay === 0) {
                                    badge = `<span class="badge badge-sm bg-dark">لم يتم السداد</span>`;
                                } else if (moneyPay < totalBought) {
                                    badge = `<span class="badge badge-sm bg-secondary">سداد جزئي</span>`;
                                } else {
                                    badge = `<span class="badge badge-sm bg-success">تم السداد</span>`;
                                }

                                let confirmBadge = '';
                                if (isAccountType2) {
                                    if (invoiceStatus === 0) {
                                        confirmBadge = `
                                            <button class="btn btn-sm btn-primary" id="rvd_${id}" onclick="do_approved(${id})">
                                                تأكيد العملية
                                            </button>
                                            <span id="apprvd_${id}" style="display:none;">
                                                <span class="badge badge-sm bg-success">تم التأكيد</span>
                                            </span>
                                        `;
                                    } else {
                                        confirmBadge = `<span class="badge badge-sm bg-success">تم التأكيد</span>`;
                                    }
                                }

                                return `
                                    <div class="status-container">
                                        ${badge}
                                        ${confirmBadge}
                                    </div>
                                `;
                            }
                        },
                        {
                            data: 'actions_dropdown',
                            title: 'اجراء',
                            orderable: false,
                            render: function (data, type, row) {
                                const id = row.id;
                                const es_id = row.es_id;
                                const moneyPay = parseFloat(row.invoice_money_pay || 0);
                                const totalBought = (row.users || []).reduce((sum, user) => {
                                    return sum + parseFloat(user.client_bought_price || 0);
                                }, 0);
                                const invoiceStatus = parseInt(row.invoice_status || 0);
                                const accountType = window.authAccountType;

                                let html = `
                                <div class="dropdown">
                                    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ri-more-fill align-middle"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a href="/invoices/${id}/show" class="dropdown-item">
                                                <i class="ri-eye-fill align-bottom me-2 text-muted"></i> تفاصيل
                                            </a>
                                        </li>
                                `;

                                if (accountType === 2 || invoiceStatus === 0) {
                                    html += `
                                        <li>
                                            <a href="/invoices/${id}/edit" class="dropdown-item">
                                                <i class="ri-edit-box-line"></i> تعديل
                                            </a>
                                        </li>
                                    `;
                                }

                                if (moneyPay < totalBought) {
                                    html += `
                                        <li>
                                            <a href="/invoices/pay-part/${id}" class="dropdown-item">
                                                <i class="ri-wallet-3-line"></i> سداد الفاتورة
                                            </a>
                                        </li>
                                    `;
                                }

                                html += `
                                    <li>
                                        <a href="/invoices/reissue/${es_id}" class="dropdown-item">
                                            <i class="ri-arrow-go-forward-line"></i> اعادة اصدار
                                        </a>
                                    </li>
                                `;

                                html += `
                                    <li>
                                        <a href="/invoices/refund/${es_id}" class="dropdown-item">
                                            <i class="ri-refund-2-line"></i> الغاء الفاتورة
                                        </a>
                                    </li>
                                `;

                                html += `
                                    <li>
                                        <a href="/invoices/${es_id}/remove" class="dropdown-item">
                                            <i class="ri-delete-bin-line"></i> حذف الفاتورة
                                        </a>
                                    </li>
                                `;

                                html += `</ul></div>`;
                                return html;
                            }
                        }
                    ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.11.3/i18n/ar.json'
                    },
                    order: [[1, 'desc']],
                    processing: true,
                    paging: true,
                    searching: true,
                    ordering: true,
                    responsive: true,
                    autoWidth: false,
                    initComplete: function() {
                        // إخفاء علامة التحميل بعد اكتمال التحميل
                        $('#loadingOverlay').fadeOut(300);
                    }
                });
            },
            error: function (xhr, status, error) {
                console.error("فشل في تحميل البيانات:", xhr.responseText);
                alert("فشل في تحميل البيانات من الخادم.");
                $('#loadingOverlay').fadeOut(300);
            }
        });
    });

    function do_approved(id){
        var url = "{{url('')}}/invoices/" + id + "/approve";
        $(document).ready(function () {
            $.ajax({
                type: "GET",
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: "id=" + id,
                success: function (data) {
                    var status_code = data.status_code;
                    if(status_code == 200){
                        swal("", "تم تأكيد العملية بنجاح", "success");
                        var n1 = "rvd_" + id;
                        var n2 = "apprvd_" + id;
                        document.getElementById(n1).style.display = "none"; 
                        document.getElementById(n2).style.display = "inline-block"; 
                    }else{
                        swal("", "فشل اثناء تأكيد العملية", "error");
                    }
                }
            });
        });
    }
</script>
@endsection