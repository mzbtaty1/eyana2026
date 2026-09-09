@extends('layouts.app')
@section('content')
@section('title', "إدارة الفواتير")

<!-- Add CSRF token meta tag -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
/* Professional Color Palette */
:root {
    --primary-color: #2563eb;
    --primary-light: #3b82f6;
    --primary-dark: #1d4ed8;
    --success-color: #059669;
    --warning-color: #d97706;
    --danger-color: #dc2626;

    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    --white: #ffffff;
    --border-radius: 8px;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
}

/* Hide broken images */
img[src*="logo-sm.png"], 
img[src*="logo-dark.png"], 
img[src*="logo-light.png"],
img[src*="avatar-"] {
    display: none !important;
}

/* Main Container */
.container-fluid {
    background-color: var(--gray-50);
    min-height: 100vh;
    padding: 24px;
}

/* Header Section */
.page-header {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 8px 0;
}

.page-subtitle {
    color: var(--gray-600);
    font-size: 16px;
    margin: 0;
}

/* Search Container */
.search-container {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
}

.search-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--gray-900);
    margin: 0 0 8px 0;
    text-align: center;
}

.search-description {
    color: var(--gray-600);
    font-size: 14px;
    margin: 0 0 20px 0;
    text-align: center;
}

.search-input-group {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}

.search-input {
    width: 100%;
    padding: 12px 50px 12px 16px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 16px;
    background: var(--white);
    color: var(--gray-900);
    transition: all 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
}

.search-input::placeholder {
    color: var(--gray-400);
}

.search-icons {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-icon {
    color: var(--gray-400);
    font-size: 18px;
}

.clear-search {
    background: var(--danger-color);
    color: var(--white);
    border: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
}

.clear-search.show {
    display: flex;
}

/* Filters Section */
.filters-container {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
}

.filter-select {
    padding: 10px 12px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    background: var(--white);
    color: var(--gray-900);
    font-size: 14px;
    transition: all 0.2s ease;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
}

.filter-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 8px;
    display: block;
}

.refresh-btn {
    background: var(--primary-color);
    color: var(--white);
    border: none;
    padding: 10px 20px;
    border-radius: var(--border-radius);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    justify-content: center;
}

.refresh-btn:hover {
    background: var(--primary-dark);
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 24px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-color);
}

.stat-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    margin: 8px 0;
    color: var(--gray-900);
}

.stat-label {
    color: var(--gray-600);
    font-size: 14px;
    font-weight: 500;
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

/* Professional Table Styling */
#invoicesTable {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    margin: 0;
}

#invoicesTable thead {
    background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
}

#invoicesTable thead th {
    padding: 16px 12px;
    font-weight: 600;
    color: var(--gray-700);
    text-align: center;
    border-bottom: 2px solid var(--gray-200);
    border-right: 1px solid var(--gray-200);
    white-space: nowrap;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#invoicesTable thead th:last-child {
    border-right: none;
}

#invoicesTable tbody tr {
    background: var(--white);
    transition: all 0.2s ease;
}

#invoicesTable tbody tr:hover {
    background: var(--gray-50);
}

#invoicesTable tbody tr:nth-child(even) {
    background: #fafbfc;
}

#invoicesTable tbody tr:nth-child(even):hover {
    background: var(--gray-50);
}

#invoicesTable tbody td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--gray-200);
    border-right: 1px solid var(--gray-100);
    vertical-align: middle;
    text-align: center;
}

#invoicesTable tbody td:last-child {
    border-right: none;
}

/* Invoice ID Styling */
.invoice-id {
    font-weight: 700;
    font-size: 14px;
    color: var(--primary-color);
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 6px;
    background: var(--gray-50);
    border: 2px solid var(--primary-color);
    display: inline-block;
    transition: all 0.2s ease;
    min-width: 80px;
}

.invoice-id:hover {
    background: var(--primary-color);
    color: var(--white);
    text-decoration: none;
}

.invoice-id.has-file {
    background: #ecfdf5;
    border-color: var(--success-color);
    color: var(--success-color);
}

.invoice-id.has-file:hover {
    background: var(--success-color);
    color: var(--white);
}

