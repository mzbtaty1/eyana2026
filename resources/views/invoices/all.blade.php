@extends('layouts.app')
@section('content')
@section('title' , 'الفواتير')
@include('components.flash-messages')

<style>
    .buttons-collection{
          width: 100%;
    }
        .dt-column-title{
        font-weight: normal;
  font-size: 12px;
    }
    
</style>
<x-page-header title="قائمة الفواتير">
   <a href="{{route('site.invoices_create')}}">
   <button class="btn btn-primary">
       <i class="ri-file-add-line"></i>
       اضافة فاتورة
       </button>
   </a>
</x-page-header>
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">
             <div class="table-responsive">
               <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100% !important;">
               <thead>
                  <tr>
                     <th data-ordering="false">#</th>
                     <th data-ordering="false">تاريخ الفاتورة</th>
                     <th data-ordering="false">تاريخ السفر</th>
                      <th data-ordering="false">المورد</th>
                     <th data-ordering="false">المستفيد</th>
                     
                     <th data-ordering="false">التكلفة</th>
                     <th data-ordering="false">البيع</th>
                     <th data-ordering="false">الاستلام / الوصول</th>
                     <th data-ordering="false">بيانات الراكب</th>
                     <th data-ordering="false">بيانات الحجز</th>
                     <th data-ordering="false">الربح</th>
                     <th data-ordering="false">نوع الفاتورة</th>
                     <th data-ordering="false">حالة الفاتورة</th>
                      <th data-ordering="false">موظف الفاتورة</th>
                     <th data-ordering="false">وصف الفاتورة</th>
                      @if(Auth::user()->account_type == 2) 
<!--                     <th data-ordering="false">اعتماد الفاتورة</th>-->
                      @endif
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
         <tbody> 
