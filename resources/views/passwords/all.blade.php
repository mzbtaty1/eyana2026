@extends('layouts.app')
@section('content')
@section('title' , 'حسابات وبيانات')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <x-page-header title="قائمة الحسابات">
<a href="{{route('site.password_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة حساب جديد
                 </button>
             </a>
</x-page-header>
<div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">الرابط</th>
                     <th data-ordering="false">الاسم / البريد</th>
                     <th data-ordering="false">كلمة السر</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($passwords as $password)
                  <tr>
                     <td>{{$password->url}}</td>
                     <td>{{$password->user}}</td>
                     <td>
                        <span class="pass-mask" data-pass="{{$password->pass}}">••••••••</span>
                        <button type="button" class="btn btn-soft-secondary btn-sm dropdown" style="font-size: 16px;" onclick="togglePasswordReveal(this)">
                           <i class="ri-eye-line align-middle"></i>
                        </button>
                     </td>
                     <td>
                        <a href="{{route('site.password_edit', $password->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a>
                        <form id="delete-form-{{$password->id}}" action="{{route('site.password_delete', $password->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$password->id}}', 'هل انت متأكد؟', 'سيتم حذف بيانات هذا الحساب')">
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
<script>
function togglePasswordReveal(btn) {
    var span = btn.previousElementSibling;
    var icon = btn.querySelector('i');
    if (span.textContent === span.dataset.pass) {
        span.textContent = '••••••••';
        icon.className = 'ri-eye-line align-middle';
    } else {
        span.textContent = span.dataset.pass;
        icon.className = 'ri-eye-off-line align-middle';
    }
}
</script>
@endsection
