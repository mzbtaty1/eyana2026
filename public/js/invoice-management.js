/**
 * مدير الفواتير المحسن - ملف JavaScript منفصل
 */

class AdvancedInvoiceManager {
    constructor() {
        this.invoices = [];
        this.filteredInvoices = [];
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.searchTerm = '';
        this.statusFilter = 'all';
        this.dateFilter = 'all';
        this.sortBy = 'created_at';
        this.sortOrder = 'desc';
        
        // تحسين الأداء
        this.searchTimeout = null;
        this.cache = new Map();
        
        this.init();
    }
    
    async init() {
        this.showLoading(true);
        await this.loadInvoices();
        this.setupEventListeners();
        this.updateDisplay();
        this.showLoading(false);
    }
    
    /**
     * تحميل الفواتير من الخادم مع التخزين المؤقت
     */
    async loadInvoices() {
        const cacheKey = 'invoices_' + Date.now();
        
        if (this.cache.has('invoices')) {
            this.invoices = this.cache.get('invoices');
            this.filteredInvoices = [...this.invoices];
            return;
        }
        
        try {
            const response = await fetch('/api/invoices?per_page=1000');
            const data = await response.json();
            
            this.invoices = data.data || data;
            this.filteredInvoices = [...this.invoices];
            
            // تخزين مؤقت لمدة 5 دقائق
            this.cache.set('invoices', this.invoices);
            setTimeout(() => this.cache.delete('invoices'), 5 * 60 * 1000);
            
        } catch (error) {
            console.error('خطأ في تحميل البيانات:', error);
            this.showError('حدث خطأ في تحميل البيانات');
        }
    }
    
