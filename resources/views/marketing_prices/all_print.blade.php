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
     print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
    
</style>
<br>
<div class="">

    <div class="border_print">
    
    <x-print-header title="قائمة تسويق الشركات" />

        <div class="t">
              <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                  @foreach($titles as $title)

                
                <?php
                $MarketingPrices0 = ($pricesByTitle[$title->id] ?? collect())->unique('travel_date');
                ?>
                
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align:right;  background-color:{{$title->bk_color}};color:black;print-color-adjust: exact;     -webkit-print-color-adjust: exact;" class="" colspan="3">{{$title->title}}
                      <img src="{{asset($title->png_icon)}}" style="  float: left;" width="100">
                      </th>
                  </tr>
                   <tr>
                     <th data-ordering="false" style="text-align:right;">تاريخ السفر</th>
                     <th data-ordering="false" style="text-align:right;">موعد الاقلاع</th>
                     <th data-ordering="false" style="text-align:right;">السعر</th>
                  </tr>
               </thead>
               <tbody>
                   @foreach($MarketingPrices0 as $MarketingPrice)
                  <tr>
                     <td style="text-align:right;">{{$MarketingPrice->travel_date}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->time_departure}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->total_price}}</td>
                  </tr>
                   @endforeach
                  @endforeach
               </tbody>
            </table>
            
             </div>
        
    </div>


</div>



@endsection