.invoice-id.has-file::after {
    content: "📎";
    margin-right: 4px;
}

/* Date Styling */
.date-text {
    color: var(--gray-600);
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

/* Names Styling */
.vendor-name, .beneficiary-name {
    color: var(--gray-700);
    font-size: 12px;
    font-weight: 500;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Money Values */
.money-value {
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
    padding: 4px 8px;
    border-radius: 4px;
}

.money-cost {
    color: var(--danger-color);
    background: #fef2f2;
}

.money-sell {
    color: var(--primary-color);
    background: #eff6ff;
}

.money-paid {
    color: var(--success-color);
    background: #ecfdf5;
}

.money-profit-positive {
    color: var(--success-color);
    background: #ecfdf5;
}

.money-profit-negative {
    color: var(--danger-color);
    background: #fef2f2;
}

/* Route Styling */
.route-container {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 100px;
}

.route-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: var(--gray-600);
}

.route-item i {
    font-size: 12px;
    width: 14px;
}

/* Passenger Names */
.passenger-names {
    max-width: 280px;
    min-width: 280px;
    text-align: right;
}

.passenger-name {
    background: var(--gray-100);
    color: var(--gray-700);
    padding: 4px 8px;
    margin: 2px 0;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    border-right: 3px solid var(--primary-color);
    display: block;
    word-wrap: break-word;
    white-space: normal;
    line-height: 1.3;
}

/* Booking Data */
.booking-data {
    font-size: 11px;
    color: var(--gray-600);
    min-width: 100px;
}

.booking-item {
    margin: 2px 0;
    display: flex;
    align-items: center;
    gap: 4px;
}

.booking-label {
    font-weight: 600;
    color: var(--gray-500);
    min-width: 30px;
}

/* Status Badges */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 100px;
    justify-content: center;
}

.status-0 {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
}

.status-1 {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #60a5fa;
}

.status-2 {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #34d399;
}

/* Confirm Status Button */
.confirm-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: 2px solid;
    background: var(--white);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 80px;
    justify-content: center;
}

.confirm-btn.confirmed {
    color: var(--success-color);
    border-color: var(--success-color);
}

.confirm-btn.confirmed:hover {
    background: var(--success-color);
    color: var(--white);
}

.confirm-btn.unconfirmed {
    color: var(--warning-color);
    border-color: var(--warning-color);
}

.confirm-btn.unconfirmed:hover {
    background: var(--warning-color);
    color: var(--white);
}

/* Employee Name */
.employee-name {
    color: var(--gray-600);
    font-size: 11px;
    font-weight: 500;
}

/* Actions Dropdown */
.actions-dropdown {
    position: relative;
    display: inline-block;
}

.actions-btn {
    background: var(--gray-600);
    color: var(--white);
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    min-width: 100px;
    justify-content: center;
}

.actions-btn:hover {
    background: var(--gray-700);
}

.actions-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    min-width: 200px;
    z-index: 10000;
    display: none;
    overflow: hidden;
}

.actions-dropdown.show .actions-menu {
    display: block;
}

.actions-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 13px;
    font-weight: 500;
    border-bottom: 1px solid var(--gray-100);
    transition: all 0.2s ease;
    gap: 12px;
}

.actions-item:hover {
    background: var(--gray-50);
    color: var(--gray-900);
    text-decoration: none;
}

.actions-item:last-child {
    border-bottom: none;
}

.actions-item i {
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.actions-item.danger {
    color: var(--danger-color);
}

.actions-item.danger:hover {
    background: #fef2f2;
    color: var(--danger-color);
}

/* DataTables Styling */
.dataTables_wrapper {
    padding: 24px;
}

.dataTables_length select {
    padding: 8px 12px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    background: var(--white);
    color: var(--gray-900);
    margin: 0 8px;
}

.dataTables_info {
    color: var(--gray-600);
    font-weight: 500;
    padding-top: 16px;
}

.dataTables_paginate {
    padding-top: 16px;
}

.dataTables_paginate .paginate_button {
    padding: 8px 12px;
    margin: 0 2px;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    color: var(--gray-700);
    text-decoration: none;
    font-weight: 500;
    background: var(--white);
    transition: all 0.2s ease;
}

.dataTables_paginate .paginate_button:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
    color: var(--gray-900);
}