@foreach($invoices as $invoice)
    <?php
        if($invoice->invoice_shared == 0){
            $bg = "";
            $style = "";
        } else {
            $bg = "bg-dark";
            $style = "color:white;";
        }
        $result = substr($invoice->es_id, 0, 6);
        if($result == "FLY-RD"){
            $bg = "bg-success";
            $style = "color:white;";
        } elseif($result == "FLY-RS") {
            $bg = "bg-primary";
            $style = "color:white;";
        } else {
            $bg = "";
            $style = "";
        }
    ?>
    <tr>
        <td class="{{$bg}}" style="{{$style}}">
            <a href="{{asset($invoice->invoice_ticket_file)}}">
                {{$invoice->es_id}}
            </a>
        </td>
        <td class="{{$bg}}" style="{{$style}}">{{$invoice->invoice_date}}</td>
        <td class="{{$bg}}" style="{{$style}}">{{$invoice->invoice_travel_date}}</td>
        <td class="{{$bg}}" style="{{$style}}">
            <?php
                $vendors = $invoice->ticketVendors;
            ?>
            @foreach($vendors as $vendor)
                <?php
                    $vendor_info = $vendor->supplier;
                ?>
                {{$vendor_info->name ?? ''}} /
            @endforeach
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            <?php
                $ben_info = $invoice->beneficiaries;
            ?>
            {{$ben_info->name ?? ''}}
        </td>
        <?php
            $users = $invoice->users;
            $total_client_net_pice = 0;
            $total_client_bought_price = 0;
            foreach($users as $user){
                $total_client_net_pice += (float) $user->client_net_pice;
                $total_client_bought_price += (float) $user->client_bought_price;
            }
        ?>
        @if($result == "FLY-RD")
            <?php
                $mostarad = $invoice->mostarad;
                $mortaga = $invoice->mortaga;
            ?>
            <td class="{{$bg}}" style="{{$style}}">{{ (float)($mostarad->debit_balance ?? 0) }}</td>
            <td class="{{$bg}}" style="{{$style}}">{{ (float)($mortaga->credit_balance ?? 0) }}</td>
        @else
            <td class="{{$bg}}" style="{{$style}}">{{ $total_client_net_pice }}</td>
            <td class="{{$bg}}" style="{{$style}}">{{ $total_client_bought_price }}</td>
        @endif
        <td class="{{$bg}}" style="{{$style}}">{{$invoice->from_location}} - {{$invoice->to_location}}</td>
        <td class="{{$bg}}" style="{{$style}}">
            @foreach($users as $user) 
                {{$user->client_name}}<br>
            @endforeach
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            ارقام الحجز : 
            @foreach($users as $user) {{$user->client_booking_id}} / @endforeach
            <br>
            ارقام التذاكر : 
            @foreach($users as $user) {{$user->client_ticket_id}} / @endforeach
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            @if($result == "FLY-RD")
                {{ (float)($mostarad->debit_balance ?? 0) - (float)($mortaga->credit_balance ?? 0) }}
            @else
                {{ $total_client_bought_price - $total_client_net_pice }}
            @endif
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            <?php
                $types = [
                    1 => "فواتير الطيران",
                    2 => "فواتير تأشيرات",
                    3 => "فواتير سياحه داخليه",
                    4 => "فواتير سياحه خارجيه",
                    5 => "فواتير سياحه دينيه",
                    6 => "فواتير تأمينات السفر",
                    7 => "فواتير تحاليل السفر",
                    8 => "فواتير نقل سياحى",
                ];
                $type = $types[$invoice->invoice_section] ?? "";
            ?>
            {{$type}}
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            @if((float)$invoice->invoice_money_pay == 0) 
                <span class="badge bg-dark my_badge">لم يتم السداد</span>
            @else
                @if((float)$invoice->invoice_money_pay < (float)$total_client_bought_price)
                    <span class="badge bg-secondary my_badge">سداد جزئي</span>  
                @else
                    <span class="badge bg-success my_badge">تم السداد</span>  
                @endif                                                           
            @endif
            @if(Auth::user()->account_type == 2)
                @if($invoice->invoice_status == 0)
                    <button class="btn btn-primary" id="rvd_{{$invoice->id}}" style="border-radius: 55px;font-size: 10px;" onclick="do_approved({{$invoice->id}})">
                        تأكيد العملية
                    </button>
                    <button class="btn btn-success" id="apprvd_{{$invoice->id}}" style="border-radius: 55px;font-size: 10px;display:none;">
                        تم التأكيد
                    </button>      
                @else
                    <button class="btn btn-success" style="border-radius: 55px;font-size: 10px;">
                        تم التأكيد
                    </button>      
                @endif
            @endif
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            <?php
                $mem_info = $invoice->creator;
            ?>
            {{$mem_info->name ?? ''}}
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            حجز الرحلة {{$invoice->es_id}}<br>
            اسماء : @foreach($users as $user) {{$user->client_name}} / @endforeach<br>
            ارقام الحجز : @foreach($users as $user) {{$user->client_booking_id}} / @endforeach<br>
            ارقام التذاكر : @foreach($users as $user) {{$user->client_ticket_id}} / @endforeach<br>
            ارقام الجواز : @foreach($users as $user) {{$user->client_passport_id}} / @endforeach
        </td>
        <td class="{{$bg}}" style="{{$style}}">
            <button class="btn btn-soft-secondary btn-sm dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri-more-fill align-middle"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a href="{{route('site.invoices_show' , $invoice->id)}}" class="dropdown-item"><i class="ri-eye-fill align-bottom me-2 text-muted"></i> تفاصيل</a></li>
                @if(Auth::user()->account_type == 2 || $invoice->invoice_status == 0)
                    <li><a href="{{route('site.invoices_edit' , $invoice->id)}}" class="dropdown-item"><i class="ri-edit-box-line"></i> تعديل</a></li>
                @endif
                @if((float)$invoice->invoice_money_pay < (float)$total_client_bought_price)
                    <li><a href="{{route('site.invoices_pay_part' , $invoice->id)}}" class="dropdown-item"><i class="ri-wallet-3-line"></i> سداد الفاتورة</a></li>
                @endif
                <li><a href="{{route('site.invoices_reissue_create' , $invoice->es_id)}}" class="dropdown-item"><i class="ri-arrow-go-forward-line"></i> اعادة اصدار</a></li>
                <li><a href="{{route('site.invoices_refund' , $invoice->es_id)}}" class="dropdown-item"><i class="ri-refund-2-line"></i> الغاء الفاتورة</a></li>
                <li><a href="{{route('site.invoices_remove' , $invoice->es_id)}}" class="dropdown-item"><i class="ri-delete-bin-line"></i> حذف الفاتورة</a></li>
            </ul>
        </td>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <script>
       let table = new DataTable('#InvoicesTable', {
           ordering: false,
// "pagingType": "full_numbers",
//   "paging": true,
//   "lengthMenu": [10, 25, 50, 75, 100],
           
           columnDefs: [
//        {
//            target: 11,
//            visible: false,
//        },
//               {
//            target: 12,
//            visible: false,
//        },
//
//        {
//            target: 13,
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
<script>
         function do_approved(id){
//          alert(id);
          
   var url = "{{url('')}}/invoices/" + id + "/approve";
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
