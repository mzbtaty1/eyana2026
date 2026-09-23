@extends('layouts.app')
@section('content')
@section('title' , 'اسعار التسويق')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">
       
      <div class="card">
         <x-page-header title="قائمة اسعار التسويق">
<a href="{{route('site.marketing_prices_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة جديد
                 </button>
             </a>
    
              <a href="#print_table"  onclick="printdiv();">
             <button class="btn btn-dark">
                 <i class="ri-printer-line"></i>
                 طباعة كـ PDF
                 </button>
             </a>
</x-page-header>
<div class="card-body">
             
            <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                  @foreach($titles as $title)

                
                <?php
                $MarketingPrices0 = ($pricesByTitle[$title->id] ?? collect())->unique('travel_date');
                ?>
                
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align:right;  background-color:{{$title->bk_color}};color:black;" class="" colspan="3">{{$title->title}}
                      <img src="{{asset($title->png_icon)}}" width="100">
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
   <!--end col-->
</div>
  <script>
       let table = new DataTable('#InvoicesTable', {
           ordering: false,
    responsive: true,
           layout: {
        topStart: {
            buttons: ['colvis']
        }
    },
});
      
      function printdiv(){
   //    hidn
      
   //  document.getElementById("hidn").style.display = "none"; 
   //  document.getElementById("main_nv").style.display = "none"; 
   //  document.getElementById("footer").style.display = "none"; 
   //  document.getElementById("rs_alert").style.display = "none"; 
   //
   //    window.print();
    var url = "{{url('')}}/marketing-prices/print";
//    alert(url);
           var printWindow = window.open( url, 'Print', 'left=200, top=200, width=950, height=500, toolbar=0, resizable=0');
       printWindow.addEventListener('load', function(){
           printWindow.print();
//           printWindow.close();
       }, true);
//       
   }
      
      
        </script>  

@endsection