.dataTables_paginate .paginate_button.current {
    background: var(--primary-color);
    color: var(--white);
    border-color: var(--primary-color);
}

.dataTables_filter {
    display: none !important;
}

/* Loading State */
.loading {
    display: none;
    text-align: center;
    padding: 40px;
    background: var(--white);
    border-radius: var(--border-radius);
    margin: 24px 0;
}

.loading.show {
    display: block;
}

/* Notifications */
.custom-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10001;
    min-width: 350px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    border: none;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 16px;
    }
    
    .page-header,
    .search-container,
    .filters-container {
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    
    .stat-card {
        padding: 16px;
    }
    
    .stat-number {
        font-size: 24px;
    }
    
    .dataTables_wrapper {
        padding: 16px;
    }
    
    #invoicesTable {
        font-size: 11px;
    }
    
    #invoicesTable thead th,
    #invoicesTable tbody td {
        padding: 8px 6px;
    }
    
    .actions-menu {
        right: 0;
        left: auto;
        min-width: 180px;
    }
}

@media (max-width: 576px) {
    .page-title {
        font-size: 24px;
    }
    
    .search-input {
        font-size: 14px;
    }
    
    .stat-number {
        font-size: 20px;
    }
    
    #invoicesTable thead th,
    #invoicesTable tbody td {
        padding: 6px 4px;
    }
}

/* Special Row Types */
.invoice-row-cancelled {
    background-color: #fef2f2 !important;
    border-right: 4px solid var(--danger-color) !important;
}

.invoice-row-reissue {
    background-color: #fffbeb !important;
    border-right: 4px solid var(--warning-color) !important;
}

.invoice-type-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.invoice-type-cancelled {
    background: var(--danger-color);
    color: var(--white);
}

