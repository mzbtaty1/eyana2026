{{-- Payment Status Badge --}}
@if((float)$invoice->invoice_money_pay == 0) 
    <span class="badge bg-dark my_badge">لم يتم السداد</span>
@else
    @if((float)$invoice->invoice_money_pay < (float)$totalClientBoughtPrice)
        <span class="badge bg-secondary my_badge">سداد جزئي</span>  
    @else
        <span class="badge bg-success my_badge">تم السداد</span>  
    @endif                                                           
@endif

{{-- Approval Status (Admin Only) --}}
@if(Auth::user()->account_type == 2)
    @if($invoice->invoice_status == 0)
        <button class="btn btn-primary action-btn" 
                id="rvd_{{ $invoice->id }}" 
                onclick="do_approved({{ $invoice->id }})">
            تأكيد العملية
        </button>
        <button class="btn btn-success action-btn" 
                id="apprvd_{{ $invoice->id }}" 
                style="display: none;">
            تم التأكيد
        </button>      
    @else
        <button class="btn btn-success action-btn">
            تم التأكيد
        </button>      
    @endif
@endif