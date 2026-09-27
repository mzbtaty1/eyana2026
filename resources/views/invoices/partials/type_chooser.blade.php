{{--
    Invoice type chooser (Visa step 2): Flight Invoice / Visa Invoice. Keeps the Counter
    Customer mode: from «عميل كونتر» both choices open with the customer fixed.
    Needs $invoiceKind ('flight' | 'visa') and $counterCustomer (null unless counter).
--}}
@php($isCounter = !empty($counterCustomer))
<div class="d-flex align-items-center gap-2 flex-wrap mb-3">
   <span class="fw-semibold">اختر نوع الفاتورة:</span>
   <div class="btn-group" role="group" aria-label="نوع الفاتورة">
      <a href="{{ route($isCounter ? 'site.invoices_create_counter' : 'site.invoices_create') }}" class="btn {{ $invoiceKind === 'flight' ? 'btn-primary active' : 'btn-outline-primary' }}" @if($invoiceKind === 'flight') aria-current="page" @endif>✈️ فاتورة طيران</a>
      <a href="{{ route($isCounter ? 'site.invoices_create_counter_visa' : 'site.invoices_create_visa') }}" class="btn {{ $invoiceKind === 'visa' ? 'btn-primary active' : 'btn-outline-primary' }}" @if($invoiceKind === 'visa') aria-current="page" @endif>🛂 فاتورة تأشيرة</a>
   </div>
</div>
