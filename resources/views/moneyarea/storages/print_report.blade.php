@extends('layouts.print')
@section('content')
<?php

$title_1 = "كشف حساب خزينة";

?>
@section('title',$title_1)
<style>

    .border_print{
/*        border-style: solid;*/
    }
    .b_balnce{
        font-size:17px;
    }
    .th_d{
        font-size: 10px !important;
    }
    #customers {
  font-family: Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

#customers td, #customers th {
  border: 1px solid #ddd;
  padding: 8px;
}

#customers tr:nth-child(even){background-color: #f2f2f2;}

#customers tr:hover {background-color: #ddd;}

#customers th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #04AA6D;
  color: white;
}
    .page_print{
            print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    }
</style>
<br>
<div class="page_print">

    <div class="border_print">
    
    <img src="{{asset('assets/images/flymix_colored.png')}}"  style="  width: 154px;">
        <h5 style="  float: right;  text-align: right;  line-height: 31px;">
        كشف حساب 
           @if($st == 1)
                خزينة : {{$storage_info->name}}
                @else
                خزائن عام
                @endif
            @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif
        </h5>
        <hr>
      
        <div class="t">
               <table id="customers"  dir="rtl">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align: right;">#</th>
                     <th data-ordering="false" style="text-align: right;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;">الخزينة المنفذه</th>
                     <th data-ordering="false" style="text-align: right;">تاريخ العملية</th>
                      
                     <th data-ordering="false" style="text-align: right;">بيان العملية</th>
                      
                      
<!--                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">تراكمي</th>-->
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">مدين</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="bg-dark">دائن</th>
                      

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
                      
<!--
 <td style="text-align: right;">
                         <?php
                         $last_row = App\Models\AccountStatement::where('id', '<', $AccountStatement->id)->where('is_storage' , 1)->get();
                         if(count($last_row) == 0){
                             $t = $AccountStatement->debit_balance + $AccountStatement->credit_balance;
                         }else{
                             $last_row = $last_row[0];
                             $t = $last_row->debit_balance + $last_row->credit_balance;
                         }

                         ?> 
                         {{$AccountStatement->debit_balance + $AccountStatement->credit_balance}}
                         {{$AccountStatement->debit_balance + $AccountStatement->credit_balance}}
                      
                      
                      </td>
-->
                      <td style="text-align: right;">{{$AccountStatement->debit_balance}}
                     
                         
                      </td>
                     <td style="text-align: right;">{{$AccountStatement->credit_balance}}
                      
                      </td>
              
                      
                   
                  </tr>
                  @endforeach
               </tbody>
            </table>
             
             </div>
        
    </div>


</div>



@endsection
