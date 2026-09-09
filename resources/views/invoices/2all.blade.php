@extends('layouts.app')
@section('content')
@section('title' , 'الفواتير')
    @if($errors->any())
<div class="alert alert-info"><i class="ri-file-info-line"></i> {{$errors->first()}}</div>
@endif 

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
            <h5 class="card-title mb-0">قائمة الفواتير</h5>
             <a href="{{route('site.invoices_create')}}">
             <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                 <i class="ri-file-add-line"></i>
                 اضافة فاتورة
                 </button>
             </a>
         </div>
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
                      @if(Auth::user()->account_type == 2) 
<!--                     <th data-ordering="false">اعتماد الفاتورة</th>-->
                      @endif
                     <th data-ordering="false">--</th>
                  </tr>
               </thead>
      
                   <tbody> 
                       
                       
                       
                   </tbody>
                   
                   
    </table>
    
             </div>
          
         </div>
      </div>
 
</div>
   <!--end col-->
</div>
<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>-->


@section('myScripts')

      
<script>
       let table = new DataTable('#InvoicesTable', {
       processing: true,
        serverSide: true, // تفعيل server-side pagination
        ajax: "{{route('site.invoices_ajax')}}", // الرابط الذي يرجع البيانات
        columns: [
//            { data: 'es_id', name: 'id' },
            
            { data: 'es_id', name: 'id', 
            render: function(data, type, row) {
                
//                var varHTML = "";
                
         
//         if(row.invoice_status == 0){
//             var varHTML = '<button class="btn btn-primary" style="border-radius: 55px;font-size: 10px;" onclick="do_approved(' + row.id + ')" id="rvd_' + row.id + '">تأكيد العملية</button><button class="btn btn-success" style="border-radius: 55px;font-size: 10px;display:none;" id="apprvd_' + row.id + '">تم التأكيد</button>';
//         }else{
//             var varHTML = '<button class="btn btn-success" style="border-radius: 55px;font-size: 10px;" id="apprvd_' + row.id + '">تم التأكيد</button>';
//
//         }
                
         

         
         var varHTML = '<a href="{{asset('')}}/' + row.invoice_ticket_file + '" target="_blank">' + data + '</a>';
                
return varHTML;
                
            }
        },
            
            
            
            
            { data: 'invoice_date', name: 'invoice_date' },
            { data: 'invoice_travel_date', name: 'invoice_travel_date' },
            { data: 'vendor_name', name: 'vendor_name' },
            { data: 'beneficiaries', name: 'beneficiaries' },
            { data: 'cost', name: 'cost' },
            { data: 'bee3', name: 'bee3' },
            { data: 'locations', name: 'locations' },
        { data: 'users.names', name: 'users.names' }, // تأكد من هيكل البيانات
        {
            data: null,
            render: function(data, type, row) {
                // تحقق من الهيكل الصحيح للبيانات
                console.log(row.users); // تأكد من وجود 'booking_ids' و 'ticket_ids'
                let bookingIds = Array.isArray(row.users.booking_ids) ? row.users.booking_ids.join(', ') : row.users.booking_ids;
                let ticketIds = Array.isArray(row.users.ticket_ids) ? row.users.ticket_ids.join(', ') : row.users.ticket_ids;

                return bookingIds + ' | ' + ticketIds; // دمج البيانات
            },
            name: 'users.booking_ids_and_tkids' // اسم مستعار للعمود
        },
        { data: 'sucPay', name: 'sucPay' },
        { data: 'type', name: 'type' }, 
        { data: 'payStatus', name: 'payStatus', 
            render: function(data, type, row) {
                
                var varHTML = "";
                
         @if(Auth::user()->account_type == 2)
         
         if(row.invoice_status == 0){
             var varHTML = '<button class="btn btn-primary" style="border-radius: 55px;font-size: 10px;" onclick="do_approved(' + row.id + ')" id="rvd_' + row.id + '">تأكيد العملية</button><button class="btn btn-success" style="border-radius: 55px;font-size: 10px;display:none;" id="apprvd_' + row.id + '">تم التأكيد</button>';
         }else{
             var varHTML = '<button class="btn btn-success" style="border-radius: 55px;font-size: 10px;" id="apprvd_' + row.id + '">تم التأكيد</button>';

         }
                
         

         
         @endif
                
return data + " " + varHTML;
                
            }
        },
            { data: 'emponame', name: 'emponame' },
            
            
            @if(Auth::user()->account_type == 2) 
            
            
                    { 
            render: function(data, type, row) {
                
                var varHTML = "";
                
         @if(Auth::user()->account_type == 2)
         
var varHTML = '<a href="{{url('')}}/invoices-info/' + row.id + '"><button class="btn btn-dark" style="border-radius: 55px;font-size: 10px;">ادارة الفاتورة</button></a>';

                
         

         
         @endif
                
return varHTML;
                
            }
        },
            @endif
//             {
//            data: 'booking_tkids',  // يجب أن تكون القيمة في الجهة الخلفية من النوع المناسب (مصفوفة أو سلسلة مفصولة)
//            render: function(data, type, row) {
//                // إذا كانت البيانات في الجهة الخلفية مصفوفة، نقوم بتحويلها إلى سلسلة مفصولة
//                if (Array.isArray(data)) {
//                    return data.join(', ');
//                }
//                return data;
//            },
//            name: 'booking_tkids'
//        },
            
        ],
});
    
    
</script>

@endsection



<script>
function Removecustomer(id){
    
      swal({
     title: "هل انت متأكد؟",
     text: "سيتم حذف ذلك الفاتورة وازالة كل البيانات المرتبطه بها",
     icon: "warning",
     buttons: true,
     dangerMode: true,
   })
   .then((willDelete) => {
     if (willDelete) {
   //       var url = "http://teacher.cuoratech.com/aladmin_srp/sections/" + id + "/remove";
   var url = "{{url('')}}/customers/" + id + "/delete";
//                     alert(url);
         window.location.href = url;
         
     } else {
       swal("تم الغاء عملية الحذف بنجاح");
     }
   });
    
}
    
    
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
