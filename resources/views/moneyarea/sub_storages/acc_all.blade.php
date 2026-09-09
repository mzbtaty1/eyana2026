@extends('layouts.app')
@section('content')
@section('title' , "بحث كشف حساب خزينة")
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
            <h5 class="card-title mb-0"> كشف حساب 
             @if($st == 1)
                خزينة : {{$storage_info->name}}
                @else
                خزائن عام
                @endif
                
             </h5>
         </div>
         <div class="card-body">
           
         
             
                       <hr>  
          <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align: right;">#</th>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;">الخزينة المنفذه</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ العملية</th>
                      
                     <th data-ordering="false" style="text-align: right;">بيان العملية</th>
                      
                      
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">تراكمي</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">مدين</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">دائن</th>
                      
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">الموظف</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-primary">المطابقة</th>

                  </tr>
               </thead>
               <tbody>
                   <?php
                   
                    $total_cumulative_balance = 0;
                   foreach($AccountStatements as $AccountStatement){
$total_cumulative_balance += $AccountStatement->cumulative_balance;
 }
                   
                   ?>
                  @foreach($AccountStatements as $AccountStatement)
                   
                   <?php
                if($AccountStatement->trans_storage == 1){
                  $ticket_info = App\Models\Bond::select('*')->where('es_id' , $AccountStatement->es_id)->get();
                  $ticket_info = $ticket_info[0];    
                  $bond = $ticket_info;    
              
                }else{
                
                          $ticket_info = App\Models\Invoice::select('*')->where('es_id' , $AccountStatement->es_id)->get();
                   $ticket_info = $ticket_info[0];
                   $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $ticket_info->ticket_system_id)->get();
                   
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                     
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }  
                    
                    
                }
                 
                   
                   
                   ?>
                  <tr>
                     <td style="text-align: right;">{{$AccountStatement->es_id}}</td>
                     <td style="text-align: right;"><?php
                          if($AccountStatement->invoice_type == 1){
                              $type = "فواتير الطيران";
                          }elseif($AccountStatement->invoice_type == 2){
                              $type = "فواتير تأشيرات";
                          }elseif($AccountStatement->invoice_type == 3){
                              $type = "فواتير سياحه داخليه";
                          }elseif($AccountStatement->invoice_type == 4){
                              $type = "فواتير سياحه خارجيه";
                          }elseif($AccountStatement->invoice_type == 5){
                              $type = "فواتير سياحه دينيه";
                          }elseif($AccountStatement->invoice_type == 6){
                              $type = "فواتير تأمينات السفر";
                          }elseif($AccountStatement->invoice_type == 7){
                              $type = "فواتير تحاليل السفر";
                          }elseif($AccountStatement->invoice_type == 8){
                              $type = "فواتير نقل سياحى";
                          }elseif($AccountStatement->invoice_type == 9){
                              $type = "سند دفع";
                          }elseif($AccountStatement->invoice_type == 10){
                              $type = "سند قبض";
                          }
                          ?>
                         @if($AccountStatement->transaction_type == 1)
                         تذاكر / 
                        @elseif($AccountStatement->transaction_type == 2)
                         سندات /
                         @endif
                      {{$type}}
                      </td>
                      <td>

                      <?php

        $check_storage = App\Models\Storage::select('*')->where('id',$AccountStatement->supp_client_id)->get();
        abort_if(count($check_storage) == 0 , 404);
        $storage_info = $check_storage[0];
        ?>
        {{$storage_info->name}}
                      </td>
                      <td>{{$AccountStatement->created_at}}</td>
                     
                     
                   
                      
                     <td style="text-align: right;">
                         {{$AccountStatement->transaction_txt}}
                        <br>
                        
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
                      
                     <td style="text-align: right;">{{$AccountStatement->debit_balance + $AccountStatement->credit_balance}}</td>
                     <td style="text-align: right;">{{$AccountStatement->debit_balance}}
                       @if($AccountStatement->transaction_type == 2)
<!--                         <br><span class="badge bg-primary my_badge">المرتجع لنا</span>-->
                         @endif
                         
                      </td>
                     <td style="text-align: right;">{{$AccountStatement->credit_balance}}
                      @if($AccountStatement->transaction_type == 2)
<!--                         <br><span class="badge bg-primary my_badge">المسترد له</span>-->
                         @endif
                      </td>
                <td style="text-align: right;">
                    
                    {{$AccountStatement->added_by}}
                    
                      </td>
                      <?php
                      if($ticket_info->invoice_status == 0){
                          $color = "danger";
                      }else{
                          $color = "success";
                      }
                      
                      ?>
                      @if($AccountStatement->transaction_type == 1)
                <td style="text-align: right;color:white;" class="bg-{{$color}}">
                    @if($ticket_info->invoice_status == 0)
                    لم يتم التأكد
                          @else
                         تم التأكيد
                          @endif
                    
                      </td>
                      @else
                      <td style="text-align: right;" class="">
                    --
                    
                      </td>
               @endif
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
        </script>  
@endsection
