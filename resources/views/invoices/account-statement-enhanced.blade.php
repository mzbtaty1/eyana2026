@extends('layouts.app')
@section('content')
@section('title', "كشف حساب محسن")

<style>
.transaction-row-reissue {
    background: linear-gradient(135deg, #fffbeb, #fef3c7) !important;
    border-left: 4px solid #d97706 !important;
}

.transaction-row-cancellation {
    background: linear-gradient(135deg, #fef2f2, #fee2e2) !important;
    border-left: 4px solid #dc2626 !important;
}

.transaction-row-modification {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe) !important;
    border-left: 4px solid #0369a1 !important;
}

.date-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.date-execution {
    background: #fee2e2;
    color: #991b1b;
}

.date-original {
    background: #e0f2fe;
    color: #0369a1;
}

.date-travel {
    background: #f3e8ff;
    color: #6b21a8;
}
</style>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0">
                    كشف حساب محسن - 
                    @if($st == 1)
                        @if($supplier->acc_type == 1) العميل @else المورد @endif
                        : <b>{{$supplier->name}}</b>
                    @else
                        عام
                    @endif
                    
                    @if($date_from !== null && $date_to !== null)
                    <br>
                    بحث من تاريخ : {{$date_from}} الي {{$date_to}}
                    @endif
                </h5>
                
                <button class="btn btn-dark" onclick="printdiv()">
                    <i class="ri-printer-line"></i> طباعة التقرير
                </button>
            </div>
            
            <div class="card-body">
                @if($st == 1)
                <div class="row mb-3">
                    <div class="col-6">
                        <h5>رصيد افتتاحى (دائن): 
                            <span class="badge bg-success">{{number_format($supplier->opening_credit_balance, 2)}} جنيها</span>
                        </h5>
                    </div>
                    <div class="col-6">
                        <h5>رصيد افتتاحى (مدين): 
                            <span class="badge bg-primary">{{number_format($supplier->debit_opening_balance, 2)}} جنيها</span>
                        </h5>
                    </div>
                </div>
                @endif
                
                <hr>
                
                <div class="table-responsive">
                    <table id="InvoicesTable" class="table table-bordered dt-responsive nowrap table-striped align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="text-align: right;">#</th>
                                <th style="text-align: right;">نوع العملية</th>
                                <th style="text-align: right;">تاريخ التنفيذ</th>
                                <th style="text-align: right;">تاريخ الفاتورة الأصلي</th>
                                <th style="text-align: right;">تاريخ السفر</th>
                                <th style="text-align: right;">وجهة الاستلام / الوصول</th>
                                <th style="text-align: right;">بيانات الركاب</th>
                                <th style="text-align: right;">بيان العملية</th>
                                <th style="text-align: right;">مدين</th>
                                <th style="text-align: right;">دائن</th>
                                <th style="text-align: right;">الرصيد</th>
                                <th style="text-align: right;">الموظف</th>
                                @if(Auth::user()->account_type == 2)
                                <th style="text-align: right;">المطابقة</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_blnc = 0;
                            $x = 0;
                            ?>
                            @foreach($AccountStatements as $key => $AccountStatement)
                            <?php
                            $closing = (int) $AccountStatement->debit_balance - (int) $AccountStatement->credit_balance;
                            $total_blnc += $closing;

                            $ticket_info = null;
                            $users = [];
                            $bond = null;

                            if($AccountStatement->trans_storage == 1){
                                $ticket_info_result = App\Models\Bond::select('*')->where('es_id', $AccountStatement->es_id)->get();
                                if(count($ticket_info_result) > 0){
                                    $ticket_info = $ticket_info_result[0];
                                }
                                $bond = $ticket_info;
                            }else{
                                if($AccountStatement->is_supp_account == 0){
                                    $ticket_info_result = App\Models\Invoice::select('*')->where('es_id', $AccountStatement->es_id)->get();
                                    if(count($ticket_info_result) > 0){
                                        $ticket_info = $ticket_info_result[0];
                                        $users = App\Models\TicketUser::select('*')->where('ticket_system_id', $ticket_info->ticket_system_id)->get();
                                    }
                                }
                            }

                            $result = substr($AccountStatement->es_id, 0, 6);
                            
                            // Determine row class based on operation type
                            $rowClass = '';
                            if($result == "FLY-RD") {
                                $rowClass = 'transaction-row-cancellation';
                            } elseif($result == "FLY-RS") {
                                $rowClass = 'transaction-row-reissue';
                            } elseif($ticket_info && $ticket_info->updated_at > $ticket_info->created_at) {
                                $rowClass = 'transaction-row-modification';
                            }
                            ?>

                            <tr class="{{$rowClass}}">
                                <td style="text-align: right;">{{$AccountStatement->es_id}}</td>
                                <td style="text-align: right;">
                                    <?php
                                    if($AccountStatement->invoice_type == 1){ $type = "فواتير الطيران"; }
                                    elseif($AccountStatement->invoice_type == 2){ $type = "فواتير تأشيرات"; }
                                    elseif($AccountStatement->invoice_type == 3){ $type = "فواتير سياحه داخليه"; }
                                    elseif($AccountStatement->invoice_type == 4){ $type = "فواتير سياحه خارجيه"; }
                                    elseif($AccountStatement->invoice_type == 5){ $type = "فواتير سياحه دينيه"; }
                                    elseif($AccountStatement->invoice_type == 6){ $type = "فواتير تأمينات السفر"; }
                                    elseif($AccountStatement->invoice_type == 7){ $type = "فواتير تحاليل السفر"; }
                                    elseif($AccountStatement->invoice_type == 8){ $type = "فواتير نقل سياحى"; }
                                    elseif($AccountStatement->invoice_type == 9){ $type = "سند دفع"; }
                                    elseif($AccountStatement->invoice_type == 10){ $type = "سند قبض"; }
                                    elseif($AccountStatement->invoice_type == 11){ $type = "ارصدة"; }
                                    elseif($AccountStatement->invoice_type == 12){ $type = "سداد فاتورة"; }
                                    ?>
                                    @if($AccountStatement->transaction_type == 1)
                                        تذاكر /
                                    @elseif($AccountStatement->transaction_type == 2)
                                        سندات /
                                    @elseif($AccountStatement->transaction_type == 3)
                                        ارصدة افتتاحية /
                                    @elseif($AccountStatement->transaction_type == 4)
                                        سداد /
                                    @endif
                                    {{$type}}
                                </td>

                                <!-- تاريخ التنفيذ - يُظهر متى تم تنفيذ العملية فعلياً -->
                                <td style="text-align: right;">
                                    <span class="date-badge date-execution" title="تاريخ تنفيذ العملية">
                                        <i class="ri-calendar-check-line"></i>
                                        {{Carbon\Carbon::parse($AccountStatement->created_at)->format('d/m/Y H:i')}}
                                    </span>
                                </td>

                                <!-- تاريخ الفاتورة الأصلي - يُظهر تاريخ إنشاء الفاتورة الأصلية -->
                                <td style="text-align: right;">
                                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                                        <span class="date-badge date-original" title="تاريخ الفاتورة الأصلي">
                                            <i class="ri-file-list-2-line"></i>
                                            {{$ticket_info->invoice_date}}
                                        </span>
                                    @elseif($result == "FLY-RD" || $result == "FLY-RS")
                                        <?php
                                        // للإلغاء وإعادة الإصدار، نعرض تاريخ الفاتورة الأصلية
                                        $originalEsId = preg_replace('/^FLY-(RD|RS)/', 'FLY', $AccountStatement->es_id);
                                        $originalInvoice = App\Models\Invoice::where('es_id', $originalEsId)->first();
                                        ?>
                                        @if($originalInvoice)
                                            <span class="date-badge date-original" title="تاريخ الفاتورة الأصلية">
                                                <i class="ri-history-line"></i>
                                                {{$originalInvoice->invoice_date}}
                                            </span>
                                        @else
                                            <span class="text-muted">غير محدد</span>
                                        @endif
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>

                                <!-- تاريخ السفر -->
                                <td style="text-align: right;">
                                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                                        <span class="date-badge date-travel" title="تاريخ السفر">
                                            <i class="ri-flight-takeoff-line"></i>
                                            {{$ticket_info->invoice_travel_date}}
                                        </span>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>

                                <td>
                                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                                        {{$ticket_info->from_location}} - {{$ticket_info->to_location}}
                                    @else
                                        --
                                    @endif
                                </td>

                                <td style="text-align: right;">
                                    @if(in_array($AccountStatement->transaction_type, [1, 4]))
                                        @foreach($users as $user)
                                            {{$user->client_name}}<br>
                                        @endforeach
                                    @else
                                        --
                                    @endif
                                </td>

                                <td style="text-align: right;">
                                    @if($result == "FLY-RD")
                                        <span class="badge bg-danger">
                                            <i class="ri-close-circle-line"></i>
                                            إلغاء تذكرة {{$AccountStatement->es_id}}
                                        </span>
                                        <small class="text-muted d-block">
                                            تاريخ الإلغاء: {{Carbon\Carbon::parse($AccountStatement->created_at)->format('d/m/Y H:i')}}
                                        </small>
                                    @elseif($result == "FLY-RS")
                                        <span class="badge bg-warning">
                                            <i class="ri-refresh-line"></i>
                                            إعادة إصدار تذكرة {{$AccountStatement->es_id}}
                                        </span>
                                        <small class="text-muted d-block">
                                            تاريخ الإعادة: {{Carbon\Carbon::parse($AccountStatement->created_at)->format('d/m/Y H:i')}}
                                        </small>
                                    @else
                                        {{$AccountStatement->transaction_txt}}
                                        @if($ticket_info && $ticket_info->updated_at > $ticket_info->created_at)
                                            <span class="badge bg-info ms-2">
                                                <i class="ri-edit-line"></i>
                                                معدلة
                                            </span>
                                            <small class="text-muted d-block">
                                                آخر تعديل: {{Carbon\Carbon::parse($ticket_info->updated_at)->format('d/m/Y H:i')}}
                                            </small>
                                        @endif
                                    @endif

                                    @if($AccountStatement->transaction_type == 1 && $ticket_info)
                                        <br>
                                        <small>
                                            خط الطيران: {{$ticket_info->invoice_airline}}
                                            <br>
                                            ارقام الحجز:
                                            @foreach($users as $user)
                                                {{$user->client_booking_id}} /
                                            @endforeach
                                            <br>
                                            ارقام التذاكر:
                                            @foreach($users as $user)
                                                {{$user->client_ticket_id}} /
                                            @endforeach
                                        </small>
                                    @elseif($AccountStatement->transaction_type == 2 && $bond)
                                        <br>
                                        <small>
                                            @if($bond->money_way == 1)
                                                دفع نقدي
                                            @elseif($bond->money_way == 2)
                                                تحويل بنكي
                                                <?php
                                                $bank_info_result = App\Models\Bank::select('*')->where('id',$bond->bank_id)->get();
                                                $bank_info = count($bank_info_result) > 0 ? $bank_info_result[0] : null;
                                                ?>
                                                @if($bank_info)
                                                    {{$bank_info->bank_name}}
                                                @endif
                                            @else
                                                تحصيل من المندوب:
                                                <?php
                                                $collector_info_result = App\Models\Collector::select('*')->where('id',$bond->collector_info)->get();
                                                $collector_info = count($collector_info_result) > 0 ? $collector_info_result[0] : null;
                                                ?>
                                                @if($collector_info)
                                                    {{$collector_info->name}}
                                                @endif
                                            @endif
                                            {{$bond->info}}
                                        </small>
                                    @endif

                                    @if($AccountStatement->trans_storage == 1 && $AccountStatement->sub_id != 0)
                                        <br>
                                        <?php
                                        $sub_id = (int) $AccountStatement->sub_id;
                                        $min_info_result = App\Models\SubStorage::select('*')->where('id', $sub_id)->get();
                                        $min_info3 = count($min_info_result) > 0 ? $min_info_result[0] : null;
                                        ?>
                                        @if($min_info3)
                                            <small>خزينة فرعية: <b>{{$min_info3->name}}</b></small>
                                        @endif
                                    @endif
                                </td>

                                <td style="text-align: right;">{{number_format($AccountStatement->debit_balance, 2)}}</td>
                                <td style="text-align: right;color:#ff7900;">{{number_format($AccountStatement->credit_balance, 2)}}</td>
                                <td style="text-align: right;">{{number_format($total_blnc, 2)}}</td>

                                <td style="text-align: right;">
                                    <?php
                                    $mem_result = App\Models\User::select('*')->where('id',$AccountStatement->added_by)->get();
                                    $mem = count($mem_result) > 0 ? $mem_result[0] : null;
                                    ?>
                                    @if($mem)
                                        {{$mem->name}}
                                    @endif
                                </td>

                                @if(Auth::user()->account_type == 2)
                                    <td>
                                        @if($AccountStatement->transaction_approved == 0)
                                            <button class="btn btn-primary" id="rvd_{{$AccountStatement->id}}" style="border-radius: 55px;font-size: 10px;" onclick="do_approved({{$AccountStatement->id}})">تأكيد العملية</button>
                                            <button class="btn btn-success" id="apprvd_{{$AccountStatement->id}}" style="border-radius: 55px;font-size: 10px;display:none;">تم التأكيد</button>
                                        @else
                                            <button class="btn btn-success" style="border-radius: 55px;font-size: 10px;">تم التأكيد</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                            <?php $x++; ?>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr>
                                <td>الاجمالي</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td style="text-align:center; background-color: #198754 !important;color:white;">
                                    {{number_format($total_debit_balance, 2)}}
                                </td>
                                <td style="text-align:center; background-color: #198754 !important;color:white;">
                                    {{number_format($total_credit_balance, 2)}}
                                </td>
                                <td class="font-weight-bold" style="text-align:center; background-color: #198754 !important;color:white;">
                                    {{number_format($total_debit_balance - $total_credit_balance, 2)}}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Summary Table -->
                <table class="table">
                    <tbody>
                        @if($st == 1)
                        <tr>
                            <td>رصيد افتتاحى (دائن)</td>
                            <td>{{number_format($supplier->opening_credit_balance, 2)}} جنيها</td>
                        </tr>
                        <tr>
                            <td>رصيد افتتاحى (مدين)</td>
                            <td>{{number_format($supplier->debit_opening_balance, 2)}} جنيها</td>
                        </tr>
                        @endif
                        <tr>
                            <td>إجمالى المدين</td>
                            <td>{{number_format($total_debit_balance, 2)}} جنيها</td>
                        </tr>
                        <tr>
                            <td>إجمالى الدائن</td>
                            <td>{{number_format($total_credit_balance, 2)}} جنيها</td>
                        </tr>
                        <tr class="table-dark">
                            <td class="font-weight-bold">الإجمالى النهائي</td>
                            <td class="font-weight-bold">
                                {{number_format($total_debit_balance - $total_credit_balance, 2)}} جنيها
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Add DataTable functionality and approval functions here
let Mytable = new DataTable('#InvoicesTable', {
    pageLength: 50,
    ordering: false,
    responsive: true,
    layout: {
        topStart: {
            buttons: ['colvis']
        }
    },
});

function do_approved(id){
    var url = "{{url('')}}/accounts-statement/" + id + "/approve";
    
    $(document).ready(function () {
        $.ajax({
            type: "GET",
            url: url,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: "id=" + id,
            success: function (data) {
                var status_code = data.status_code;
                
                if(status_code == 200){
                    swal("", "تم تأكيد العملية بنجاح", "success");
                    
                    var n1 = "rvd_" + id;
                    var n2 = "apprvd_" + id;
                    document.getElementById(n1).style.display = "none"; 
                    document.getElementById(n2).style.display = "block"; 
                } else {
                    swal("", "فشل اثناء تأكيد العملية", "error");
                }
            }
        });
    });
}

function printdiv(){
    window.print();
}
</script>

@endsection