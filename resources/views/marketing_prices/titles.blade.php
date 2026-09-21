@extends('layouts.app')
@section('content')
@section('title' , 'البيانات (العناوين)')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">قائمة البيانات (العناوين)</h5>
             <a href="{{route('site.marketing_title_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة بيان (عنوان) جديد
                 </button>
             </a>
         </div>
         <div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">اسم البيان</th>
                     <th data-ordering="false">عدد القوائم المربوطة</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($titles as $title)
                  <tr>
                     <td style="  background-color:{{$title->bk_color}};color:black;">{{$title->title}}
                      <img src="{{asset($title->png_icon)}}" width="100">
                      </td>
                      <td>{{$title->usage_count}}</td>
                     <td>
                        <a href="{{route('site.marketing_titles_edit' , $title->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <form id="delete-form-{{$title->id}}" action="{{route('site.marketing_titles_remove', $title->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$title->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك البيان وازالة كل البيانات المرتبطه به')">
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
