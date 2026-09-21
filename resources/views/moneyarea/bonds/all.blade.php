@extends('layouts.app')
@section('content')
@section('title' , "بحث السندات")
<style>
    .buttons-collection{
          width: 100%;
    }
    .dt-column-title{
        font-weight: normal;
  font-size: 12px;
    }
</style>
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> 
                السندات
             </h5>
             <a href="{{route('site.bonds_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة سندات  
                 </button>
             </a>
         </div>
         <div class="card-body">
          
             
          <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align: right;">#</th>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ العملية</th>
                      
                     <th data-ordering="false" style="text-align: right;">بيان العملية</th>
                     <th data-ordering="false" style="text-align: right;">مبلغ العملية</th>
                     <th data-ordering="false" style="text-align: right;">معلومات التحصيل</th>
                      
                      
                      
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">الموظف</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">--</th>

                  </tr>  
               </thead>
               <tbody>
                   <?php
                       $total_bonds = 0;
                   foreach($bonds as $bond){
                       if($bond->type == 1){
                            $total_bonds -= $bond->amount;
                       }else{
                            $total_bonds += $bond->amount;
                       }
                      
                   }
                   
                   ?>
                  @foreach($bonds as $bond)
                  <tr>
                     <td>
                      <a href="{{asset($bond->file_path)}}">
                         {{$bond->es_id}}
                         </a>
                      </td>
                     <td>
                      <?php
                         if($bond->type == 1){
                             $type = "سند دفع";
                         }else{
                             $type = "سند قبض";
                         }
    
                         ?>
                         {{$type}}
                      </td>
                     <td>{{$bond->crt_date}}</td>
                     <td>
                      
                      تحويل من
                         @if($bond->from_type == "storage")
                         خزينة
                         @php $min_info = $bondStorages[$bond->from_account] ?? null; @endphp
                         @else
                         حساب
                         @php $min_info = $bondSuppliers[$bond->from_account] ?? null; @endphp
                         @endif
                         @if($min_info && $min_info->acc_type == 3)
                         مصروفات
                         @endif
                         <b>{{$min_info->name ?? ''}}</b>


                         لصالح
                          @if($bond->to_type == "storage")
                         خزينة
                         @php $min_info2 = $bondStorages[$bond->to_account] ?? null; @endphp
                         @else
                         حساب
                         @php $min_info2 = $bondSuppliers[$bond->to_account] ?? null; @endphp
                         @endif
                          @if($min_info2 && $min_info2->acc_type == 3)
                         مصروفات
                         @endif
                          <b>{{$min_info2->name ?? ''}}</b>
                         @if($bond->sub_id == null)
                         @else
                         <br>
                         @php
                         $sub_id = (int) $bond->sub_id;
                         $min_info3 = $sub_id !== 0 ? ($bondSubStorages[$bond->sub_id] ?? null) : null;
                         @endphp
                         @if($sub_id == 0)
                         @else
                         خزينة فرعية : <b>{{$min_info3->name ?? ''}}</b>
                         @endif
                         @endif
                         
                         @if($bond->is_invoice == 1)
                         <br>
                        لمعاينة الفاتورة <a href="{{route('site.invoices_show',$bond->invoice_id)}}">اضغط هنا</a>
                         @endif
                        <br>
                          {{$bond->info}}
                      </td>
                     <td>{{number_format($bond->amount,2)}}</td>
                     <td>
                      @if($bond->money_way == 1)
                         دفع نقدي
                         @elseif($bond->money_way == 2)
                         تحويل بنكي
                         {{ ($bondBanks[$bond->bank_id] ?? null)->bank_name ?? '' }}
                         @else
                         تحصيل من المندوب :
                         {{ ($bondCollectors[$bond->collector_info] ?? null)->name ?? '' }}
                         @endif



                      </td>

                   <td>
                       {{ ($bondUsers[$bond->created_by] ?? null)->name ?? '' }}
                      </td>
                   <td>
<!--
                        <a href="{{route('site.bonds_edit',$bond->id)}}">
                              <button class="btn btn-primary">
                          <i class="ri-edit-2-line"></i>
                           </button>
                       </a>
-->
                         <form id="delete-form-{{$bond->id}}" action="{{route('site.bonds_delete', $bond->id)}}" method="POST" style="display:none;">
                            @csrf
                         </form>
                       <button class="btn btn-danger" onclick="confirmDeleteForm('delete-form-{{$bond->id}}', 'هل انت متأكد؟', 'سيتم حذف ذلك السند وازالة كل البيانات المرتبطه به')">
                           <i class="ri-delete-bin-line"></i>
                           </button>
                           <button class="btn btn-dark" onclick="printdiv({{$bond->id}})">
                           <i class="ri-printer-line"></i>
                           </button>

                      </td>
                  </tr>
                  @endforeach
                   
               </tbody>
            </table>
             
             </div>
  <table class="table">
                        <tbody>
                         
                            
  
                            

                                                                          
   <tr class="table-dark">
                            <td class="font-weight-bold">
الاجمالي
       </td>
                            <td class="font-weight-bold">{{number_format($total_bonds,2)}} جنيها</td>
                        </tr>

                            
                            
                      
                      
                            
                            
                    </tbody></table>
             
             
             
         </div>
      </div>
   </div>
   <!--end col-->
</div>


  <script>
       let table = new DataTable('#InvoicesTable', {
           ordering: false,
           columnDefs: [
//        {
//            target: 0,
//            visible: false,
//        },

    ],
    responsive: true,
           layout: {
        topStart: {
            buttons: ['colvis']
        }
    },
});



function printdiv(id){
   //    hidn
      
   //  document.getElementById("hidn").style.display = "none"; 
   //  document.getElementById("main_nv").style.display = "none"; 
   //  document.getElementById("footer").style.display = "none"; 
   //  document.getElementById("rs_alert").style.display = "none"; 
   //
   //    window.print();
    var url = "{{url('')}}/bonds/" + id + "/print";
//    alert(url);
           var printWindow = window.open( url, 'Print', 'left=200, top=200, width=950, height=500, toolbar=0, resizable=0');
       printWindow.addEventListener('load', function(){
           printWindow.print();
//           printWindow.close();
       }, true);
//       
   }
        </script>
@endsection
