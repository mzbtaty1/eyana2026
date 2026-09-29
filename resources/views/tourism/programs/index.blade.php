@extends('layouts.app')
@section('content')
@section('title' , 'البرامج السياحية')
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header title="البرامج السياحية">
            <div class="ey-action-group">
               <a href="{{route('site.tourism_programs_create')}}" class="btn btn-primary"><i class="ri-add-line"></i><span>برنامج جديد</span></a>
            </div>
         </x-page-header>
         <div class="card-body">
            @include('components.flash-messages')
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
            <p class="text-muted small">البرنامج قالب: عند إنشاء حجز منه تنسخ خدماته إلى الحجز، وأي تعديل لاحق على البرنامج لا يغير الحجوزات السابقة. البرامج توقف ولا تحذف.</p>
            <div class="table-responsive">
               <table class="table table-bordered table-striped align-middle text-nowrap">
                  <thead class="table-light"><tr><th>البرنامج</th><th>الوجهة</th><th>المدة</th><th>الخدمات</th><th>الحجوزات</th><th>الحالة</th><th>--</th></tr></thead>
                  <tbody>
                     @forelse($programs as $p)
                     <tr>
                        <td><strong>{{$p->name}}</strong></td>
                        <td>{{$p->destination}}</td>
                        <td>@if($p->days){{$p->days}} أيام / {{$p->nights}} ليالي @endif</td>
                        <td>{{$p->items_count}}</td>
                        <td>{{$p->bookings_count}}</td>
                        <td><span class="badge {{$p->isActive() ? 'bg-success' : 'bg-secondary'}} my_badge">{{$p->isActive() ? 'فعال' : 'موقوف'}}</span></td>
                        <td>
                           <div class="ey-row-actions">
                              <a href="{{route('site.tourism_programs_edit', $p->id)}}" class="btn btn-soft-primary btn-sm"><i class="ri-edit-line"></i><span>تعديل</span></a>
                              <form method="POST" action="{{route('site.tourism_programs_status', $p->id)}}">@csrf
                                 <button class="btn btn-soft-{{$p->isActive() ? 'secondary' : 'success'}} btn-sm"><i class="{{$p->isActive() ? 'ri-pause-circle-line' : 'ri-play-circle-line'}}"></i><span>{{$p->isActive() ? 'إيقاف' : 'تفعيل'}}</span></button>
                              </form>
                              @if($p->isActive())
                              @can('tourism.create')
                              <a href="{{route('site.tourism_bookings_create', ['program_id' => $p->id])}}" class="btn btn-soft-success btn-sm"><i class="ri-add-line"></i><span>حجز منه</span></a>
                              @endcan
                              @endif
                           </div>
                        </td>
                     </tr>
                     @empty
                     <tr><td colspan="7" class="text-center">لا توجد برامج</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
@endsection
