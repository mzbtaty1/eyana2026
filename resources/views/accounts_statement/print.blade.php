@extends('layouts.print')
@section('content')
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
    
</style>
<br>
<div class="container">

    <div class="border_print container">
    
    <img src="{{asset('assets/images/flymix_colored.png')}}"  style="  width: 154px;">
        <h5 style="  float: right;  text-align: right;  line-height: 31px;">
        كشف حساب شامل
            <br>
            @if($supplier->acc_type == 1) العميل @else المورد @endif
                : <b>{{$supplier->name}}</b>
        00000
        </h5>
        <hr>
         <h5 style="  float: left;  text-align: right;  line-height: 31px;" class="b_balnce">
         رصيد افتتاحى (دائن)
        : {{$supplier->opening_credit_balance}} جنيها
        
        </h5>
         <h5 style="  float: right;  text-align: right;  line-height: 31px;" class="b_balnce">
         رصيد افتتاحى (مدين)
        : {{$supplier->debit_opening_balance}} جنيها
        
        </h5>
        
        <div class="t">
               <table id="customers"  dir="rtl">
               <thead>
                  <tr>
                     <th style="text-align: right;" class="th_d">#</th>
                     <th style="text-align: right;" class="th_d">نوع العملية</th>
                     <th style="text-align: right;" class="th_d">تاريخ العملية</th>
                     <th style="text-align: right;" class="th_d">تاريخ السفر</th>
                     <th style="text-align: right;color:white;" class="bg-danger th_d">وجهة الاستلام / الوصول</th>
                      
                     <th style="text-align: right;" class="th_d">بيانات الركاب</th>
                     <th style="text-align: right;" class="th_d">بيان العملية</th>
                      
                      
                     <th style="text-align: right;color:white;" class="bg-dark th_d">رصيد</th>
                     <th style="text-align: right;color:white;" class="bg-dark th_d">مدين</th>
                     <th style="text-align: right;color:white;" class="bg-dark th_d">دائن</th>
                      

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
                   $ticket_info = App\Models\Invoice::select('*')->where('es_id' , $AccountStatement->es_id)->get();
                   $ticket_info = $ticket_info[0];
                   $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $ticket_info->ticket_system_id)->get();
                   
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                     
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
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
                          }
                          ?>
                         @if($AccountStatement->transaction_type == 1)
                         تذاكر / 
                         @endif
                      {{$type}}
                      </td>
                     <td style="text-align: right;">{{$ticket_info->invoice_date}}</td>
                     <td style="text-align: right;">{{$ticket_info->invoice_travel_date}}</td>
                     <td>{{$ticket_info->from_location}} - {{$ticket_info->to_location}}</td>
                      
                            <td style="text-align: right;">
                           @if($AccountStatement->transaction_type == 1)     
                         @foreach($users as $user) 
    
                         {{$user->client_name}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                         @else
                                --
                                @endif
                      </td>
                     <td style="text-align: right;">
                         {{$AccountStatement->transaction_txt}}
                         @if($AccountStatement->transaction_type == 1)
                         / خط الطيران : {{$ticket_info->invoice_airline}}
                         @endif
                         @if($AccountStatement->transaction_type == 1)
                         /
                          ارقام الحجز : @foreach($users as $user) 
                        
                         {{$user->client_booking_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                         
                          /
                         
                          ارقام التذاكر : @foreach($users as $user) 
                        
                         {{$user->client_ticket_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
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
            
                      
                    
                  </tr>
                  @endforeach
               </tbody>
            </table>
             
             </div>
        
    </div>


</div>



@endsection
