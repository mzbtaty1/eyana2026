@extends('layouts.app')
@section('content')
@section('title' , 'العملاء')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <x-page-header title="قائمة العملاء">
<a href="{{route('site.customers_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة عميل
                 </button>
             </a>
</x-page-header>
<div class="card-body">
             
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
                  @foreach($customers as $customer)
                  <tr>
                     <td>{{$customer->name}}</td>
                     <td>{{$customer->phone_1}}
                        @if($customer->phone_2 == "")
                        @else
                        /{{$customer->phone_2}}
                        @endif
                     </td>
                     <td>{{$customer->passport_id}}</td>
                     <td>
                        @if($customer->type == 1)
                        <span class="badge bg-dark my_badge">فرد</span>
                        @else
                        <span class="badge bg-primary my_badge">شركة</span>
                        @endif
                        @if($customer->status == 1)
                        <span class="badge bg-success my_badge">فعال</span>
                        @else
                        <span class="badge bg-danger my_badge">موقوف</span>
                        @endif
                     </td>
                     <td>
                        <a href="{{route('site.customers_edit' , $customer->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <form id="delete-form-{{$customer->id}}" action="{{route('site.customers_delete', $customer->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$customer->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك العميل وازالة كل البيانات المرتبطه به')">
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
   <!--end col-->
</div>
@endsection
