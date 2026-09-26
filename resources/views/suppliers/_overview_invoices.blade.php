{{--
    Account overview, invoice analysis per role (AccountInvoices, from the detailed
    invoice report). Loaded after the overview opens: SuppliersController@overviewInvoices
    returns this fragment for /suppliers/{id}/overview/invoices.
--}}
@if(! $invoices)
<div class="card mt-3"><div class="card-body text-muted" data-state="empty">لا توجد فواتير لهذا الحساب.</div></div>
@endif
@foreach($invoices as $inv)
<?php $reportLink = route('site.invoices_full_report', [$inv->role == 'supplier' ? 'supplier_id' : 'customer_id' => $account->id]); ?>
<div class="card mt-3" id="ovInvoices-{{$inv->role}}">
   <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
         <h6 class="text-muted fs-13 mb-0">تحليل الفواتير {{ $inv->role == 'supplier' ? 'كمورد' : 'كعميل' }}</h6>
         <span class="text-muted fs-12">من التقرير التفصيلي للفواتير · <a href="{{$reportLink}}">فتح التقرير</a></span>
      </div>

      <div class="row g-2 mb-3">
         <div class="col-6 col-md">
            <div class="border rounded p-2 h-100"><p class="text-muted fs-12 mb-1">الفواتير</p><h5 class="mb-0" data-f="invoices">{{ number_format($inv->invoices) }}</h5></div>
         </div>
         <div class="col-6 col-md">
            <div class="border rounded p-2 h-100"><p class="text-muted fs-12 mb-1">التذاكر</p><h5 class="mb-0" data-f="tickets">{{ number_format($inv->tickets) }}</h5></div>
         </div>
         <div class="col-6 col-md">
            <div class="border rounded p-2 h-100"><p class="text-muted fs-12 mb-1">{{$inv->amount_label}}</p><h5 class="mb-0" data-f="amount">{{ number_format($inv->amount, 2) }}</h5></div>
         </div>
         <div class="col-6 col-md">
            <div class="border rounded p-2 h-100">
               <p class="text-muted fs-12 mb-1">{{$inv->refund_label}} @if($inv->refund_tickets) <span class="fs-11">({{ number_format($inv->refund_tickets) }} تذكرة)</span> @endif</p>
               <h5 class="mb-0" data-f="refund">{{ number_format($inv->refund, 2) }}</h5>
            </div>
         </div>
         <div class="col-12 col-md">
            <div class="border rounded p-2 h-100 bg-light"><p class="text-muted fs-12 mb-1">الصافي</p><h5 class="mb-0" data-f="net">{{ number_format($inv->net, 2) }}</h5></div>
         </div>
      </div>

      <ul class="nav nav-tabs nav-tabs-custom mb-2" role="tablist">
         @foreach(App\Services\AccountInvoices::GROUPS as $by => $title)
         <li class="nav-item" role="presentation">
            <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#ov-{{$inv->role}}-{{$by}}" type="button" role="tab">{{$title}}</button>
         </li>
         @endforeach
      </ul>
      <div class="tab-content">
         @foreach(App\Services\AccountInvoices::GROUPS as $by => $title)
         <div class="tab-pane fade @if($loop->first) show active @endif" id="ov-{{$inv->role}}-{{$by}}" role="tabpanel">
            @if($by == 'employee')
            <p class="text-muted fs-11 mb-1">الفاتورة المشتركة تُحسب لكل من موظفيها، لذلك قد يزيد مجموع الموظفين عن الإجمالي.</p>
            @endif
            <div class="table-responsive">
               <table class="table table-sm table-striped align-middle mb-0" data-group="{{$inv->role}}-{{$by}}">
                  <thead class="table-light">
                     <tr>
                        <th>{{ ['airline' => 'شركة الطيران', 'employee' => 'الموظف', 'op' => 'نوع العملية'][$by] }}</th>
                        <th class="text-center">الفواتير</th>
                        <th class="text-center">التذاكر</th>
                        <th>{{$inv->amount_label}}</th>
                        <th>{{$inv->refund_label}}</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach($inv->groups[$by] as $g)
                     <tr>
                        <td>{{$g->label}}</td>
                        <td class="text-center">{{ number_format($g->invoices) }}</td>
                        <td class="text-center">{{ number_format($g->tickets) }}</td>
                        <td>{{ number_format($g->amount, 2) }}</td>
                        <td>@if($g->refund) {{ number_format($g->refund, 2) }} @else <span class="text-muted">0.00</span> @endif</td>
                     </tr>
                     @endforeach
                  </tbody>
               </table>
            </div>
         </div>
         @endforeach
      </div>
   </div>
</div>
@endforeach
