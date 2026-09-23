@extends('layouts.app')
@section('content')
@section('title' , 'المصروفات')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <x-page-header title="قائمة المصروفات">
<a href="{{route('site.expenses_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة مصروف جديد
                 </button>
             </a>
</x-page-header>
<div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">الاسم</th>
                     <th data-ordering="false">رصيد دائن</th>
                     <th data-ordering="false">رصيد مدين</th>
<!--                     <th data-ordering="false">الاسم</th>-->
<!--                     <th data-ordering="false">رقم الهاتف</th>-->
<!--                     <th data-ordering="false">رقم الباسبور</th>-->
<!--                     <th data-ordering="false">التصنيف</th>-->
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($expenses as $supplier)
                  <tr>
                     <td>{{$supplier->name}}</td>
                     <td>{{$supplier->opening_credit_balance}}</td>
                     <td>{{$supplier->debit_opening_balance}}</td>
                  
                      
                      
                     <td>
                        <a href="{{route('site.expenses_edit' , $supplier->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <form id="delete-form-{{$supplier->id}}" action="{{route('site.expenses_delete', $supplier->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$supplier->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك المصروف وازالة كل البيانات المرتبطه به')">
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