.invoice-type-reissue {
    background: var(--warning-color);
    color: var(--white);
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1 class="page-title">إدارة الفواتير</h1>
                <p class="page-subtitle">إدارة ومتابعة جميع الفواتير بكفاءة عالية</p>
            </div>
            <div class="d-flex gap-3">
                <a href="{{route('site.invoices_create')}}" class="btn btn-primary">
                    <i class="ri-add-line"></i> فاتورة جديدة
                </a>
                <button class="btn btn-outline-secondary">
                    <i class="ri-download-line"></i> تصدير
                </button>
            </div>
        </div>
    </div>

    <!-- Search Container -->
    <div class="search-container">
        <h3 class="search-title">البحث الشامل في الفواتير</h3>
        <p class="search-description">ابحث عن أي شيء: رقم الفاتورة، اسم الراكب، رقم الحجز، المورد، أو أي تفاصيل أخرى</p>
        
        <div class="search-input-group">
            <input 
                type="text" 
                id="globalSearchInput"
                class="search-input" 
                placeholder="مثال: أحمد محمد، رقم الفاتورة INV001، رقم الحجز PNR123..."
                autocomplete="off"
            >
            <div class="search-icons">
                <button class="clear-search" id="clearSearchBtn">
                    <i class="ri-close-line"></i>
                </button>
                <i class="ri-search-line search-icon"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-container">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <label class="filter-label">الفترة الزمنية</label>
                <select id="periodFilter" class="filter-select form-select">
                    <option value="15days" selected>آخر 15 يوم</option>
                    <option value="1month">الشهر الماضي</option>
                    <option value="3months">آخر 3 شهور</option>
                    <option value="6months">آخر 6 شهور</option>
                    <option value="1year">السنة الماضية</option>
                    <option value="all">جميع الفواتير</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <label class="filter-label">حالة السداد</label>
                <select id="statusFilter" class="filter-select form-select">
                    <option value="all">جميع الحالات</option>
                    <option value="0">غير مدفوعة</option>
                    <option value="1">مدفوعة جزئياً</option>
                    <option value="2">مدفوعة بالكامل</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <label class="filter-label">نوع الفاتورة</label>
                <select id="sectionFilter" class="filter-select form-select">
                    <option value="all">جميع الأنواع</option>
                    <option value="1">فواتير الطيران</option>
                    <option value="2">فواتير تأشيرات</option>
                    <option value="3">سياحة داخلية</option>
                    <option value="4">سياحة خارجية</option>
                    <option value="5">سياحة دينية</option>
                    <option value="6">تأمينات السفر</option>
                    <option value="7">تحاليل السفر</option>
                    <option value="8">نقل سياحي</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="filter-label d-none d-lg-block">&nbsp;</label>
                <button id="refreshBtn" class="refresh-btn">
                    <i class="ri-refresh-line"></i> تحديث البيانات
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="ri-file-list-3-line stat-icon" style="color: var(--primary-color);"></i>
            <div class="stat-number" id="totalInvoices">0</div>
            <div class="stat-label">إجمالي الفواتير</div>
        </div>
        <div class="stat-card">
            <i class="ri-time-line stat-icon" style="color: var(--warning-color);"></i>
            <div class="stat-number" id="unpaidInvoices">0</div>
            <div class="stat-label">غير مدفوعة</div>
        </div>
        <div class="stat-card">
            <i class="ri-wallet-3-line stat-icon" style="color: var(--primary-light);"></i>
            <div class="stat-number" id="partialInvoices">0</div>
            <div class="stat-label">مدفوعة جزئياً</div>
        </div>
        <div class="stat-card">
            <i class="ri-check-double-line stat-icon" style="color: var(--success-color);"></i>
            <div class="stat-number" id="paidInvoices">0</div>
            <div class="stat-label">مكتملة الدفع</div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="loading" id="loadingIndicator">
        <div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border text-primary me-3" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <span>جاري تحميل الفواتير...</span>
        </div>
    </div>

   <!-- Invoices Table -->
<div class="table-container">
    <div class="table-responsive">
        <table id="invoicesTable" style="background-color:#f5f0e6;">
            <thead style="background-color:##87ceeb; color:#fff;">
                <tr>
                    <th style="width: 100px;">رقم الفاتورة</th>
                    <th style="width: 90px;">تاريخ الفاتورة</th>
                    <th style="width: 90px;">تاريخ السفر</th>
                    <th style="width: 120px;">المورد</th>
                    <th style="width: 120px;">المستفيد</th>
                    <th style="width: 80px;">التكلفة</th>
                    <th style="width: 80px;">البيع</th>
                    <th style="width: 80px;">المدفوع</th>
                    <th style="width: 100px;">المسار</th>
                    <th style="width: 200px;">الراكب</th>
                    <th style="width: 100px;">بيانات الحجز</th>
                    <th style="width: 80px;">الربح</th>
                    <th style="width: 100px;">نوع الفاتورة</th>
                    <th style="width: 120px;">حالة السداد</th>
                    <th style="width: 100px;">التأكيد</th>
                    <th style="width: 80px;">الموظف</th>
                    <th style="width: 120px;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be loaded by DataTables -->
            </tbody>
        </table>
    </div>
</div>
<!-- Include jQuery and DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<!-- Include Remix Icons -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

<script>
$(document).ready(function() {
    let table;
    let searchTimeout;
    
    // Check if required libraries are loaded
    if (typeof $.fn.dataTable === 'undefined') {
        console.error('DataTables library not loaded');
        showNotification('مكتبة DataTables غير محملة', 'error');
        return;
    }
    
    // Initialize DataTable with server-side processing
    function initializeDataTable() {
        try {
            $('#loadingIndicator').addClass('show');
            
            table = $('#invoicesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/invoices/data',
                    type: 'GET',
                    data: function(data) {
                        // Add custom filters
                        data.period_filter = $('#periodFilter').val() || '15days';
                        data.status_filter = $('#statusFilter').val() || 'all';
                        data.section_filter = $('#sectionFilter').val() || 'all';
                        
                        // Add global search parameter
                        if ($('#globalSearchInput').length) {
                            data.search.value = $('#globalSearchInput').val() || '';
                        }
                        
                        console.log('DataTables request data:', data);
                    },
                    beforeSend: function() {
                        $('#loadingIndicator').addClass('show');
                    },
                    complete: function() {
                        $('#loadingIndicator').removeClass('show');
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX Error:', error, thrown, xhr);
                        
                        let errorMessage = 'حدث خطأ في تحميل البيانات';
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.error) {
                                errorMessage = response.error;
                            }
                        } catch (e) {
                            errorMessage += ': ' + (xhr.status || 'خطأ غير معروف');
                        }
                        
                        showNotification(errorMessage, 'error');
                        $('#loadingIndicator').removeClass('show');
                    }
                },
                columns: [
                    { 
                        data: 'es_id',
                        render: function(data, type, row) {
                            if (!data) return '<div class="invoice-id">غير محدد</div>';
                            
                            if (row.invoice_ticket_file) {
                                let filePath = row.invoice_ticket_file;
                                if (filePath.startsWith('storage/')) {
                                    filePath = filePath.substring(8);
                                }
                                const fileUrl = `/public/storage/${filePath}`;
                                return `<a href="${fileUrl}" target="_blank" class="invoice-id has-file" title="انقر لتنزيل ملف الفاتورة">${data}</a>`;
                            }
                            
                            return `<div class="invoice-id">${data}</div>`;
                        }
                    },
                    { 
                        data: 'invoice_date',
                        render: function(data) {
                            return '<div class="date-text">' + (data || 'غير محدد') + '</div>';
                        }
                    },
                    { 
                        data: 'travel_date',
                        render: function(data) {
                            return '<div class="date-text">' + (data || 'غير محدد') + '</div>';
                        }
                    },
                    { 
                        data: 'vendor_names',
                        render: function(data) {
                            data = data || 'غير محدد';
                            return '<div class="vendor-name" title="' + data + '">' + 
                                   (data.length > 15 ? data.substring(0, 15) + '...' : data) + 
                                   '</div>';
                        }
                    },
                    { 
                        data: 'beneficiary_name',
                        render: function(data) {
                            data = data || 'غير محدد';
                            return '<div class="beneficiary-name" title="' + data + '">' + 
                                   (data.length > 15 ? data.substring(0, 15) + '...' : data) + 
                                   '</div>';
                        }
                    },
                    { 
                        data: 'total_net_price',
                        render: function(data) {
                            return '<div class="money-value money-cost">' + 
                                   parseFloat(data || 0).toLocaleString() + '</div>';
                        }
                    },
                    { 
                        data: 'total_bought_price',
                        render: function(data) {
                            return '<div class="money-value money-sell">' + 
                                   parseFloat(data || 0).toLocaleString() + '</div>';
                        }
                    },
                    { 
                        data: 'invoice_money_pay',
                        render: function(data) {
                            return '<div class="money-value money-paid">' + 
                                   parseFloat(data || 0).toLocaleString() + '</div>';
                        }
                    },
                    { 
                        data: null,
                        render: function(data) {
                            const from = data.from_location || 'غير محدد';
                            const to = data.to_location || 'غير محدد';
                            return '<div class="route-container">' +
                                   '<div class="route-item"><i class="ri-takeoff-line" style="color: var(--success-color);"></i>' + 
                                   (from.length > 8 ? from.substring(0, 8) + '...' : from) + '</div>' +
                                   '<div class="route-item"><i class="ri-map-pin-line" style="color: var(--danger-color);"></i>' + 
                                   (to.length > 8 ? to.substring(0, 8) + '...' : to) + '</div>' +
                                   '</div>';
                        }
                    },
                    { 
data: 'passenger_names',
render: function(data) {
    data = data || 'غير محدد';
    
    // تقسيم الأسماء وتنظيفها
    let passengers = data.split(/\n/)
        .map(name => name.trim())
        .filter(name => name.length > 0);
    
    // نرجع كل راكب في <div> لوحده
    let formatted = passengers.map(name => {
        return '<div title="' + name + '">' + name + '</div>';
    }).join('');
    
    return '<div class="passenger-names" title="' + data + '">' + formatted + '</div>';
}
                    },
                    { 
                        data: null,
                        render: function(data) {
                            const bookingIds = data.booking_ids || 'غير محدد';
                            const ticketIds = data.ticket_ids || 'غير محدد';
                            return '<div class="booking-data">' +
                                   '<div class="booking-item"><span class="booking-label">حجز:</span>' + 
                                   (bookingIds.length > 8 ? bookingIds.substring(0, 8) + '...' : bookingIds) + '</div>' +
                                   '<div class="booking-item"><span class="booking-label">تذكرة:</span>' + 
                                   (ticketIds.length > 8 ? ticketIds.substring(0, 8) + '...' : ticketIds) + '</div>' +
                                   '</div>';
                        }
                    },
                    { 
                        data: 'profit',
                        render: function(data) {
                            data = data || 0;
                            const profitClass = data >= 0 ? 'money-profit-positive' : 'money-profit-negative';
                            return '<div class="money-value ' + profitClass + '">' + 
                                   parseFloat(data).toLocaleString() + '</div>';
                        }
                    },
                    { 
                        data: 'invoice_type',
                        render: function(data) {
                            return '<span class="badge bg-light text-dark">' + (data || 'غير محدد') + '</span>';
                        }
                    },
                    { 
                        data: 'payment_status',
                        render: function(data) {
                            const statusTexts = {
                                '0': 'غير مدفوعة',
                                '1': 'مدفوعة جزئياً', 
                                '2': 'مدفوعة بالكامل'
                            };
                            const statusIcons = {
                                '0': 'ri-time-line',
                                '1': 'ri-wallet-line',
                                '2': 'ri-check-double-line'
                            };
                            data = data || '0';
                            return '<span class="status-badge status-' + data + '"><i class="' + statusIcons[data] + '"></i>' + statusTexts[data] + '</span>';
                        }
                    },
                    { 
                        data: null,
                        render: function(data) {
                            const isConfirmed = (data.invoice_status == 1);
                            const statusClass = isConfirmed ? 'confirmed' : 'unconfirmed';
                            const statusText = isConfirmed ? 'مؤكدة' : 'غير مؤكدة';
                            const statusIcon = isConfirmed ? 'ri-checkbox-circle-line' : 'ri-time-line';
                            
                            return '<button class="confirm-btn ' + statusClass + '" onclick="toggleConfirmStatus(' + data.id + ', ' + (!isConfirmed) + ')" title="انقر لتغيير حالة التأكيد">' +
                                   '<i class="' + statusIcon + '"></i> ' + statusText +
                                   '</button>';
                        }
                    },
                    { 
                        data: 'employee_name',
                        render: function(data) {
                            return '<div class="employee-name">' + (data || 'غير محدد') + '</div>';
                        }
                    },
                    { 
                        data: null,
                        render: function(data) {
                            return createActionsDropdown(data);
                        }
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
                },
                order: [[1, 'desc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']],
                drawCallback: function(settings) {
                    updateStatistics();
                    $('#loadingIndicator').removeClass('show');
                    
                    // Check for server errors in response
                    const json = settings.json;
                    if (json && json.error) {
                        showNotification(json.error, 'error');
                    }
                    
                    // Apply row colors based on invoice type
                    $('#invoicesTable tbody tr').each(function() {
                        const row = $(this);
                        const invoiceId = row.find('td:first').text().trim();
                        
                        if (invoiceId.startsWith('FLY-RD')) {
                            row.addClass('invoice-row-cancelled');
                            row.find('td:nth-child(13)').html('<span class="invoice-type-badge invoice-type-cancelled"><i class="ri-close-circle-line"></i>إلغاء</span>');
                        } else if (invoiceId.startsWith('FLY-RS')) {
                            row.addClass('invoice-row-reissue');
                            row.find('td:nth-child(13)').html('<span class="invoice-type-badge invoice-type-reissue"><i class="ri-refresh-line"></i>إعادة إصدار</span>');
                        }
                    });
                },
                initComplete: function() {
                    $('#loadingIndicator').removeClass('show');
                    console.log('DataTable initialized successfully');
                }
            });
        } catch (error) {
            console.error('DataTable initialization error:', error);
            showNotification('خطأ في تهيئة الجدول: ' + error.message, 'error');
            $('#loadingIndicator').removeClass('show');
        }
    }

    // Initialize table
    initializeDataTable();

    // Enhanced Global Search Implementation
    $(document).on('input', '#globalSearchInput', function() {
        const searchValue = $(this).val().trim();
        
        console.log('Search input changed:', searchValue);
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Show/hide clear button
        if (searchValue.length > 0) {
            $('#clearSearchBtn').addClass('show');
        } else {
            $('#clearSearchBtn').removeClass('show');
        }
        
        // Only search if we have at least 2 characters to improve performance
        if (searchValue.length < 2 && searchValue.length > 0) {
            return;
        }
        
        // Debounce search
        searchTimeout = setTimeout(function() {
            console.log('Searching for:', searchValue);
            
            // Reload table with new search
            if (table) {
                table.ajax.reload(function(json) {
                    console.log('Search completed:', json);
                    
                    // Check for errors
                    if (json && json.error) {
                        console.error('Search error:', json.error);
                        showNotification('خطأ في البحث: ' + json.error, 'error');
                    }
                });
            }
        }, 300);
    });

    // Clear search functionality
    $(document).on('click', '#clearSearchBtn', function() {
        $('#globalSearchInput').val('').trigger('input');
        $(this).removeClass('show');
    });

    // Filter change handlers
    $('#periodFilter, #statusFilter, #sectionFilter').on('change', function() {
        console.log('Filter changed:', $(this).attr('id'), $(this).val());
        if (table) {
            table.ajax.reload();
        }
    });

    // Refresh button
    $('#refreshBtn').on('click', function() {
        const $btn = $(this);
        $btn.find('i').addClass('ri-spin');
        if (table) {
            table.ajax.reload(function() {
                $btn.find('i').removeClass('ri-spin');
            });
        } else {
            setTimeout(function() {
                $btn.find('i').removeClass('ri-spin');
            }, 1000);
        }
    });

    // Update statistics based on current table data
    function updateStatistics() {
        if (table) {
            const info = table.page.info();
            if ($('#totalInvoices').length) {
                $('#totalInvoices').text(info.recordsDisplay.toLocaleString());
            }
            
            // Calculate statistics from visible data
            let unpaid = 0, partial = 0, paid = 0;
            
            try {
                table.rows({page: 'current'}).data().each(function(row) {
                    switch(parseInt(row.payment_status)) {
                        case 0: unpaid++; break;
                        case 1: partial++; break;
                        case 2: paid++; break;
                    }
                });
            } catch (error) {
                console.error('Statistics calculation error:', error);
            }
            
            $('#unpaidInvoices').text(unpaid.toLocaleString());
            $('#partialInvoices').text(partial.toLocaleString());
            $('#paidInvoices').text(paid.toLocaleString());
        }
    }

    // Actions dropdown functionality
    $(document).on('click', '.actions-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();
        
        // Close all other dropdowns
        $('.actions-dropdown').removeClass('show');
        
        // Toggle current dropdown
        const dropdown = $(this).closest('.actions-dropdown');
        dropdown.toggleClass('show');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.actions-dropdown').length) {
            $('.actions-dropdown').removeClass('show');
        }
    });

    // Prevent dropdown from closing when clicking inside menu
    $(document).on('click', '.actions-menu', function(e) {
        e.stopPropagation();
    });
});

