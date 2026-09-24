<table dir="rtl">
    <thead>
         <tr>
                     <td style="text-align: center !important;width:100%;background-color:#198754;color:white;">
                         @if($st == 0)
                         كشف حساب عام
                         @else
                         كشف حساب المورد {{$supplier->name}}
                         @endif
                         
                            @if($date_from !== null && $date_to !== null)
            <br>
            بحث من تاريخ : {{$date_from}} الي {{$date_to}}
            @endif
                         
                         </td>
                     </tr>
                  <tr>

   <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;">#</th>
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;">نوع العملية</th>
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;">تاريخ العملية</th>
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;">تاريخ السفر</th>
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;" class="bg-dark">وجهة الاستلام / الوصول</th>
                      
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;">بيانات الركاب</th>
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;">بيان العملية</th>
                      
                      
              
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;" class="bg-dark">مدين</th>
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;" class="bg-dark">دائن</th>
                             <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;" class="bg-dark">الرصيد</th>
                     <th data-ordering="false" style="text-align: right;color:white;background-color:#212529;" class="bg-dark">الموظف</th>
                  </tr>
    </thead>
       <tbody>
                   @if($st == 1)
<!--
                    <tr>
                   
                       <td colspan="7">
                       الارصدة الافتتاحية
                       </td>
                       <td style="display:none"></td>
                       
                       <td style="display:none"></td>
                       <td style="display:none"></td>
                       <td style="display:none"></td>
                       
                       <td style="display:none"></td>
                       <td style="display:none"></td>
                       <td style="display:none"></td>
                        

                      
                       
                  <td style="text-align:center;">
                       {{$supplier->debit_opening_balance}}
                       </td>
                  <td colspan="" style="text-align:center;">
                       {{$supplier->opening_credit_balance}}
                       </td>
                         <td colspan="" style="text-align:center;">
                       {{$supplier->opening_credit_balance + $supplier->debit_opening_balance}}
                       </td>
                        
                       <td colspan="1"></td>
                   </tr>
-->
                   
                   @endif
                   <?php
                   
                   // Seeded with the carried-forward opening balance (0 when there is no
                   // date filter), same as the Account Statement screen and Print Preview.
                   $total_blnc = $opening_balance_for_period;
                  
                       
                   
                    $total_cumulative_balance = 0;
                   foreach($AccountStatements as $AccountStatement){
$total_cumulative_balance += $AccountStatement->cumulative_balance;
 }
                   
                   ?>
               @if($date_from !== null && $date_to !== null)
                  <tr>
                     <td colspan="7" style="text-align: right;font-weight:bold;">رصيد افتتاحي للفترة (مرحل حتى {{$date_from}})</td>
                     <td></td>
                     <td></td>
                     <td style="text-align: right;font-weight:bold;">{{number_format($opening_balance_for_period , 2)}}</td>
                     <td></td>
                  </tr>
               @endif
               <?php $x = 0; ?>
                  @foreach($AccountStatements as $key => $AccountStatement)
                   
                   <?php
                   
                    // Balances are DECIMAL(14,2): keep the cents (an (int) cast truncated them).
                    $closing = (float) $AccountStatement->debit_balance - (float) $AccountStatement->credit_balance;
                    $total_blnc = round($total_blnc + $closing, 2);
                   
                   // Lookups come from maps batched in AccountatExport (no per-row queries).
                   // Reset per row so a row never shows the previous row's passengers.
                   $ticket_info = new App\Models\Invoice();
                   $users = collect();
                   $bond = new App\Models\Bond();
                if($AccountStatement->trans_storage == 1){
//                    dd(4);
                  $ticket_info = $bondsByEsId->get($AccountStatement->es_id) ?? new App\Models\Bond();
               $bond = $ticket_info;    
                }else{
              if($AccountStatement->is_supp_account == 0){
                   $ticket_info = $invoicesByEsId->get($AccountStatement->es_id) ?? new App\Models\Invoice();
                   $users = $usersByTicketSystemId->get($ticket_info->ticket_system_id) ?? collect();
                   
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                     
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }  
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
                          }elseif($AccountStatement->invoice_type == 11){
                              $type = "ارصدة";
                          }elseif($AccountStatement->invoice_type == 12){
                              $type = "سداد فاتورة";
                          }
                          ?>
                         @if($AccountStatement->transaction_type == 1)
                         تذاكر / 
                        @elseif($AccountStatement->transaction_type == 2)
                         سندات /
                        @elseif($AccountStatement->transaction_type == 3)
                         ارصدة افتتاحية /
                         @elseif($AccountStatement->transaction_type == 4)
                         سداد /
                         @endif
                      {{$type}}
                      </td>
                      
                      
                       <td style="text-align: right;">
                         
                         @if($AccountStatement->transaction_type == 1) 
{{$ticket_info->invoice_date}}
                         @else
                           --
                           @endif
                         </td>
                      
                         
                     <td style="text-align: right;">
                         @if($AccountStatement->transaction_type == 1) 
                          {{$ticket_info->invoice_travel_date}}
                         @else
                         {{$AccountStatement->created_at}}
                         @endif

                         
                        </td>
                         <td>
                             @if($AccountStatement->transaction_type == 1) 
                                      {{$ticket_info->from_location}} - {{$ticket_info->to_location}}
