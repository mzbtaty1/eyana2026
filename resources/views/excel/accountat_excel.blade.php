{{--
    Account Statement Excel sheet. Same columns, order, passenger breakdown and
    balances as accounts_statement/show.blade.php and print_report.blade.php.
    Every row is prepared in App\Exports\AccountatExport (no queries here);
    RTL, column widths, wrapping and borders are applied there in AfterSheet.
    Amounts are real numeric cells (data-type="n") formatted to 2 decimals.
--}}
@php
    $money = '#,##0.00;-#,##0.00';
    $num = fn ($v) => number_format((float) $v, 2, '.', '');
@endphp
<table dir="rtl">
    <tr>
        <td colspan="12">
            كشف حساب
            @if($st == 1)
                @if($supplier->acc_type == 1) العميل @else المورد @endif
                : {{$supplier->name}}
            @else
                عام
            @endif
        </td>
    </tr>
    <tr>
        <td colspan="12">
            @if($isDateFiltered)
                فترة التقرير: من {{$date_from}} إلى {{$date_to}}
            @else
                كل الفترات
            @endif
        </td>
    </tr>
    <tr><td colspan="12"></td></tr>

    {{-- Opening balance section (a summary, not a transaction row) --}}
    @if($st == 1)
    <tr>
        <td colspan="10" style="font-weight: bold;">رصيد افتتاحي (دائن)</td>
        <td data-type="n" data-format="{{$money}}">{{$num($supplier->opening_credit_balance)}}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="10" style="font-weight: bold;">رصيد افتتاحي (مدين)</td>
        <td data-type="n" data-format="{{$money}}">{{$num($supplier->debit_opening_balance)}}</td>
        <td></td>
    </tr>
    @endif
    @if($isDateFiltered)
    <tr>
        <td colspan="10" style="font-weight: bold; background-color: #FFF2CC;">الرصيد الافتتاحي (بداية الفترة) - مرحل حتى {{$date_from}}</td>
        <td data-type="n" data-format="{{$money}}" style="font-weight: bold; background-color: #FFF2CC;">{{$num($opening_balance_for_period)}}</td>
        <td></td>
    </tr>
    @endif
    <tr><td colspan="12"></td></tr>

    {{-- Transactions --}}
    <tr>
        <td>رقم العملية</td>
        <td>نوع العملية</td>
        <td>تاريخ العملية</td>
        <td>نوع الطيران</td>
        <td>خط السير</td>
        <td>تاريخ السفر</td>
        <td>المسافر / بيان العملية</td>
        <td>رقم الحجز</td>
        <td>مدين</td>
        <td>دائن</td>
        <td>الرصيد</td>
        <td>الموظف</td>
    </tr>
    @foreach($rows as $row)
    <tr>
        <td data-type="s" style="font-weight: bold;">{{$row['es_id']}}</td>
        <td>{{$row['type']}}</td>
        <td data-type="s">{{$row['date']}}</td>
        <td data-type="s">{{$row['airline']}}</td>
        <td data-type="s">{{$row['route']}}</td>
        <td data-type="s">{{$row['travel_date']}}</td>
        <td data-type="s">@if(is_array($row['details'])){!! collect($row['details'])->map(fn ($l) => e($l))->implode('<br>') !!}@else{{$row['details']}}@endif</td>
        <td data-type="s">{{$row['booking']}}</td>
        @if($row['debit'] === null)<td></td>@else<td data-type="n" data-format="{{$money}}">{{$num($row['debit'])}}</td>@endif
        @if($row['credit'] === null)<td></td>@else<td data-type="n" data-format="{{$money}}" style="color: #FF7900;">{{$num($row['credit'])}}</td>@endif
        <td data-type="n" data-format="{{$money}}">{{$num($row['balance'])}}</td>
        <td>{{$row['employee']}}</td>
    </tr>
    @endforeach
    <tr>
        <td colspan="8">الإجمالي</td>
        <td data-type="n" data-format="{{$money}}">{{$num($total_debit_balance)}}</td>
        <td data-type="n" data-format="{{$money}}">{{$num($total_credit_balance)}}</td>
        <td data-type="n" data-format="{{$money}}">{{$num($total_blnc)}}</td>
        <td></td>
    </tr>
    <tr><td colspan="12"></td></tr>

    {{-- Summary (same lines as the Print Preview summary table) --}}
    @if($isDateFiltered)
    <tr>
        <td colspan="10" style="font-weight: bold;">الرصيد الافتتاحي (بداية الفترة)</td>
        <td data-type="n" data-format="{{$money}}">{{$num($opening_balance_for_period)}}</td>
        <td></td>
    </tr>
    @endif
    <tr>
        <td colspan="10" style="font-weight: bold;">إجمالي المدين @if($isDateFiltered) للفترة @endif</td>
        <td data-type="n" data-format="{{$money}}">{{$num($total_debit_balance)}}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="10" style="font-weight: bold;">إجمالي الدائن @if($isDateFiltered) للفترة @endif</td>
        <td data-type="n" data-format="{{$money}}">{{$num($total_credit_balance)}}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="10" style="font-weight: bold; background-color: #D9EAD3;">@if($isDateFiltered) الرصيد النهائي @else الإجمالي النهائي @endif</td>
        <td data-type="n" data-format="{{$money}}" style="font-weight: bold; background-color: #D9EAD3;">{{$num($total_blnc)}}</td>
        <td></td>
    </tr>
</table>
