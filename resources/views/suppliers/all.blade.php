@extends('layouts.app')
@section('content')
@section('title' , 'الموردين')
@include('components.flash-messages')
<x-page-header title="قائمة الموردين">
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
             
            <div class="table-responsive">
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">الاسم</th>
                     <th data-ordering="false">رقم الهاتف</th>
                     <th data-ordering="false">رقم الباسبور</th>
                     <th data-ordering="false">التصنيف</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($suppliers as $supplier)
                  <tr>
                     <td>{{$supplier->name}}</td>
                     <td>{{$supplier->phone_1}}
                        @if($supplier->phone_2 == "")
                        @else
                        /{{$supplier->phone_2}}
                        @endif
                     </td>
                     <td>{{$supplier->passport_id}}</td>
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
                        <a href="{{route('site.suppliers_edit' , $supplier->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <form id="delete-form-{{$supplier->id}}" action="{{route('site.suppliers_delete', $supplier->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$supplier->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك الحساب نهائياً. لا يمكن حذف حساب له فواتير أو سندات أو حركات في كشف الحساب')">
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
@endsection
