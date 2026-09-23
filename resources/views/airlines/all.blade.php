@extends('layouts.app')
@section('content')
@section('title' , 'خطوط الطيران')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <x-page-header title="قائمة خطوط الطيران">
<a href="{{route('site.airlines_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة خط طيران
                 </button>
             </a>
</x-page-header>
<div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">اسم خط الطيران</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($airlines as $airline)
                  <tr>
                     <td>{{$airline->airline_name}}</td>
                     <td>
                        <a href="{{route('site.airlines_edit' , $airline->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <form id="delete-form-{{$airline->id}}" action="{{route('site.airlines_delete', $airline->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$airline->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك خط الطيران وازالة كل البيانات المرتبطه به')">
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
