@extends('layouts.app')
@section('content')
@section('title' , "تذاكر وارباح الموظفين")
<div class="row">
   <div class="col-lg-12">
  
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> بحث تذاكر وارباح الموظفين </h5>
         </div>
         <div class="card-body">
            <form action="{{route('site.employee_log_view')}}" method="POST" autocomplete="off">
               @csrf
         
               
               <center>
                   @if(Auth::user()->account_type == 2)
                   <p>
                   الموظف 
                   </p>
                   <select class="form-select" name="user_id">
                      <option value="">الكل</option>
                       <?php
                       
                       $users = App\Models\User::select('*')->get();
                       ?>
                   @foreach($users as $user)
                       <option value="{{$user->id}}">{{$user->name}}</option>
                       @endforeach
                   </select>
                   <br>
                   @else
                   <input type="hidden" name="user_id" value="{{Auth::user()->id}}">
                   @endif
                 <p>
                لفرز حساب تاريخ الي تاريخ يرجي ادخال تاريخين وليس تاريخ واحد
                </p>
                </center>
                <div class="row">
               <div class="col-6 mb-3">
                  <p>
                     فرز تاريخ من
                  </p>
                  <input type="date" name="date_from" class="form-control" value="">
               </div>
               <div class="col-6">
                  <p>
                     فرز تاريخ الي
                  </p>
                  <input type="date" name="date_to" class="form-control" value="">
               </div>
            </div>
                
                
                <p>
           نوع الفواتير    
                </p>
                <select class="form-select" name="invoice_section">
                
                                      <option value="">الكل</option>
                                      <option value="1">فواتير الطيران</option>
                  <option value="2">فواتير تأشيرات</option>
                  <option value="3">فواتير سياحه داخليه</option>
                  <option value="4">فواتير سياحه خارجيه</option>
                  <option value="5">فواتير سياحه دينيه</option>
                  <option value="6">فواتير تأمينات السفر</option>
                  <option value="7">فواتير تحاليل السفر</option>
                  <option value="8">فواتير نقل سياحى</option>
                </select>
                                <br>
 <p>
           خط الطيران 
                </p>
                <select class="form-select" name="airline_id">
                <?php
                    $airlines = App\Models\Airline::select('*')->get();
                    ?>
                                      <option value="">الكل</option>
                        @foreach($airlines as $airline)
                    
                    <option value="{{$airline->airline_name}}">{{$airline->airline_name}}</option>
                    @endforeach
                </select>
                                <br>

                <p>
                تاريخ السفر
                </p>
                
                <input type="date" name="travel_date" class="form-control">
                <br>
                <div class="row">
                  <div class="col-6 mb-3">
                     <p>
                        المورد  
                     </p>
 <select class="form-select" name="vendor_id">
                <?php
                    $suppliers = App\Models\Supplier::select('*')->get();
//     dd($suppliers);
                    ?>
                                      <option value="">الكل</option>
                        @foreach($suppliers as $supplier)
                    
                    <option value="{{$supplier->id}}">{{$supplier->name}}</option>
                    @endforeach
                </select>
                    </div>
                  <div class="col-6">
                     <p>
                        المستفيد
                     </p>
 <select class="form-select" name="invoice_beneficiaries">
                <?php
                    $suppliers = App\Models\Supplier::select('*')->get();
//     dd($suppliers);
                    ?>
                                      <option value="">الكل</option>
                        @foreach($suppliers as $supplier)
                    
                    <option value="{{$supplier->id}}">{{$supplier->name}}</option>
                    @endforeach
                </select>
                    </div>
               </div>
                
                <br>
                
               <div class="d-grid gap-2">
                  <button class="btn btn-primary">
                  <i class="ri-save-line"></i>
                   بحث تقرير الفواتير  
                  </button>
               </div>
            </form>
         </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
