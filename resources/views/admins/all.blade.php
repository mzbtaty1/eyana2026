@extends('layouts.app')
@section('content')
@section('title' , 'حسابات الموظفين')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <x-page-header title="قائمة الحسابات">
<a href="{{route('site.admins_create')}}">
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
                     <th data-ordering="false">اسم الموظف</th>
                     <th data-ordering="false">البريد الالكتروني</th>
                     <th data-ordering="false">نوع الحساب - الحالة</th>
                     <th data-ordering="false">نسبة العمولة</th>
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($admins as $admin)
                  <tr>
                     <td>{{$admin->name}}</td>
                     <td>{{$admin->email}}</td>
                      <td>
                      @if($admin->status == 0)
                    <span class="badge bg-danger my_badge">محظور</span>
                          @else
                          <span class="badge bg-primary my_badge">فعال</span>
                          @endif
                          @if($admin->account_type == 1) 
                          
                           <span class="badge bg-dark my_badge">حساب موظف</span>
                          @else
                          
                          <span class="badge bg-secondary my_badge">حساب محاسب / مسئول</span>                                                        
                          
                          @endif
                          
                      </td>
                      <td>{{$admin->commission}}%</td>
                     <td>
                        <a href="{{route('site.admins_edit' , $admin->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                        <a href="#Removeairline" onclick="Removeairline({{$admin->id}})">
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-delete-bin-line align-middle"></i>
                        </button>
                        </a>
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
