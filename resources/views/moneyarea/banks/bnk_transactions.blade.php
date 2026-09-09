@extends('layouts.app')
@section('content')
@section('title' , 'كشف حساب بنك')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> كشف حساب بنك : {{$bank_info->bank_name}}</h5>
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
                     <th data-ordering="false" style="text-align:right;">نوع السند</th>
                     <th data-ordering="false" style="text-align:right;">بيان العملية</th>
                     <th data-ordering="false" style="text-align:right;">المبلغ</th>
                     <th data-ordering="false" style="text-align:right;">تاريخ السند</th>
                       <th data-ordering="false" style="text-align:right;">اعتماد العملية</th>
                  </tr>
               </thead>
               <tbody>
                   
                     <?php
                 
                      $totalBonds = 0;
                      foreach($bonds as $bank_transaction){
                          $totalBonds += $bank_transaction->amount;
                      }
                      ?>
                   
                  @foreach($bonds as $bond)
                   
                  <tr>
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
                     <td>
                      
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
                      </td>
                      <td style="text-align:right;">{{number_format($bond->amount,2)}}</td>
                      <td style="text-align:right;">{{$bond->crt_date}}</td>
                     <td>
                       @if($bond->bond_status == 0)
                    
                    <button class="btn btn-primary" id="rvd_{{$bond->id}}" style="border-radius: 55px;font-size: 10px;" onclick="do_approved({{$bond->id}})">
                         تأكيد العملية
                        </button>
                     <button class="btn btn-success" id="apprvd_{{$bond->id}}" style="border-radius: 55px;font-size: 10px;display:none;">
                     تم التأكيد
                        </button>      
                          @else
                         
                       <button class="btn btn-success" style="border-radius: 55px;font-size: 10px;">
                     تم التأكيد
                        </button>      
                    @endif
                      </td>
                  </tr>
                   
                  @endforeach
               </tbody>
                <tfoot>
                <tr>
                        <td style="text-align:right;">الاجمالي</td>
                        <td style="text-align:right;">--</td>
                    <td style="text-align:right;color:white;" class="bg-dark">{{number_format($totalBonds,2)}}</td>
                    <td style="text-align:right;">--</td>
                     <td style="text-align:right;">--</td>
                    </tr>
                </tfoot>
            </table>
   
          </div>
      </div>
   </div>
   <!--end col-->
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <script>
        function do_approved(id){
//          alert(id);
          
   var url = "{{url('')}}/bonds/" + id + "/approve";
//          alert(url);
           
           $(document).ready(function () {

      $.ajax({
         type: "GET",
         url: url,
         headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
         },
         data: "id=" + id,
         //   beforeSend: function() {
         //   $('.message_box').html(
         //   '<img src="Loader.gif" width="25" height="25"/>'
         //   );
         //   },
         success: function (data) {
            //var dataResult = JSON.parse(data);

            //console.log(data.status_code);

            var status_code = data.status_code;
 
             
             if(status_code == 200){
                 swal("", "تم تأكيد العملية بنجاح", "success");
                 
                 var n1 = "rvd_" + id;
                 var n2 = "apprvd_" + id;
                  document.getElementById(n1).style.display = "none"; 
                  document.getElementById(n2).style.display = "block"; 
//                  document.getElementById("myDIV").style.display = "none"; 
                 
             }else{
                 swal("", "فشل اثناء تأكيد العملية", "error");
             }


         }
      });
      //   });

   });
          
          
          
      }
      
    
    
</script>
@endsection
