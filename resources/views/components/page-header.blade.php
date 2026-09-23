{{--
    Reusable page header, using the theme's own (previously unused)
    .page-title-box component instead of cramming the title into the
    content card's header.

    Simple usage (plain-text title, HTML-escaped):
    <x-page-header title="قائمة الموردين">
        <a href="{{route('site.suppliers_create')}}">
            <button class="btn btn-primary"><i class="ri-file-add-line"></i> اضافة مورد</button>
        </a>
    </x-page-header>

    Rich/dynamic title (conditionals, bold text, line breaks) -- use the
    named "heading" slot instead of the title attribute, since slot
    content is raw Blade/HTML and isn't escaped:
    <x-page-header>
        <x-slot:heading>
            كشف حساب @if($st == 1) : <b>{{$supplier->name}}</b> @endif
        </x-slot:heading>
        <button class="btn btn-dark">...</button>
    </x-page-header>
--}}
@props(['title' => null])
<div class="row">
   <div class="col-12">
      <div class="ey-page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
         <h4 class="mb-0">
            @isset($heading)
               {{ $heading }}
            @else
               {{ $title }}
            @endisset
         </h4>
         @if(trim($slot) !== '')
         <div class="d-flex align-items-center gap-2">
            {{ $slot }}
         </div>
         @endif
      </div>
   </div>
</div>
