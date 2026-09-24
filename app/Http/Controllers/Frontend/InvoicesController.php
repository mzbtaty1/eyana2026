<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use Illuminate\Support\Carbon;
use App\Models\{Supplier, Invoice, TicketUser, TicketVendor, Airline, AccountStatement , Log , TransactionBalance,Storage,StorageStatement,Bond , User, Bank, BankStatement};
use Yajra\DataTables\Facades\DataTables;
use App\Services\InvoicePassengerLedger;
use App\Services\CounterPayments;
use App\Services\CounterInvoiceDeletion;

use DB;

class InvoicesController extends Controller
{

    public function getInvoices(Request $request)
{
//  if ($request->ajax()) {
  
// }

//     // لو مش Ajax — رجع الـ View
//         return view('invoices.ajax');
    

//   $query = Invoice::with([
//         'ticketVendors.supplier',
//         'beneficiaries',
//         'users',
//         'creator',
//         'accountStatements'
//     ]);

    if(Auth::user()->account_type == 2){

                $query = Invoice::with([
        'ticketVendors.supplier',
        'beneficiaries',
        'users',
        'creator',
        'accountStatements'
    ]);
        }else{
        


                $query = Invoice::with([
        'ticketVendors.supplier',
        'beneficiaries',
        'users',
        'creator',
        'accountStatements'
    ])->where('invoice_create_by', Auth::user()->id);

        }


    if ($search = $request->input('search.value')) {
        $query->where(function ($q) use ($search) {
            $q->where('id', 'LIKE', "%{$search}%")
              ->orWhere('invoice_number', 'LIKE', "%{$search}%")
              ->orWhere('total', 'LIKE', "%{$search}%")
              ->orWhereHas('users', function ($q2) use ($search) {
                  $q2->where('name', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('beneficiaries', function ($q3) use ($search) {
                  $q3->where('name', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('ticketVendors.supplier', function ($q4) use ($search) {
                  $q4->where('name', 'LIKE', "%{$search}%");
              });
        });
    }

    $total = $query->count();

    $length = $request->input('length') > 0 ? $request->input('length') : 10000;
    $page = floor($request->input('start') / $length) + 1;

    $invoices = $query
        ->orderBy('invoice_date', 'desc')
        ->paginate($length, ['*'], 'page', $page);

    return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => $total,
        'recordsFiltered' => $total,
        'data' => $invoices->items(),
    ]);
    
}
  
    
public function getInvoices3Months(Request $request)
{
    if (Auth::user()->account_type == 2) {
        $query = Invoice::with([
            'ticketVendors.supplier',
            'beneficiaries',
            'users',
            'creator',
            // فلترة accountStatements بحيث transaction_type = 1
            'accountStatements' => function ($q) {
                $q->where('transaction_type', 1);
            },
        ]);
    } else {
        $query = Invoice::with([
            'ticketVendors.supplier',
            'beneficiaries',
            'users',
            'creator',
            'accountStatements' => function ($q) {
                $q->where('transaction_type', 1);
            },
        ])->where('invoice_create_by', Auth::user()->id);
    }

    // فلترة لآخر 3 شهور فقط
    $query->whereDate('invoice_date', '>=', Carbon::now()->subMonths(3));
    $query->where('invoice_shared', '!=', 1);

    // لو فيه بحث
    if ($search = $request->input('search.value')) {
        $query->where(function ($q) use ($search) {
            $q->where('id', 'LIKE', "%{$search}%")
              ->orWhere('invoice_number', 'LIKE', "%{$search}%")
              ->orWhere('total', 'LIKE', "%{$search}%")
              ->orWhereHas('users', function ($q2) use ($search) {
                  $q2->where('name', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('beneficiaries', function ($q3) use ($search) {
                  $q3->where('name', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('ticketVendors.supplier', function ($q4) use ($search) {
                  $q4->where('name', 'LIKE', "%{$search}%");
              });
        });
    }

    // نجمع العدد قبل التحديد
    $total = $query->count();

    // هنا نجبره يرجع 3 فواتير فقط
    $invoices = $query
        ->orderBy('updated_at', 'desc')
        ->get();

    // Refund totals per account from the already loaded rows (includes edits of the
    // refund): refund_supplier_total = «مرتجع لنا من المورد», refund_client_total = «مسترد للعميل».
    foreach ($invoices as $invoice) {
        if (str_starts_with((string) $invoice->es_id, 'FLY-RD')) {
            $t = InvoicePassengerLedger::refundTotalsFromRows($invoice->accountStatements,
                optional($invoice->ticketVendors->first())->vendor_id, $invoice->invoice_beneficiaries);
            $invoice->setAttribute('refund_supplier_total', $t['supplier']);
            $invoice->setAttribute('refund_client_total', $t['client']);
        }
    }

    return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => $total,
        'recordsFiltered' => $total,
        'data' => $invoices,
    ]);
}




    /**
     * Server-side DataTables data for the main invoices list ("كل الفواتير"):
     * ALL invoices (normal + shared, every date; own invoices unless account_type 2),
     * optionally filtered by invoice date (date_from / date_to), and only the
     * requested page is loaded:
     * every displayed value is computed here with a fixed number of queries per
     * page (no per-row lookups, no multi-MB payload). Values are computed exactly
     * as the page's former client-side render functions did.
     */
    public function invoicesListData(Request $request)
    {
        $base = DB::table('invoices as i');
        if (Auth::user()->account_type != 2) {
            $base->where('i.invoice_create_by', Auth::user()->id);
        }
        $recordsTotal = (clone $base)->count();

        // optional date filter on the invoice date
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date_from'))) {
            $base->whereDate('i.invoice_date', '>=', $request->input('date_from'));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date_to'))) {
            $base->whereDate('i.invoice_date', '<=', $request->input('date_to'));
        }

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            // Resolve the related matches once (ticket_system_id is an unindexed text
            // column, so correlated EXISTS per invoice would be very slow).
            $paxSystems = DB::table('ticket_users')->where(fn ($w) => $w->where('client_name', 'like', $like)
                ->orWhere('client_booking_id', 'like', $like)->orWhere('client_ticket_id', 'like', $like))
                ->distinct()->pluck('ticket_system_id')->all();
            $vendorSystems = DB::table('ticket_vendors as tv')->join('suppliers as sv', 'sv.id', '=', 'tv.vendor_id')
                ->where('sv.name', 'like', $like)->distinct()->pluck('tv.ticket_system_id')->all();
            $beneficiaryIds = DB::table('suppliers')->where('name', 'like', $like)->pluck('id')->all();
            $creatorIds = DB::table('users')->where('name', 'like', $like)->pluck('id')->all();
            $base->where(function ($q) use ($like, $paxSystems, $vendorSystems, $beneficiaryIds, $creatorIds) {
                $q->where('i.es_id', 'like', $like)
                  ->orWhere('i.invoice_date', 'like', $like)
                  ->orWhere('i.invoice_travel_date', 'like', $like)
                  ->orWhere('i.from_location', 'like', $like)
                  ->orWhere('i.to_location', 'like', $like);
                if ($paxSystems || $vendorSystems) {
                    $q->orWhereIn('i.ticket_system_id', array_values(array_unique(array_merge($paxSystems, $vendorSystems))));
                }
                if ($beneficiaryIds) {
                    $q->orWhereIn('i.invoice_beneficiaries', $beneficiaryIds);
                }
                if ($creatorIds) {
                    $q->orWhereIn('i.invoice_create_by', $creatorIds);
                }
            });
        }
        $recordsFiltered = (clone $base)->count();

        $orderable = [0 => 'i.id', 2 => 'i.invoice_date', 3 => 'i.invoice_travel_date'];
        $col = (int) $request->input('order.0.column', 2);
        $dir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $base->orderBy($orderable[$col] ?? 'i.invoice_date', $dir)->orderBy('i.id', 'desc');

        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 500) : 25;
        $rows = $base->select('i.id', 'i.es_id', 'i.ticket_system_id', 'i.invoice_ticket_file', 'i.invoice_date', 'i.created_at', 'i.invoice_travel_date',
                'i.from_location', 'i.to_location', 'i.invoice_beneficiaries', 'i.invoice_money_pay', 'i.invoice_status', 'i.invoice_shared',
                'i.invoice_account_1', 'i.invoice_account_2', 'i.invoice_create_by')
            ->skip(max(0, (int) $request->input('start', 0)))->take($length)->get();

        // batched lookups for this page only
        $systems = $rows->pluck('ticket_system_id')->filter()->unique()->values();
        $vendorNames = DB::table('ticket_vendors as tv')->join('suppliers as s', 's.id', '=', 'tv.vendor_id')->whereIn('tv.ticket_system_id', $systems)
            ->orderBy('tv.id')->get(['tv.ticket_system_id', 'tv.vendor_id', 's.name'])->groupBy('ticket_system_id');
        $passengers = TicketUser::whereIn('ticket_system_id', $systems)->orderBy('id')
            ->get(['ticket_system_id', 'client_name', 'client_booking_id', 'client_ticket_id', 'client_net_pice', 'client_bought_price'])->groupBy('ticket_system_id');
        $supplierNames = DB::table('suppliers')->whereIn('id', $rows->pluck('invoice_beneficiaries')->filter()->unique())->pluck('name', 'id');
        $userNames = DB::table('users')->whereIn('id', $rows->pluck('invoice_create_by')->merge($rows->pluck('invoice_account_1'))->merge($rows->pluck('invoice_account_2'))->filter()->unique())->pluck('name', 'id');
        $refundRows = AccountStatement::whereIn('es_id', $rows->pluck('es_id')->filter(fn ($e) => str_starts_with((string) $e, 'FLY-RD')))
            ->where('transaction_type', 1)->orderBy('id')->get(['es_id', 'supp_client_id', 'debit_balance', 'credit_balance'])->groupBy('es_id');
        // rows carrying an operation marker (edits dated later, refund mode) for this page
        $markedRows = AccountStatement::whereIn('es_id', $rows->pluck('es_id')->filter())->where('transaction_type', 1)
            ->whereNotNull('description')->orderBy('id')->get(['es_id', 'description', 'crt_date'])->groupBy('es_id');

        // payment status / paid / remaining: Counter Customer invoices only (batched for the page)
        $counterSummaries = CounterPayments::summaries($rows);

        $data = $rows->map(function ($r) use ($vendorNames, $passengers, $supplierNames, $userNames, $refundRows, $markedRows, $counterSummaries) {
            $pax = $passengers->get($r->ticket_system_id, collect());
            $isRefund = str_starts_with((string) $r->es_id, 'FLY-RD');
            $sumNet = $pax->sum(fn ($u) => (float) ($u->client_net_pice ?? 0));
            $sumBought = $pax->sum(fn ($u) => (float) ($u->client_bought_price ?? 0));
            if ($isRefund) {
                // refund: «مسترد للعميل» = client credit, «مرتجع لنا من المورد» = supplier debit,
                // summed per account (includes any adjustment rows of the refund).
                $st = $refundRows->get($r->es_id, collect());
                $vendorId = (int) optional($vendorNames->get($r->ticket_system_id, collect())->first())->vendor_id;
                $cost = (float) $st->filter(fn ($x) => (int) $x->supp_client_id === (int) $r->invoice_beneficiaries)
                    ->sum(fn ($x) => (float) $x->credit_balance - (float) $x->debit_balance);
                $sale = (float) $st->filter(fn ($x) => (int) $x->supp_client_id === $vendorId)
                    ->sum(fn ($x) => (float) $x->debit_balance - (float) $x->credit_balance);
            } else {
                $cost = $sumNet;
                $sale = $sumBought;
            }
            // operation kind of this invoice, from explicit data (invoice number prefix,
            // refund marker) -- never from amounts
            $marked = $markedRows->get($r->es_id, collect());
            $markers = $marked->map(fn ($x) => InvoicePassengerLedger::marker($x))->filter();
            $refundMarker = $markers->first(fn ($m) => ($m['kind'] ?? '') === 'refund');
            $reissueMarker = $markers->first(fn ($m) => ($m['kind'] ?? '') === 'reissue');
            if ($isRefund) {
                // one operation: refund of one passenger or of the whole invoice
                $op = 'refund';
                $opLabel = !$refundMarker ? 'مرتجع'
                    : (($refundMarker['mode'] ?? '') === 'single' ? 'مرتجع - ' . ($refundMarker['lines'][0]['name'] ?? '') : 'مرتجع كامل للفاتورة');
            } elseif ($reissueMarker || str_starts_with((string) $r->es_id, 'FLY-RS')) {
                // new invoice from the re-issue workflow (marker since now; its number prefix before)
                $op = 'reissue';
                $opLabel = (int) $r->invoice_shared === 1 ? 'إعادة إصدار مشتركة' : 'إعادة إصدار';
            } else {
                // original invoice; an edit (correction) keeps it as it is -- see 'edits' below
                $op = 'sale';
                $opLabel = (int) $r->invoice_shared === 1 ? 'بيع تذكرة مشتركة' : 'بيع تذكرة';
            }
            $editRows = $marked->filter(fn ($x) => (InvoicePassengerLedger::marker($x)['kind'] ?? '') === 'edit');

            return [
                'id' => $r->id,
                'es_id' => $r->es_id,
                'op' => $op,
                'op_label' => $opLabel,
                'edits' => $editRows->count(),
                'last_edit' => $editRows->isEmpty() ? null : (string) $editRows->last()->crt_date,
                'invoice_ticket_file' => $r->invoice_ticket_file,
                'is_refund' => $isRefund,
                'is_shared' => (int) $r->invoice_shared === 1,
                'shared_owner' => $userNames[$r->invoice_account_1] ?? null,
                'shared_seller' => $userNames[$r->invoice_account_2] ?? null,
                'invoice_date' => $r->invoice_date ?: $r->created_at,
                'invoice_travel_date' => $r->invoice_travel_date,
                'vendors' => $vendorNames->get($r->ticket_system_id, collect())->pluck('name')->filter()->implode(' / '),
                'beneficiary' => $supplierNames[$r->invoice_beneficiaries] ?? '',
                'passengers' => $pax->map(fn ($u) => ['name' => $u->client_name, 'booking' => $u->client_booking_id, 'ticket' => $u->client_ticket_id])->values(),
                'locations' => trim((string) $r->from_location) !== '' || trim((string) $r->to_location) !== '' ? $r->from_location . ' / ' . $r->to_location : '',
                'cost' => number_format($cost, 2, '.', ''),
                'sale' => number_format($sale, 2, '.', ''),
                'profit' => number_format($sale - $cost, 2, '.', ''),
                'creator' => $userNames[$r->invoice_create_by] ?? '-',
                'counter' => $counterSummaries[$r->id] ?? null,          // null = no payment status (not a counter invoice)
                'invoice_status' => (int) ($r->invoice_status ?? 0),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function ajax(Request $request){

        if ($request->ajax()) {
            // بناء الاستعلام الأساسي
            $query = Invoice::query()
                ->select([
                    'invoices.*',
                    DB::raw('(SELECT GROUP_CONCAT(suppliers.name SEPARATOR ", ") 
                              FROM ticket_vendors 
                              JOIN suppliers ON ticket_vendors.vendor_id = suppliers.id 
                              WHERE ticket_vendors.ticket_system_id = invoices.ticket_system_id) AS vendor_info'),
                    DB::raw('(SELECT suppliers.name 
                              FROM suppliers 
                              WHERE suppliers.id = invoices.invoice_beneficiaries) AS beneficiary_info'),
                ])
                ->where('invoice_shared', '!=', 1);
    
            // تصفية البيانات بناءً على نوع المستخدم
            if (Auth::user()->account_type != 2) {
                $query->where('invoice_create_by', Auth::user()->id);
            }
    
            // البحث
            if ($request->has('search') && !empty($request->search['value'])) {
                $search = $request->search['value'];
                $query->where(function ($q) use ($search) {
                    $q->where('invoices.invoice_date', 'LIKE', "%{$search}%")
                      ->orWhere('invoices.invoice_travel_date', 'LIKE', "%{$search}%")
                      ->orWhereRaw('EXISTS (SELECT 1 FROM ticket_vendors 
                                              JOIN suppliers ON ticket_vendors.vendor_id = suppliers.id 
                                              WHERE ticket_vendors.ticket_system_id = invoices.ticket_system_id 
                                              AND suppliers.name LIKE ?)', ["%{$search}%"])
                      ->orWhereRaw('EXISTS (SELECT 1 FROM suppliers 
                                              WHERE suppliers.id = invoices.invoice_beneficiaries 
                                              AND suppliers.name LIKE ?)', ["%{$search}%"]);
                });
            }
    
            // Pagination
            $start = $request->start;
            $length = $request->length;
            $invoices = $query->skip($start)->take($length)->get();
    
            // العدد الإجمالي للسجلات
            $totalRecords = Invoice::count();
            $filteredRecords = $query->count();
    
            // تنسيق البيانات لـ DataTables
            $data = [];
            foreach ($invoices as $invoice) {
                $data[] = [
                    'DT_RowIndex' => ++$start,
                    'invoice_date' => $invoice->invoice_date,
                    'invoice_travel_date' => $invoice->invoice_travel_date,
                    'vendor_info' => $invoice->vendor_info,
                    'beneficiary_info' => $invoice->beneficiary_info,
                    'cost' => $invoice->cost,
                    'sale' => $invoice->sale,
                    'location' => $invoice->from_location . ' - ' . $invoice->to_location,
                    'passenger_info' => $this->getPassengerInfo($invoice->ticket_system_id),
                    'booking_info' => $this->getBookingInfo($invoice->ticket_system_id),
                    'profit' => $invoice->profit,
                    'invoice_type' => $this->getInvoiceType($invoice->invoice_section),
                    'invoice_status' => $this->getInvoiceStatus($invoice->invoice_money_pay, $invoice->cost),
                    'created_by' => $this->getCreatedBy($invoice->invoice_create_by),
                    'description' => $invoice->description,
                    'action' => '<a href="' . route('site.invoices_show', $invoice->id) . '" class="btn btn-primary btn-sm">View</a>',
                ];
            }
    
            return response()->json([
                'draw' => $request->draw, // رقم الطلب (ضروري لتجنب مشاكل التحديث)
                'recordsTotal' => $totalRecords, // العدد الإجمالي للسجلات
                'recordsFiltered' => $filteredRecords, // العدد الإجمالي للسجلات بعد التصفية
                'data' => $data, // البيانات المطلوبة
            ]);
        }
        return view('invoices.ajax');



    }

    public function lite(Request $request){

        // if ($request->ajax()) {
        //     // بناء الاستعلام الأساسي
        //     $query = Invoice::query()
        //         ->select([
        //             'invoices.*',
        //             DB::raw('(SELECT GROUP_CONCAT(suppliers.name SEPARATOR ", ") 
        //                       FROM ticket_vendors 
        //                       JOIN suppliers ON ticket_vendors.vendor_id = suppliers.id 
        //                       WHERE ticket_vendors.ticket_system_id = invoices.ticket_system_id) AS vendor_info'),
        //             DB::raw('(SELECT suppliers.name 
        //                       FROM suppliers 
        //                       WHERE suppliers.id = invoices.invoice_beneficiaries) AS beneficiary_info'),
        //         ])
        //         ->where('invoice_shared', '!=', 1);
    
        //     // تصفية البيانات بناءً على نوع المستخدم
        //     if (Auth::user()->account_type != 2) {
        //         $query->where('invoice_create_by', Auth::user()->id);
        //     }
    
        //     // البحث
        //     if ($request->has('search') && !empty($request->search['value'])) {
        //         $search = $request->search['value'];
        //         $query->where(function ($q) use ($search) {
        //             $q->where('invoices.invoice_date', 'LIKE', "%{$search}%")
        //               ->orWhere('invoices.invoice_travel_date', 'LIKE', "%{$search}%")
        //               ->orWhereRaw('EXISTS (SELECT 1 FROM ticket_vendors 
        //                                       JOIN suppliers ON ticket_vendors.vendor_id = suppliers.id 
        //                                       WHERE ticket_vendors.ticket_system_id = invoices.ticket_system_id 
        //                                       AND suppliers.name LIKE ?)', ["%{$search}%"])
        //               ->orWhereRaw('EXISTS (SELECT 1 FROM suppliers 
        //                                       WHERE suppliers.id = invoices.invoice_beneficiaries 
        //                                       AND suppliers.name LIKE ?)', ["%{$search}%"]);
        //         });
        //     }
    
        //     // Pagination
        //     $start = $request->start;
        //     $length = $request->length;
        //     $invoices = $query->skip($start)->take($length)->get();
    
        //     // العدد الإجمالي للسجلات
        //     $totalRecords = Invoice::count();
        //     $filteredRecords = $query->count();
    
        //     // تنسيق البيانات لـ DataTables
        //     $data = [];
        //     foreach ($invoices as $invoice) {
        //         $data[] = [
        //             'DT_RowIndex' => ++$start,
        //             'invoice_date' => $invoice->invoice_date,
        //             'invoice_travel_date' => $invoice->invoice_travel_date,
        //             'vendor_info' => $invoice->vendor_info,
        //             'beneficiary_info' => $invoice->beneficiary_info,
        //             'cost' => $invoice->cost,
        //             'sale' => $invoice->sale,
        //             'location' => $invoice->from_location . ' - ' . $invoice->to_location,
        //             'passenger_info' => $this->getPassengerInfo($invoice->ticket_system_id),
        //             'booking_info' => $this->getBookingInfo($invoice->ticket_system_id),
        //             'profit' => $invoice->profit,
        //             'invoice_type' => $this->getInvoiceType($invoice->invoice_section),
        //             'invoice_status' => $this->getInvoiceStatus($invoice->invoice_money_pay, $invoice->cost),
        //             'created_by' => $this->getCreatedBy($invoice->invoice_create_by),
        //             'description' => $invoice->description,
        //             'action' => '<a href="' . route('site.invoices_show', $invoice->id) . '" class="btn btn-primary btn-sm">View</a>',
        //         ];
        //     }
    
        //     return response()->json([
        //         'draw' => $request->draw, // رقم الطلب (ضروري لتجنب مشاكل التحديث)
        //         'recordsTotal' => $totalRecords, // العدد الإجمالي للسجلات
        //         'recordsFiltered' => $filteredRecords, // العدد الإجمالي للسجلات بعد التصفية
        //         'data' => $data, // البيانات المطلوبة
        //     ]);
        // }

        if ($request->ajax()) {
    // بناء الاستعلام الأساسي
    $query = Invoice::query()
        ->select([
            'invoices.*',
            DB::raw('(SELECT GROUP_CONCAT(suppliers.name SEPARATOR ", ") 
                      FROM ticket_vendors 
                      JOIN suppliers ON ticket_vendors.vendor_id = suppliers.id 
                      WHERE ticket_vendors.ticket_system_id = invoices.ticket_system_id) AS vendor_info'),
            DB::raw('(SELECT suppliers.name 
                      FROM suppliers 
                      WHERE suppliers.id = invoices.invoice_beneficiaries) AS beneficiary_info'),
        ])
        ->where('invoice_shared', '!=', 1);

    // تصفية البيانات بناءً على نوع المستخدم
    if (Auth::user()->account_type != 2) {
        $query->where('invoice_create_by', Auth::user()->id);
    }

    // البحث
    if ($request->has('search') && !empty($request->search['value'])) {
        $search = $request->search['value'];
        $query->where(function ($q) use ($search) {
            $q->where('invoices.invoice_date', 'LIKE', "%{$search}%")
              ->orWhere('invoices.invoice_travel_date', 'LIKE', "%{$search}%")
              ->orWhereRaw('EXISTS (SELECT 1 FROM ticket_vendors 
                                      JOIN suppliers ON ticket_vendors.vendor_id = suppliers.id 
                                      WHERE ticket_vendors.ticket_system_id = invoices.ticket_system_id 
                                      AND suppliers.name LIKE ?)', ["%{$search}%"])
              ->orWhereRaw('EXISTS (SELECT 1 FROM suppliers 
                                      WHERE suppliers.id = invoices.invoice_beneficiaries 
                                      AND suppliers.name LIKE ?)', ["%{$search}%"]);
        });
    }

    // Pagination (لكن أقصى حد 3 فقط)
    $start = $request->start ?? 0;
    $length = min($request->length ?? 3, 3); // مهما طلب الداتا تيبلز، مش هيتعدى 3
    $invoices = $query
        ->orderBy('updated_at', 'desc')
        ->skip($start)
        ->take($length)
        ->get();

    // العدد الإجمالي للسجلات
    $totalRecords = Invoice::count();
    $filteredRecords = $query->count();

    // تنسيق البيانات لـ DataTables
    $data = [];
    foreach ($invoices as $invoice) {
        $data[] = [
            'DT_RowIndex' => ++$start,
            'invoice_date' => $invoice->invoice_date,
            'invoice_travel_date' => $invoice->invoice_travel_date,
            'vendor_info' => $invoice->vendor_info,
            'beneficiary_info' => $invoice->beneficiary_info,
            'cost' => $invoice->cost,
            'sale' => $invoice->sale,
            'location' => $invoice->from_location . ' - ' . $invoice->to_location,
            'passenger_info' => $this->getPassengerInfo($invoice->ticket_system_id),
            'booking_info' => $this->getBookingInfo($invoice->ticket_system_id),
            'profit' => $invoice->profit,
            'invoice_type' => $this->getInvoiceType($invoice->invoice_section),
            'invoice_status' => $this->getInvoiceStatus($invoice->invoice_money_pay, $invoice->cost),
            'created_by' => $this->getCreatedBy($invoice->invoice_create_by),
            'description' => $invoice->description,
            'action' => '<a href="' . route('site.invoices_show', $invoice->id) . '" class="btn btn-primary btn-sm">View</a>',
        ];
    }

    return response()->json([
        'draw' => $request->draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data,
    ]);
}


        return view('invoices.lite');



    }


    private function getPassengerInfo($ticketSystemId)
{
    $users = TicketUser::where('ticket_system_id', $ticketSystemId)->get();
    return $users->pluck('client_name')->implode(', ');
}

private function getBookingInfo($ticketSystemId)
{
    $users = TicketUser::where('ticket_system_id', $ticketSystemId)->get();
    return $users->pluck('client_booking_id')->implode(', ');
}

private function getInvoiceType($section)
{
    $types = [
        1 => 'فواتير الطيران',
        2 => 'فواتير تأشيرات',
        3 => 'فواتير سياحه داخليه',
        4 => 'فواتير سياحه خارجيه',
        5 => 'فواتير سياحه دينيه',
        6 => 'فواتير تأمينات السفر',
        7 => 'فواتير تحاليل السفر',
        8 => 'فواتير نقل سياحى',
    ];
    return $types[$section] ?? 'غير معروف';
}

private function getInvoiceStatus($moneyPay, $cost)
{
    if ($moneyPay == 0) {
        return '<span class="badge bg-dark my_badge">لم يتم السداد</span>';
    } elseif ($moneyPay < $cost) {
        return '<span class="badge bg-secondary my_badge">سداد جزئي</span>';
    } else {
        return '<span class="badge bg-success my_badge">تم السداد كامل</span>';
    }
}

private function getCreatedBy($userId)
{
    $user = User::find($userId);
    return $user ? $user->name : 'غير معروف';
}


    
    public function invoice_info($id){
        
        $invoiceInfo = Invoice::select('*')->where('id',$id)->get();
        abort_if(count($invoiceInfo) == 0,404);
        $invoiceInfo = $invoiceInfo[0];
        
        return view('invoices.invoice_info' , ["invoiceInfo" => $invoiceInfo]);
        
        
    }
    
    // دالة لتحديد نوع الفاتورة بناءً على invoice_section
    
 
    /**
     * Display a listing of the resource.
     */
    public function invoices_approve($id){
//        dd($id);
         $check = Invoice::select('*')->where('id' , $id)->get();
        if(count($check) == 0){
            $st_code = 404;
        }else{
            $st_code = 200;
            $update = Invoice::select('*')->where('id' , $id)->update([
                "invoice_status" => 1,
            ]);
        }
        
        
             return response()->json([
    'status_code' => $st_code,
   
]);

    }
    public function air_cairo_calc(){
        return view('invoices.air_cairo_calc');
    }
    public function index()
    {
        // Eager-loads the same relations the row loop in invoices/all.blade.php
        // needs (previously fetched with a handful of per-row queries each).
        $eagerLoad = ['ticketVendors.supplier', 'beneficiaries', 'users', 'creator', 'mostarad', 'mortaga'];

        if(Auth::user()->account_type == 2){
        $invoices = Invoice::select('*')
            ->with($eagerLoad)
            ->orderBy('id', 'DESC')
            ->where('invoice_shared', '!=', 1)
            ->get();
        }else{

$invoices = Invoice::select('*')
            ->with($eagerLoad)
            ->orderBy('id', 'DESC')
            ->where('invoice_shared', '!=', 1)
            ->where('invoice_create_by', Auth::user()->id)
            ->get();
        }

        return view('invoices.all', [
            "invoices" => $invoices,
        ]);
    }
 public function daily_report()
    {
     if(Auth::user()->account_type == 2){
        $invoices = Invoice::select('*')
            ->orderBy('id', 'DESC')
            ->where('invoice_shared' , 0)
            
            ->where('crt_at', date('Y-m-d'))
            
            ->get();
     }else{
  $invoices = Invoice::select('*')
            ->orderBy('id', 'DESC')
            ->where('invoice_shared' , 0)
            ->where('crt_at', date('Y-m-d'))
      ->where('invoice_create_by', Auth::user()->id)
            ->get();
     }
//     dd($invoices);
        return view('invoices.daily_report', [
            "invoices" => $invoices,
        ]);
    }
 public function full_report()
    {
//        $invoices = Invoice::select('*')
//            ->orderBy('id', 'DESC')
//            ->where('invoice_shared' , 0)
//            ->where('invoice_date', date('Y-m-d'))
//            ->get();
//$shared_invoices = Invoice::select('*')
//            ->orderBy('id', 'DESC')
//            ->where('invoice_shared' , 1)
//            ->where('invoice_date', date('Y-m-d'))
//            ->get();
//
//        return view('invoices.full_report', [
//            "invoices" => $invoices,
//            "shared_invoices" => $shared_invoices,
//        ]);
     
     return view('invoices.full_report');
     
    }
     public function employee_log()
    {
//        $invoices = Invoice::select('*')
//            ->orderBy('id', 'DESC')
//            ->where('invoice_shared' , 0)
//            ->where('invoice_date', date('Y-m-d'))
//            ->get();
//$shared_invoices = Invoice::select('*')
//            ->orderBy('id', 'DESC')
//            ->where('invoice_shared' , 1)
//            ->where('invoice_date', date('Y-m-d'))
//            ->get();
//
//        return view('invoices.full_report', [
//            "invoices" => $invoices,
//            "shared_invoices" => $shared_invoices,
//        ]);
     
     return view('invoices.employee_log');
     
    }
    public function employee_log_view(Request $request){
//        dd($request);
        
     
//        dd($request);
         $invoices = Invoice::select('*')->where('invoice_shared' , 0);
        
        $stpp = 0;
        $user_info = [];
         if(isset($request->user_id)){
             $stpp = 1;
             
                $user_info = User::select('*')->where('id',$request->user_id)->get();
        abort_if(count($user_info) == 0 , 404);
        $user_info = $user_info[0];
             
            $invoices = $invoices->where('invoice_create_by' , $request->user_id);
        }
        

        if(isset($request->invoice_section)){
            $invoices = $invoices->where('invoice_section' , $request->invoice_section);
        }
        
          if(isset($request->date_from) && isset($request->date_to)){
            $invoices = $invoices->whereBetween('invoice_date' , [$request->date_from , $request->date_to]);
        }
        
          if(isset($request->airline_id)){
            $invoices = $invoices->where('invoice_airline' , $request->airline_id);
        }
          if(isset($request->travel_date)){
            $invoices = $invoices->where('invoice_travel_date' , $request->travel_date);
        }
           if(isset($request->invoice_beneficiaries)){
            $invoices = $invoices->where('invoice_beneficiaries' , $request->invoice_beneficiaries);
        }
        
        
        $invoices = $invoices->get();
        return view('invoices.employee_log_view' , [
            "id" => $request->user_id,
            "user_info" => $user_info,
            "invoices"=>$invoices ,
            "date_from"=>$request->date_from ,
            "date_to"=>$request->date_to ,
            "invoice_section"=>$request->invoice_section ,
            "invoice_airline"=>$request->airline_id ,
            "invoice_beneficiaries" => $request->invoice_beneficiaries,
            "vendor_id" => $request->vendor_id,
            "travel_date" => $request->travel_date,
            "stpp" => $stpp,
            "request" => $request,
        ]);
        
        
    }
//    public function employee_log_print($user_id , $invoice_section = null , $date_from = null , $date_to = null , $airline_id = null , $travel_date = null , $invoice_beneficiaries = null , $vendor_id = null){
        
  public function employee_log_print(Request $request){
        
//dd($request->invoice_section);
      
      
      
          $invoices = Invoice::select('*')->where('invoice_shared' , 0);
        
        $stpp = 0;
        $user_info = [];
         if(isset($request->user_id)){
             $stpp = 1;
             
                $user_info = User::select('*')->where('id',$request->user_id)->get();
        abort_if(count($user_info) == 0 , 404);
        $user_info = $user_info[0];
             
            $invoices = $invoices->where('invoice_create_by' , $request->user_id);
        }
      
//         $invoices = Invoice::select('*')->where('invoice_shared' , 0)->where('invoice_create_by',$request->user_id);
//        dd($stpp); 
         

        if(isset($request->invoice_section)){
            $invoices = $invoices->where('invoice_section' , $request->invoice_section);
        }
        
          if(isset($request->date_from) && isset($request->date_to)){
            $invoices = $invoices->whereBetween('invoice_date' , [$request->date_from , $request->date_to]);
        }
        
          if(isset($request->airline_id)){
            $invoices = $invoices->where('invoice_airline' , $request->airline_id);
        }
          if(isset($request->travel_date)){
            $invoices = $invoices->where('invoice_travel_date' , $request->travel_date);
        }
           if(isset($request->invoice_beneficiaries)){
            $invoices = $invoices->where('invoice_beneficiaries' , $request->invoice_beneficiaries);
        }
        
        
        $invoices = $invoices->get();

       return view('invoices.employee_log_print' , [
            "id" => $request->user_id,
            "user_info" => $user_info,
            "invoices"=>$invoices ,
            "date_from"=>$request->date_from ,
            "date_to"=>$request->date_to ,
            "invoice_section"=>$request->invoice_section ,
            "invoice_airline"=>$request->airline_id ,
            "invoice_beneficiaries" => $request->invoice_beneficiaries,
            "vendor_id" => $request->vendor_id,
            "travel_date" => $request->travel_date,
                       "stpp" => $stpp,

        ]);
      
      
    }
    public function full_report_get(Request $request){
        
        
        $invoices = Invoice::select('*')->where('invoice_shared' , 0);
        
        
        if(Auth::user()->account_type == 2){
            
        }else{
          $invoices = $invoices->where('invoice_create_by', Auth::user()->id);  
        }
        
        if(isset($request->invoice_section)){
            $invoices = $invoices->where('invoice_section' , $request->invoice_section);
        }
        
          if(isset($request->date_from) && isset($request->date_to)){
            $invoices = $invoices->whereBetween('invoice_date' , [$request->date_from , $request->date_to]);
        }
        $invoices = $invoices->get();
        return view('invoices.full_report_get' , [
            "invoices"=>$invoices ,
            "date_from"=>$request->date_from ,
            "date_to"=>$request->date_to ,
            "invoice_section"=>$request->invoice_section ,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */

    public function create(Request $request)
    {
        $suppliers = Supplier::select('*')
            ->where('status', 1)
            ->where('acc_type', '!=' , 3)
            ->orderBy('id', 'DESC')
            ->get();
        $my_suppliers = Supplier::select('*')
            ->where('acc_type', 2)
            ->where('acc_type', '!=' , 3)
            ->where('status', 1)
            ->orderBy('id', 'DESC')
            ->get();
        $airlines = Airline::select('*')
            ->orderBy('id', 'DESC')
            ->get();

        // "عميل كونتر": the normal Add Invoice form with the customer fixed to the Counter Customer
        $counterCustomer = null;
        if ($request->route('counter')) {
            $counterCustomer = $suppliers->firstWhere('id', CounterPayments::counterIds()[0] ?? 0);
            if (!$counterCustomer) {
                return redirect()->route('site.invoices_ajax')->withErrors(['msg' => 'حساب عميل كونتر غير موجود أو غير مفعل']);
            }
        }

        return view('invoices.create', [
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
            "counterCustomer" => $counterCustomer,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->hasFile('myPoster')) {
            $imagePath = $request->file('myPoster');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster')->storeAs('pdf_files', $imageNewName, 'public');
        } else {
            $path = "no";
        }
        //
        //
        //
        $system_id = \Str::random(8);
        //
        $createVendors = TicketVendor::create([
            "ticket_system_id" => $system_id,
            "vendor_id" => $request->vendor_id,
            "price" => $request->vendor_cost,
        ]);

        foreach ($request->ticket_info as $key => $value) {
            $createClients = TicketUser::create([
                "crt_at" => date('Y-m-d'),
                "ticket_system_id" => $system_id,
                "client_name" => $value['name'],
                "client_type" => $value['client_type'],
                "client_net_pice" => $value['net_price'],
                "client_bought_price" => $value['bought_price'],
                "client_booking_id" => $value['book_id'],
                "client_ticket_id" => $value['tikcet_id'],
                "client_phone" => $value['client_phone'],
                //                "client_passport_id" => $value['passport_id'],
            ]); 
        }

        $create = Invoice::create([
            "ticket_system_id" => $system_id,
            "invoice_date" => $request->invoice_date,
            "invoice_travel_date" => $request->invoice_travel_date,
            "return_date" => $request->return_date,
            "invoice_airline" => $request->invoice_airline,
            "from_location" => $request->from_location,
            "to_location" => $request->to_location,
            "invoice_group_id" => null,
            "invoice_beneficiaries" => $request->invoice_beneficiaries,
            "invoice_section" => $request->invoice_section,
            "invoice_comments" => $request->invoice_comments,
            "invoice_currency" => $request->invoice_currency,
            "invoice_draft" => $request->invoice_draft,
            "invoice_create_by" => $request->added_user,
            "invoice_ticket_file" => $path,
            "crt_at" => date('Y-m-d'),
        ]);
        
        

        //        dd($create->id);
        $update_es_id = Invoice::select('*')
            ->where('id', $create->id)
            ->update([
                "es_id" => "FLY-A" . $create->id,
            ]);

        
          $save_log = Log::create([
            "log_txt" => "تم اضافة فاتورة " . "FLY-A" . $create->id,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        // Vendor Account Statement
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $total_client_net_pice = 0;
        $total_client_bought_price = 0;
        $text = "";

        foreach ($users as $user) {
            $total_client_net_pice += $user->client_net_pice;
            $total_client_bought_price += $user->client_bought_price;
        }
      
        
        

        $Statement_Vendor = AccountStatement::create([
            "supp_client_id" => $request->vendor_id,
            "invoice_type" => $request->invoice_section,
            "es_id" => "FLY-A" . $create->id,
            "invoice_date" => $request->invoice_date,
            "debit_balance" => 0,
            "credit_balance" => $total_client_net_pice,
            "ledger_net_effect" => -$total_client_net_pice,
            "transaction_txt" => "حجز الرحلة " . "FLY-A" . $create->id,
            "transaction_type" => 1,
            "added_by" => $request->added_user,
            "crt_date" => date('Y-m-d'),
        ]);


 
        

//        exit();
        // invoice_beneficiaries Account Statement

        $invoice_beneficiaries_Vendor = AccountStatement::create([
            "supp_client_id" => $request->invoice_beneficiaries,
            "invoice_type" => $request->invoice_section,
            "es_id" => "FLY-A" . $create->id,
            "invoice_date" => $request->invoice_date,
            "debit_balance" => $total_client_bought_price,
            "credit_balance" => 0,
            "ledger_net_effect" => $total_client_bought_price,
            "transaction_txt" => "حجز الرحلة " . "FLY-A" . $create->id,
            "transaction_type" => 1,
            "added_by" => $request->added_user,
            "crt_date" => date('Y-m-d'),
        ]);
        
        

        
        

        
  
        
        
        

        return redirect()->route('site.invoices_ajax');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $id = (int) $id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        $system_id = $invoice_info->ticket_system_id;

        $vendors = TicketVendor::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $suppliers = Supplier::select('*')
            ->orderBy('id', 'DESC')
            ->where('acc_type', '!=' , 3)
            ->get();
        $my_suppliers = Supplier::select('*')
            ->where('acc_type', 2)
            ->where('acc_type', '!=' , 3)
            ->orderBy('id', 'DESC')
            ->get();
        $airlines = Airline::select('*')
            ->orderBy('id', 'DESC')
            ->get();

        return view('invoices.edit', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }
    public function edit_invoice($id)
    {
        $id = (int) $id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        $system_id = $invoice_info->ticket_system_id;

        $vendors = TicketVendor::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $suppliers = Supplier::select('*')
            ->orderBy('id', 'DESC')
            ->where('acc_type', '!=' , 3)
            ->get();
        $my_suppliers = Supplier::select('*')
            ->where('acc_type', 2)
            ->where('acc_type', '!=' , 3)
            ->orderBy('id', 'DESC')
            ->get();
        $airlines = Airline::select('*')
            ->orderBy('id', 'DESC')
            ->get();

        // Each passenger's [debit share, credit share] as the Account Statement shows them.
        $shares = InvoicePassengerLedger::currentShares($invoice_info, $users);

        return view('invoices.edit_invoice', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "shares" => $shares,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }

    /**
     * Edit ONE passenger/ticket of an invoice: pick the passenger, change only
     * that passenger. The other passengers stay untouched and the invoice's
     * ledger rows move by exactly that passenger's difference.
     */
    public function edit_passenger(Request $request, $id)
    {
        $invoice_info = Invoice::find((int) $id);
        abort_if(!$invoice_info, 404);

        $users = InvoicePassengerLedger::passengers($invoice_info);
        $shares = InvoicePassengerLedger::currentShares($invoice_info, $users);
        $selected = $users->firstWhere('id', (int) $request->query('passenger'));

        return view('invoices.edit_passenger', [
            "invoice_info" => $invoice_info,
            "users" => $users,
            "shares" => $shares,
            "selected" => $selected,
            "isRefund" => InvoicePassengerLedger::isRefund($invoice_info),
        ]);
    }

    public function save_passenger(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'passenger_id' => 'required|integer',
            'name' => 'required|string',
            'client_type' => 'required|in:1,2,3',
            'net_price' => 'required|numeric|min:0',
            'bought_price' => 'required|numeric|min:0',
        ]);

        $invoice_info = Invoice::find((int) $request->id);
        abort_if(!$invoice_info, 404);

        try {
            [$dDebit, $dCredit] = InvoicePassengerLedger::updatePassenger($invoice_info, (int) $request->passenger_id, [
                "client_name" => $request->name,
                "client_type" => $request->client_type,
                "client_net_pice" => $request->net_price,
                "client_bought_price" => $request->bought_price,
                "client_booking_id" => $request->book_id,
                "client_ticket_id" => $request->tikcet_id,
                "client_phone" => $request->client_phone,
            ]);
        } catch (\RuntimeException $e) {
            return Redirect::back()->withErrors(['msg' => 'تعذر تعديل الراكب: ' . $e->getMessage()]);
        }

        Log::create([
            "log_txt" => "تم تعديل الراكب " . $request->name . " في الفاتورة " . $invoice_info->es_id
                . " (فرق المدين: " . number_format($dDebit, 2) . " - فرق الدائن: " . number_format($dCredit, 2) . ")",
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);

        return redirect()->route('site.invoices_edit_passenger', [$invoice_info->id, 'passenger' => (int) $request->passenger_id])
            ->with('success', 'تم تعديل الراكب فقط. فرق المدين: ' . number_format($dDebit, 2) . ' - فرق الدائن: ' . number_format($dCredit, 2));
    }
    
    public function save_update(Request $request){
        $id = (int) $request->id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];
        
        
        
          if ($request->hasFile('myPoster')) {
            $imagePath = $request->file('myPoster');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster')->storeAs('pdf_files', $imageNewName, 'public');
        } else {
            $path = $invoice_info->invoice_ticket_file;
        }
        
        // Whole-invoice edit. Date rule: the original ledger rows (and the invoice's
        // own date) are never changed -- every financial change is booked as
        // adjustment rows dated TODAY, passenger by passenger
        // (see InvoicePassengerLedger::recordEdit).
        DB::transaction(function () use ($request, $invoice_info, $path, $id) {
            $oldAccounts = InvoicePassengerLedger::accounts($invoice_info);
            $before = InvoicePassengerLedger::beginEdit($invoice_info);

            TicketVendor::where('ticket_system_id', $invoice_info->ticket_system_id)->update([
                "vendor_id" => $request->vendor_id,
            ]);

            // Every passenger keeps its own record and amounts. Posted rows carry their
            // TicketUser id and are updated in place; rows without an id are new
            // passengers; passengers no longer posted are removed.
            $existing_tickets = TicketUser::where('ticket_system_id', $invoice_info->ticket_system_id)->get()->keyBy('id');
            $kept_ticket_ids = [];
            foreach ($request->ticket_info as $key => $value) {
                $ticket_data = [
                    "client_name" => $value['name'],
                    "client_type" => $value['client_type'],
                    "client_net_pice" => $value['net_price'],
                    "client_bought_price" => $value['bought_price'],
                    "client_booking_id" => $value['book_id'],
                    "client_ticket_id" => $value['tikcet_id'],
                    "client_phone" => $value['client_phone'],
                ];
                $ticket_id = (int) ($value['id'] ?? 0);
                if ($ticket_id && $existing_tickets->has($ticket_id)) {
                    $existing_tickets[$ticket_id]->update($ticket_data);
                    $kept_ticket_ids[] = $ticket_id;
                } else {
                    $kept_ticket_ids[] = TicketUser::create(array_merge([
                        "crt_at" => date('Y-m-d'),
                        "ticket_system_id" => $invoice_info->ticket_system_id,
                    ], $ticket_data))->id;
                }
            }
            TicketUser::where('ticket_system_id', $invoice_info->ticket_system_id)
                ->whereNotIn('id', $kept_ticket_ids)
                ->delete();

            $after = InvoicePassengerLedger::snapshot($invoice_info);
            $newAccounts = InvoicePassengerLedger::isRefund($invoice_info)
                ? [(int) $request->vendor_id, (int) $request->invoice_beneficiaries]
                : [(int) $request->invoice_beneficiaries, (int) $request->vendor_id];
            InvoicePassengerLedger::recordEdit($invoice_info, $before, $after, $oldAccounts, $newAccounts, $request->invoice_section);

            // TicketVendor.price mirrors the cost total on normal invoices (it is not read anywhere).
            if (!InvoicePassengerLedger::isRefund($invoice_info)) {
                TicketVendor::where('ticket_system_id', $invoice_info->ticket_system_id)
                    ->update(["price" => round((float) collect($after)->sum('credit'), 2)]);
            }

            Invoice::where('id', $id)->update([
                // invoice_date is intentionally not updated: the original date never changes.
                "invoice_travel_date" => $request->invoice_travel_date,
                "return_date" => $request->return_date,
                "invoice_airline" => $request->invoice_airline,
                "from_location" => $request->from_location,
                "to_location" => $request->to_location,
                "invoice_group_id" => null,
                "invoice_beneficiaries" => $request->invoice_beneficiaries,
                "invoice_section" => $request->invoice_section,
                "invoice_comments" => $request->invoice_comments,
                "invoice_currency" => $request->invoice_currency,
                "invoice_draft" => $request->invoice_draft,
                "invoice_ticket_file" => $path,
                "invoice_create_by" => $request->added_user,
            ]);
        });

        return redirect()->route('site.invoices_edit' , $id);
        
    }
    
    public function confirm($id)
    {
        $id = (int) $id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        $system_id = $invoice_info->ticket_system_id;

        $vendors = TicketVendor::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $suppliers = Supplier::select('*')
            ->orderBy('id', 'DESC')
            ->where('acc_type', '!=' , 3)
            ->get();
        $my_suppliers = Supplier::select('*')
            ->where('acc_type', 2)
            ->where('acc_type', '!=' , 3)
            ->orderBy('id', 'DESC')
            ->get();
        $airlines = Airline::select('*')
            ->orderBy('id', 'DESC')
            ->get();

        return view('invoices.confirm', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function confirm_save(Request $request)
    {
        $id = (int) $request->id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];
//dd($invoice_info);
        $invoice_info_update = Invoice::select('*')
            ->where('id', $id)
            ->update(["invoice_status" => 1, "invoice_approved_by" => Auth::user()->id]);
        
        
          $save_log = Log::create([
            "log_txt" => "تم اعتماد فاتورة " . $invoice_info->es_id,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);
        
        
        
        return redirect()->route('site.invoices');
    }

    /**
     * Update the specified resource in storage.
     */
    public function invoices_reissue()
    {
        $invoices = Invoice::select('*')
            ->where('invoice_shared', 0)
            ->orderBy('id', 'DESC')
            ->get();
        return view('invoices.reissue.add', ["invoices" => $invoices]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function invoices_reissue_get(Request $request)
    {
        return redirect()->route('site.invoices_reissue_create', $request->es_id);
    }
    public function invoices_reissue_create($es_id)
    {
        $invoice_info = Invoice::select('*')
            ->where('es_id', $es_id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        $system_id = $invoice_info->ticket_system_id;

        $vendors = TicketVendor::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $suppliers = Supplier::select('*')
            ->where('acc_type', '!=' , 3)
            ->orderBy('id', 'DESC')
            ->get();
        $my_suppliers = Supplier::select('*')
            ->where('acc_type', '!=' , 3)
            ->where('acc_type', 2)
            ->orderBy('id', 'DESC')
            ->get();
        $airlines = Airline::select('*')
            ->orderBy('id', 'DESC')
            ->get();

        return view('invoices.reissue.create', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }
    public function invoices_reissue_save(Request $request)
    {
        $id = (int) $request->id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        if ($request->hasFile('myPoster')) {
            $imagePath = $request->file('myPoster');
            $imageName = $imagePath->getClientOriginalName();
            $extension = $imagePath->extension();

            $list_allow = ["jpg", "png", "jpeg", "webp", "pdf"];
            if (!in_array($extension, $list_allow)) {
                $msg = "برجاء الالتزام بالصيغة المحددة لرفع صورة / ملف التذكرة الخاصة بالفاتورة";
                return Redirect::back()->withErrors(['msg' => $msg]);
            }

            $imageNewName = \Str::random(32) . "." . $extension;
            $path = 'storage/' . $request->file('myPoster')->storeAs('pdf_files', $imageNewName, 'public');
        } else {
            $path = $invoice_info->invoice_ticket_file;
        }
        //
        //
        //
        $system_id = \Str::random(8);
        //
        $createVendors = TicketVendor::create([
            "ticket_system_id" => $system_id,
            "vendor_id" => $request->vendor_id,
            "price" => $request->vendor_cost,
        ]);

        foreach ($request->ticket_info as $key => $value) {
            $createClients = TicketUser::create([
                "crt_at" => date('Y-m-d'),
                "ticket_system_id" => $system_id,
                "client_name" => $value['name'],
                "client_type" => $value['client_type'],
                "client_net_pice" => $value['net_price'],
                "client_bought_price" => $value['bought_price'],
                "client_booking_id" => $value['book_id'],
                "client_ticket_id" => $value['tikcet_id'],
                "client_phone" => $value['client_phone'],
                //                "client_passport_id" => $value['passport_id'],
            ]);
        }

        $create = Invoice::create([
            "ticket_system_id" => $system_id,
            "invoice_date" => date('Y-m-d'),   // re-issue is a new operation: today
            "invoice_travel_date" => $request->invoice_travel_date,
            "return_date" => $request->return_date,
            "invoice_airline" => $request->invoice_airline,
            "from_location" => $request->from_location,
            "to_location" => $request->to_location,
            "invoice_group_id" => null,
            "invoice_beneficiaries" => $request->invoice_beneficiaries,
            "invoice_section" => $request->invoice_section,
            "invoice_comments" => $request->invoice_comments,
            "invoice_currency" => $request->invoice_currency,
            "invoice_draft" => $request->invoice_draft,
            "invoice_create_by" => Auth::user()->id,
            "invoice_ticket_file" => $path,
            "crt_at" => date('Y-m-d'),
        ]);

        // Safety fix: es_id must be derived from this new invoice's own id,
        // not the original invoice's id, so a second reissue/refund of the
        // same original invoice never collides on es_id.
        $newEsId = "FLY-RS" . $create->id;

        //        dd($create->id);
        $update_es_id = Invoice::select('*')
            ->where('id', $create->id)
            ->update([
                "es_id" => $newEsId,
            ]);



          $save_log = Log::create([
            "log_txt" => "تم اعادة اصدار فاتورة " . $newEsId,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);



        // Vendor Account Statement
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $total_client_net_pice = 0;
        $total_client_bought_price = 0;
        $text = "";

        foreach ($users as $user) {
            $total_client_net_pice += $user->client_net_pice;
            $total_client_bought_price += $user->client_bought_price;
        }

        $Statement_Vendor = AccountStatement::create([
            "supp_client_id" => $request->vendor_id,
            "invoice_type" => $request->invoice_section,
            "es_id" => $newEsId,
            "invoice_date" => date('Y-m-d'),   // re-issue is a new operation: today
            "debit_balance" => 0,
            "credit_balance" => $total_client_net_pice,
            "ledger_net_effect" => -$total_client_net_pice,
            "transaction_txt" => " تعديل / اعادة اصدار " . $newEsId,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        // invoice_beneficiaries Account Statement

        $invoice_beneficiaries_Vendor = AccountStatement::create([
            "supp_client_id" => $request->invoice_beneficiaries,
            "invoice_type" => $request->invoice_section,
            "es_id" => $newEsId,
            "invoice_date" => date('Y-m-d'),   // re-issue is a new operation: today
            "debit_balance" => $total_client_bought_price,
            "credit_balance" => 0,
            "ledger_net_effect" => $total_client_bought_price,
            "transaction_txt" => " تعديل / اعادة اصدار " . $newEsId,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        // Re-issue = a NEW invoice dated today. Record it explicitly on its ledger rows
        // (kind "reissue" + per-passenger lines) so it is never confused with an edit.
        $reissued = Invoice::find($create->id);
        InvoicePassengerLedger::freezeRows($reissued, InvoicePassengerLedger::passengers($reissued), 'reissue');

        return redirect()->route('site.invoices_ajax');
    }

    public function invoices_refund($id)
    {
        $invoice_info = Invoice::select('*')
            ->where('es_id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        $system_id = $invoice_info->ticket_system_id;

        $vendors = TicketVendor::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $suppliers = Supplier::select('*')
            ->where('acc_type', '!=' , 3)
            ->orderBy('id', 'DESC')
            ->get();
        $my_suppliers = Supplier::select('*')
            ->where('acc_type', '!=' , 3)
            ->where('acc_type', 2)
            ->orderBy('id', 'DESC')
            ->get();
        $airlines = Airline::select('*')
            ->orderBy('id', 'DESC')
            ->get();

        return view('invoices.refund.create', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }

    public function invoices_remove($id)
    {
        $invoice_info = Invoice::select('*')
            ->where('es_id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        $system_id = $invoice_info->ticket_system_id;

        $vendors = TicketVendor::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();
        $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();

        $suppliers = Supplier::select('*')
            ->where('acc_type', '!=' , 3)
            ->orderBy('id', 'DESC')
            ->get();
        $my_suppliers = Supplier::select('*')
            ->where('acc_type', '!=' , 3)
            ->where('acc_type', 2)
            ->orderBy('id', 'DESC')
            ->get();
        $airlines = Airline::select('*')
            ->orderBy('id', 'DESC')
            ->get();

        // Counter Customer invoice: the linked vouchers that deletion will reverse,
        // or why it can't be deleted now
        $counterDeletion = null;
        if (CounterInvoiceDeletion::applies($invoice_info)) {
            $linkedBonds = CounterInvoiceDeletion::linkedBonds($invoice_info);
            $counterDeletion = ['bonds' => $linkedBonds, 'blocker' => CounterInvoiceDeletion::blocker($invoice_info, $linkedBonds)];
        }

        return view('invoices.remove', [
            "invoice_info" => $invoice_info,
            "counterDeletion" => $counterDeletion,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }
 
    public function invoices_remove_send($id){
              $invoice_info = Invoice::select('*')
            ->where('es_id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];
        
        // Counter Customer invoice: reverse its linked payments / payouts and delete it
        // in one transaction, or refuse with the reason (nothing changed).
        if (CounterInvoiceDeletion::applies($invoice_info)) {
            try {
                $error = CounterInvoiceDeletion::delete((int) $invoice_info->id);
            } catch (\Throwable $e) {
                report($e);
                $error = 'تعذر حذف الفاتورة بسبب خطأ غير متوقع، ولم يتم تغيير أي بيانات.';
            }
            if ($error) {
                return redirect()->route('site.invoices_remove', $invoice_info->es_id)->withErrors(['msg' => $error]);
            }
            return redirect()->route('site.invoices');
        }

        $remove = Invoice::select('*')
            ->where('es_id', $id)
            ->delete();
    
        $removeAccs = AccountStatement::select('*')->where('es_id',$id)->delete();
        
        return redirect()->route('site.invoices');
        
    }
    
    
    public function invoices_refund_save(Request $request)
    {
//                dd($request);
        // Safety fix: validate refund amounts. Losses are allowed and are
        // never blocked here — only invalid input, or a loss submitted
        // without the employee's explicit confirmation, is rejected.
        if (!is_numeric($request->bought_price_total) || (float) $request->bought_price_total <= 0) {
            return Redirect::back()->withErrors(['msg' => 'برجاء إدخال قيمة صحيحة أكبر من صفر في «مرتجع لنا من المورد»']);
        }
        if (!is_numeric($request->net_pice_total) || (float) $request->net_pice_total < 0) {
            return Redirect::back()->withErrors(['msg' => 'برجاء إدخال قيمة صحيحة في «مسترد للعميل»']);
        }
        if ((float) $request->net_pice_total > (float) $request->bought_price_total && $request->loss_confirmed != '1') {
            return Redirect::back()->withErrors(['msg' => 'هذه العملية تسجل خسارة («مسترد للعميل» أكبر من «مرتجع لنا من المورد»)، برجاء تأكيد الموافقة على الخسارة قبل الحفظ']);
        }

        $id = (int) $request->id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

        // Refund mode (explicit, never guessed):
        //  full   = cancel the whole invoice: the entered amounts are split
        //           equally between all its passengers
        //  single = refund one passenger: the entered amounts belong only to
        //           the selected passenger; the other passengers are untouched
        $refund_mode = $request->refund_mode === 'single' ? 'single' : 'full';
        $refund_users = TicketUser::where('ticket_system_id', $invoice_info->ticket_system_id)->orderBy('id')->get();
        if ($refund_mode === 'single') {
            $refund_users = $refund_users->where('id', (int) $request->refund_passenger_id)->values();
            if ($refund_users->isEmpty()) {
                return Redirect::back()->withErrors(['msg' => 'برجاء اختيار الراكب المسترد من الجدول']);
            }
        }

        // «مرتجع لنا من المورد» (bought_price_total) is what the supplier returns to us;
        // «مسترد للعميل» (net_pice_total) is what we return to the client. Neither
        // should exceed what the original invoice holds for the refunded passenger(s)
        // -- if one does, the two fields were most likely swapped. Allowed only after
        // explicit confirmation.
        if ($request->over_refund_confirmed != '1') {
            $refund_cost = round((float) $refund_users->sum('client_net_pice'), 2);
            $refund_sale = round((float) $refund_users->sum('client_bought_price'), 2);
            $over = [];
            if ((float) $request->bought_price_total > $refund_cost + 0.005) {
                $over[] = '«مرتجع لنا من المورد» (' . number_format((float) $request->bought_price_total, 2) . ') أكبر مما دُفع للمورد في الفاتورة الأصلية (' . number_format($refund_cost, 2) . ')';
            }
            if ((float) $request->net_pice_total > $refund_sale + 0.005) {
                $over[] = '«مسترد للعميل» (' . number_format((float) $request->net_pice_total, 2) . ') أكبر مما دفعه العميل في الفاتورة الأصلية (' . number_format($refund_sale, 2) . ')';
            }
            if ($over) {
                return Redirect::back()->withErrors(['msg' => 'تنبيه: ' . implode(' ، ', $over) . '. برجاء التأكد من عدم عكس الحقلين ثم تأكيد الموافقة قبل الحفظ']);
            }
        }

        $system_id = \Str::random(8);
        $vendors = TicketVendor::select('*')
            ->where('ticket_system_id', $invoice_info->ticket_system_id)
            ->get();
        $vendors = $vendors[0];

        $vendor_id = $vendors->vendor_id;

        $vendors = Supplier::select('*')
            ->where('id', $vendor_id)
            ->get();
        $vendors = $vendors[0];

        $createVendors = TicketVendor::create([
            "ticket_system_id" => $system_id,
            "vendor_id" => $vendors->id,
            "price" => $request->net_pice_total,
        ]);

        // Supplier debit = bought_price_total, client credit = net_pice_total (see below).
        $refund_markers = InvoicePassengerLedger::createRefundPassengers($refund_users, $system_id, $request->bought_price_total, $request->net_pice_total, $refund_mode, (int) $invoice_info->id);
        $refund_passenger_txt = $refund_mode === 'single' ? " - الراكب: " . $refund_users[0]->client_name : "";

        $create = Invoice::create([
            "ticket_system_id" => $system_id,
            "invoice_date" => date('Y-m-d'),
            "invoice_travel_date" => $invoice_info->invoice_travel_date,
            "return_date" => $invoice_info->return_date,
            "invoice_airline" => $invoice_info->invoice_airline,
            "from_location" => $invoice_info->from_location,
            "to_location" => $invoice_info->to_location,
            "invoice_group_id" => null,
            "invoice_beneficiaries" => $invoice_info->invoice_beneficiaries,
            "invoice_section" => $invoice_info->invoice_section,
            "invoice_comments" => $invoice_info->invoice_comments,
            "invoice_currency" => $invoice_info->invoice_currency,
            "invoice_draft" => $invoice_info->invoice_draft,
            "invoice_create_by" => Auth::user()->id,
            "invoice_ticket_file" => $invoice_info->invoice_ticket_file,
            "crt_at" => date('Y-m-d'),
        ]);

        // Safety fix: es_id must be derived from this new invoice's own id,
        // not the original invoice's id, so a second reissue/refund of the
        // same original invoice never collides on es_id.
        $newEsId = "FLY-RD" . $create->id;

        //        dd($create->id);
        $update_es_id = Invoice::select('*')
            ->where('id', $create->id)
            ->update([
                "es_id" => $newEsId,
            ]);

              $save_log = Log::create([
            "log_txt" => "تم ارجاع فاتورة " . $newEsId . $refund_passenger_txt,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);


        $Statement_Vendor = AccountStatement::create([
            "supp_client_id" => $vendors->id,
            "invoice_type" => $invoice_info->invoice_section,
            "es_id" => $newEsId,
            "invoice_date" => date('Y-m-d'),
            "debit_balance" => $request->bought_price_total,
            "credit_balance" => 0,
            "ledger_net_effect" => $request->bought_price_total,
            "transaction_txt" => " مرتجع الفاتورة " . $newEsId . $refund_passenger_txt,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
            "description" => $refund_markers['debit'],
        ]);

        $invoice_beneficiaries_Vendor = AccountStatement::create([
            "supp_client_id" => $invoice_info->invoice_beneficiaries,
            "invoice_type" => $invoice_info->invoice_section,
            "es_id" => $newEsId,
            "invoice_date" => date('Y-m-d'),
            "debit_balance" => 0,
            "credit_balance" => $request->net_pice_total,
            "ledger_net_effect" => -$request->net_pice_total,
            "transaction_txt" => " مرتجع الفاتورة " . $newEsId . $refund_passenger_txt,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
            "description" => $refund_markers['credit'],
        ]);

        return redirect()->route('site.invoices_ajax');
    }
    
    public function pay_part($id){
        $invoice_info = Invoice::find((int) $id);
        abort_if(!$invoice_info, 404);

        $users = TicketUser::select('*')
            ->where('ticket_system_id', $invoice_info->ticket_system_id)
            ->get();

        // Counter-customer payment screen: totals, payments, refunds and remaining
        // come from CounterPayments (the same figures as the invoices list).
        return view('invoices.pay_part.view' , [
            "invoice_info" => $invoice_info,
            "users" => $users,
            "summary" => CounterPayments::summary($invoice_info),
            "client" => Supplier::find($invoice_info->invoice_beneficiaries),
            "banks" => Bank::orderBy('id')->get(['id', 'bank_name']),
            "storages" => Storage::orderBy('id')->get(['id', 'name']),
        ]);
    }

    /**
     * Record one payment of a Counter Customer invoice ("سداد").
     *
     * One payment = one receipt Bond + the customer / treasury ledger rows + the
     * treasury statement -- and, for a bank payment, the bank balance and bank
     * statement too: exactly what a bank receipt voucher records (the money is
     * received into the treasury through the selected bank). Every payment keeps
     * its own date and stays individually traceable. The invoice itself is never
     * rewritten (only its running invoice_money_pay).
     */
    public function pay_part_save(Request $request){
        $id = (int) $request->id;

        if (!is_numeric($request->money_pay) || (float) $request->money_pay <= 0) {
            return Redirect::back()->withErrors(['msg' => 'برجاء إدخال مبلغ سداد صحيح أكبر من صفر']);
        }
        $moneyPay = round((float) $request->money_pay, 2);
        $method = (int) $request->money_way === 2 ? 2 : 1;          // 1 = treasury (cash), 2 = bank
        $bankId = $method === 2 ? (int) $request->bank_id : null;
        if ($method === 2 && !Bank::where('id', $bankId)->exists()) {
            return Redirect::back()->withErrors(['msg' => 'برجاء اختيار البنك']);
        }

        // The whole payment is atomic: the invoice row is locked first, so two
        // concurrent payments on the same invoice are serialised and the second
        // one sees the first one's amount when it checks the remaining amount.
        $result = DB::transaction(function () use ($id, $moneyPay, $method, $bankId) {
            $invoice_info = Invoice::where('id', $id)->lockForUpdate()->first();
            abort_if(!$invoice_info, 404);

            if (!CounterPayments::isPayable($invoice_info)) {
                return Redirect::back()->withErrors(['msg' => 'تسجيل السداد متاح لفواتير عميل الكونتر فقط']);
            }

            // duplicate-submission guard (unchanged): the same payment again within 10 seconds
            $duplicateBond = Bond::where('invoice_id', $id)
                ->where('is_invoice', 1)
                ->where('type', 2)
                ->where('amount', $moneyPay)
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();
            if ($duplicateBond) {
                return Redirect::back()->withErrors(['msg' => 'تم استلام هذا السداد بالفعل، برجاء عدم تكرار الإرسال']);
            }

            // remaining = total - payments - client refunds (read while the invoice is locked)
            $summary = CounterPayments::summary($invoice_info);
            if ($moneyPay > $summary['remaining'] + 0.005) {
                return Redirect::back()->withErrors(['msg' => 'قيمة السداد أكبر من المبلغ المتبقي.']);
            }

            // canonical lock order (as in BondsController): Storage before Bank
            $storage_info = Storage::where('name', 'الخزنة الرئيسية')->lockForUpdate()->first();
            abort_if(!$storage_info, 404);
            $bank = $method === 2 ? Bank::where('id', $bankId)->lockForUpdate()->first() : null;
            if ($method === 2 && !$bank) {
                return Redirect::back()->withErrors(['msg' => 'برجاء اختيار البنك']);
            }

            $create_bond = Bond::create([
                "type" => 2,
                "system_id" => \Str::random(8),
                "from_account" => $invoice_info->invoice_beneficiaries,
                "from_type" => "supplier",
                "to_account" => $storage_info->id,
                "to_type" => "storage",
                "amount" => $moneyPay,
                "info" => "سداد مبلغ لفاتورة $invoice_info->es_id" . ($bank ? " - بنك {$bank->bank_name}" : ""),
                "money_way" => $method,
                "bank_id" => $bank ? $bank->id : null,
                "crt_date" => date('Y-m-d'),
                "created_by" => Auth::user()->id,
                "is_invoice" => 1,
                "invoice_id" => $id,
            ]);

            Storage::where('id', $storage_info->id)->update([
                "balance" => $storage_info->balance + $moneyPay,
            ]);
            if ($bank) {
                Bank::where('id', $bank->id)->update(["bank_balance" => $bank->bank_balance + $moneyPay]);
            }

            // explicit record of what this row is (shown as «سداد» / «سداد - بنك <name>»)
            $marker = json_encode(['kind' => 'payment', 'method' => $bank ? 'bank' : 'cash', 'bank_id' => $bank ? $bank->id : null,
                'bank_name' => $bank ? $bank->bank_name : null, 'bond_id' => $create_bond->id], JSON_UNESCAPED_UNICODE);
            $txt = "سداد مبلغ لصالح رحلة $invoice_info->es_id" . ($bank ? " - بنك {$bank->bank_name}" : "");

            AccountStatement::create([
                "supp_client_id" => $invoice_info->invoice_beneficiaries,
                "invoice_type" => 12,
                "es_id" => $invoice_info->es_id,
                "invoice_date" => date('Y-m-d'),
                "debit_balance" => 0,
                "credit_balance" => $moneyPay,
                "ledger_net_effect" => -$moneyPay,
                "transaction_txt" => $txt,
                "transaction_type" => 4,
                "added_by" => Auth::user()->id,
                "crt_date" => date('Y-m-d'),
                "description" => $marker,
            ]);

            AccountStatement::create([
                "supp_client_id" => $storage_info->id,
                "trans_storage" => 1,
                "is_storage" => 1,
                "invoice_type" => 12,
                "es_id" => $invoice_info->es_id,
                "invoice_date" => date('Y-m-d'),
                "debit_balance" => $moneyPay,
                "credit_balance" => 0,
                "ledger_net_effect" => $moneyPay,
                "transaction_txt" => $txt,
                "transaction_type" => 4,
                "added_by" => Auth::user()->id,
                "crt_date" => date('Y-m-d'),
                "description" => $marker,
            ]);

            // P3 storage ledger: one credit entry for the money received into the treasury.
            StorageStatement::record([
                'storage_id' => $storage_info->id,
                'bond_id' => $create_bond->id,
                'entry_type' => 'bond',
                'transaction_date' => date('Y-m-d'),
                'description' => $txt,
                'reference' => $invoice_info->es_id,
                'debit' => 0,
                'credit' => $moneyPay,
                'commission' => 0,
                'created_by' => Auth::user()->id,
            ]);

            // P2 bank ledger: bank payments also credit the selected bank, as a bank receipt voucher does.
            if ($bank) {
                BankStatement::record([
                    'bank_id' => $bank->id,
                    'bond_id' => $create_bond->id,
                    'entry_type' => 'bond',
                    'transaction_date' => date('Y-m-d'),
                    'description' => $txt,
                    'reference' => $invoice_info->es_id,
                    'debit' => 0,
                    'credit' => $moneyPay,
                    'commission' => 0,
                    'created_by' => Auth::user()->id,
                ]);
            }

            Invoice::where('id', $id)->update([
                "invoice_money_pay" => round((float) $invoice_info->invoice_money_pay + $moneyPay, 2),
            ]);

            return null;
        });

        if ($result) {
            return $result;
        }

        return redirect()->route('site.invoices_ajax')->with('success', 'تم تسجيل السداد');
    }
}
