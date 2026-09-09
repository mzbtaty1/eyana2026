// DataTables Configuration for Invoices - New Version
class InvoiceDataTableNew {
    constructor() {
        this.authAccountType = window.authAccountType || 0;
        this.init();
    }

    init() {
        this.loadData();
    }

    loadData() {
        $.ajax({
            url: invoicesJsonRoute,
            method: 'GET',
            success: (response) => this.handleSuccess(response),
            error: (xhr, status, error) => this.handleError(xhr, status, error)
        });
    }

    handleSuccess(response) {
        if (!response || !response.data || !Array.isArray(response.data)) {
            alert('البيانات غير صحيحة أو فارغة');
            return;
        }

        this.initializeDataTable(response.data);
    }

    handleError(xhr, status, error) {
        console.error("فشل في تحميل البيانات:", xhr.responseText);
        alert("فشل في تحميل البيانات من الخادم.");
    }

    initializeDataTable(data) {
        $('#invoicesTableNew').DataTable({
            data: data,
            order: [[1, 'desc']],
            columns: this.getColumns(),
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.3/i18n/ar.json'
            },
            processing: true,
            paging: true,
            searching: true,
            ordering: true,
            responsive: true,
            autoWidth: false
        });
    }

    getColumns() {
        return [
            this.getEsIdColumn(),
            { data: 'invoice_date', title: 'تاريخ الفاتورة' },
            { data: 'invoice_travel_date', title: 'تاريخ السفر' },
            this.getSupplierColumn(),
            this.getBeneficiaryColumn(),
            this.getUsersColumn(),
            this.getLocationColumn(),
            this.getCostColumn(),
            this.getSaleColumn(),
            this.getProfitColumn(),
            this.getEmployeeColumn(),
            this.getStatusColumn(),
            this.getActionsColumn()
        ];
    }

    getEsIdColumn() {
        return {
            data: 'es_id',
            title: 'ES ID',
            render: (data, type, row) => {
                if (!row.invoice_ticket_file) return data;
                const fileUrl = `/public/storage/${row.invoice_ticket_file.replace('storage/', '')}`;
                return `<a href="${fileUrl}" target="_blank">${data}</a>`;
            }
        };
    }

    getSupplierColumn() {
        return {
            data: null,
            title: 'اسم المورد',
            render: (data, type, row) => {
                if (!row.ticket_vendors || !Array.isArray(row.ticket_vendors)) return '';
                return row.ticket_vendors
                    .filter(v => v?.supplier?.name)
                    .map(v => v.supplier.name)
                    .join(" / ");
            }
        };
    }

    getBeneficiaryColumn() {
        return {
            data: null,
            title: 'اسم المستفيد',
            render: (data, type, row) => row?.beneficiaries?.name || ''
        };
    }

    getUsersColumn() {
        return {
            data: 'custom_users',
            title: 'المستخدمين',
            render: (data, type, row) => {
                if (!row.users || !Array.isArray(row.users)) return '';
                return row.users.map(u => `
                    <strong>${u.client_name}</strong><br>
                    ارقام الحجز: ${u.client_booking_id || '-'}<br>
                    ارقام التذاكر: ${u.client_ticket_id || '-'}<br>
                `).join('');
            }
        };
    }

    getLocationColumn() {
        return {
            data: 'combined_location',
            title: 'الاستلام / الوصول',
            render: (data, type, row) => {
                const from = row.from_location || '';
                const to = row.to_location || '';
                return from || to ? `${from} / ${to}` : '';
            }
        };
    }

    getCostColumn() {
        return {
            data: 'combined_client_net_pice',
            title: 'التكلفة',
            render: (data, type, row) => {
                return (row.users || []).reduce((sum, user) => {
                    return sum + parseFloat(user.client_net_pice || 0);
                }, 0).toFixed(2);
            }
        };
    }

    getSaleColumn() {
        return {
            data: 'combined_client_net_pice',
            title: 'البيع',
            render: (data, type, row) => {
                return (row.users || []).reduce((sum, user) => {
                    return sum + parseFloat(user.client_bought_price || 0);
                }, 0).toFixed(2);
            }
        };
    }

    getProfitColumn() {
        return {
            data: 'profit_or_loss',
            title: 'الربح',
            render: (data, type, row) => {
                const net = (row.users || []).reduce((sum, user) => {
                    return sum + parseFloat(user.client_net_pice || 0);
                }, 0);

                const bought = (row.users || []).reduce((sum, user) => {
                    return sum + parseFloat(user.client_bought_price || 0);
                }, 0);

                const diff = bought - net;
                return `${diff.toFixed(2)}`;
            }
        };
    }

    getEmployeeColumn() {
        return {
            data: 'creator_name',
            title: 'الموظف',
            render: (data, type, row) => {
                return row.creator && row.creator.name ? row.creator.name : '-';
            }
        };
    }

    getStatusColumn() {
        return {
            data: 'payment_and_action',
            title: 'الإجراء / السداد',
            render: (data, type, row) => {
                const id = row.id;
                const moneyPay = parseFloat(row.invoice_money_pay || 0);
                const totalBought = (row.users || []).reduce((sum, user) => {
                    return sum + parseFloat(user.client_bought_price || 0);
                }, 0);
                const invoiceStatus = parseInt(row.invoice_status || 0);
                const isAccountType2 = this.authAccountType === 2;

                let badge = this.getPaymentBadge(moneyPay, totalBought);
                let buttons = this.getActionButtons(id, invoiceStatus, isAccountType2);

                return `
                    <div style="text-align: center;">
                        ${badge}<br>
                        ${buttons}
                    </div>
                `;
            }
        };
    }

    getPaymentBadge(moneyPay, totalBought) {
        if (moneyPay === 0) {
            return `<span class="badge bg-dark my_badge">لم يتم السداد</span>`;
        } else if (moneyPay < totalBought) {
            return `<span class="badge bg-secondary my_badge">سداد جزئي</span>`;
        } else {
            return `<span class="badge bg-success my_badge">تم السداد</span>`;
        }
    }

    getActionButtons(id, invoiceStatus, isAccountType2) {
        if (!isAccountType2) return '';

        if (invoiceStatus === 0) {
            return `
                <button class="btn btn-primary action-btn" id="rvd_new_${id}" onclick="approveInvoiceNew(${id})">
                    تأكيد العملية
                </button>
                <button class="btn btn-success action-btn" id="apprvd_new_${id}" style="display:none;">
                    تم التأكيد
                </button>
            `;
        } else {
            return `
                <button class="btn btn-success action-btn">
                    تم التأكيد
                </button>
            `;
        }
    }

    getActionsColumn() {
        return {
            data: 'actions_dropdown',
            title: 'اجراء',
            orderable: false,
            render: (data, type, row) => {
                const id = row.id;
                const es_id = row.es_id;
                const moneyPay = parseFloat(row.invoice_money_pay || 0);
                const totalBought = (row.users || []).reduce((sum, user) => {
                    return sum + parseFloat(user.client_bought_price || 0);
                }, 0);
                const invoiceStatus = parseInt(row.invoice_status || 0);
                const accountType = this.authAccountType;

                return this.buildActionsDropdown(id, es_id, moneyPay, totalBought, invoiceStatus, accountType);
            }
        };
    }

    buildActionsDropdown(id, es_id, moneyPay, totalBought, invoiceStatus, accountType) {
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

        // Edit invoice
        if (accountType === 2 || invoiceStatus === 0) {
            html += `
                <li>
                    <a href="/invoices/${id}/edit" class="dropdown-item">
                        <i class="ri-edit-box-line me-2"></i> تعديل
                    </a>
                </li>
            `;
        }

        // Partial payment
        if (moneyPay < totalBought) {
            html += `
                <li>
                    <a href="/invoices/pay-part/${id}" class="dropdown-item">
                        <i class="ri-wallet-3-line me-2"></i> سداد الفاتورة
                    </a>
                </li>
            `;
        }

        // Reissue
        html += `
            <li>
                <a href="/invoices/reissue/${es_id}" class="dropdown-item">
                    <i class="ri-arrow-go-forward-line me-2"></i> اعادة اصدار
                </a>
            </li>
        `;

        // Cancel
        html += `
            <li>
                <a href="/invoices/refund/${es_id}" class="dropdown-item">
                    <i class="ri-refund-2-line me-2"></i> الغاء الفاتورة
                </a>
            </li>
        `;

        // Delete
        html += `
            <li>
                <a href="/invoices/${es_id}/remove" class="dropdown-item text-danger">
                    <i class="ri-delete-bin-line me-2"></i> حذف الفاتورة
                </a>
            </li>
        `;

        html += `</ul></div>`;
        return html;
    }
}

// Initialize when document is ready
$(document).ready(function() {
    window.authAccountType = authAccountType;
    new InvoiceDataTableNew();
});