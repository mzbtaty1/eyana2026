// متغيرات عامة
let invoicesTable;
let invoicesData = [];
let currentPage = 1;
let itemsPerPage = 10;
let searchTerm = '';
let sortColumn = 1;
let sortDirection = 'desc';

// إعدادات المصادقة
window.authAccountType = window.authAccountType || 1;

// دالة التهيئة الرئيسية
$(document).ready(function() {
    initializeInvoicesTable();
    setupEventListeners();
});

// تهيئة الجدول
async function initializeInvoicesTable() {
    try {
        showLoading(true);
        await loadInvoicesData();
        renderTable();
        setupPagination();
        showLoading(false);
    } catch (error) {
        console.error('خطأ في تهيئة الجدول:', error);
        showError('فشل في تحميل البيانات من الخادم');
        showLoading(false);
    }
}

// تحميل البيانات من الخادم
async function loadInvoicesData() {
    try {
        const response = await fetch('/invoices/json', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (!data || !data.data || !Array.isArray(data.data)) {
            throw new Error('البيانات غير صحيحة أو فارغة');
        }

        invoicesData = data.data;
        return invoicesData;
    } catch (error) {
        console.error('خطأ في تحميل البيانات:', error);
        throw error;
    }
}

// عرض الجدول
function renderTable() {
    const filteredData = getFilteredData();
    const paginatedData = getPaginatedData(filteredData);
    
    const tbody = document.querySelector('#invoicesTable tbody');
    tbody.innerHTML = '';
    
    if (paginatedData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="13" class="text-center py-4">
                    <i class="ri-inbox-line" style="font-size: 48px; color: #6c757d;"></i>
                    <p class="mt-2 text-muted">لا توجد فواتير للعرض</p>
                </td>
            </tr>
        `;
        return;
    }
    
    paginatedData.forEach(row => {
        const tr = createTableRow(row);
        tbody.appendChild(tr);
    });
    
    updateTableInfo(filteredData.length);
}

// إنشاء صف الجدول
function createTableRow(row) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="text-center">${renderESID(row)}</td>
        <td class="text-center">${formatDate(row.invoice_date)}</td>
        <td class="text-center">${formatDate(row.invoice_travel_date)}</td>
        <td class="text-center">${renderSuppliers(row)}</td>
        <td class="text-center">${renderBeneficiary(row)}</td>
        <td>${renderUsers(row)}</td>
        <td class="text-center">${renderLocations(row)}</td>
        <td class="text-center text-danger fw-bold">${calculateTotalCost(row)}</td>
        <td class="text-center text-success fw-bold">${calculateTotalSale(row)}</td>
        <td class="text-center">${renderProfit(row)}</td>
        <td class="text-center">${renderCreator(row)}</td>
        <td class="text-center">${renderInvoiceStatus(row)}</td>
        <td class="text-center">${renderActions(row)}</td>
    `;
    return tr;
}

// دوال العرض المساعدة
function renderESID(row) {
    if (!row.invoice_ticket_file) {
        return `<span class="badge bg-secondary">${row.es_id}</span>`;
    }
    
    const fileUrl = `/storage/${row.invoice_ticket_file.replace('storage/', '')}`;
    return `<a href="${fileUrl}" target="_blank" class="file-link">
        <span class="badge bg-primary">${row.es_id}</span>
    </a>`;
}

function renderSuppliers(row) {
    if (!row.ticket_vendors || !Array.isArray(row.ticket_vendors)) {
        return '<span class="text-muted">-</span>';
    }
    
    const suppliers = row.ticket_vendors
        .filter(v => v?.supplier?.name)
        .map(v => `<small class="badge bg-info me-1">${v.supplier.name}</small>`)
        .join(' ');
    
    return suppliers || '<span class="text-muted">-</span>';
}

function renderBeneficiary(row) {
    return row?.beneficiaries?.name ? 
        `<span class="badge bg-success">${row.beneficiaries.name}</span>` : 
        '<span class="text-muted">-</span>';
}

function renderUsers(row) {
    if (!row.users || !Array.isArray(row.users)) {
        return '<span class="text-muted">-</span>';
    }
    
    return row.users.map(user => `
        <div class="border rounded p-2 mb-2 bg-light">
            <strong class="text-primary">${user.client_name}</strong><br>
            <small class="text-muted">
                حجز: ${user.client_booking_id || '-'}<br>
                تذكرة: ${user.client_ticket_id || '-'}
            </small>
        </div>
    `).join('');
}

function renderLocations(row) {
    const from = row.from_location || '';
    const to = row.to_location || '';
    
    if (!from && !to) {
        return '<span class="text-muted">-</span>';
    }
    
    return `
        <div class="text-center">
            <small class="text-primary">${from}</small>
            <br>
            <i class="ri-arrow-down-line text-muted"></i>
            <br>
            <small class="text-success">${to}</small>
        </div>
    `;
}

function calculateTotalCost(row) {
    const total = (row.users || []).reduce((sum, user) => {
        return sum + parseFloat(user.client_net_pice || 0);
    }, 0);
    return total.toFixed(2);
}

function calculateTotalSale(row) {
    const total = (row.users || []).reduce((sum, user) => {
        return sum + parseFloat(user.client_bought_price || 0);
    }, 0);
    return total.toFixed(2);
}

function renderProfit(row) {
    const cost = parseFloat(calculateTotalCost(row));
    const sale = parseFloat(calculateTotalSale(row));
    const profit = sale - cost;
    
    let className = '';
    let icon = '';
    
    if (profit > 0) {
        className = 'profit-positive';
        icon = 'ri-arrow-up-line';
    } else if (profit < 0) {
        className = 'profit-negative';
        icon = 'ri-arrow-down-line';
    } else {
        className = 'profit-zero';
        icon = 'ri-subtract-line';
    }
    
    return `<span class="${className}">
        <i class="${icon}"></i>
        ${profit.toFixed(2)}
    </span>`;
}

function renderCreator(row) {
    return row.creator?.name ? 
        `<span class="badge bg-outline-primary">${row.creator.name}</span>` : 
        '<span class="text-muted">-</span>';
}

function renderInvoiceStatus(row) {
    const moneyPay = parseFloat(row.invoice_money_pay || 0);
    const totalSale = parseFloat(calculateTotalSale(row));
    const invoiceStatus = parseInt(row.invoice_status || 0);
    
    // حالة السداد
    let paymentBadge = '';
    if (moneyPay === 0) {
        paymentBadge = '<span class="badge bg-danger mb-1">لم يتم السداد</span>';
    } else if (moneyPay < totalSale) {
        paymentBadge = '<span class="badge bg-warning mb-1">سداد جزئي</span>';
    } else {
        paymentBadge = '<span class="badge bg-success mb-1">تم السداد</span>';
    }
    
    // زر التأكيد
    let approvalButton = '';
    if (window.authAccountType === 2) {
        if (invoiceStatus === 0) {
            approvalButton = `
                <button class="btn btn-outline-primary btn-sm d-block mt-1 approve-btn" 
                        data-id="${row.id}" onclick="approveInvoice(${row.id})">
                    <i class="ri-check-line me-1"></i>تأكيد
                </button>
            `;
        } else {
            approvalButton = `
                <button class="btn btn-success btn-sm d-block mt-1" disabled>
                    <i class="ri-check-double-line me-1"></i>مؤكد
                </button>
            `;
        }
    }
    
    return `
        <div class="text-center">
            ${paymentBadge}<br>
            ${approvalButton}
        </div>
    `;
}

function renderActions(row) {
    const accountType = window.authAccountType;
    const invoiceStatus = parseInt(row.invoice_status || 0);
    const moneyPay = parseFloat(row.invoice_money_pay || 0);
    const totalSale = parseFloat(calculateTotalSale(row));
    
    let actions = `
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                    type="button" data-bs-toggle="dropdown">
                <i class="ri-more-2-line"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a href="/invoices/${row.id}/show" class="dropdown-item">
                        <i class="ri-eye-line me-2"></i>عرض التفاصيل
                    </a>
                </li>
    `;
    
    // تعديل (للمدير أو إذا كانت غير مؤكدة)
    if (accountType === 2 || invoiceStatus === 0) {
        actions += `
            <li>
                <a href="/invoices/${row.id}/edit" class="dropdown-item">
                    <i class="ri-edit-2-line me-2"></i>تعديل الفاتورة
                </a>
            </li>
        `;
    }
    
    // سداد جزئي
    if (moneyPay < totalSale) {
        actions += `
            <li>
                <a href="/invoices/pay-part/${row.id}" class="dropdown-item text-warning">
                    <i class="ri-wallet-3-line me-2"></i>سداد الفاتورة
                </a>
            </li>
        `;
    }
    
    actions += `
        <li><hr class="dropdown-divider"></li>
        <li>
            <a href="/invoices/reissue/${row.es_id}" class="dropdown-item">
                <i class="ri-refresh-line me-2"></i>إعادة إصدار
            </a>
        </li>
        <li>
            <a href="/invoices/refund/${row.es_id}" class="dropdown-item text-warning">
                <i class="ri-refund-2-line me-2"></i>إلغاء الفاتورة
            </a>
        </li>
        <li>
            <a href="/invoices/${row.es_id}/remove" class="dropdown-item text-danger">
                <i class="ri-delete-bin-line me-2"></i>حذف الفاتورة
            </a>
        </li>
    </ul>
</div>
    `;
    
    return actions;
}

// دوال المساعدة
function getFilteredData() {
    if (!searchTerm) return invoicesData;
    
    return invoicesData.filter(row => {
        const searchableText = [
            row.es_id,
            row.invoice_date,
            row.invoice_travel_date,
            row.beneficiaries?.name || '',
            row.creator?.name || '',
            ...(row.ticket_vendors || []).map(v => v.supplier?.name || ''),
            ...(row.users || []).map(u => u.client_name || '')
        ].join(' ').toLowerCase();
        
        return searchableText.includes(searchTerm.toLowerCase());
    });
}

function getPaginatedData(data) {
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    return data.slice(startIndex, endIndex);
}

function updateTableInfo(totalItems) {
    const startItem = totalItems === 0 ? 0 : ((currentPage - 1) * itemsPerPage) + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    
    document.getElementById('tableInfo').innerHTML = 
        `عرض ${startItem} إلى ${endItem} من ${totalItems} عنصر`;
}

function setupPagination() {
    const filteredData = getFilteredData();
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    
    const paginationContainer = document.getElementById('tablePagination');
    
    if (totalPages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let paginationHTML = '<nav><ul class="pagination pagination-sm">';
    
    // Previous button
    paginationHTML += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage - 1})">السابق</a>
        </li>
    `;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            paginationHTML += `
                <li class="page-item ${currentPage === i ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="goToPage(${i})">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Next button
    paginationHTML += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage + 1})">التالي</a>
        </li>
    `;
    
    paginationHTML += '</ul></nav>';
    paginationContainer.innerHTML = paginationHTML;
}

// معالجات الأحداث
function setupEventListeners() {
    // البحث
    document.getElementById('searchInput').addEventListener('input', function(e) {
        searchTerm = e.target.value;
        currentPage = 1;
        renderTable();
        setupPagination();
    });
    
    // تغيير عدد العناصر في الصفحة
    document.getElementById('entriesPerPage').addEventListener('change', function(e) {
        itemsPerPage = parseInt(e.target.value);
        currentPage = 1;
        renderTable();
        setupPagination();
    });
    
    // تأكيد الموافقة
    document.getElementById('confirmApproval').addEventListener('click', function() {
        const invoiceId = this.dataset.invoiceId;
        if (invoiceId) {
            performApproval(invoiceId);
        }
    });
}

// دوال التنقل
function goToPage(page) {
    const filteredData = getFilteredData();
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        renderTable();
        setupPagination();
    }
}

// دالة التأكيد
async function approveInvoice(id) {
    const result = await Swal.fire({
        title: 'تأكيد العملية',
        text: 'هل أنت متأكد من تأكيد هذه الفاتورة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، أكد',
        cancelButtonText: 'إلغاء'
    });
    
    if (result.isConfirmed) {
        await performApproval(id);
    }
}

async function performApproval(id) {
    try {
        showButtonLoading(id, true);
        
        const response = await fetch(`/invoices/${id}/approve`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.status_code === 200) {
            await Swal.fire({
                title: 'تم بنجاح!',
                text: 'تم تأكيد الفاتورة بنجاح',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
            
            // تحديث البيانات
            await loadInvoicesData();
            renderTable();
        } else {
            throw new Error('فشل في تأكيد الفاتورة');
        }
    } catch (error) {
        console.error('خطأ في التأكيد:', error);
        await Swal.fire({
            title: 'خطأ!',
            text: 'فشل في تأكيد الفاتورة',
            icon: 'error'
        });
    } finally {
        showButtonLoading(id, false);
    }
}

// دوال المساعدة للواجهة
function showLoading(show) {
    const tbody = document.querySelector('#invoicesTable tbody');
    if (show) {
        tbody.innerHTML = `
            <tr>
                <td colspan="13" class="text-center py-4">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        جاري تحميل البيانات...
                    </div>
                </td>
            </tr>
        `;
    }
}

function showButtonLoading(id, show) {
    const button = document.querySelector(`[data-id="${id}"]`);
    if (!button) return;
    
    if (show) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>جاري التأكيد...';
    } else {
        button.disabled = false;
        button.innerHTML = '<i class="ri-check-line me-1"></i>تأكيد';
    }
}

function showError(message) {
    Swal.fire({
        title: 'خطأ!',
        text: message,
        icon: 'error'
    });
}

function formatDate(dateString) {
    if (!dateString) return '-';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-EG', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch (error) {
        return dateString;
    }
}

// تصدير الدوال للاستخدام العام
window.goToPage = goToPage;
window.approveInvoice = approveInvoice;