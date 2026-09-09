@extends('layouts.app')
@section('content')
@section('title' , 'البنوك')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0">قائمة البنوك</h5>
             <a href="{{route('site.banks_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة بنك جديد
                 </button>
             </a>
         </div>
         <div class="card-body">
             
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
                      <?php
                      $bank_transactions = App\Models\Bond::select('*')
            ->where('bank_id' ,$bank->id)
                          ->where('bond_status',0)
            ->where('money_way' ,2)
            ->get();
                      $totalBonds = 0;
                      foreach($bank_transactions as $bank_transaction){
                          $totalBonds += $bank_transaction->amount;
                      }
                      ?>
                     <td>{{$totalBonds}}</td>
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
                        <a href="#Removeairline" onclick="Removeairline({{$bank->id}})">
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
     text: "سيتم حذف ذلك خط البنك وازالة كل البيانات المرتبطه به",
     icon: "warning",
     buttons: true,
     dangerMode: true,
   })
   .then((willDelete) => {
     if (willDelete) {
   //       var url = "http://teacher.cuoratech.com/aladmin_srp/sections/" + id + "/remove";
   var url = "{{url('')}}/banks/" + id + "/delete";
//                     alert(url);
         window.location.href = url;
         
     } else {
       swal("تم الغاء عملية الحذف بنجاح");
     }
   });
    
}
</script>
@endsection