    /**
     * إعداد مستمعي الأحداث مع تحسين الأداء
     */
    setupEventListeners() {
        // البحث مع تأخير لتحسين الأداء
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.searchTerm = e.target.value;
                    this.filterInvoices();
                }, 300); // تأخير 300ms
            });
        }
        
        // فلاتر فورية
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.statusFilter = e.target.value;
                this.filterInvoices();
            });
        }
        
        const dateFilter = document.getElementById('dateFilter');
        if (dateFilter) {
            dateFilter.addEventListener('change', (e) => {
                this.dateFilter = e.target.value;
                this.filterInvoices();
            });
        }
        
        // إعادة تحميل البيانات كل 30 ثانية
        setInterval(() => {
            this.refreshData();
        }, 30000);
    }
    
    /**
     * فلترة الفواتير مع تحسين الأداء
     */
    filterInvoices() {
        this.showLoading(true);
        
        // استخدام requestAnimationFrame لتحسين الأداء
        requestAnimationFrame(() => {
            this.filteredInvoices = this.invoices.filter(invoice => {
                return this.matchesFilters(invoice);
            });
            
            this.currentPage = 1;
            this.updateDisplay();
            this.showLoading(false);
        });
    }
    
    /**
     * فحص تطابق الفلاتر
     */
    matchesFilters(invoice) {
        // فلتر البحث
        const searchLower = this.searchTerm.toLowerCase();
        const matchesSearch = !this.searchTerm || 
            invoice.es_id.toLowerCase().includes(searchLower) ||
            invoice.client_name.toLowerCase().includes(searchLower);
        
        // فلتر الحالة
        const matchesStatus = this.statusFilter === 'all' || 
            invoice.invoice_status.toString() === this.statusFilter;
        
        // فلتر التاريخ
        const matchesDate = this.dateFilter === 'all' || 
            this.checkDateFilter(invoice.created_at);
        
        return matchesSearch && matchesStatus && matchesDate;
    }
    
    /**
     * فحص فلتر التاريخ
     */
    checkDateFilter(dateString) {
        const invoiceDate = new Date(dateString);
        const today = new Date();
        const diffTime = today.getTime() - invoiceDate.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        switch (this.dateFilter) {
            case 'today':
                return diffDays <= 1;
            case 'week':
                return diffDays <= 7;
            case 'month':
                return diffDays <= 30;
            default:
                return true;
        }
    }
    
    /**
     * تحديث العرض
     */
    updateDisplay() {
        this.updateStats();
        this.updateTable();
        this.updatePagination();
    }
    
    /**
     * تحديث الإحصائيات
     */
    updateStats() {
        const total = this.invoices.length;
        const unpaid = this.invoices.filter(inv => inv.invoice_status === 0).length;
        const partial = this.invoices.filter(inv => inv.invoice_status === 1).length;
        const paid = this.invoices.filter(inv => inv.invoice_status === 2).length;
        
        this.animateNumber('totalInvoices', total);
        this.animateNumber('unpaidInvoices', unpaid);
        this.animateNumber('partialInvoices', partial);
        this.animateNumber('paidInvoices', paid);
    }
    
    /**
     * تحريك الأرقام في الإحصائيات
     */
    animateNumber(elementId, targetNumber) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const currentNumber = parseInt(element.textContent) || 0;
        const increment = (targetNumber - currentNumber) / 20;
        
        let current = currentNumber;
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= targetNumber) || 
                (increment < 0 && current <= targetNumber)) {
                current = targetNumber;
                clearInterval(timer);
            }
            element.textContent = Math.round(current);
        }, 50);
    }
    
    /**
     * تحديث الجدول مع التحسينات
     */
    updateTable() {
        const tbody = document.getElementById('invoicesTableBody');
        const noResults = document.getElementById('noResults');
        
        if (!tbody) return;
        
        if (this.filteredInvoices.length === 0) {
            tbody.innerHTML = '';
            if (noResults) noResults.style.display = 'block';
            return;
        }
        
        if (noResults) noResults.style.display = 'none';
        
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const currentInvoices = this.filteredInvoices.slice(startIndex, endIndex);
        
        // استخدام DocumentFragment لتحسين الأداء
        const fragment = document.createDocumentFragment();
        
        currentInvoices.forEach(invoice => {
            const row = this.createInvoiceRow(invoice);
            fragment.appendChild(row);
        });
        
        tbody.innerHTML = '';
        tbody.appendChild(fragment);
    }
    
    /**
     * إنشاء صف الفاتورة
     */
    createInvoiceRow(invoice) {
        const row = document.createElement('tr');
        row.className = 'invoice-row';
        row.innerHTML = this.getInvoiceRowHTML(invoice);
        
        // إضافة تأثيرات التفاعل
        row.addEventListener('mouseenter', () => {
            row.style.backgroundColor = '#f8f9fa';
        });
        
        row.addEventListener('mouseleave', () => {
            row.style.backgroundColor = '';
        });
        
        return row;
    }
    
    /**
     * HTML صف الفاتورة
     */
    getInvoiceRowHTML(invoice) {
        const statusText = this.getStatusText(invoice.invoice_status);
        const statusClass = `status-${invoice.invoice_status}`;
        const formattedDate = new Date(invoice.created_at).toLocaleDateString('ar-EG');
        
        return `
            <td><strong>${invoice.es_id}</strong></td>
            <td>${invoice.client_name}</td>
            <td><strong>${this.formatCurrency(invoice.total_amount)}</strong></td>
            <td><span style="color: #10b981; font-weight: bold;">${this.formatCurrency(invoice.invoice_money_pay)}</span></td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td>${formattedDate}</td>
            <td>${this.createActionButtons(invoice)}</td>
        `;
    }
    
    /**
     * تنسيق العملة
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('ar-EG').format(amount) + ' ج.م';
    }
    
    /**
     * إنشاء أزرار الإجراءات
     */
    createActionButtons(invoice) {
        const buttons = [];
        
        // زر التعديل
        if (window.userAccountType == 2 || invoice.invoice_status == 0) {
            buttons.push(`
                <a href="/invoices/edit/${invoice.id}" class="action-btn btn-edit" title="تعديل">
                    <i class="ri-edit-box-line"></i>
                </a>
            `);
        }
        
        // زر السداد
        if (invoice.invoice_money_pay < invoice.total_amount) {
            buttons.push(`
                <a href="/invoices/pay/${invoice.id}" class="action-btn btn-pay" title="سداد">
                    <i class="ri-wallet-3-line"></i>
                </a>
            `);
        }
        
        // باقي الأزرار...
        buttons.push(`
            <a href="/invoices/reissue/${invoice.es_id}" class="action-btn btn-reissue" title="إعادة إصدار">
                <i class="ri-arrow-go-forward-line"></i>
            </a>
        `);
        
        buttons.push(`
            <a href="/invoices/refund/${invoice.es_id}" class="action-btn btn-cancel" title="إلغاء">
                <i class="ri-refund-2-line"></i>
            </a>
        `);
        
        buttons.push(`
            <a href="/invoices/remove/${invoice.es_id}" class="action-btn btn-delete" title="حذف" 
               onclick="return confirm('هل أنت متأكد من حذف هذه الفاتورة؟')">
                <i class="ri-delete-bin-line"></i>
            </a>
        `);
        
        return buttons.join('');
    }
    
    /**
     * نص الحالة
     */
    getStatusText(status) {
        const statusTexts = {
            0: 'غير مدفوعة',
            1: 'مدفوعة جزئياً',
            2: 'مدفوعة بالكامل'
        };
        return statusTexts[status] || 'غير محدد';
    }
    
    /**
     * تحديث الترقيم
     */
    updatePagination() {
        const totalPages = Math.ceil(this.filteredInvoices.length / this.itemsPerPage);
        const pagination = document.getElementById('pagination');
        
        if (!pagination || totalPages <= 1) {
            if (pagination) pagination.innerHTML = '';
            return;
        }
        
        pagination.innerHTML = this.createPaginationHTML(totalPages);
    }
    
    /**
     * إنشاء HTML الترقيم
     */
    createPaginationHTML(totalPages) {
        let html = '';
        
        // زر السابق
        html += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="invoiceManager.goToPage(${this.currentPage - 1}); return false;">السابق</a>
            </li>
        `;
        
        // أرقام الصفحات
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(totalPages, startPage + 4);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${this.currentPage === i ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="invoiceManager.goToPage(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        // زر التالي
        html += `
            <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="invoiceManager.goToPage(${this.currentPage + 1}); return false;">التالي</a>
            </li>
        `;
        
        return html;
    }
    
    /**
     * الانتقال لصفحة معينة
     */
    goToPage(page) {
        const totalPages = Math.ceil(this.filteredInvoices.length / this.itemsPerPage);
        if (page >= 1 && page <= totalPages && page !== this.currentPage) {
            this.currentPage = page;
            this.updateTable();
            this.updatePagination();
            
            // التمرير لأعلى الجدول
            document.getElementById('invoicesTable').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
    }
    
    /**
     * تحديث البيانات
     */
    async refreshData() {
        this.cache.clear();
        await this.loadInvoices();
        this.filterInvoices();
    }
    
    /**
     * إظهار/إخفاء التحميل
     */
    showLoading(show) {
        const loader = document.getElementById('loadingSpinner');
        if (loader) {
            loader.style.display = show ? 'block' : 'none';
        }
    }
    
    /**
     * إظهار رسالة خطأ
     */
    showError(message) {
        if (typeof swal !== 'undefined') {
            swal('خطأ', message, 'error');
        } else {
            alert(message);
        }
    }
    
    /**
     * تصدير البيانات
     */
    exportData() {
        const params = new URLSearchParams({
            search: this.searchTerm,
            status: this.statusFilter,
            date_filter: this.dateFilter
        });
        
        window.open(`/invoices/export?${params.toString()}`, '_blank');
    }
}

// تشغيل المدير عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    window.invoiceManager = new AdvancedInvoiceManager();
    
    // إضافة متغير نوع الحساب للاستخدام في JavaScript
    window.userAccountType = {{ Auth::user()->account_type ?? 0 }};
});