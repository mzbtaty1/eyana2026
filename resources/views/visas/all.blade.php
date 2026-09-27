@extends('layouts.app')
@section('content')
@section('title' , 'التأشيرات')
@include('components.flash-messages')
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <x-page-header title="أنواع التأشيرات">
             <a href="{{route('site.visas_create')}}">
             <button class="btn btn-primary">
                 <i class="ri-file-add-line"></i>
                 اضافة تأشيرة جديدة
                 </button>
             </a>
</x-page-header>
<div class="card-body">
            <p class="text-muted fs-12 mb-3">
               أنواع التأشيرات منفصلة عن شركات الطيران. التأشيرة المفعلة تظهر في فواتير التأشيرات الجديدة؛
               التأشيرة المستخدمة في فواتير لا تُحذف ولا يتغير اسمها — يمكن تعطيلها فقط.
            </p>
            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                    <tr>
                    <th style="text-align:center;font-weight: normal; ">#</th>
               <th style="text-align:center;font-weight: normal; ">اسم التأشيرة</th>
               <th style="text-align:center;font-weight: normal; ">الحالة</th>
               <th style="text-align:center;font-weight: normal; ">الفواتير</th>
               <th style="text-align:center;font-weight: normal; ">--</th>
         </tr>
               </thead>
           <tbody>
             <?php $x = 1; ?>
            @foreach($visas as $visa)
            <?php $used = (int) ($usage[$visa->visa_name] ?? 0); ?>
            <tr data-visa="{{$visa->id}}">
               <td style="text-align:center;">{{$x}}</td>
               <td style="text-align:center;">{{$visa->visa_name}}</td>
               <td style="text-align:center;">
                  @if($visa->status == 1)
                  <span class="badge bg-success my_badge">مفعلة</span>
                  @else
                  <span class="badge bg-secondary my_badge">معطلة</span>
                  @endif
               </td>
               <td style="text-align:center;" data-order="{{$used}}">{{ number_format($used) }}</td>
               <td style="text-align:center;">
                   <a href="{{route('site.visas_edit' ,['id' => $visa->id])}}" title="تعديل">
              <button class="btn btn-soft-secondary btn-sm dropdown" type="button" style="font-size: 16px;">
                        <i class="ri-edit-2-line align-middle"></i>
                        </button>
               </a>
                  <form id="status-form-{{$visa->id}}" action="{{route('site.visas_status', $visa->id)}}" method="POST" class="d-inline">
                     @csrf
                     @if($visa->status == 1)
                     <button class="btn btn-soft-warning btn-sm" type="submit" style="font-size: 16px;" title="تعطيل"><i class="ri-forbid-line align-middle"></i></button>
                     @else
                     <button class="btn btn-soft-success btn-sm" type="submit" style="font-size: 16px;" title="تفعيل"><i class="ri-checkbox-circle-line align-middle"></i></button>
                     @endif
                  </form>
                  @if($used == 0)
                  <form id="delete-form-{{$visa->id}}" action="{{route('site.visas_delete', $visa->id)}}" method="POST" style="display:none;">
                     @csrf
                  </form>
                  <button class="btn btn-soft-danger btn-sm dropdown" type="button" style="font-size: 16px;" title="حذف" onclick="confirmDeleteForm('delete-form-{{$visa->id}}', 'هل انت متأكد؟', 'سيتم حذف هذه التأشيرة نهائياً (غير مستخدمة في أي فاتورة)')">
                        <i class="ri-delete-bin-line align-middle"></i>
                        </button>
                  @else
                  <button class="btn btn-soft-dark btn-sm" type="button" style="font-size: 16px;" disabled title="مستخدمة في فواتير: لا تُحذف، يمكن تعطيلها"><i class="ri-lock-2-line align-middle"></i></button>
                  @endif
               </td>
            </tr>
            <?php $x++; ?>
            @endforeach
      </tbody>
             </table>

          </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
