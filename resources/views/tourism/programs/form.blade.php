@extends('layouts.app')
@section('content')
@section('title' , $program->exists ? 'تعديل برنامج' : 'برنامج سياحي جديد')
@php($v = fn ($k) => old($k, $program->{$k}))
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header :title="$program->exists ? 'تعديل برنامج: ' . $program->name : 'برنامج سياحي جديد'">
            <div class="ey-action-group">
               <a href="{{route('site.tourism_programs')}}" class="btn btn-light"><i class="ri-arrow-right-line"></i><span>رجوع</span></a>
            </div>
         </x-page-header>
         <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{$e}}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ $program->exists ? route('site.tourism_programs_update', $program->id) : route('site.tourism_programs_store') }}" autocomplete="off">
               @csrf
               <div class="row">
                  <div class="col-md-4 mb-3"><label class="form-label">اسم البرنامج <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" value="{{$v('name')}}" placeholder="مثال: الغردقة 4 أيام / 3 ليالي" required></div>
                  <div class="col-md-4 mb-3"><label class="form-label">الوجهة</label><input type="text" class="form-control" name="destination" value="{{$v('destination')}}"></div>
                  <div class="col-md-2 mb-3"><label class="form-label">أيام</label><input type="number" min="1" class="form-control" name="days" value="{{$v('days')}}"></div>
                  <div class="col-md-2 mb-3"><label class="form-label">ليالي</label><input type="number" min="0" class="form-control" name="nights" value="{{$v('nights')}}"></div>
                  <div class="col-12 mb-3"><label class="form-label">الوصف</label><textarea class="form-control" name="description" rows="2">{{$v('description')}}</textarea></div>
               </div>

               <h6>خدمات البرنامج <small class="text-muted">(الفندق: عدد الليالي والغرف، والسعر للغرفة في الليلة. غيره: «للفرد» = الكمية بعدد أفراد الحجز)</small></h6>
               <div class="table-responsive">
                  <table class="table table-bordered align-middle" id="tp-items">
                     <thead class="table-light text-nowrap"><tr><th>النوع</th><th>الوصف</th><th>المورد الافتراضي</th><th>اليوم</th><th>ليالي</th><th>غرف</th><th>نوع الغرفة</th><th>التسعير</th><th>الكمية</th><th>تكلفة الوحدة</th><th>بيع الوحدة</th><th></th></tr></thead>
                     <tbody>
                        @foreach(array_values($items) as $i => $it)
                           @include('tourism.programs._item_row', ['i' => $i, 'it' => $it])
                        @endforeach
                     </tbody>
                  </table>
               </div>
               <div class="ey-action-group mb-3">
                  <button type="button" class="btn btn-soft-success" id="tp-add"><i class="ri-add-line"></i><span>إضافة خدمة</span></button>
               </div>
               <div class="d-grid"><button class="btn btn-primary"><i class="ri-save-line"></i><span>حفظ البرنامج</span></button></div>
            </form>
         </div>
      </div>
   </div>
</div>
<template id="tp-tpl">@include('tourism.programs._item_row', ['i' => '__I__', 'it' => []])</template>
<script src="{{asset('assets/dselect.js')}}"></script>
<script>
(function () {
   var idx = {{ count($items) + 100 }};
   document.querySelectorAll('#tp-items .tp-supplier').forEach(function (s) { dselect(s, { search: true }); });
   document.getElementById('tp-add').addEventListener('click', function () {
      var body = document.querySelector('#tp-items tbody');
      body.insertAdjacentHTML('beforeend', document.getElementById('tp-tpl').innerHTML.replace(/__I__/g, idx++));
      dselect(body.lastElementChild.querySelector('.tp-supplier'), { search: true });
   });
   document.getElementById('tp-items').addEventListener('click', function (e) {
      var b = e.target.closest('.tp-remove');
      if (b) { b.closest('tr').remove(); }
   });
})();
</script>
@endsection
