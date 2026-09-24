@extends('layouts.app')
@section('content')
@section('title' , "استرجاع فاتورة")
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
            <h5 class="card-title mb-0"> استرجاع فاتورة : <b>{{$invoice_info->es_id}}</b> </h5>
         </div>
         <div class="card-body">
            <form id="refundForm" action="{{route('site.invoices_refund_save')}}" method="POST" autocomplete="off" enctype="multipart/form-data">
               @csrf
                <input type="hidden" name="id" value="{{$invoice_info->id}}">
                <!-- إضافة حقل تاريخ اليوم المخفي -->
                <input type="hidden" name="refund_date" value="{{ date('Y-m-d') }}">
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        تاريخ الفاتورة
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_date" value="{{ date('Y-m-d') }}" placeholder="تاريخ الفاتورة" class="form-control" style="text-align:right;" disabled>
                  </div>
                  <div class="col-6">
                    <p>
                        تاريخ السفر
                        <span class="text-danger">*</span>
                     </p>
                     <input type="date" name="invoice_travel_date" value="{{$invoice_info->invoice_travel_date}}" placeholder="تاريخ السفر" class="form-control" style="text-align:right;" required disabled>
                  </div>
               </div>
                
                <p>
                        تاريخ العودة
                     </p>
                     <input type="date" name="return_date" value="{{$invoice_info->return_date}}" placeholder="تاريخ العودة" class="form-control" style="text-align:right;" disabled>
                <br>
                
               <div class="row">
      <div class="col-6 mb-3">
         <p>
            واجهة الاستلام 
      <span class="text-danger">*</span>
          </p>
            <input type="text" name="from_location" value="{{$invoice_info->from_location}}" placeholder="واجهة الاستلام" class="form-control" style="text-align:right;" required="" disabled>
      </div>
      <div class="col-6">
         <p>
            واجهة الوصول 
         <span class="text-danger">*</span>
          </p>
            <input type="text" name="to_location" value="{{$invoice_info->to_location}}" placeholder="واجهة الوصول" class="form-control" style="text-align:right;" required="" disabled>
      </div>
    </div>
                 <p>
                        خط الطيران
                 <span class="text-danger">*</span>     
                </p>
                     <select class="form-control" name="invoice_airline" id="invoice_airline" style="text-align:right;" disabled required>
                         @foreach($airlines as $airline)
                         <option value="{{$airline->airline_name}}" @if($invoice_info->invoice_airline == $airline->airline_name) selected @endif>{{$airline->airline_name}}</option>
                         @endforeach
                     </select>
                <br> 
                
                <p>
                        الجروب
                     </p>
                     <select class="form-control" name="invoice_group_id" id="invoice_group_id" style="text-align:right;" disabled>
                     </select>
                <br>
               
                <?php
                         $ben_info = App\Models\Supplier::select('*')->where('id' , $invoice_info->invoice_beneficiaries)->get();
                            if(count($ben_info) == 0){
                                $ben_info = [];
                            } else {
                                $ben_info = $ben_info[0];
                            }
                             ?>
               <p>
                  اسم العميل (المستفيد)
                  <span class="text-danger">*</span>
               </p>
               <select class="form-control" name="invoice_beneficiaries" id="invoice_group_id" style="text-align:right;" disabled required>
                  @foreach($suppliers as $supplier)
                  <option value="{{$supplier->id}}" @if($supplier->id == $ben_info->id) selected @endif>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                  @endforeach
               </select>
               <br>
               <table class="table table-bordered">
                  <tr>
                     <th>
                        اسم المورد (المنفذ)  
                        <span class="text-danger">*</span>
                     </th>
                    
                  </tr>
                  <tr>
                     <td>
                        <!--                <input type="text" name="vendor[0][name]" placeholder="Enter your Name" class="form-control" />-->
                        <!--                    <select name="vendor[0][vendor_id]" class="form-control">-->
                         
                         <?php
                         
                         $ticket_vendor = App\Models\TicketVendor::select('*')->where('ticket_system_id' , $invoice_info->ticket_system_id)->get();
                         if(count($ticket_vendor) == 0){
                                $ticket_vendor = [];
                            } else {
                                $ticket_vendor = $ticket_vendor[0];
                            }
                         
                      $users = App\Models\TicketUser::select('*')->where('ticket_system_id' , $invoice_info->ticket_system_id)->get();
                      
                      $total_client_net_pice = 0;
                      $total_client_bought_price = 0;
                      foreach($users as $user){
                          $total_client_net_pice += $user->client_net_pice;
                          $total_client_bought_price += $user->client_bought_price;
                      }
                         
                         
                             ?>
                         
                        <select name="vendor_id" class="form-control" disabled required>
                           @foreach($suppliers as $supplier)
                           <option value="{{$supplier->id}}" @if($supplier->id == $ticket_vendor->vendor_id) selected @endif>{{$supplier->name}} - @if($supplier->acc_type == 1) عميل @else مورد @endif</option>
                           @endforeach
                        </select>
                     </td>
