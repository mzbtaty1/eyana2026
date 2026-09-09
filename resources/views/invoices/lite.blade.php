@extends('layouts.app')
@section('content')
@section('title', 'الفواتير')

@if($errors->any())
    <div class="alert alert-info">
        <i class="ri-file-info-line"></i> {{$errors->first()}}
    </div>
@endif 

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
</style>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">قائمة الفواتير</h5>
                <a href="{{route('site.invoices_create')}}">
                    <button class="btn btn-primary" style="float: left;margin-top: -22px;">
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
    $.ajax({
        url: '{{ route("site.invoices_json_3months") }}',
        method: 'GET',
        success: function (response) {
            if (!response || !response.data || !Array.isArray(response.data)) {
                alert('البيانات غير صحيحة أو فارغة');
                return;
            }

            let data = response.data;
            console.log(data);

            // ترتيب البيانات حسب تاريخ الفاتورة من الأحدث للأقدم
            data.sort(function(a, b) {
                let dateA = new Date(a.invoice_date || a.created_at);
                let dateB = new Date(b.invoice_date || b.created_at);
                return dateB - dateA; // الأحدث أولاً
            });

            const table = $('#invoicesTable').DataTable({
                data: data,
                order: [[2, 'desc']], // ترتيب حسب تاريخ الفاتورة (العمود الثالث الآن)
                columns: [
                    {
                        data: 'es_id',
                        title: 'ES ID',
                        render: function(data, type, row) {
                            let esIdDisplay = data;
                            if (row.invoice_ticket_file) {
                                const fileUrl = `/public/storage/${row.invoice_ticket_file.replace('storage/', '')}`;
                                esIdDisplay = `<a href="${fileUrl}" target="_blank">${data}</a>`;
                            }
                            return esIdDisplay;
                        }
                    },
                    {
                        data: 'es_id',
                        title: 'حالة الفاتورة',
                        render: function(data, type, row) {
                            if (data && data.startsWith('FLY-RD')) {
                                return '<span class="cancelled-badge">إلغاء</span>';
                            }
                            return '<span class="badge bg-success">فعالة</span>';
                        }
                    },
                    { 
                        data: 'invoice_date', 
                        title: 'تاريخ الفاتورة',
                        render: function(data, type, row) {
                            return data || row.created_at || '-';
                        }
                    },
                    { data: 'invoice_travel_date', title: 'تاريخ السفر' },
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
                                <strong>${u.client_name}</strong><br>
                                حجز: ${u.client_booking_id || '-'} | تذكرة: ${u.client_ticket_id || '-'}<br>
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
                            // للفواتير الملغاة (FLY-RD)
                            if (row.es_id && row.es_id.startsWith("FLY-RD")) {
                                const mostarad = (row.account_statements || []).find(st =>
                                    parseFloat(st.credit_balance) > 0 && parseFloat(st.debit_balance) === 0
                                );
                                return mostarad ? parseFloat(mostarad.credit_balance).toFixed(2) : "0.00";
                            }

                            // للفواتير العادية
                            return (row.users || []).reduce((sum, user) => {
                                return sum + parseFloat(user.client_net_pice || 0);
                            }, 0).toFixed(2);
                        }
                    },
                    {
                        data: 'combined_client_net_pice',
                        title: 'البيع',
                        render: function (data, type, row) {
                            // للفواتير الملغاة (FLY-RD)
                            if (row.es_id && row.es_id.startsWith("FLY-RD")) {
                                const mortaga = (row.account_statements || []).find(st =>
                                    parseFloat(st.debit_balance) > 0 && parseFloat(st.credit_balance) === 0
                                );
                                return mortaga ? parseFloat(mortaga.debit_balance).toFixed(2) : "0.00";
                            }

                            // للفواتير العادية
                            return (row.users || []).reduce((sum, user) => {
                                return sum + parseFloat(user.client_bought_price || 0);
                            }, 0).toFixed(2);
                        }
                    },
                    {
                        data: 'profit_or_loss',
                        title: 'الربح',
                        render: function (data, type, row) {
                            // للفواتير الملغاة (FLY-RD)
                            if (row.es_id && row.es_id.startsWith("FLY-RD")) {
                                const mostarad = (row.account_statements || []).find(st =>
                                    parseFloat(st.credit_balance) > 0 && parseFloat(st.debit_balance) === 0
                                );
                                const mortaga = (row.account_statements || []).find(st =>
                                    parseFloat(st.debit_balance) > 0 && parseFloat(st.credit_balance) === 0
                                );

                                const net = mostarad ? parseFloat(mostarad.credit_balance) : 0;
                                const bought = mortaga ? parseFloat(mortaga.debit_balance) : 0;

                                return (bought - net).toFixed(2);
                            }

                            // للفواتير العادية
                            const net = (row.users || []).reduce((sum, user) => {
                                return sum + parseFloat(user.client_net_pice || 0);
                            }, 0);
                            const bought = (row.users || []).reduce((sum, user) => {
                                return sum + parseFloat(user.client_bought_price || 0);
                            }, 0);
                            const diff = bought - net;
                            return `${diff.toFixed(2)}`;
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
                        title: 'حالة السداد',
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
                                badge = `<span class="badge bg-dark my_badge">لم يتم السداد</span>`;
                            } else if (moneyPay < totalBought) {
                                badge = `<span class="badge bg-secondary my_badge">سداد جزئي</span>`;
                            } else {
                                badge = `<span class="badge bg-success my_badge">تم السداد</span>`;
                            }

                            let buttons = '';
                            if (isAccountType2) {
                                if (invoiceStatus === 0) {
                                    buttons = `
                                        <button class="btn btn-primary mt-1" id="rvd_${id}" 
                                                style="border-radius: 55px;font-size: 10px;" 
                                                onclick="do_approved(${id})">
                                            تأكيد العملية
                                        </button>
                                        <button class="btn btn-success mt-1" id="apprvd_${id}" 
                                                style="border-radius: 55px;font-size: 10px;display:none;">
                                            تم التأكيد
                                        </button>
                                    `;
                                } else {
                                    buttons = `
                                        <button class="btn btn-success mt-1" 
                                                style="border-radius: 55px;font-size: 10px;">
                                            تم التأكيد
                                        </button>
                                    `;
                                }
                            }

                            return `
                                <div style="text-align: center;">
                                    ${badge}<br>
                                    ${buttons}
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
                                    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" 
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                                        <li>
                                            <a href="/invoices/refund/${es_id}" class="dropdown-item">
                                                <i class="ri-refund-2-line"></i> الغاء الفاتورة
                                            </a>
                                        </li>
                                        <li>
                                            <a href="/invoices/${es_id}/remove" class="dropdown-item">
                                                <i class="ri-delete-bin-line"></i> حذف الفاتورة
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            `;
                            return html;
                        }
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.3/i18n/ar.json'
                },
                processing: true,
                paging: true,
                searching: true,
                ordering: true,
                responsive: true,
                autoWidth: false,
                rowCallback: function(row, data) {
                    // إضافة كلاس للصفوف الملغاة
                    if (data.es_id && data.es_id.startsWith('FLY-RD')) {
                        $(row).addClass('cancelled-invoice');
                    }
                }
            });
        },
        error: function (xhr, status, error) {
            console.error("فشل في تحميل البيانات:", xhr.responseText);
            alert("فشل في تحميل البيانات من الخادم.");
        }
    });
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