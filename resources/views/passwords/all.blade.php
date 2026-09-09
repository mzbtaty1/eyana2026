@extends('layouts.app')
@section('content')
@section('title' , 'حسابات وبيانات')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">قائمة الحسابات </h5>
             <a href="{{route('site.password_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة حساب جديد
                 </button>
             </a>
         </div>
         <div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false">الرابط</th>
                     <th data-ordering="false">الاسم / البريد</th>
                     <th data-ordering="false">كلمة السر</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($passwords as $password)
                  <tr>
                     <td>{{$password->url}}</td>
                     <td>{{$password->user}}</td>
                     <td>{{$password->pass}}</td>
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
function Removeairline(id){
    
      swal({
     title: "هل انت متأكد؟",
     text: "سيتم حذف ذلك خط الحساب وازالة كل البيانات المرتبطه به",
     icon: "warning",
     buttons: true,
     dangerMode: true,
   })
   .then((willDelete) => {
     if (willDelete) {
   //       var url = "http://teacher.cuoratech.com/aladmin_srp/sections/" + id + "/remove";
   var url = "{{url('')}}/admins/" + id + "/remove";
//                     alert(url);
         window.location.href = url;
         
     } else {
       swal("تم الغاء عملية الحذف بنجاح");
     }
   });
    
}
</script>
@endsection
