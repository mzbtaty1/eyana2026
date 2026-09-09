@extends('layouts.print')
@section('content')

<?php
$title = "كشف حساب موردين " . \Str::random(8);
?>
@section('title' , $title)
<style>
    body{
         print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    }
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
     print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
    .cls{
         print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    }
</style>
<br>
<div class="">

<!--    <button class="btn btn-primary" style="width:100%;" id="printBtn" onclick="do_print()">اضغط هنا للطباعة</button>-->
    <div class="border_print">
    
    <img src="{{asset('assets/images/flymix_colored.png')}}"  style="  width: 154px;">
        <h5 style="  float: right;  text-align: right;  line-height: 31px;">
        كشف حساب موردين
        
            
        </h5>
       
        <hr>
        
        <div class="t">
              <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%" dir="rtl">
                    
                  
                    
               <thead>

                     <tr>
                     <th data-ordering="false" style="text-align: right;">#</th>
                     <th data-ordering="false" style="text-align: right;">اسم الحساب</th>
                   
                     <th data-ordering="false" style="text-align: right;color:white;" class="cls bg-danger">دائن</th>
                     <th data-ordering="false" style="text-align: right;color:white;" class="cls bg-danger">مدين</th>
                        <th data-ordering="false" style="text-align: right;color:white;" class="cls bg-danger">رصيد</th>

                  </tr>
               </thead>
                <tbody>
                   <?php
                   
                   $x = 1;
                   ?>
                    @foreach($suppliers as $supplier)
                    
                     <?php
                          $trsnactions = App\Models\AccountStatement::select('*')
                          ->where('supp_client_id' , $supplier->id)
                          ->where('is_storage', '!=' , 1);
                          
                      
                    if($sts == 0){
                     
                    }else{
                           $trsnactions = $trsnactions->whereBetween('crt_date' , [$date_from , $date_to]);
                    }  
                   
                      $trsnactions = $trsnactions->get();
                      $total_credit = 0;
                      $total_debit = 0;
                      
                    foreach($trsnactions as $trsnaction){
                        $total_credit += $trsnaction->credit_balance;
                        $total_debit += $trsnaction->debit_balance;
                    }  
                      
                      ?>
                    
                                @if($total_debit - $total_credit == 0)
                   @else
                  <tr>
                      <td style="text-align:right;">{{$x}}</td>
                   <td style="text-align:right;">{{$supplier->name}}</td>
                      
                     
                   
                     <td style="text-align:right;">{{number_format($total_credit , 2)}}</td>
                   <td style="text-align:right;">{{number_format($total_debit , 2)}}</td>
                   <td style="text-align:right;">{{number_format($total_debit - $total_credit , 2)}}
                      
                  
                      </td>
                   </tr>
                @endif
                  
                <?php $x++; ?>
                   @endforeach
                   
        <tr>
    <td style="text-align:right;">الاجمالي</td>
    <td style="text-align:right;">--</td>
    <td style="text-align:right;" id="subtotal"></td>
    <td style="text-align:right;" id="total"></td>
    <td style="text-align:right;color:white;" class="bg-primary" id="total0">--</td>
<!--    <td style="text-align:right;color:white;">--</td>-->
  </tr>    
                    

                    
                   
               </tbody>
            </table>
            
             </div>
        
    </div>


</div>
      <script src="https://cuoratech.com/assets/jquery-3.7.0.slim.min.js?v=6655"></script> 
  <script>
        window.onload = setTimeout(function(){
    
window.print();
}, 1000);
      
      
      
     
      $(function() {
  $("#subtotal").html(sumColumn(3));
  $("#total").html(sumColumn(4));
          
          $("#total0").html(calc_get());
  
          
          
});

      function calc_get(){
          
          var val1 = parseInt($("#subtotal").text().replace(',',''));
          var val2 = parseInt($("#total").text().replace(',',''));
          var val1_total = val1 + val2;
          
          const formatter = new Intl.NumberFormat('en');
    var val1_totaltrurn = formatter.format(val1_total);
          
           $('#total0').html(val1_totaltrurn);
      }
      
function sumColumn(index) {
  var total = 0;
  $("td:nth-child(" + index + ")").each(function() {
//       let total = total.replace(",", ""); 
      
     total += parseInt($(this).text().replace(',',''), 10) || 0;
    
  });  
    const formatter = new Intl.NumberFormat('en');
    return formatter.format(total);
//  return total;
}
      
      function do_print(){
           document.getElementById("printBtn").style.display = "none";
          window.print();
      }
      
</script>

@endsection
