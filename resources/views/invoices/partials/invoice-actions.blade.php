{{-- Actions Dropdown --}}
<button class="btn btn-soft-secondary btn-sm dropdown" 
        type="button" 
        data-bs-toggle="dropdown" 
        aria-expanded="false">
    <i class="ri-more-fill align-middle"></i>
</button>

<ul class="dropdown-menu dropdown-menu-end">
    {{-- View Details --}}
    <li>
        <a href="{{ route('site.invoices_show', $invoice->id) }}" class="dropdown-item">
            <i class="ri-eye-fill align-bottom me-2 text-muted"></i> تفاصيل
        </a>
    </li>
    
    {{-- Edit (Admin or if invoice not approved) --}}
    @if(Auth::user()->account_type == 2 || $invoice->invoice_status == 0)
        <li>
            <a href="{{ route('site.invoices_edit', $invoice->id) }}" class="dropdown-item">
                <i class="ri-edit-box-line"></i> تعديل
            </a>
        </li>
    @endif
    
    {{-- Pay Invoice (if not fully paid) --}}
    @if((float)$invoice->invoice_money_pay < (float)$totalClientBoughtPrice)
        <li>
            <a href="{{ route('site.invoices_pay_part', $invoice->id) }}" class="dropdown-item">
                <i class="ri-wallet-3-line"></i> سداد الفاتورة
            </a>
        </li>
    @endif
    
    {{-- Reissue --}}
    <li>
        <a href="{{ route('site.invoices_reissue_create', $invoice->es_id) }}" class="dropdown-item">
            <i class="ri-arrow-go-forward-line"></i> اعادة اصدار
        </a>
    </li>
    
    {{-- Refund --}}
    <li>
        <a href="{{ route('site.invoices_refund', $invoice->es_id) }}" class="dropdown-item">
            <i class="ri-refund-2-line"></i> الغاء الفاتورة
        </a>
    </li>
    
    {{-- Delete --}}
    <li>
        <a href="{{ route('site.invoices_remove', $invoice->es_id) }}" class="dropdown-item">
            <i class="ri-delete-bin-line"></i> حذف الفاتورة
        </a>
    </li>
</ul>