<!--                     <td><input type="text" name="vendor_cost" id="vendor_cost" inputmode="numeric" oninput="this.value = this.value.replace(/\D+/g, '')" value="" placeholder="التكلفة" disabled class="form-control" disabled required="" /></td>-->
                  </tr>
               </table>
               <p>
                  تصنيف الفاتورة
                  <span class="text-danger">*</span>
               </p>
               <select class="form-control" style="text-align:right;" name="invoice_section" disabled required>
                  <option value="1" @if($invoice_info->invoice_section == 1) selected @endif>فواتير الطيران</option>
                  <option value="2" @if($invoice_info->invoice_section == 2) selected @endif>فواتير تأشيرات</option>
                  <option value="3" @if($invoice_info->invoice_section == 3) selected @endif>فواتير سياحه داخليه</option>
                  <option value="4" @if($invoice_info->invoice_section == 4) selected @endif>فواتير سياحه خارجيه</option>
                  <option value="5" @if($invoice_info->invoice_section == 5) selected @endif>فواتير سياحه دينيه</option>
                  <option value="6" @if($invoice_info->invoice_section == 6) selected @endif>فواتير تأمينات السفر</option>
                  <option value="7" @if($invoice_info->invoice_section == 7) selected @endif>فواتير تحاليل السفر</option>
                  <option value="8" @if($invoice_info->invoice_section == 8) selected @endif>فواتير نقل سياحى</option>
               </select>
               <br>
               <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        ملاحظات
                     </p>
                     <input type="text" name="invoice_comments" value="{{$invoice_info->invoice_comments}}" placeholder="ملاحظات" class="form-control" style="text-align:right;" disabled>
                  </div>
                  <div class="col-6">
                     <p>
                        العملة
                        <span class="text-danger">*</span>
                     </p>
                     <select class="form-control" style="text-align:right;" name="invoice_currency" disabled required>
                       
                         <option value="جنية مصري" @if($invoice_info->invoice_currency == "جنية مصري") selected @endif>جنية مصري</option>
                         <option value="درهم اماراتي" @if($invoice_info->invoice_currency == "درهم اماراتي") selected @endif>درهم اماراتي</option>
                         
                     </select>
                  </div>
               </div>
               <p>
                  مسودة
               </p>
               <textarea class="form-control" style="text-align:right;" name="invoice_draft" placeholder="مسودة" disabled>{{$invoice_info->invoice_draft}}</textarea>
               <br> 
               <p>
                  ملف / صورة التذكرة
                  (المسموح : JPG , PNG , JPEG , PDF فقط)
                <br>
                   لا تقم بتغيير الملف في حالة عدم الرغبة في التغيير
                   
               </p>
               <input type="file" class="form-control" name="myPoster" accept="image/jpeg,image/jpg,image/png,application/pdf" disabled>
               <br>
               <h6 style="font-size: 16px;">
                  معلومات الفاتوره
                  <span class="text-danger">*</span>
