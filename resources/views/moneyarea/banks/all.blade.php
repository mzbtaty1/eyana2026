@extends('layouts.app')
@section('content')
@section('title' , 'البنوك')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <x-page-header title="قائمة البنوك">
<a href="{{route('site.banks_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة بنك جديد
                 </button>
             </a>
</x-page-header>
<div class="card-body">
             
            <div class="table-responsive">
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">اسم البنك</th>
                     <th data-ordering="false">رصيد البنك</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($banks as $bank)
                  <tr>
                     <td>{{$bank->bank_name}}</td>
                     <td>{{ $bondTotals[$bank->id] ?? 0 }}</td>
                     <td>
                        <a href="{{route('site.bank_account_transactions' , $bank->id)}}">
                        <button class="btn btn-soft-dark btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-file-chart-line"></i>
                        </button>
                        </a>
                         <a href="{{route('site.banks_edit' , $bank->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a>
                        <form id="delete-form-{{$bank->id}}" action="{{route('site.banks_delete', $bank->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$bank->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك البنك وازالة كل البيانات المرتبطه به')">
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
