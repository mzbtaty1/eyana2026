@extends('layouts.app')
@section('content')
@section('title' , 'حجز ' . $booking->booking_no)
@php
   $money = fn ($v) => \App\Services\InvoiceFullReport::money($v);
   $s = $summary;
   $draft = $booking->isDraft();
   $active = $booking->items->where('status', 'active');
   // a draft is not on the ledger: its figures are the items' totals (estimate)
   $sale = $draft ? $active->sum('total_sale') : $s['sale'];
   $cost = $draft ? $active->sum('total_cost') : $s['cost'];
   $badge = ['draft' => 'bg-secondary', 'confirmed' => 'bg-success', 'cancelled' => 'bg-danger'][$booking->status] ?? 'bg-light';
   $canEdit = Gate::allows('tourism.edit') && !$booking->isCancelled() && ($draft || Gate::allows('tourism.confirm'));
   $finance = Gate::allows('finance.manage');
@endphp
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <x-page-header>
            <x-slot:heading>حجز سياحة داخلية <b>{{$booking->booking_no}}</b> <span class="badge {{$badge}} my_badge">{{$booking->statusLabel()}}</span></x-slot:heading>
            {{-- order (RTL, start first): main action, edit, print, destructive, back --}}
            <div class="ey-action-group">
               @if($draft)
                  @can('tourism.confirm')
                  <form method="POST" action="{{route('site.tourism_bookings_confirm', $booking->id)}}" onsubmit="return confirm('تأكيد الحجز وتسجيله في كشوف حساب العميل والموردين؟')">@csrf
                     <button class="btn btn-success"><i class="ri-check-double-line"></i><span>تأكيد الحجز</span></button>
                  </form>
                  @endcan
               @endif
               @if($canEdit)<a href="{{route('site.tourism_bookings_edit', $booking->id)}}" class="btn btn-primary"><i class="ri-edit-line"></i><span>تعديل</span></a>@endif
               <div class="btn-group">
                  <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="ri-printer-line"></i><span>طباعة</span></button>
                  <ul class="dropdown-menu dropdown-menu-end">
                     <li><a class="dropdown-item" target="_blank" href="{{route('site.tourism_bookings_print', [$booking->id, 'confirmation'])}}">تأكيد الحجز للعميل</a></li>
                     <li><a class="dropdown-item" target="_blank" href="{{route('site.tourism_bookings_print', [$booking->id, 'passengers'])}}">كشف الأفراد</a></li>
                     @foreach($booking->items->pluck('supplier')->filter()->unique('id') as $sup)
                     <li><a class="dropdown-item" target="_blank" href="{{route('site.tourism_bookings_print', [$booking->id, 'service_order', 'supplier_id' => $sup->id])}}">أمر خدمة / فاوتشر: {{$sup->name}}</a></li>
                     @endforeach
                  </ul>
               </div>
               @if($draft)
                  @can('tourism.edit')
                  <form method="POST" action="{{route('site.tourism_bookings_delete', $booking->id)}}" onsubmit="return confirm('حذف المسودة نهائيا؟')">@csrf
                     <button class="btn btn-outline-danger"><i class="ri-delete-bin-line"></i><span>حذف المسودة</span></button>
                  </form>
                  @endcan
               @endif
               @if(!$booking->isCancelled())
                  @can('tourism.cancel')
                  <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#tb-cancel-booking" aria-expanded="false"><i class="ri-close-circle-line"></i><span>إلغاء الحجز</span></button>
                  @endcan
               @endif
               <a href="{{route('site.tourism_bookings')}}" class="btn btn-light"><i class="ri-list-check"></i><span>القائمة</span></a>
            </div>
         </x-page-header>
         <div class="card-body">
            @include('components.flash-messages')
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

            @if(!$booking->isCancelled())
            @can('tourism.cancel')
            <div class="collapse mb-3" id="tb-cancel-booking">
               <form method="POST" action="{{route('site.tourism_bookings_cancel', $booking->id)}}" class="border border-danger rounded p-3" onsubmit="return confirm('إلغاء الحجز بالكامل؟')">
                  @csrf
                  <h6 class="text-danger">إلغاء الحجز بالكامل</h6>
                  @if($booking->isConfirmed())
                  <p class="text-muted small mb-2">تلغى كل الخدمات، وتسجل قيود الإلغاء بتاريخ اليوم. أدخل غرامة الإلغاء لكل خدمة إن وجدت: التكلفة التي تبقى علينا للمورد، والرسوم التي تبقى على العميل.</p>
                  <table class="table table-sm table-bordered">
                     <thead class="table-light"><tr><th>الخدمة</th><th>المورد</th><th>غرامة المورد (تكلفة)</th><th>رسوم على العميل</th></tr></thead>
                     <tbody>
                        @foreach($active as $it)
                        <tr>
                           <td>{{$it->title()}}</td><td>{{$it->supplier->name ?? ''}}</td>
                           <td><input type="number" step="0.01" min="0" max="{{$it->total_cost}}" class="form-control form-control-sm" name="penalties[{{$it->id}}][cost]" value="0"></td>
                           <td><input type="number" step="0.01" min="0" max="{{$it->total_sale}}" class="form-control form-control-sm" name="penalties[{{$it->id}}][fee]" value="0"></td>
                        </tr>
                        @endforeach
                     </tbody>
                  </table>
                  @endif
                  <div class="row g-2 align-items-end">
                     <div class="col-md-9"><label class="form-label mb-1">سبب الإلغاء</label><input type="text" class="form-control" name="cancel_reason" maxlength="500"></div>
                     <div class="col-md-3"><div class="ey-filter-actions"><button class="btn btn-danger"><i class="ri-close-circle-line"></i><span>تأكيد الإلغاء</span></button></div></div>
                  </div>
               </form>
            </div>
            @endcan
            @endif

            <div class="row g-3 mb-3">
               <div class="col-md-6">
                  <table class="table table-sm table-borderless mb-0">
                     <tr><th class="text-muted" style="width:35%">العميل</th><td><strong>{{$booking->customer->name ?? ''}}</strong></td></tr>
                     <tr><th class="text-muted">المسئول / الهاتف</th><td>{{$booking->contact_name ?: '—'}} {{$booking->contact_phone}}</td></tr>
                     <tr><th class="text-muted">البرنامج</th><td>{{$booking->program->name ?? 'حجز خاص'}}</td></tr>
                     <tr><th class="text-muted">الأفراد</th><td>{{$booking->adults}} بالغ + {{$booking->children}} طفل</td></tr>
                     <tr><th class="text-muted">المدة</th><td>{{$booking->start_date?->format('Y-m-d') ?? '—'}} : {{$booking->end_date?->format('Y-m-d') ?? '—'}}</td></tr>
                  </table>
               </div>
               <div class="col-md-6">
                  <table class="table table-sm table-borderless mb-0">
                     <tr><th class="text-muted" style="width:35%">الموظف</th><td>{{$booking->owner->name ?? ''}}</td></tr>
                     <tr><th class="text-muted">تاريخ الإنشاء</th><td>{{$booking->created_at?->format('Y-m-d H:i')}}</td></tr>
                     @if($booking->confirmed_at)<tr><th class="text-muted">التأكيد</th><td>{{$booking->confirmed_at->format('Y-m-d H:i')}} - {{$users[$booking->confirmed_by] ?? ''}}</td></tr>@endif
                     @if($booking->cancelled_at)<tr><th class="text-muted">الإلغاء</th><td class="text-danger">{{$booking->cancelled_at->format('Y-m-d H:i')}} - {{$users[$booking->cancelled_by] ?? ''}} {{$booking->cancel_reason ? '(' . $booking->cancel_reason . ')' : ''}}</td></tr>@endif
                     @if($booking->notes)<tr><th class="text-muted">ملاحظات</th><td>{{$booking->notes}}</td></tr>@endif
                  </table>
               </div>
            </div>

            {{-- figures: the ledger (a draft: the items, as an estimate) --}}
            <div class="row g-2 mb-3">
               @foreach([
                  ['إجمالي البيع', $sale], ['إجمالي التكلفة', $cost], ['الربح', $sale - $cost],
                  ['المدفوع من العميل', $s['customer_paid']], [$s['customer_remaining'] < 0 ? 'مستحق للعميل' : 'المتبقي على العميل', abs($s['customer_remaining'])],
                  ['مستحق للموردين', $s['supplier_payable']], ['المدفوع للموردين', $s['supplier_paid']], ['المتبقي للموردين', $s['supplier_remaining']],
               ] as [$label, $v])
               <div class="col-6 col-md-3 col-xl"><div class="border rounded p-2 h-100"><small class="text-muted">{{$label}}</small><br><strong>{{$money($v)}}</strong></div></div>
               @endforeach
            </div>
            @if($draft)<p class="text-muted small">مسودة: الأرقام تقديرية من الخدمات، ولا يسجل شيء في كشوف الحساب قبل التأكيد.</p>@endif

            <h6>الخدمات</h6>
            <div class="table-responsive mb-3">
               <table class="table table-bordered table-striped align-middle text-nowrap">
                  <thead class="table-light">
                     <tr><th>النوع</th><th>الوصف</th><th>المورد</th><th>التاريخ</th><th>التفاصيل</th><th>الكمية</th><th>التكلفة</th><th>البيع</th><th>الربح</th><th>الحالة</th><th>--</th></tr>
                  </thead>
                  <tbody>
                     @foreach($booking->items as $it)
                     <tr @class(['table-secondary' => !$it->isActive()])>
                        <td>{{\App\Models\TourismBookingItem::typeLabel($it->service_type)}}</td>
                        <td>{{$it->description}}@if($it->notes)<div class="text-muted fs-11">{{$it->notes}}</div>@endif</td>
                        <td>{{$it->supplier->name ?? ''}}</td>
                        <td>{{$it->dateText() ?: '—'}}</td>
                        <td class="text-wrap">{{$it->detailsText() ?: '—'}}</td>
                        <td>{{$it->quantityText()}}</td>
                        <td>{{$money($it->total_cost)}}<div class="text-muted fs-11">{{$money($it->unit_cost)}} للوحدة</div></td>
                        <td>{{$money($it->total_sale)}}<div class="text-muted fs-11">{{$money($it->unit_price)}} للوحدة</div></td>
                        <td><strong>{{$money($it->total_sale - $it->total_cost)}}</strong></td>
                        <td>
                           @if($it->isActive())<span class="badge bg-success my_badge">فعالة</span>
                           @else
                              <span class="badge bg-danger my_badge">ملغاة</span>
                              <div class="fs-11">غرامة المورد {{$money($it->cancel_cost)}} · رسوم العميل {{$money($it->cancel_fee)}}</div>
                           @endif
                        </td>
                        <td>
                           @if($it->isActive() && $booking->isConfirmed())
                           @can('tourism.cancel')
                           <div class="ey-row-actions"><button type="button" class="btn btn-soft-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#tb-cancel-item-{{$it->id}}" aria-expanded="false"><i class="ri-close-circle-line"></i><span>إلغاء الخدمة</span></button></div>
                           @endcan
                           @endif
                        </td>
                     </tr>
                     @if($it->isActive() && $booking->isConfirmed())
                     @can('tourism.cancel')
                     <tr class="collapse" id="tb-cancel-item-{{$it->id}}">
                        <td colspan="11">
                           <form method="POST" action="{{route('site.tourism_bookings_cancel_item', [$booking->id, $it->id])}}" class="row g-2 align-items-end" onsubmit="return confirm('إلغاء هذه الخدمة؟')">
                              @csrf
                              <div class="col-md-3"><label class="form-label mb-1">غرامة المورد (تكلفة تبقى علينا)</label><input type="number" step="0.01" min="0" max="{{$it->total_cost}}" class="form-control" name="cancel_cost" value="0"></div>
                              <div class="col-md-3"><label class="form-label mb-1">رسوم إلغاء على العميل</label><input type="number" step="0.01" min="0" max="{{$it->total_sale}}" class="form-control" name="cancel_fee" value="0"></div>
                              <div class="col-md-3"><div class="ey-filter-actions"><button class="btn btn-danger"><i class="ri-close-circle-line"></i><span>تأكيد إلغاء الخدمة</span></button></div></div>
                           </form>
                        </td>
                     </tr>
                     @endcan
                     @endif
                     @endforeach
                  </tbody>
               </table>
            </div>

            @if(!$draft)
            <h6>الموردين</h6>
            <div class="table-responsive mb-3">
               <table class="table table-bordered align-middle text-nowrap">
                  <thead class="table-light"><tr><th>المورد</th><th>المستحق</th><th>المدفوع</th><th>المتبقي</th><th>--</th></tr></thead>
                  <tbody>
                     @forelse($s['suppliers'] as $sup)
                     <tr>
                        <td>{{$sup['name']}}</td><td>{{$money($sup['payable'])}}</td><td>{{$money($sup['paid'])}}</td><td><strong>{{$money($sup['remaining'])}}</strong></td>
                        <td>
                           @if($finance && $sup['remaining'] > 0.005)
                           <div class="ey-row-actions"><button type="button" class="btn btn-soft-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#tb-pay-{{$sup['id']}}" aria-expanded="false"><i class="ri-bank-card-line"></i><span>سند دفع للمورد</span></button></div>
                           @endif
                        </td>
                     </tr>
                     @if($finance && $sup['remaining'] > 0.005)
                     <tr class="collapse" id="tb-pay-{{$sup['id']}}">
                        <td colspan="5">
                           <form method="POST" action="{{route('site.tourism_bookings_pay_supplier', $booking->id)}}" class="row g-2 align-items-end tb-money-form">
                              @csrf
                              <input type="hidden" name="supplier_id" value="{{$sup['id']}}">
                              @include('tourism.bookings._money_fields', ['max' => $sup['remaining']])
                              <div class="col-md-4">
                                 <label class="form-label mb-1">الخدمة (اختياري)</label>
                                 <select class="form-select" name="item_id"><option value="">-- كل خدمات المورد --</option>
                                    @foreach($booking->items->where('supplier_id', $sup['id']) as $it)<option value="{{$it->id}}">{{$it->title()}}</option>@endforeach
                                 </select>
                              </div>
                              <div class="col-md-4 col-xl-2"><div class="ey-filter-actions"><button class="btn btn-primary"><i class="ri-save-line"></i><span>تسجيل سند الدفع</span></button></div></div>
                           </form>
                        </td>
                     </tr>
                     @endif
                     @empty
                     <tr><td colspan="5" class="text-center">لا توجد مستحقات للموردين</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>

            <h6>مدفوعات العميل</h6>
            {{-- a trigger opens its form (outline); the form's own submit is the solid button --}}
            <div class="ey-action-group mb-2">
               @if($canReceive && $s['customer_remaining'] > 0.005)
               <button type="button" class="btn btn-outline-success" data-bs-toggle="collapse" data-bs-target="#tb-receive" aria-expanded="false"><i class="ri-hand-coin-line"></i><span>سند قبض من العميل</span></button>
               @endif
               @if($finance && $s['customer_remaining'] < -0.005)
               <button type="button" class="btn btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#tb-refund" aria-expanded="false"><i class="ri-refund-2-line"></i><span>رد مبلغ للعميل</span></button>
               @endif
            </div>
            @if($canReceive && $s['customer_remaining'] > 0.005)
            <div class="collapse mb-3" id="tb-receive">
               <form method="POST" action="{{route('site.tourism_bookings_receive', $booking->id)}}" class="row g-2 align-items-end border rounded p-2 tb-money-form">
                  @csrf
                  @include('tourism.bookings._money_fields', ['max' => $s['customer_remaining']])
                  <div class="col-md-4 col-xl-2"><div class="ey-filter-actions"><button class="btn btn-success"><i class="ri-save-line"></i><span>تسجيل سند القبض</span></button></div></div>
               </form>
            </div>
            @endif
            @if($finance && $s['customer_remaining'] < -0.005)
            <div class="collapse mb-3" id="tb-refund">
               <form method="POST" action="{{route('site.tourism_bookings_refund', $booking->id)}}" class="row g-2 align-items-end border rounded p-2 tb-money-form">
                  @csrf
                  @include('tourism.bookings._money_fields', ['max' => -$s['customer_remaining']])
                  <div class="col-md-4 col-xl-2"><div class="ey-filter-actions"><button class="btn btn-warning"><i class="ri-save-line"></i><span>تسجيل رد المبلغ</span></button></div></div>
               </form>
            </div>
            @endif

            <h6>السندات المرتبطة</h6>
            <div class="table-responsive mb-3">
               <table class="table table-bordered align-middle text-nowrap">
                  <thead class="table-light"><tr><th>رقم السند</th><th>النوع</th><th>الحساب</th><th>التاريخ</th><th>المبلغ</th><th>البيان</th><th>الحالة</th><th>--</th></tr></thead>
                  <tbody>
                     @forelse($s['vouchers'] as $v)
                     <tr @class(['table-secondary' => $v->reversed])>
                        <td>{{$v->bond->es_id}}</td>
                        <td>{{$v->label}}</td>
                        <td>{{$v->kind === 'supplier' ? ($s['suppliers'][$v->account]['name'] ?? '#' . $v->account) : ($booking->customer->name ?? '')}}</td>
                        <td>{{$v->bond->crt_date}}</td>
                        <td>{{$money($v->bond->amount)}}</td>
                        <td class="text-wrap">{{$v->bond->info}}</td>
                        <td>@if($v->reversed)<span class="badge bg-secondary my_badge">معكوس</span>@else<span class="badge bg-success my_badge">فعال</span>@endif</td>
                        <td>
                           @if($finance && !$v->reversed)
                           <div class="ey-row-actions">
                              <form method="POST" action="{{route('site.tourism_bookings_reverse_voucher', [$booking->id, $v->bond->id])}}" onsubmit="return confirm('عكس هذا السند؟ يتم استرداد المبلغ للخزنة/البنك وإضافة قيود عكسية دون حذف السند.')">@csrf<button class="btn btn-soft-danger btn-sm"><i class="ri-arrow-go-back-line"></i><span>عكس السند</span></button></form>
                           </div>
                           @endif
                        </td>
                     </tr>
                     @empty
                     <tr><td colspan="8" class="text-center">لا توجد سندات</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
            @endif

            <h6>الأفراد ({{$booking->passengers->count()}})</h6>
            <div class="table-responsive mb-3">
               <table class="table table-bordered table-sm align-middle">
                  <thead class="table-light"><tr><th>#</th><th>الاسم</th><th>النوع</th><th>السن</th><th>رقم الهوية / الجواز</th><th>الهاتف</th><th>الغرفة</th><th>ملاحظات</th></tr></thead>
                  <tbody>
                     @forelse($booking->passengers as $n => $p)
                     <tr><td>{{$n + 1}}</td><td>{{$p->name}}</td><td>{{\App\Models\TourismBookingPassenger::TYPES[$p->type] ?? $p->type}}</td><td>{{$p->age}}</td><td>{{$p->id_number}}</td><td>{{$p->phone}}</td><td>{{$p->room_ref}}</td><td>{{$p->notes}}</td></tr>
                     @empty
                     <tr><td colspan="8" class="text-center">لم يتم إدخال الأفراد</td></tr>
                     @endforelse
                  </tbody>
               </table>
            </div>

            @if($ledger->isNotEmpty())
            <h6>القيود في كشوف الحساب</h6>
            <div class="table-responsive mb-3">
               <table class="table table-bordered table-sm align-middle">
                  <thead class="table-light"><tr><th>التاريخ</th><th>العملية</th><th>الحساب</th><th>مدين</th><th>دائن</th><th>التفاصيل</th><th>بواسطة</th></tr></thead>
                  <tbody>
                     @php($accountNames = \App\Models\Supplier::whereIn('id', $ledger->pluck('supp_client_id'))->pluck('name', 'id'))
                     @foreach($ledger as $row)
                     @php($m = \App\Services\InvoicePassengerLedger::marker($row))
                     <tr>
                        <td>{{$row->crt_date}}</td>
                        <td>{{\App\Services\InvoicePassengerLedger::kindLabel($row)}}</td>
                        <td>{{$accountNames[$row->supp_client_id] ?? '#' . $row->supp_client_id}} <span class="text-muted fs-11">({{($m['role'] ?? '') === 'customer' ? 'عميل' : 'مورد'}})</span></td>
                        <td>{{(float) $row->debit_balance ? $money($row->debit_balance) : ''}}</td>
                        <td>{{(float) $row->credit_balance ? $money($row->credit_balance) : ''}}</td>
                        <td class="fs-11">@foreach($m['lines'] ?? [] as $l)<div>{{$l['name']}}: {{$l['debit'] !== null ? 'مدين ' . $money($l['debit']) : 'دائن ' . $money($l['credit'])}}</div>@endforeach</td>
                        <td>{{$users[$row->added_by] ?? ''}}</td>
                     </tr>
                     @endforeach
                  </tbody>
               </table>
            </div>
            @endif

            <h6>سجل الحجز</h6>
            <ul class="list-unstyled small mb-0">
               @forelse($logs as $log)
               <li>{{$log->log_date}} — {{$log->log_txt}} — {{$users[$log->log_by] ?? ''}}</li>
               @empty
               <li class="text-muted">لا يوجد</li>
               @endforelse
            </ul>
         </div>
      </div>
   </div>
</div>
<script>
   document.querySelectorAll('.tb-money-form').forEach(function (f) {
      var way = f.querySelector('.tb-money-way'), bank = f.querySelector('.tb-bank');
      var sync = function () { bank.style.display = way.value === '2' ? '' : 'none'; };
      way.addEventListener('change', sync); sync();
   });
</script>
@endsection
