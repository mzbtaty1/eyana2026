@extends('layouts.app')
@section('content')
@section('title' , 'الخزائن وحسابات البنوك')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">قائمة الخزائن الفرعية</h5>
            
             
             <a href="{{route('site.sub_storages_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;  margin-left: 8px;">
                 <i class="ri-file-add-line"></i>
                 اضافة خزنة فرعية 
                 </button>
             </a>
             
             
             
         </div>
         <div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">اسم الخزينة</th>
                     <th data-ordering="false">النوع</th>
                     <th data-ordering="false">البنك</th>
                     <th data-ordering="false">رقم الحساب البنكي</th>
                     <th data-ordering="false">الرصيد</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($storages as $storage)
                  <tr>
                     <td>{{$storage->name}}</td>
                     <td>{{$storage->type}}</td>
                     <td>{{$storage->bank_id}}</td>
                     <td>{{$storage->bank_number}}</td>
                     <td>{{$storage->balance}}</td>
                     <td>
                        <a href="{{route('site.sub_storages_edit' , $storage->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <form id="delete-form-{{$storage->id}}" action="{{route('site.sub_storages_delete', $storage->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$storage->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك الخزينة وازالة كل البيانات المرتبطه به')">
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
