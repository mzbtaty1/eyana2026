@extends('layouts.app')
@section('content')
@section('title' , "سداد الفاتورة")
<style>
    .aler_error{
        display: none !important;
    }
</style>

@if($errors->any())

<script>
swal("", "{{$errors->first()}}", "info");

</script>
@endif 
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> سداد الفاتورة : <b>{{$invoice_info->es_id}}</b> </h5>
         </div>
         <div class="card-body">
                 <table class="table table-bordered" id="dynamicTableTwo">
                  <tr>
                     <th>الاسم</th>
                     <th>نوع الراكب</th>
                     <th>سعر التكلفة</th>
                     <th>سعر البيع</th>
                     <th>رقم الحجز</th>
                     <th>رقم التكت</th>
                  </tr>
                   <?php $x = 0; ?>
                   @foreach($users as $user)
                  <tr>
                     <td>
                        <input type="text" value="{{$user->client_name}}" name="ticket_info[{{$x}}][name]" placeholder="الاسم" class="form-control" disabled>
                     </td>
                     <td>
                        <select class="form-control" name="ticket_info[{{$x}}][client_type]" disabled>
                           <option value="1" @if($user->client_type == 1) selected @endif>رضيع</option>
                           <option value="2" @if($user->client_type == 2) selected @endif>طفل</option>
                           <option value="3" @if($user->client_type == 3) selected @endif>بالغ</option>
                        </select>
                     </td>
                     <td>
                        <input type="number" name="ticket_info[{{$x}}][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" value="{{$user->client_net_pice}}" disabled="" />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[{{$x}}][bought_price]" oninput="findTotal2()" oninput="" placeholder="سعر البيع" class="form-control amount2" value="{{$user->client_bought_price}}" disabled="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_booking_id}}" name="ticket_info[{{$x}}][book_id]" placeholder="رقم الحجز" class="form-control" disabled="" />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_ticket_id}}" name="ticket_info[{{$x}}][tikcet_id]" placeholder="رقم الحجز" class="form-control" disabled="" />
                     </td>
                     
                    
                    
                  </tr>
                   <?php $x++; ?>
                   @endforeach
               </table>
               <br>
             
            <form action="{{route('site.pay_part_save')}}" onsubmit="return validateForm()" name="myForm" method="POST" autocomplete="off">
               @csrf
                <input type="hidden" name="id" value="{{$invoice_info->id}}">

                
           
                <?php
  $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice_info->ticket_system_id)->get();
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }                
                ?>
          
                       <div class="row">
      <div class="col-sm">
          <h4>
          اجمالي سعر البيع الحالي : {{$total_client_bought_price}} ج.م
          </h4>
      </div>
      <div class="col-sm">
          <h4>
اجمالي المسدد من الفاتورة : {{$invoice_info->invoice_money_pay}} ج.م
          </h4>
      </div>
    </div>
                
               <hr>
        @if($invoice_info->invoice_money_pay == $total_client_bought_price)
                <div class="alert alert-success border-0" style="  font-size: 16px;">
                    <i class="ri-information-line"></i>
                تم سداد فاتورة : {{$invoice_info->es_id}} بالكامل
                </div>
                @else
                <h5 style="  line-height: 27px;">
                المبلغ الذي تريد اضافتة للدفع : 
                <br>
                    يمكنك كتابة مبالغ تصل الي {{$total_client_bought_price - $invoice_info->invoice_money_pay}} جنية فقط
                </h5>
                
               <input type="tel" name="money_pay" id="money_pay" class="form-control" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" placeholder="المبلغ : (*)" style="text-align: right;direction: rtl;">
                
               <br> 
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
دفع لصالح الفاتورة 
                   </button>
                   
@endif
                   
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script type="text/javascript">
    
function validateForm() {
    var val = {{$total_client_bought_price - $invoice_info->invoice_money_pay}};
    
  let x = document.forms["myForm"]["money_pay"].value;
  if (x == "") {
    swal("", "برجاء ادخال القيمة التي ترغب في سدادها", "error");
    return false;
  }
    
     if (x == 0) {
    swal("", "يرجي ادخال قيمة صحيحة للسداد", "error");
    return false;
  }
   
     if (x > val) {
         var msg = "يرجي ادخال قيمة للسداد لا تزيد عن " + val + " ج.م ";
    swal("", msg, "error");
    return false;
  }
   
}
    
    
    
</script>
@endsection
