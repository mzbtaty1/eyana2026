@extends('layouts.app')
@section('content')
@section('title' , 'التأشيرات')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">قائمة التأشيرات</h5>
                          @if(Auth::user()->account_type == 2)

             <a href="{{route('site.visas_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة تأشيرة جديدة
                 </button>
             </a>
             @endif
         </div>
         <div class="card-body">
             
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                    <tr>
                    <th style="text-align:center;font-weight: normal; ">#</th>
               <th style="text-align:center;font-weight: normal; ">اسم التأشيرة</th>
               <th style="text-align:center;font-weight: normal; ">سعر التأشيرة</th>
             
             @if(Auth::user()->account_type == 2)
               <th style="text-align:center;font-weight: normal; ">سعر التنفيذ التأشيرة</th>
               <th style="text-align:center;font-weight: normal; ">--</th>
             @endif
         </tr>
               </thead>
           <tbody>
             <?php $x = 1; ?>
            @foreach($visas as $visa)
            <tr>
               <td style="text-align:center;">{{$x}}</td>
               <td style="text-align:center;">{{$visa->visa_name}}</td>
               <td style="text-align:center;">{{$visa->visa_price}}</td>
             @if(Auth::user()->account_type == 2)
               <td style="text-align:center;">{{$visa->visa_ext_price}}</td>
                @endif
               <td style="text-align:center;">
             @if(Auth::user()->account_type == 2)
                   <a href="{{route('site.visas_edit' ,['id' => $visa->id])}}">
              <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
               </a>
                  <a href="#" onclick="Removeairline({{$visa->id}})">
                  <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-delete-bin-line align-middle"></i>
                        </button>
                  </a>
                   @endif
               </td>
            </tr>
            <?php $x++; ?>
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
     text: "سيتم حذف ذلك التأشيرة وازالة كل البيانات المرتبطه به",
     icon: "warning",
     buttons: true,
     dangerMode: true,
   })
   .then((willDelete) => {
     if (willDelete) {
   //       var url = "http://teacher.cuoratech.com/aladmin_srp/sections/" + id + "/remove";
   var url = "{{url('')}}/visas/" + id + "/delete";
//                     alert(url);
         window.location.href = url;
         
     } else {
       swal("تم الغاء عملية الحذف بنجاح");
     }
   });
    
}
</script>
@endsection