@else
                             --
                             @endif
                         </td>
                      
                        <td style="text-align: right;">
                            @if($AccountStatement->transaction_type == 1) 
                          
                            
                             @foreach($users as $user) 
    
                         {{$user->client_name}} @if(count($users) == 0)
                         @else
                         <br>
                         @endif
                         @endforeach
                            
                            
                            
                            @elseif($AccountStatement->transaction_type == 4)
                            @foreach($users as $user) 
    
                         {{$user->client_name}} @if(count($users) == 0)
                         @else
                         <br>
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
                         <br>
                          ارقام الحجز : @foreach($users as $user) 
                        
                         {{$user->client_booking_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                         
                          <br>
                         
                          ارقام التذاكر : @foreach($users as $user) 
                        
                         {{$user->client_ticket_id}} @if(count($users) == 0)
                         @else
                         /
                         @endif
                         @endforeach
                         @else
                                                  
                         <br>
                         @if($AccountStatement->transaction_type == 2)
@if($bond->money_way == 1)
                         دفع نقدي 
                         @elseif($bond->money_way == 2)
                         تحويل بنكي
                    <?php
                         $bank_info = $banksById->get($bond->bank_id) ?? new App\Models\Bank();
                         ?>
                         {{$bank_info->bank_name}}
                         @else
                         تحصيل من المندوب : 
                         <?php
                         $collector_info = $collectorsById->get($bond->collector_info) ?? new App\Models\Collector();
                         ?>
                         {{$collector_info->name}}
                         
                         @endif
                         @endif
                     @if($AccountStatement->trans_storage == 1)
                        @if($AccountStatement->sub_id == 0)
                         @else
                          <br>
                         <?php
                         $sub_id = (int) $AccountStatement->sub_id;
                          $min_info3 = $subStoragesById->get($sub_id) ?? new App\Models\SubStorage();
                             
                         
                         ?>
                         خزينة فرعية : <b>{{$min_info3->name}}</b>
                       
                           @endif
                         @endif
                       
                         
                         <br>
                         @endif
                         
                      </td>
                      
                       
                 
                       
                    
                      <td style="text-align: right;">{{number_format($AccountStatement->debit_balance , 2)}}
                      
                         
                      </td>
                     <td style="text-align: right;color:#ff7900;">
                         {{number_format($AccountStatement->credit_balance , 2)}}
                    
                      </td>
                      
                       <td style="text-align: right;">

                         {{number_format($total_blnc , 2)}}
                      </td>
                      
                <td style="text-align: right;">
                    <?php
                    $mem = $usersById->get($AccountStatement->added_by) ?? new App\Models\User();
                    ?>
                    {{$mem->name}}
                    
                      </td>
                     
                      
                       
                      <?php
//                      if($AccountStatement->transaction_approved == 0){
//                          $color = "danger";
//                      }else{
//                          $color = "success";
//                      }
//                      
                      ?>
      
                  </tr>
                    <?php $x++; ?>
                  @endforeach
                 
               </tbody>
               <tfoot>
                  <tr>
                     <td colspan="7" style="text-align: right;font-weight:bold;">الإجمالي</td>
                     <td style="text-align: right;font-weight:bold;">{{number_format($total_debit_balance , 2)}}</td>
                     <td style="text-align: right;font-weight:bold;">{{number_format($total_credit_balance , 2)}}</td>
                     <td style="text-align: right;font-weight:bold;">{{number_format($total_blnc , 2)}}</td>
                     <td></td>
                  </tr>
               </tfoot>
               
</table>
