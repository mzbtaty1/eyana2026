@extends('layouts.app')
@section('content')
@section('title' , 'شرائح العمولات')
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
         <x-page-header title="جداول شرائح العمولات">
            <a href="{{route('site.commission_tiers_create')}}" class="btn btn-primary">
               <i class="ri-file-add-line"></i>
               اضافة جدول شرائح
            </a>
         </x-page-header>
         <div class="card-body">
            <p class="text-muted fs-12 mb-3">
               جداول شرائح العمولات يديرها المسئول فقط، ويمكن لاحقاً ربط الجدول بأكثر من موظف.
               تُحدد الشريحة من <b>إجمالي ربح الموظف خلال الفترة المختارة</b> وليس من كل فاتورة.
               الجدول المستخدم لموظفين لا يُحذف — يمكن تعطيله فقط.
            </p>
            <table class="table table-bordered table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th style="text-align:center;font-weight: normal;">#</th>
                     <th style="text-align:center;font-weight: normal;">اسم الجدول</th>
                     <th style="text-align:center;font-weight: normal;">الشرائح</th>
                     <th style="text-align:center;font-weight: normal;">الحالة</th>
                     <th style="text-align:center;font-weight: normal;">آخر تعديل</th>
                     <th style="text-align:center;font-weight: normal;">--</th>
                  </tr>
               </thead>
               <tbody>
                  @forelse($tables as $i => $table)
                  @php($used = (int) ($usage[$table->id] ?? 0))
                  <tr data-table="{{$table->id}}">
                     <td style="text-align:center;">{{ $i + 1 }}</td>
                     <td style="text-align:center;"><a href="{{route('site.commission_tiers_show', $table->id)}}">{{ $table->name }}</a></td>
                     <td style="text-align:center;">{{ $table->tiers_count }}</td>
                     <td style="text-align:center;">
                        @if($table->isActive())
                        <span class="badge bg-success my_badge">مفعل</span>
                        @else
                        <span class="badge bg-secondary my_badge">معطل</span>
                        @endif
                     </td>
                     <td style="text-align:center;">{{ optional($table->updated_at)->format('Y-m-d H:i') }}</td>
                     <td style="text-align:center;">
                        <a href="{{route('site.commission_tiers_show', $table->id)}}" class="btn btn-soft-info btn-sm" style="font-size: 16px;" title="عرض"><i class="ri-eye-line align-middle"></i></a>
                        <a href="{{route('site.commission_tiers_edit', $table->id)}}" class="btn btn-soft-secondary btn-sm" style="font-size: 16px;" title="تعديل"><i class="ri-edit-2-line align-middle"></i></a>
                        <form action="{{route('site.commission_tiers_status', $table->id)}}" method="POST" class="d-inline">
                           @csrf
                           @if($table->isActive())
                           <button class="btn btn-soft-warning btn-sm" type="submit" style="font-size: 16px;" title="تعطيل"><i class="ri-forbid-line align-middle"></i></button>
                           @else
                           <button class="btn btn-soft-success btn-sm" type="submit" style="font-size: 16px;" title="تفعيل"><i class="ri-checkbox-circle-line align-middle"></i></button>
                           @endif
                        </form>
                        @if($used == 0)
                        <form id="delete-tier-table-{{$table->id}}" action="{{route('site.commission_tiers_delete', $table->id)}}" method="POST" style="display:none;">
                           @csrf
                        </form>
                        <button class="btn btn-soft-danger btn-sm" type="button" style="font-size: 16px;" title="حذف" onclick="confirmDeleteForm('delete-tier-table-{{$table->id}}', 'هل انت متأكد؟', 'سيتم حذف جدول الشرائح نهائياً (غير مستخدم لأي موظف)')"><i class="ri-delete-bin-line align-middle"></i></button>
                        @else
                        <button class="btn btn-soft-dark btn-sm" type="button" style="font-size: 16px;" disabled title="مستخدم لموظفين: لا يُحذف، يمكن تعطيله"><i class="ri-lock-2-line align-middle"></i></button>
                        @endif
                     </td>
                  </tr>
                  @empty
                  <tr><td colspan="6" style="text-align:center;">لا توجد جداول شرائح بعد</td></tr>
                  @endforelse
               </tbody>
            </table>
         </div>
      </div>
   </div>
</div>
@endsection
