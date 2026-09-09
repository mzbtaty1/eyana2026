@extends('layouts.app')

@section('title' , 'الرئيسية')
@section('content')

<!-- JS for jQuery -->
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.css" />

<table id="myTable" class="display">
    <thead>
        <tr>
            <th>Column 1</th>
            <th>Column 2</th>
        </tr>
    </thead>
    <tbody>
      
        @for ($i = 1; $i < 1000; $i++)
  <tr>
            <td>Row 1 Data 1</td>
            <td>Row 1 Data 2</td>
        </tr>
@endfor
    </tbody>
</table>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
<script>
$(document).ready( function () {
    $('#myTable').DataTable();
} );
</script>

@endsection
