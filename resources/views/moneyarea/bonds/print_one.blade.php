@extends('layouts.print')
@section('content')
<?php
$bond = $bond_info;
                         if($bond->type == 1){
                             $type = "سند دفع";
                         }else{
                             $type = "سند قبض";
                         }
    
$title = "Print $bond->es_id";
?>
@section('title', $title)
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
  /* background-color: #04AA6D; */
  /* color: white; */
   
}
   .bg_d{
    background-color:#f4f4f4;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
   } 
   .border_c2{
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    border-style: solid;
    padding: 10px;
   }
</style>
<br>
<div class="">

    <div class="border_print">

    <x-print-header title="السندات - {{ $type }}" :heading="'مسلسل السند: '.$bond->es_id" />
        
     
        <div class="bg_d">
            <h2 class="bg_dark" style="text-align: center;   font-size: 19px;   padding: 24px;">

            {{$type}}

            </h2>

            <center>

            <div class="container row">
         
             <div class="col-sm">


            <div class="card border-0" style="  padding: 22px;">
                <br>
                <p> لصالح </p>
                @if($bond->to_type == "storage")
                         خزينة
                         
                          <?php
                         $min_info2 = App\Models\Storage::select('*')->where('id' , $bond->to_account)->get();
                         $min_info2 = $min_info2[0];
                         ?>
                         
                         @else
                         حساب
                         <?php
                         $min_info2 = App\Models\Supplier::select('*')->where('id' , $bond->to_account)->get();
                         $min_info2 = $min_info2[0];
                         ?>
                         @endif 
                          <b>{{$min_info2->name}}</b> 
                         <br>
            </div>
            

            </div>    
            <div class="col-sm">
                <img src="{{asset('assets/images/work-from-home.png')}}" style="width: 80px;   margin-top: 20px;">
            </div>
            <div class="col-sm">


            <div class="card border-0" style="  padding: 22px;">
    <br>
    <p>الدفع من</p>
    @if($bond->from_type == "storage")
             خزينة
             <?php
             $min_info = App\Models\Storage::select('*')->where('id' , $bond->from_account)->get();
             $min_info = $min_info[0];
             ?>
             @else
             حساب
             <?php
             $min_info = App\Models\Supplier::select('*')->where('id' , $bond->from_account)->get();
             $min_info = $min_info[0];
             ?>
             @endif
             <b>{{$min_info->name}}</b>
           
</div>


</div>    
            <div>
<br>
            </center>
            <hr>
            <h5 class="container" style="  text-align: right;">
    <?php
                    $mem = App\Models\User::select('*')->where('id',$bond->created_by)->get();
                    $mem = $mem[0];
                    ?>
            نفذت بواسطة : <b>{{$mem->name}}</b>

            </h5>
            <h5 class="container" style="  text-align: left;  margin-top: -21px;">

             المبلغ : <b>{{number_format($bond->amount,2)}} ج.م</b>

            </h5>
            <br>
 <h5 class="container" style="  text-align: right;">

            التاريخ : <b>{{$bond->crt_date}}</b>

            </h5>
           
            <br>
<center>
<p>
    ملاحظات
</p>
</center>
            <div class="border_c2 container">
                <center>
{{$bond->info}}
                        </center>
                        </div>
                        <br><br>
        </div>
        
        <br><br>
            
             </div>
        
    </div>


</div>

<x-print-footer :note="$type" />

@endsection
