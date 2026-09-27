@extends('layouts.app')
@section('content')
@section('title' , 'جدول شرائح العمولات')
@include('components.flash-messages')
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
   <i class="ri-checkbox-circle-line me-2"></i>{{ session('success') }}
   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
@php($fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.'))
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="جدول الشرائح: {{ $table->name }}">
            <a href="{{route('site.commission_tiers_edit', $table->id)}}" class="btn btn-primary"><i class="ri-edit-2-line"></i> تعديل</a>
            <a href="{{route('site.commission_tiers')}}" class="btn btn-light">جداول الشرائح</a>
         </x-page-header>
         <div class="card-body">
            <p class="mb-2">
               الحالة:
               @if($table->isActive())
               <span class="badge bg-success my_badge">مفعل</span>
               @else
               <span class="badge bg-secondary my_badge">معطل</span>
               @endif
               <span class="text-muted ms-3">عدد الموظفين المستخدمين للجدول: {{ number_format($used) }}</span>
            </p>
            <table class="table table-bordered align-middle" style="max-width: 720px;">
               <thead>
                  <tr>
                     <th style="text-align:center;font-weight: normal;">الشريحة</th>
                     <th style="text-align:center;font-weight: normal;">من (إجمالي الربح)</th>
                     <th style="text-align:center;font-weight: normal;">إلى (إجمالي الربح)</th>
                     <th style="text-align:center;font-weight: normal;">نسبة العمولة</th>
                  </tr>
               </thead>
               <tbody>
                  @foreach($table->tiers as $i => $tier)
                  <tr>
                     <td style="text-align:center;">{{ $i + 1 }}</td>
                     <td style="text-align:center;">{{ $fmt($tier->from_amount) }}</td>
                     <td style="text-align:center;">{{ $tier->to_amount === null ? 'بدون حد أعلى' : $fmt($tier->to_amount) }}</td>
                     <td style="text-align:center;">{{ $fmt($tier->rate) }}%</td>
                  </tr>
                  @endforeach
               </tbody>
            </table>
            <p class="text-muted fs-12 mb-0">
               تُحدد شريحة واحدة من إجمالي ربح الموظف خلال الفترة المختارة (وليس من كل فاتورة)، وتُطبق نسبتها على إجمالي الربح.
            </p>
         </div>
      </div>
   </div>
</div>
@endsection