<br>
                   <tag style="font-size: 14px;">رقم الهاتف اختياري</tag>

                </h6>
               <div class="card border mb-3">
                  <div class="card-body">
                     <p class="mb-2"><b>نوع الاسترداد</b> <span class="text-danger">*</span></p>
                     <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="refund_mode" id="refund_mode_full" value="full" checked onchange="onRefundModeChange()">
                        <label class="form-check-label" for="refund_mode_full">مرتجع كامل للفاتورة</label>
                     </div>
                     <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="refund_mode" id="refund_mode_single" value="single" onchange="onRefundModeChange()">
                        <label class="form-check-label" for="refund_mode_single">مرتجع راكب واحد فقط</label>
                     </div>
                     <p id="refund_mode_hint" class="mb-0 mt-2" style="font-size:13px;"></p>
                  </div>
               </div>
               <table class="table table-bordered" id="dynamicTableTwo">
                  <tr>
                     <th class="refund-pick" style="display:none;">الراكب المسترد</th>
                     <th>الاسم</th>
                     <th>نوع الراكب</th>
                     <th>سعر التكلفة</th>
                     <th>سعر البيع</th>
                     <th>رقم الحجز</th>
                     <th>رقم التكت</th>
                     <th>رقم الهاتف</th>
                  </tr>
                  <?php $x = 0; ?>
                   @foreach($users as $user)
                  <tr id="remove{{$user->id}}">
                     <td class="refund-pick" style="display:none;"><input type="radio" name="refund_passenger_id" value="{{$user->id}}" data-cost="{{round((float) $user->client_net_pice, 2)}}" data-sale="{{round((float) $user->client_bought_price, 2)}}" class="form-check-input"></td>
                     <td>
                        <input type="text" value="{{$user->client_name}}" name="ticket_info[{{$x}}][name]" placeholder="الاسم" class="form-control" required disabled>
                     </td>
                     <td>
                        <select class="form-control" name="ticket_info[{{$x}}][client_type]" required disabled>
                           <option value="1" @if($user->client_type == 1) selected @endif>رضيع</option>
                           <option value="2" @if($user->client_type == 2) selected @endif>طفل</option>
                           <option value="3" @if($user->client_type == 3) selected @endif>بالغ</option>
                        </select>
                     </td>
                     <td>
                        <input type="number" name="ticket_info[{{$x}}][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" value="{{$user->client_net_pice}}" required="" disabled />
                     </td>
                     <td>
                        <input type="text" name="ticket_info[{{$x}}][bought_price]" oninput="findTotal2()" oninput="" placeholder="سعر البيع" class="form-control amount2" value="{{$user->client_bought_price}}" required="" disabled />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_booking_id}}" name="ticket_info[{{$x}}][book_id]" placeholder="رقم الحجز" class="form-control" required="" disabled />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_ticket_id}}" name="ticket_info[{{$x}}][tikcet_id]" placeholder="رقم الحجز" class="form-control" required="" disabled />
                     </td>
                     <td>
                        <input type="text" value="{{$user->client_phone}}" name="ticket_info[{{$x}}][client_phone]" placeholder="رقم الهاتف" class="form-control" required="" disabled />
                     </td>
                     
                     
                  </tr>
                   <?php $x++; ?>
                   @endforeach
               </table>
               <br>
    <div class="row" id="refund_amounts" data-cost="{{round($total_client_net_pice, 2)}}" data-sale="{{round($total_client_bought_price, 2)}}">
      <div class="col-6 mb-3">
          <p class="mb-1"><b>مرتجع لنا من المورد</b> <span class="text-danger">*</span></p>
          <input type="number" step="0.01" min="0" name="bought_price_total" id="bought_price_total" class="form-control" style="text-align:right;" oninput="calculateRefundLoss(); checkOverRefund()">
          <small class="text-muted">المبلغ الذي يرده المورد لشركتنا — يُسجل مدين على المورد</small>
      </div>
      <div class="col-6 mb-3">
          <p class="mb-1"><b>مسترد للعميل</b> <span class="text-danger">*</span></p>
          <input type="number" step="0.01" min="0" name="net_pice_total" id="net_pice_total" class="form-control" style="text-align:right;" oninput="calculateRefundLoss(); checkOverRefund()">
          <small class="text-muted">المبلغ الذي ترده شركتنا للعميل — يُسجل دائن للعميل</small>
      </div>
    </div>
    <div id="loss_warning" class="alert alert-warning border-0" style="display:none;">
        <center>
        تنويه: سيتم تسجيل خسارة على هذه العملية بقيمة <b id="loss_amount"></b> {{$invoice_info->invoice_currency}} نتيجة أن «مسترد للعميل» أكبر من «مرتجع لنا من المورد».
        <br>
        <div class="form-check mt-2" style="display:inline-block;">
            <input class="form-check-input" type="checkbox" id="loss_confirm_checkbox" onchange="onLossConfirmChange()">
            <label class="form-check-label" for="loss_confirm_checkbox">أوافق على تسجيل هذه الخسارة</label>
        </div>
        </center>
    </div>
    <input type="hidden" name="loss_confirmed" id="loss_confirmed" value="0">
    <div id="over_refund_warning" class="alert alert-danger border-0" style="display:none;">
        <center>
        تنبيه: <span id="over_refund_text"></span>
        <br>برجاء التأكد من عدم عكس الحقلين: «مرتجع لنا من المورد» هو ما يرده المورد لنا، و«مسترد للعميل» هو ما نرده نحن للعميل.
        <br>
        <div class="form-check mt-2" style="display:inline-block;">
            <input class="form-check-input" type="checkbox" id="over_refund_checkbox" onchange="document.getElementById('over_refund_confirmed').value = this.checked ? '1' : '0'">
            <label class="form-check-label" for="over_refund_checkbox">المبالغ صحيحة، أوافق على الحفظ</label>
        </div>
        </center>
    </div>
    <input type="hidden" name="over_refund_confirmed" id="over_refund_confirmed" value="0">
                
