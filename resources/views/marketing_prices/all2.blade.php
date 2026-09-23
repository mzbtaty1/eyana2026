@extends('layouts.app')
@section('content')
@section('title' , 'اسعار التسويق')
<?php
use Carbon\Carbon;
?>
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
</x-page-header>
<div class="card-body">
             
            <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                                      @foreach($titles as $title)

                
                <?php
                $MarketingPrices0 = $pricesByTitle[$title->id] ?? collect();
                ?>
                
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align:right;  background-color:{{$title->bk_color}};color:white;" class="" colspan="10">{{$title->title}}
                      <img src="{{asset($title->png_icon)}}" width="100">
                      </th>
                  </tr>
                   <tr>
                     <th data-ordering="false" style="text-align:right;">تاريخ السفر</th>
                     <th data-ordering="false" style="text-align:right;">موعد الاقلاع</th>
                     <th data-ordering="false" style="text-align:right;">السعر</th>
                     <th data-ordering="false" style="text-align:right;">التكلفة</th>
                     <th data-ordering="false" style="text-align:right;">رقم الحجز</th>
                     <th data-ordering="false" style="text-align:right;">رقم الشاشة</th>
                     <th data-ordering="false" style="text-align:right;">الموظف</th>
                       <th data-ordering="false" style="text-align:right;">الامكان المتاحة</th>
                     <th data-ordering="false" style="text-align:right;">وقت انتهاء الهولد</th>
                     
                     <th data-ordering="false" style="text-align:right;">--</th>
                  </tr>
               </thead>
               <tbody>
                    @foreach($MarketingPrices0 as $MarketingPrice)
                  <tr>
                     <td style="text-align:right;">{{$MarketingPrice->travel_date}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->time_departure}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->total_price}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->cost_price}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->booking_id}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->screen_id}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->added_by}}</td>
                     <td style="text-align:right;">{{$MarketingPrice->places_available}}</td>
                     <td style="text-align:right;">
                         <?php
                         $now_date = date('Y-m-d H:i') . ":00";
                         
$dateTimestamp1 = ($now_date); 
//                         dd($dateTimestamp1);
$dateTimestamp2 = ($MarketingPrice->hold_finish_time); 
                         
                         ?>
                         {{$MarketingPrice->hold_finish_time}}
@if($dateTimestamp1 > $dateTimestamp2) 
                         <br>
                           <span class="badge bg-danger my_badge">تم الانتهاء</span>
    
@else
    <br>
   
      
                         
             
      @endif       
             <?php
                         $date = Carbon::parse($MarketingPrice->hold_finish_time);
$now = Carbon::parse($now_date)->addMinutes(30);

$diff = $date->diffInSeconds($now);
   $diff = $diff / 60;                      
                         ?>
        @if($diff <= 30)
                 <span class="badge bg-dark my_badge">اوشك علي الانتهاء</span>        
                        @endif
                      </td>
                      <td>
                         <a href="{{route('site.marketing_prices_show' , $MarketingPrice->id)}}">
                        <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
                        </a> 
                          <form id="delete-form-{{$MarketingPrice->id}}" action="{{route('site.marketing_prices_delete', $MarketingPrice->id)}}" method="POST" style="display:none;">
                             @csrf
                          </form>
                        <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" onclick="confirmDeleteForm('delete-form-{{$MarketingPrice->id}}', 'هل انت متأكد؟', 'سيتم حذف قائمة الاسعار وازالة كل البيانات المرتبطه به')">
                        <i class="ri-delete-bin-line align-middle"></i>
                        </button>
                      </td>
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
        </script>  

@endsection
