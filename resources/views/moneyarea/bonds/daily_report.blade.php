@extends('layouts.app')
@section('content')
@section('title' , "القاصة اليومية  - بحث بتاريخ اليوم")
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
                القاصة اليومية  - بحث بتاريخ اليوم : {{date('Y/m/d')}}
             </h5>
<!--
             <a href="{{route('site.bonds_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة سندات  
                 </button>
             </a>
-->
         </div>
         <div class="card-body">
          
             
          <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align: right;">#</th>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                      
                     <th data-ordering="false" style="text-align: right;">بيان العملية</th>
                     <th data-ordering="false" style="text-align: right;">مبلغ العملية</th>
                     <th data-ordering="false" style="text-align: right;">معلومات التحصيل</th>
                      
                      
                      
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">الموظف</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">--</th>

                  </tr>  
               </thead>
               <tbody>
                   <?php
                   $start = date('Y-m-d');
                   $yas_date = date('Y-m-d',strtotime('-1440 minutes',strtotime($start)));  
                   
                   $yas_date2 = date('Y/m/d',strtotime('-1440 minutes',strtotime($start)));
                   
                   $bonds_yasterday = App\Models\Bond::select('*')->where('crt_date' , $yas_date)->get();
                   $bonds_yasterday_als = App\Models\Bond::select('*')->where('crt_date' , '<', $start)->get();
                   
                   $total_bonds = 0;
                   foreach($bonds_yasterday as $bond_yasterday){
//                       $total_bonds += $bond_yasterday->amount;
                       
                       if($bond_yasterday->type == 1){
                            $total_bonds -= $bond_yasterday->amount;
                       }else{
                            $total_bonds += $bond_yasterday->amount;
                       }
                       
                       
                   }
                   
                   
                   $total_bonds_als = 0;
                   foreach($bonds_yasterday_als as $bonds_yasterday_al){
//                       $total_bonds += $bond_yasterday->amount;
                       
                       if($bonds_yasterday_al->type == 1){
                            $total_bonds_als -= $bonds_yasterday_al->amount;
                       }else{
                            $total_bonds_als += $bonds_yasterday_al->amount;
                       }
                       
                       
                   }
                      $total_bonds_2 = 0;
                   foreach($bonds as $bond){
                       if($bond->type == 1){
                            $total_bonds_2 -= $bond->amount;
                       }else{
                            $total_bonds_2 += $bond->amount;
                       }
                      
                   }
                   
                   ?>
                   <tr>
                   
                   <td style="text-align: right;">
                       الرصيد السابق
                       </td>
                <td>--</td>
                <td>--</td>
               
                       <td style="text-align: right;">{{number_format($total_bonds_als,2)}}</td>
                       
                       <td>--</td>
                       <td>--</td>
                       <td>--</td>
                       
                   </tr>
                   <tr>
                   
                   <td style="text-align: right;">
                       الرصيد السابق
                    أمس : {{$yas_date2}}
                       </td>
                <td>--</td>
                <td>--</td>
               
                       <td style="text-align: right;">{{number_format($total_bonds,2)}}</td>
                       
                       <td>--</td>
                       <td>--</td>
                       <td>--</td>
                       
                   </tr>
                  @foreach($bonds as $bond)
                  <tr>
                     <td style="text-align: right;">{{$bond->es_id}}</td>
                     <td style="text-align: right;">
                      <?php
                         if($bond->type == 1){
                             $type = "سند دفع";
                         }else{
                             $type = "سند قبض";
                         }
    
                         ?>
                         {{$type}}
                      </td>
                     <td style="text-align: right;">
                      
                      تحويل من 
                         @if($bond->from_type == "storage")
                         خزينة
                         <?php
                         $min_info = App\Models\Storage::select('*')->where('id' , $bond->from_account)->get();
                         $min_info = $min_info[0];
                         ?>
                         @else
                         حساب
                         <?php
                         $min_info = App\Models\Supplier::select('*')->where('id' , $bond->from_account)->get();
                         $min_info = $min_info[0];
                         ?>
                         @endif
                         <b>{{$min_info->name}}</b>
                         
                         
                         لصالح
                          @if($bond->to_type == "storage")
                         خزينة
                         
                          <?php
                         $min_info2 = App\Models\Storage::select('*')->where('id' , $bond->to_account)->get();
                         $min_info2 = $min_info2[0];
                         ?>
                         
                         @else
                         حساب
                         <?php
                         $min_info2 = App\Models\Supplier::select('*')->where('id' , $bond->to_account)->get();
                         $min_info2 = $min_info2[0];
                         ?>
                         @endif 
                          <b>{{$min_info2->name}}</b>
                         @if($bond->sub_id == null)
                         @else
                         <br>
                         <?php
                         $sub_id = (int) $bond->sub_id;
                         if($sub_id == 0){
                             
                         }else{
                             $min_info3 = App\Models\SubStorage::select('*')->where('id' , $bond->sub_id)->get();
                         $min_info3 = $min_info3[0];
                             
                         }
                         
                         ?>
                         @if($sub_id == 0)
                         @else
                         خزينة فرعية : <b>{{$min_info3->name}}</b>
                         @endif
                         @endif
                         
                      </td>
                     <td style="text-align: right;">{{number_format($bond->amount,2)}}</td>
                     <td style="text-align: right;">
                      @if($bond->money_way == 1)
                         دفع نقدي 
                         @elseif($bond->money_way == 2)
                         تحويل بنكي
                    <?php
                         $bank_info = App\Models\Bank::select('*')->where('id',$bond->bank_id)->get();
                         $bank_info = $bank_info[0];
                         ?>
                         {{$bank_info->bank_name}}
                         @else
                         تحصيل من المندوب : 
                         <?php
                         $collector_info = App\Models\Collector::select('*')->where('id',$bond->collector_info)->get();
                         $collector_info = $collector_info[0];
                         ?>
                         {{$collector_info->name}}
                         
                         @endif
                      </td>
                    <?php
                      $user_inf = App\Models\User::select('*')->where('id',$bond->created_by)->get();
                      $user_inf = $user_inf[0];
                          
                      ?>
                   <td style="text-align: right;">{{$user_inf->name}}</td>
                   <td>
                        
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
                         
                            
   <tr>
                            <td>
اجمالي رصيد السابق 
       </td>
                            <td>{{number_format($total_bonds_als,2)}} جنيها</td>
                        </tr>

                                              
   <tr>
                            <td>
       اجمالي اليوم : 
       </td>
                            <td>{{number_format($total_bonds_2,2)}} جنيها</td>
                        </tr>

                                                                          
   <tr class="table-dark">
                            <td class="font-weight-bold">
الاجمالي
       </td>
                            <td class="font-weight-bold">{{number_format($total_bonds_als+$total_bonds_2,2)}} جنيها</td>
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