// Toggle confirm status function
window.toggleConfirmStatus = async function(invoiceId, newStatus) {
    try {
        let button = null;
        if (typeof event !== 'undefined' && event.target) {
            button = event.target.closest('button');
            if (button) {
                button.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> جاري التحديث...';
                button.disabled = true;
            }
        }

        const response = await fetch('/invoices/toggle-confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({
                invoice_id: invoiceId,
                status: newStatus ? 1 : 0
            })
        });

        const result = await response.json();

        if (result.success) {
            if (typeof table !== 'undefined' && table) {
                table.ajax.reload(null, false);
            }
            showNotification(result.message, 'success');
        } else {
            throw new Error(result.message || 'حدث خطأ في تحديث حالة الفاتورة');
        }
    } catch (error) {
        console.error('Toggle confirm status error:', error);
        showNotification('حدث خطأ في تحديث حالة الفاتورة: ' + error.message, 'error');
    } finally {
        if (button) {
            const isConfirmed = newStatus;
            const statusClass = isConfirmed ? 'confirmed' : 'unconfirmed';
            const statusText = isConfirmed ? 'مؤكدة' : 'غير مؤكدة';
            const statusIcon = isConfirmed ? 'ri-checkbox-circle-line' : 'ri-time-line';
            
            button.className = 'confirm-btn ' + statusClass;
            button.innerHTML = '<i class="' + statusIcon + '"></i> ' + statusText;
            button.disabled = false;
        }
    }
};
 
