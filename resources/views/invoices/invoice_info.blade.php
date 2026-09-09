@extends('layouts.app')
@section('content')
@section('title' , "ادارة فاتورة")


@if($errors->any())
<style>
    .aler_error{
        display: none !important;
    }
</style>
<script>
swal("", "{{$errors->first()}}", "info");

</script>
@endif 
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> ادارة الفاتورة : {{$invoiceInfo->es_id}} </h5>
         </div>
         <div class="card-body">
<?php
             $invoice = $invoiceInfo;
             ?>
             <?php
                      $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice->ticket_system_id)->get();
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                      
                      ?>
             <div class="row">
                 @if(Auth::user()->account_type == 2) 
             <div class="col-sm-4 card text-center" style="padding: 14px;">
                 
                    <a href="{{route('site.invoices_edit' , $invoice->id)}}" class="dropdown-item">
          <i class="ri-edit-box-line"></i>
                        تعديل          
</a>
                 <br>
                 
                 </div>
                 @else
                 
                  @if($invoice->invoice_status == 0) 
                              
                 
           
                 
     <div class="col-sm-4 card text-center" style="padding: 14px;">
                   <a href="{{route('site.invoices_edit' , $invoice->id)}}" class="dropdown-item">
          <i class="ri-edit-box-line"></i>
                        تعديل          
</a>
                 </div>
                              
            @endif
                 
                 
                 @endif
                 
         
                @if($invoice->invoice_money_pay < $total_client_bought_price)                          
    <div class="col-sm-4 card text-center" style="padding: 14px;">
      <a href="{{route('site.invoices_pay_part' , $invoice->id)}}" class="dropdown-item"><i class="ri-wallet-3-line"></i> سداد الفاتورة</a>
             </div>
    @endif 
             
             
             
             
              <div class="col-sm-4 card text-center" style="padding: 14px;">
      <a href="{{route('site.invoices_reissue_create' , $invoice->es_id)}}" class="dropdown-item">
      <i class="ri-arrow-go-forward-line"></i>
                                    اعادة اصدار
                                  </a>
   </div>
                               <div class="col-sm-4 card text-center" style="padding: 14px;">
      <a href="{{route('site.invoices_refund' , $invoice->es_id)}}" class="dropdown-item">
     <i class="ri-refund-2-line"></i>
                                   الغاء الفاتورة
                                  </a>
   </div>
 

                             <div class="col-sm-4 card text-center" style="padding: 14px;">
      <a href="{{route('site.invoices_remove' , $invoice->es_id)}}" class="dropdown-item">
    <i class="ri-delete-bin-line"></i>
                                   حذف الفاتورة
                                  </a>
   </div>
 
             
             
             
             
             </div>
             
            
         </div>
      </div>
   </div>
   <!--end col-->
</div>




@endsection
