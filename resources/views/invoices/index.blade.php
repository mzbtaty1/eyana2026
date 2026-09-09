@extends('layouts.app')

@section('title', 'الفواتير')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/invoices-datatable.css') }}">
@endpush

@section('content')

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-2"></i>
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 text-white">
                    <i class="ri-file-list-3-line me-2"></i>
                    قائمة الفواتير
                </h5>
                <a href="{{ route('site.invoices_create') }}" class="btn btn-light btn-sm">
                    <i class="ri-add-line me-1"></i>
                    إضافة فاتورة جديدة
                </a>
            </div>
            
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <label class="form-label me-2">عرض:</label>
                            <select id="entriesPerPage" class="form-select form-select-sm w-auto">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span class="ms-2">عنصر</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end">
                            <div class="input-group" style="max-width: 300px;">
                                <span class="input-group-text">
                                    <i class="ri-search-line"></i>
                                </span>
                                <input type="text" id="searchInput" class="form-control" placeholder="البحث في الفواتير...">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="invoicesTable" class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">ES ID</th>
                                <th class="text-center">تاريخ الفاتورة</th>
                                <th class="text-center">تاريخ السفر</th>
                                <th class="text-center">المورد</th>
                                <th class="text-center">المستفيد</th>
                                <th class="text-center">المستخدمين</th>
                                <th class="text-center">المسار</th>
                                <th class="text-center">التكلفة</th>
                                <th class="text-center">البيع</th>
                                <th class="text-center">الربح/الخسارة</th>
                                <th class="text-center">الموظف</th>
                                <th class="text-center">حالة الفاتورة</th>
                                <th class="text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="13" class="text-center">
                                    <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">جاري التحميل...</span>
                                        </div>
                                        <span class="ms-2">جاري تحميل البيانات...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div id="tableInfo" class="dataTables_info"></div>
                    </div>
                    <div class="col-md-6">
                        <div id="tablePagination" class="d-flex justify-content-end"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.authAccountType = {{ Auth::user()->account_type }};
</script>
<script src="{{ asset('js/invoices-datatable.js') }}"></script>
@endpush

@endsection