// Create actions dropdown
function createActionsDropdown(invoice) {
    if (!invoice || !invoice.id) {
        return '<span class="text-muted">غير متاح</span>';
    }
    
    return `
        <div class="actions-dropdown">
            <button type="button" class="actions-btn">
                الإجراءات
                <i class="ri-arrow-down-s-line"></i>
            </button>
            <div class="actions-menu">
                <a href="/invoices/${invoice.id}/show" class="actions-item">
                    <i class="ri-eye-line" style="color: var(--primary-color);"></i>
                    عرض التفاصيل
                </a>
                <a href="/invoices/${invoice.id}/edit" class="actions-item">
                    <i class="ri-edit-line" style="color: var(--warning-color);"></i>
                    تعديل الفاتورة
                </a>
                <a href="/invoices/pay-part/${invoice.id}/" class="actions-item">
                    <i class="ri-wallet-line" style="color: var(--success-color);"></i>
                    سداد الفاتورة
                </a>
                <a href="/invoices/reissue/${invoice.es_id || invoice.id}/" class="actions-item">
                    <i class="ri-refresh-line" style="color: var(--primary-light);"></i>
                    إعادة إصدار الفاتورة
                </a>
                <a href="/invoices/refund/${invoice.es_id || invoice.id}/" class="actions-item" onclick="return confirm('هل أنت متأكد من إلغاء هذه الفاتورة؟')">
                    <i class="ri-close-circle-line" style="color: var(--warning-color);"></i>
                    إلغاء الفاتورة
                </a>
                <a href="#" onclick="deleteInvoice('${invoice.es_id || invoice.id}'); return false;" class="actions-item danger">
                    <i class="ri-delete-bin-line"></i>
                    حذف الفاتورة
                </a>
            </div>
        </div>
    `;
}

