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
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
            <p class="text-muted fs-12 mb-3">
               الرصيد من كشف حساب كل حساب (مدين − دائن):
               <span class="badge bg-success-subtle text-success">لنا</span> الحساب مدين لنا ،
               <span class="badge bg-danger-subtle text-danger">علينا</span> نحن مدينون له.
               @if($expenseAccounts)
               لا تظهر هنا حسابات المصروفات ({{$expenseAccounts}}) — <a href="{{route('site.expenses')}}">صفحة المصروفات</a>.
               @endif
            </p>

            <div class="table-responsive">
            <table id="suppliersTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th>الاسم</th>
                     <th>الهاتف</th>
                     <th>الدور</th>
                     <th>فواتير كمورد</th>
                     <th>فواتير كعميل</th>
                     <th>الرصيد</th>
                     <th>آخر حركة</th>
                     <th>التصنيف</th>
                     <th>--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($rows as $row)
                  <?php $supplier = $row->account; ?>
                  <tr>
                     <td>{{$supplier->name}}</td>
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
                        @if($row->invoices_as_supplier) {{ number_format($row->invoices_as_supplier) }} @else <span class="text-muted">0</span> @endif
                     </td>
                     <td data-order="{{$row->invoices_as_customer}}">
                        @if($row->invoices_as_customer) {{ number_format($row->invoices_as_customer) }} @else <span class="text-muted">0</span> @endif
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
                        <a href="{{route('site.suppliers_edit' , $supplier->id)}}" title="تعديل" aria-label="تعديل {{$supplier->name}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a>
                        <form id="delete-form-{{$supplier->id}}" action="{{route('site.suppliers_delete', $supplier->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" title="حذف" aria-label="حذف {{$supplier->name}}" onclick="confirmDeleteForm('delete-form-{{$supplier->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك الحساب نهائياً. لا يمكن حذف حساب له فواتير أو سندات أو حركات في كشف الحساب')">
                        <i class="ri-delete-bin-line align-middle"></i>
                        </button>
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
    new DataTable('#suppliersTable', {
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [{ targets: -1, orderable: false, searchable: false }],
        // same Arabic texts as the detailed invoice report (inline, no language file request)
        language: {
            search: 'بحث:', lengthMenu: 'عرض _MENU_ صف',
            info: 'عرض _START_ إلى _END_ من _TOTAL_ حساب', infoEmpty: 'لا توجد نتائج',
            infoFiltered: '(من أصل _MAX_)', emptyTable: 'لا توجد حسابات', zeroRecords: 'لا توجد نتائج',
            paginate: { first: 'الأول', previous: 'السابق', next: 'التالي', last: 'الأخير' },
        },
    });
});
</script>
@endsection
