@extends('layouts.app')

@section('title', 'الفواتير - نسخة محسنة')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/invoices-new.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
@endpush

@section('content')
    @include('components.alerts-new')

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                @include('invoices.partials.header-new')
                @include('invoices.partials.table-new')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="{{ asset('js/invoices/datatables-config-new.js') }}"></script>
    <script src="{{ asset('js/invoices/invoice-actions-new.js') }}"></script>
@endpush