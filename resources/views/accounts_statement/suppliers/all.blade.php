@extends('layouts.app')
@section('content')
@section('title' , "كشف حساب موردين")

<style>
    .buttons-collection{
        width: 100%;
    }
    .dt-column-title{
        font-weight: normal;
        font-size: 12px;
    }
    #InvoicesTable th, #InvoicesTable td {
        vertical-align: middle;
    }
</style>

<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header">
            <h5 class="card-title mb-0"> كشف حساب موردين</h5>

            <a href="#print_table" onclick="printdiv();">
                <button class="btn btn-primary" style="float: left;margin-top: -22px;">
                    <i class="ri-printer-line"></i>
                    طباعة كـ PDF
                </button>
            </a>
         </div>

         <div class="card-body">
            <form>
                <div class="table-responsive">
                    <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="text-align: right;">#</th>
                                <th style="text-align: right;">اسم الحساب</th>
                                <th style="text-align: right;color:white;" class="bg-danger">دائن</th>
                                <th style="text-align: right;color:white;" class="bg-danger">مدين</th>
                                <th style="text-align: right;color:white;" class="bg-danger">رصيد</th>
                                <th style="text-align: right;color:white;" class="bg-danger">نسخ الاشعار</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php $x = 1; ?>
                        @foreach($suppliers as $supplier)
                        <?php
                            $trsnactions = App\Models\AccountStatement::where('supp_client_id' , $supplier->id)
                                ->where('is_storage','!=',1);

                            if($sts != 0){
                                $trsnactions = $trsnactions->whereBetween('crt_date' , [$date_from , $date_to]);
                            }

                            $trsnactions = $trsnactions->get();

                            $total_credit = $trsnactions->sum('credit_balance');
                            $total_debit = $trsnactions->sum('debit_balance');
                            $balance = $total_debit - $total_credit;
                        ?>

                        @if(!($balance == 0 && $zero != 1))
                        <tr>
                            <td style="text-align:right;">{{$x}}</td>
                            <td style="text-align:right;">{{$supplier->name}}</td>

                            <td style="text-align:right;" data-order="{{ $total_credit }}">
                                {{ number_format($total_credit, 2) }}
                            </td>

                            <td style="text-align:right;" data-order="{{ $total_debit }}">
                                {{ number_format($total_debit, 2) }}
                            </td>

                            <td style="text-align:right;" data-order="{{ $balance }}">
                                {{ number_format($balance, 2) }}
                            </td>

                            <td>
                                <button type="button" class="btn btn-primary"
                                    onclick="copyText('{{ number_format($balance, 2) }}','{{$supplier->name}}')">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                            </td>
                        </tr>
                        <?php $x++; ?>
                        @endif
                        @endforeach
                        </tbody>

                        <tfoot>
                        <tr>
                            <td style="text-align:right; font-weight: bold;">الاجمالي الكلي</td>
                            <td>--</td>
                            <td id="subtotal" style="text-align:right; font-weight: bold;"></td>
                            <td id="total" style="text-align:right; font-weight: bold;"></td>
                            <td class="bg-primary text-white" id="total0" style="text-align:right; font-weight: bold;"></td>
                            <td>--</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
         </div>
      </div>
   </div>
</div>

<?php
$link = route('site.accounts_statement_suppliers_print' , [
    'sup_stauts' => $report_type,
    'from' => $date_from,
    'to' => $date_to,
]);
?>

<script>
$(document).ready(function() {
    let table = new DataTable('#InvoicesTable', {
        ordering: false,
        responsive: true,
        pageLength: 25,
        layout: {
            topStart: {
                buttons: ['colvis']
            }
        },
        footerCallback: function (row, data, start, end, display) {
            var api = this.api();

            var intVal = function (i) {
                if (typeof i === 'number') return i;
                if (typeof i === 'string') {
                    let clean = i.replace(/[,]| ج.م/g, '').trim();
                    if (clean.endsWith('-')) clean = '-' + clean.slice(0, -1);
                    return parseFloat(clean) || 0;
                }
                return 0;
            };

            var totalCredit = api.column(2, { search: 'applied' }).data().reduce((a, b) => intVal(a) + intVal(b), 0);
            var totalDebit  = api.column(3, { search: 'applied' }).data().reduce((a, b) => intVal(a) + intVal(b), 0);
            var totalBalance = api.column(4, { search: 'applied' }).data().reduce((a, b) => intVal(a) + intVal(b), 0);

            $(api.column(2).footer()).html(totalCredit.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $(api.column(3).footer()).html(totalDebit.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            $(api.column(4).footer()).html(totalBalance.toLocaleString('en-US', { minimumFractionDigits: 2 }));
        }
    });
});

function printdiv(){
    var url = "{{$link}}"; 
    window.open(url, 'Print', 'width=950,height=600');
}

function copyText(val, clientName){
    var msg ="لذا لطفاً برجاء سداد المبالغ و ارسال الايصال - مع تحيات إدارة شركة إيانا تورز سوهاج";

    var rtl = "\u202B";
    var ltr = "\u202A";
    var end = "\u202C";

    var text = rtl + "السادة/ " + clientName + " 💫🌸 برجاء العلم ان رصيدكم الحالي "
        + ltr + val + end + " ج.م " + msg + end;

    var input = document.createElement("textarea");
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand("copy");
    document.body.removeChild(input);

    alert("تم نسخ نص المطالبة بنجاح ✅");
}
</script>

@endsection