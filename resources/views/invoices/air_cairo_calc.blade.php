@extends('layouts.app')
@section('content')
@section('title' , "تكلفة اير كايرو")


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
            <h5 class="card-title mb-0"> تكلفة اير كايرو </h5>
         </div>
         <div class="card-body">
 <form name="myForm" action="#" onsubmit="return validateForm()" method="post">
           <div class="row">
      <div class="col-6 mb-3">
          <p>
          اجمالي التذكرة
          </p>
          <input type="number" id="ticket_cost" class="form-control" style="text-align:right;">
      </div>
      <div class="col-6">
          <p>
          الفير بيس
          </p>
            <input type="text" id="firbase" class="form-control" style="text-align:right;">
      </div>
    </div>
          
             <p>
          نسبة الخصم
          </p>
            <input type="text" id="dis_num" class="form-control" style="text-align:right;">     
            <br>
     <center><h2 style="display:none;" id='msg_id_all'>سعر الاجمالي هو : <tag id="msg_id" style="color:green;"></tag></h2></center>
            <br>
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                  حساب
                  </button>
                   

                   
               </div>
             </form>
            
         </div>
      </div>
   </div>
   <!--end col-->
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>

 <script src="{{asset('assets/dselect.js')}}"></script>
 

<script type="text/javascript">
    
    
 function validateForm() {
  let ticket_cost = document.forms["myForm"]["ticket_cost"].value;
  if (ticket_cost == "") {
    swal("","برجاء ادخال اجمالي سعر التذكرة" , "error");
    return false;
  }
     
     
      let firbase = document.forms["myForm"]["firbase"].value;
  if (firbase == "") {
    swal("","برجاء ادخال الفير بيس" , "error");
    return false;
  }
     
     
      let dis_num = document.forms["myForm"]["dis_num"].value;
  if (dis_num == "") {
    swal("","برجاء ادخال نسبة الخصم" , "error");
    return false;
  }
     var coNum = dis_num / 100;
     
     var totalValue = firbase - (firbase * coNum);   
     var totalValue = firbase - totalValue;
     
     
     var total_print = ticket_cost - totalValue;
      document.getElementById("msg_id_all").style.display = "block"; 
      document.getElementById("msg_id").innerHTML = total_print + " ج.م ";
     
//     alert(total_print);
//     var total1 = 
     
    return false;   
} 
</script>


@endsection
