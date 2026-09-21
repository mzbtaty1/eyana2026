@extends('layouts.app')
@section('content')
@section('title' , 'المحصلين')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">قائمة المحصلين</h5>
             <a href="{{route('site.collectors_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة محصل
                 </button>
             </a>
         </div>
         <div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">اسم المحصل</th>
                     <th data-ordering="false">رقم الهاتف</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($collectors as $collector)
                  <tr>
                     <td>{{$collector->name}}</td>
                     <td>{{$collector->phone}}</td>
                     <td>
                        <a href="{{route('site.collectors_edit' , $collector->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <form id="delete-form-{{$collector->id}}" action="{{route('site.collectors_delete', $collector->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$collector->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك المحصل وازالة كل البيانات المرتبطه به')">
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