<!--
                  <div class="row">
      <div class="col-sm">
          <h4>
          اجمالي التكلفة الحالية : {{$total_client_net_pice}} {{$invoice_info->invoice_currency}}
          </h4>
      </div>
      <div class="col-sm">
          <h4>
          اجمالي البيع الحالي : {{$total_client_bought_price}} {{$invoice_info->invoice_currency}} 
          </h4>
      </div>
    </div>
-->
                
                
               <br> 
                @if($total_client_bought_price < $total_client_net_pice)
                <div class="alert alert-danger border-0">
                <center>
                    
                    تنوية : هناك مديونية بسبب زيادة سعر التكلفة عن سعر البيع وتبلغ قيمتها {{$total_client_bought_price - $total_client_net_pice}} {{$invoice_info->invoice_currency}}  
                    </center>
                </div>
             @endif
               <br> 
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                  اضافة فاتورة  
                  </button>
                   

                   
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script>
function RemoveClient(id){ 
    
    var name = "remove" + id;
//    alert(name);
    
    const element = document.getElementById(name);
element.remove();
    
//    var ticket_id = "{{$invoice_info->es_id}}";
//      swal({
//     title: "هل انت متأكد؟",
//     text: "سيتم حذف ذلك العميل وازالة كل البيانات المرتبطه به",
//     icon: "warning",
//     buttons: true,
//     dangerMode: true,
//   })
//   .then((willDelete) => {
//     if (willDelete) {
//   //       var url = "http://teacher.cuoratech.com/aladmin_srp/sections/" + id + "/remove";
//   var url = "{{url('')}}/invoices/remove_client/" + ticket_id + "/" + id;
////                     alert(url);
//         window.location.href = url;
//         
//     } else {
//       swal("تم الغاء عملية الحذف بنجاح");
//     }
//   });

    
}
</script>
<script type="text/javascript">
    
 
     
 var i = {{count($users) - 1}};
 var i2 = 0;
   
      
   
   $("#add_create").click(function(){
   
   
   
       ++i;
   
   
   
   
       $("#dynamicTableTwo").append('<tr><td><input type="text" name="ticket_info['+i+'][name]" placeholder="الاسم" class="form-control" required="" /></td><td><select class="form-control" name="ticket_info['+i+'][client_type]" required=""><option value="1">رضيع</option><option value="2">طفل</optio><option value="3">بالغ</option></select></td><td><input type="number" name="ticket_info['+i+'][net_price]" placeholder="سعر التكلفة" class="form-control amount" oninput="findTotal()" required="" /></td>           <td><input type="text" name="ticket_info['+i+'][bought_price]" placeholder="سعر البيع" oninput="findTotal2()" class="form-control amount2" required="" /></td>           <td><input type="text" name="ticket_info['+i+'][book_id]" placeholder="رقم الحجز" class="form-control" required="" /></td><td><input type="text" name="ticket_info['+i+'][tikcet_id]" placeholder="رقم الحجز" class="form-control" required="" /></td><td><input type="text" name="ticket_info['+i+'][passport_id]" placeholder="رقم الجواز" class="form-control" required="" /></td><td><button type="button" class="btn btn-danger remove-tr">ازالة</button></td></tr>');
   
   });
   
   
   
   $(document).on('click', '.remove-tr', function(){  
   
        $(this).parents('tr').remove();
   
   });  
       
       function findTotal(){
    var arr = document.getElementsByClassName('amount');
    var tot=0;
    for(var i=0;i<arr.length;i++){
        if(parseFloat(arr[i].value))
            tot += parseFloat(arr[i].value);
    }
    document.getElementById('vendor_cost').value = tot;
    document.getElementById('vendor_cost2').value = tot;
}
    function findTotal2(){
    var arr = document.getElementsByClassName('amount2');
    var tot=0;
    for(var i=0;i<arr.length;i++){
        if(parseFloat(arr[i].value))
            tot += parseFloat(arr[i].value);
    }
        
        var val22 = document.getElementById("vendor_cost2").value;
      
        if(val22 == ""){
            
        }else{
            console.log(val22);
         document.getElementById('bought_price_total').value = tot;   
            
            if(val22 > tot){
//               aler_error
                 document.getElementById("aler_error").style.display = "block"; 
            }else{
                
            document.getElementById("aler_error").style.display = "none";     
                
            }
            
            
        }
        
    
} 
    
