@extends('layouts.app')
@section('content')
@section('title' , 'كشف حساب بنك')
@include('components.flash-messages')
<x-page-header title="كشف حساب بنك : {{$bank_info->bank_name}}">
   <span class="ey-no-print d-inline-flex gap-2">
      <button type="button" class="btn btn-outline-dark" onclick="window.print()">
         <i class="ri-printer-line"></i> طباعة
      </button>
      <a href="{{route('site.banks_create')}}">
         <button class="btn btn-primary">
            <i class="ri-file-add-line"></i>
            اضافة بنك جديد
         </button>
      </a>
   </span>
</x-page-header>
<div class="row">
   <div class="col-lg-12">

      <div class="card">
         <div class="card-body">

            <div class="ey-no-print">
            <form method="GET" action="{{route('site.bank_account_transactions', $bank_info->id)}}" class="row g-2 mb-3">
                <div class="col-auto">
                    <label class="col-form-label">من تاريخ</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="date_from" class="form-control" value="{{$date_from}}">
                </div>
                <div class="col-auto">
                    <label class="col-form-label">الى تاريخ</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="date_to" class="form-control" value="{{$date_to}}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">عرض</button>
                </div>
            </form>
            </div>

            <p class="d-none d-print-block text-muted mb-2">
                الفترة: {{ $date_from ?: '—' }} : {{ $date_to ?: '—' }} — تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}
            </p>

            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <div class="text-muted">الرصيد الافتتاحي</div>
                            <h5 class="mb-0">{{number_format($opening_balance,2)}}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <div class="text-muted">اجمالي مدين (خارج)</div>
                            <h5 class="mb-0 text-danger">{{number_format($total_debit,2)}}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <div class="text-muted">اجمالي دائن (داخل)</div>
                            <h5 class="mb-0 text-success">{{number_format($total_credit,2)}}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <div class="text-muted">الرصيد الختامي</div>
                            <h5 class="mb-0">{{number_format($closing_balance,2)}}</h5>
                        </div>
                    </div>
                </div>
            </div>

            <table id="myTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
               <thead>
                  <tr>
                     <th data-ordering="false" style="text-align:right;">التاريخ</th>
                     <th data-ordering="false" style="text-align:right;">البيان / المرجع</th>
                     <th data-ordering="false" style="text-align:right;">مدين</th>
                     <th data-ordering="false" style="text-align:right;">دائن</th>
                     <th data-ordering="false" style="text-align:right;">الرصيد بعد العملية</th>
                     <th data-ordering="false" style="text-align:right;">الحالة</th>
                  </tr>
                  <tr class="table-secondary">
                     <td colspan="4"><b>رصيد افتتاحي{{ $date_from ? ' بتاريخ '.$date_from : '' }}</b></td>
                     <td style="text-align:right;"><b>{{number_format($opening_balance,2)}}</b></td>
                     <td>--</td>
                  </tr>
               </thead>
               <tbody>
                  @foreach($entries as $entry)
                  <tr @if($entry->is_voided) class="text-muted" style="text-decoration: line-through;" @endif>
                     <td style="text-align:right;">{{ \Illuminate\Support\Carbon::parse($entry->transaction_date)->format('Y-m-d') }}</td>
                     <td>
                        {{$entry->description}}
                        @if($entry->reference)
                        <br><span class="text-muted small">المرجع: {{$entry->reference}}</span>
                        @endif
                     </td>
                     <td style="text-align:right;">{{ $entry->debit > 0 ? number_format($entry->debit,2) : '--' }}</td>
                     <td style="text-align:right;">{{ $entry->credit > 0 ? number_format($entry->credit,2) : '--' }}</td>
                     <td style="text-align:right;">{{number_format($entry->running_balance,2)}}</td>
                     <td>
                        @if($entry->entry_type == 'reversal')
                            <span class="badge bg-danger">عكس / Reversal</span>
                        @elseif($entry->is_voided)
                            <span class="badge bg-warning text-dark">ملغي / Voided</span>
                        @elseif($entry->entry_type == 'opening')
                            <span class="badge bg-secondary">رصيد افتتاحي</span>
                        @else
                            <span class="badge bg-success">نشط / Active</span>
                        @endif
                     </td>
                  </tr>
                  @endforeach
               </tbody>
                <tfoot>
                <tr>
                        <td colspan="2" style="text-align:right;">الاجمالي</td>
                    <td style="text-align:right;color:white;" class="bg-dark">{{number_format($total_debit,2)}}</td>
                    <td style="text-align:right;color:white;" class="bg-dark">{{number_format($total_credit,2)}}</td>
                    <td style="text-align:right;"><b>{{number_format($closing_balance,2)}} (رصيد ختامي)</b></td>
                     <td>--</td>
                    </tr>
                </tfoot>
            </table>

          </div>
      </div>
   </div>
   <!--end col-->
</div>
@endsection