// Delete invoice function
window.deleteInvoice = async function(invoiceId) {
    if (!confirm('هل أنت متأكد من حذف هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.')) {
        return;
    }

    try {
        const response = await fetch(`/invoices/${invoiceId}/remove`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        const result = await response.json();

        if (result.success) {
            $('.actions-dropdown').removeClass('show');
            
            if (typeof table !== 'undefined' && table) {
                table.ajax.reload(null, false);
            }
            
            showNotification('تم حذف الفاتورة بنجاح', 'success');
        } else {
            throw new Error(result.message || 'حدث خطأ في حذف الفاتورة');
        }
    } catch (error) {
        console.error('Delete invoice error:', error);
        showNotification('حدث خطأ في حذف الفاتورة: ' + error.message, 'error');
    }
};

// Enhanced notification system
function showNotification(message, type = 'info') {
    if (!message) return;
    
    $('.custom-notification').remove();
    
    const notificationClass = type === 'success' ? 'alert-success' : 
                             type === 'error' ? 'alert-danger' : 'alert-info';
    const iconClass = type === 'success' ? 'ri-check-circle-line' : 
                     type === 'error' ? 'ri-error-warning-line' : 'ri-information-line';
    
    const notification = $(`
        <div class="alert ${notificationClass} custom-notification">
            <div class="d-flex align-items-center">
                <i class="${iconClass}" style="font-size: 18px; margin-left: 8px;"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(function() {
        notification.fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}
</script>

@endsection