</script>
<script>
function calculateRefundLoss() {
    var netPiceEl = document.getElementById('net_pice_total');
    var boughtPriceEl = document.getElementById('bought_price_total');
    var warningEl = document.getElementById('loss_warning');
    var lossAmountEl = document.getElementById('loss_amount');
    var confirmedEl = document.getElementById('loss_confirmed');
    var checkboxEl = document.getElementById('loss_confirm_checkbox');

    var netPice = parseFloat(netPiceEl.value);
    var boughtPrice = parseFloat(boughtPriceEl.value);

    // Any change to either amount invalidates a previous confirmation.
    confirmedEl.value = '0';
    if (checkboxEl) {
        checkboxEl.checked = false;
    }

    if (!isNaN(netPice) && !isNaN(boughtPrice) && netPice > boughtPrice) {
        var loss = netPice - boughtPrice;
        lossAmountEl.textContent = loss.toFixed(2);
        warningEl.style.display = 'block';
    } else {
        warningEl.style.display = 'none';
    }
}

function onLossConfirmChange() {
    var checkboxEl = document.getElementById('loss_confirm_checkbox');
    var confirmedEl = document.getElementById('loss_confirmed');
    confirmedEl.value = checkboxEl.checked ? '1' : '0';
}

document.getElementById('refundForm').addEventListener('submit', function (e) {
    var netPiceEl = document.getElementById('net_pice_total');
    var boughtPriceEl = document.getElementById('bought_price_total');
    var confirmedEl = document.getElementById('loss_confirmed');
    var warningEl = document.getElementById('loss_warning');

    var netPice = parseFloat(netPiceEl.value);
    var boughtPrice = parseFloat(boughtPriceEl.value);

    if (!isNaN(netPice) && !isNaN(boughtPrice) && netPice > boughtPrice && confirmedEl.value !== '1') {
        e.preventDefault();
        warningEl.style.display = 'block';
        warningEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>



<script>
// Refund mode: full = the entered amounts are split equally between all
// passengers; single = they belong only to the passenger picked in the table.
function onRefundModeChange() {
    var single = document.getElementById('refund_mode_single').checked;
    document.querySelectorAll('.refund-pick').forEach(function (el) { el.style.display = single ? '' : 'none'; });
    var n = document.querySelectorAll('input[name="refund_passenger_id"]').length;
    var hint = document.getElementById('refund_mode_hint');
    hint.className = 'mb-0 mt-2 text-muted';
    hint.textContent = single
        ? 'اختر الراكب المسترد من الجدول. المبالغ المدخلة تخص هذا الراكب فقط ولا تؤثر على باقي الركاب.'
        : 'المبالغ المدخلة تقسم بالتساوي على ' + n + ' ركاب.';
}
document.getElementById('refundForm').addEventListener('submit', function (e) {
    if (document.getElementById('refund_mode_single').checked
        && !document.querySelector('input[name="refund_passenger_id"]:checked')) {
        e.preventDefault();
        var hint = document.getElementById('refund_mode_hint');
        hint.className = 'mb-0 mt-2 text-danger';
        hint.textContent = 'برجاء اختيار الراكب المسترد من الجدول';
        hint.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
onRefundModeChange();
</script>
<script>
// Warn when an amount is more than the original invoice holds for the refunded
// passenger(s): «مرتجع لنا من المورد» above what the supplier was paid (cost),
// or «مسترد للعميل» above what the client was charged (sale) -- usually the two
// fields were swapped.
function checkOverRefund() {
    var single = document.getElementById('refund_mode_single').checked;
    var picked = document.querySelector('input[name="refund_passenger_id"]:checked');
    var box = document.getElementById('refund_amounts');
    var src = single ? picked : box;
    var warn = document.getElementById('over_refund_warning');
    document.getElementById('over_refund_confirmed').value = '0';
    document.getElementById('over_refund_checkbox').checked = false;
    if (!src) { warn.style.display = 'none'; return; }
    var cost = parseFloat(src.dataset.cost), sale = parseFloat(src.dataset.sale);
    var ret = parseFloat(document.getElementById('bought_price_total').value);
    var cli = parseFloat(document.getElementById('net_pice_total').value);
    var msgs = [];
    if (!isNaN(ret) && ret > cost + 0.005) msgs.push('«مرتجع لنا من المورد» (' + ret.toFixed(2) + ') أكبر مما دُفع للمورد في الفاتورة الأصلية (' + cost.toFixed(2) + ')');
    if (!isNaN(cli) && cli > sale + 0.005) msgs.push('«مسترد للعميل» (' + cli.toFixed(2) + ') أكبر مما دفعه العميل في الفاتورة الأصلية (' + sale.toFixed(2) + ')');
    document.getElementById('over_refund_text').textContent = msgs.join(' ، ');
    warn.style.display = msgs.length ? 'block' : 'none';
}
document.querySelectorAll('input[name="refund_mode"], input[name="refund_passenger_id"]').forEach(function (el) {
    el.addEventListener('change', checkOverRefund);
});
document.getElementById('refundForm').addEventListener('submit', function (e) {
    var warn = document.getElementById('over_refund_warning');
    if (warn.style.display === 'block' && document.getElementById('over_refund_confirmed').value !== '1') {
        e.preventDefault();
        warn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
@endsection