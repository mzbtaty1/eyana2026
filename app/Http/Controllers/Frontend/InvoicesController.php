<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use Illuminate\Support\Carbon;
use App\Models\{Supplier, Invoice, TicketUser, TicketVendor, Airline, AccountStatement , Log , TransactionBalance,Storage,Bond , User};
use Yajra\DataTables\Facades\DataTables;

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

    return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => $total,
        'recordsFiltered' => $total,
        'data' => $invoices,
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
        if(Auth::user()->account_type == 2){
        $invoices = Invoice::select('*')
            ->orderBy('id', 'DESC')
            ->where('invoice_shared', '!=', 1)
            ->get();
        }else{
        
$invoices = Invoice::select('*')
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

    public function create()
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

        return view('invoices.create', [
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
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
            "cumulative_balance" => -$total_client_net_pice,
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
            "cumulative_balance" => $total_client_bought_price,
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

        return view('invoices.edit_invoice', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
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
        
        $old_sup = TicketVendor::select('*')->where('ticket_system_id',$invoice_info->ticket_system_id)->get();
        $old_sup = $old_sup[0];
        /* EDIT HERE */
        
         $createVendors = TicketVendor::select('*')->where('ticket_system_id',$invoice_info->ticket_system_id)->update([
//            "ticket_system_id" => $system_id,
            "vendor_id" => $request->vendor_id,
            "price" => $request->vendor_cost,
        ]);

        
        $remove_tickets = TicketUser::select('*')->where('ticket_system_id',$invoice_info->ticket_system_id)->delete();
        
        
           foreach ($request->ticket_info as $key => $value) {
            $createClients = TicketUser::create([
                "crt_at" => date('Y-m-d'),
                "ticket_system_id" => $invoice_info->ticket_system_id,
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
        
        
         $users = TicketUser::select('*')
            ->where('ticket_system_id', $invoice_info->ticket_system_id)
            ->get();

        $total_client_net_pice = 0;
        $total_client_bought_price = 0;
        $text = "";

        foreach ($users as $user) {
            $total_client_net_pice += $user->client_net_pice;
            $total_client_bought_price += $user->client_bought_price;
        }

        
        // Get any existing vendor and beneficiary statements with same es_id and transaction_type
$existing_statements = AccountStatement::where('transaction_type', 1)
    ->where('es_id', $invoice_info->es_id)
    ->get();

$existing_vendor_statement = $existing_statements->firstWhere('supp_client_id', $request->vendor_id);
$existing_beneficiary_statement = $existing_statements->firstWhere('supp_client_id', $request->invoice_beneficiaries);

// حذف سجل المورد السابق إن وُجد وكان يختلف عن المورد الجديد
foreach ($existing_statements as $statement) {
    if ($statement->supp_client_id != $request->vendor_id && $statement->supp_client_id != $request->invoice_beneficiaries) {
        $statement->delete();
    }
}

// تجهيز البيانات حسب نوع الفاتورة (استرداد أو عادي)
if ($request->is_rfund == 1) {
    // vendor: debit, beneficiary: credit
    $vendor_data = [
        "invoice_type" => $request->invoice_section,
        "invoice_date" => $request->invoice_date,
        "debit_balance" => floatval($request->bought_price_total),
        "credit_balance" => 0,
        "cumulative_balance" => floatval($request->bought_price_total),
        "transaction_txt" => "حجز الرحلة " . $invoice_info->es_id,
        "added_by" => $request->added_user,
    ];

    $beneficiary_data = [
        "invoice_type" => $request->invoice_section,
        "invoice_date" => $request->invoice_date,
        "debit_balance" => 0,
        "credit_balance" => floatval($request->net_pice_total),
        "cumulative_balance" => -floatval($request->net_pice_total),
        "transaction_txt" => "حجز الرحلة " . $invoice_info->es_id,
        "added_by" => $request->added_user,
    ];
} else {
    // vendor: credit, beneficiary: debit
    $vendor_data = [
        "invoice_type" => $request->invoice_section,
        "invoice_date" => $request->invoice_date,
        "debit_balance" => 0,
        "credit_balance" => floatval($total_client_net_pice),
        "cumulative_balance" => -floatval($total_client_net_pice),
        "transaction_txt" => "حجز الرحلة " . $invoice_info->es_id,
        "added_by" => $request->added_user,
    ];

    $beneficiary_data = [
        "invoice_type" => $request->invoice_section,
        "invoice_date" => $request->invoice_date,
        "debit_balance" => floatval($total_client_bought_price),
        "credit_balance" => 0,
        "cumulative_balance" => floatval($total_client_bought_price),
        "transaction_txt" => "حجز الرحلة " . $invoice_info->es_id,
        "added_by" => $request->added_user,
    ];
}

// حفظ/تحديث كشف حساب المورد
if ($existing_vendor_statement) {
    $existing_vendor_statement->update($vendor_data);
} else {
    AccountStatement::create(array_merge([
        "supp_client_id" => $request->vendor_id,
        "es_id" => $invoice_info->es_id,
        "transaction_type" => 1,
        "crt_date" => date('Y-m-d'),
    ], $vendor_data));
}

// حفظ/تحديث كشف حساب المستفيد
if ($existing_beneficiary_statement) {
    $existing_beneficiary_statement->update($beneficiary_data);
} else {
    AccountStatement::create(array_merge([
        "supp_client_id" => $request->invoice_beneficiaries,
        "es_id" => $invoice_info->es_id,
        "transaction_type" => 1,
        "crt_date" => date('Y-m-d'),
    ], $beneficiary_data));
}




        
        
        $update_invoice = Invoice::select('*')
            ->where('id', $id)
            ->update([
//                            "ticket_system_id" => $system_id,
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
            "invoice_ticket_file" => $path,
                            "invoice_create_by" => $request->added_user,

            ]);
        
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
            "invoice_date" => $request->invoice_date,
            "debit_balance" => 0,
            "credit_balance" => $total_client_net_pice,
            "cumulative_balance" => -$total_client_net_pice,
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
            "invoice_date" => $request->invoice_date,
            "debit_balance" => $total_client_bought_price,
            "credit_balance" => 0,
            "cumulative_balance" => $total_client_bought_price,
            "transaction_txt" => " تعديل / اعادة اصدار " . $newEsId,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

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

        return view('invoices.remove', [
            "invoice_info" => $invoice_info,
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
            return Redirect::back()->withErrors(['msg' => 'برجاء إدخال قيمة صحيحة أكبر من صفر للمبلغ المرتجع من المورد']);
        }
        if (!is_numeric($request->net_pice_total) || (float) $request->net_pice_total < 0) {
            return Redirect::back()->withErrors(['msg' => 'برجاء إدخال قيمة صحيحة للمبلغ المسترد للعميل']);
        }
        if ((float) $request->net_pice_total > (float) $request->bought_price_total && $request->loss_confirmed != '1') {
            return Redirect::back()->withErrors(['msg' => 'هذه العملية تسجل خسارة، برجاء تأكيد الموافقة على الخسارة قبل الحفظ']);
        }

        $id = (int) $request->id;
        $invoice_info = Invoice::select('*')
            ->where('id', $id)
            ->get();
        abort_if(count($invoice_info) == 0, 404);
        $invoice_info = $invoice_info[0];

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

        $users = TicketUser::select('*')
            ->where('ticket_system_id', $invoice_info->ticket_system_id)
            ->get();

        foreach ($users as $user) {
            $createClients = TicketUser::create([
                "crt_at" => date('Y-m-d'),
                "ticket_system_id" => $system_id,
                "client_name" => $user->client_name,
                "client_type" => $user->client_type,
                "client_net_pice" => $user->client_net_pice,
                "client_bought_price" => $user->client_bought_price,
                "client_booking_id" => $user->client_booking_id,
                "client_ticket_id" => $user->client_ticket_id,
                "client_phone" => $user->client_phone,
                //                "client_passport_id" => $user->client_passport_id,
            ]);
        }

        $create = Invoice::create([
            "ticket_system_id" => $system_id,
            "invoice_date" => $invoice_info->invoice_date,
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
            "log_txt" => "تم ارجاع فاتورة " . $newEsId,
            "log_ip" => $request->ip(),
            "log_by" => Auth::user()->id,
            "log_date" => date('Y-m-d'),
        ]);


        $Statement_Vendor = AccountStatement::create([
            "supp_client_id" => $vendors->id,
            "invoice_type" => $invoice_info->invoice_section,
            "es_id" => $newEsId,
            "invoice_date" => $invoice_info->invoice_date,
            "debit_balance" => $request->bought_price_total,
            "credit_balance" => 0,
            "cumulative_balance" => $request->bought_price_total,
            "transaction_txt" => " مرتجع الفاتورة " . $newEsId,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        $invoice_beneficiaries_Vendor = AccountStatement::create([
            "supp_client_id" => $invoice_info->invoice_beneficiaries,
            "invoice_type" => $invoice_info->invoice_section,
            "es_id" => $newEsId,
            "invoice_date" => $invoice_info->invoice_date,
            "debit_balance" => 0,
            "credit_balance" => $request->net_pice_total,
            "cumulative_balance" => -$request->net_pice_total,
            "transaction_txt" => " مرتجع الفاتورة " . $newEsId,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        return redirect()->route('site.invoices_ajax');
    }
    
    public function pay_part($id){
        $invoice_check = Invoice::select('*')->where('id',$id)->get();
        abort_if(count($invoice_check) == 0,404);
        
        $invoice_info = $invoice_check[0];
         $system_id = $invoice_info->ticket_system_id;
 $users = TicketUser::select('*')
            ->where('ticket_system_id', $system_id)
            ->get();
        
        return view('invoices.pay_part.view' , [
            "invoice_info" => $invoice_info,
            "users" => $users,
        ]);
        
        
    }
    public function pay_part_save(Request $request){
        $id = (int) $request->id;

        // Safety fix: validate the payment amount before touching anything.
        if (!is_numeric($request->money_pay) || (float) $request->money_pay <= 0) {
            return Redirect::back()->withErrors(['msg' => 'برجاء إدخال مبلغ سداد صحيح أكبر من صفر']);
        }
        $moneyPay = (float) $request->money_pay;

        // Safety fix: the whole financial operation is now atomic.
        $result = DB::transaction(function () use ($id, $moneyPay) {
            // Safety fix: lock the invoice row for the duration of the transaction.
            $invoice_info = Invoice::where('id', $id)->lockForUpdate()->first();
            abort_if(!$invoice_info, 404);

            // Safety fix: duplicate-submission guard using the existing Bond
            // records, checked while the invoice row lock is held (no session,
            // no new table). A recent matching Bond means this exact payment
            // was already processed — the invoice row lock guarantees this
            // check sees any payment committed by a concurrent request.
            $duplicateBond = Bond::where('invoice_id', $id)
                ->where('is_invoice', 1)
                ->where('amount', $moneyPay)
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();
            if ($duplicateBond) {
                return Redirect::back()->withErrors(['msg' => 'تم استلام هذا السداد بالفعل، برجاء عدم تكرار الإرسال']);
            }

            // Safety fix: lock the storage row for the duration of the transaction.
            $storage_info = Storage::where('name', 'الخزنة الرئيسية')->lockForUpdate()->first();
            abort_if(!$storage_info, 404);

            // Actual invoice total, computed from the real ticket data (moved
            // here from further down in the method, where it was previously
            // computed but never used).
            $system_id = $invoice_info->ticket_system_id;
            $users = TicketUser::where('ticket_system_id', $system_id)->get();
            $total_client_bought_price = 0;
            foreach ($users as $user) {
                $total_client_bought_price += $user->client_bought_price;
            }

            // Safety fix: never let invoice_money_pay exceed the invoice's actual total.
            $newMoneyPay = $invoice_info->invoice_money_pay + $moneyPay;
            if ($newMoneyPay > $total_client_bought_price) {
                return Redirect::back()->withErrors(['msg' => 'المبلغ المدخل يتجاوز إجمالي قيمة الفاتورة، برجاء مراجعة المبلغ']);
            }

            $create_bond = Bond::create([
                           "type" => 2,
               "system_id" => \Str::random(8),
               "from_account" => $invoice_info->invoice_beneficiaries,
               "from_type" => "supplier",
               "to_account" => $storage_info->id,
               "to_type" => "storage",
               "amount" => $moneyPay,
               "info" => "سداد مبلغ لفاتورة $invoice_info->es_id",
               "money_way" => 1,
               "crt_date" => date('Y-m-d'),
               "created_by" => Auth::user()->id,

                "is_invoice" => 1,
                "invoice_id" => $id,

            ]);

            $balance = $storage_info->balance;
            $update = Storage::where('name', 'الخزنة الرئيسية')->update([
                "balance" => $balance + $moneyPay,
            ]);

            $Statement_Vendor = AccountStatement::create([
                "supp_client_id" => $invoice_info->invoice_beneficiaries,
                "invoice_type" => 12,
                "es_id" => $invoice_info->es_id,
                "invoice_date" => date('Y-m-d'),
                "debit_balance" => 0,
                "credit_balance" => $moneyPay,
                "cumulative_balance" => -$moneyPay,
                "transaction_txt" => "سداد مبلغ لصالح رحلة $invoice_info->es_id",
                "transaction_type" => 4,
                "added_by" => Auth::user()->id,
                "crt_date" => date('Y-m-d'),
            ]);

             $storage_log = AccountStatement::create([
                "supp_client_id" => $storage_info->id,
                "trans_storage" => 1,
                "is_storage" => 1,
                "invoice_type" => 12,
                "es_id" => $invoice_info->es_id,
                "invoice_date" => date('Y-m-d'),
                "debit_balance" => $moneyPay,
                "credit_balance" => 0,
                "cumulative_balance" => $moneyPay,
                "transaction_txt" => "سداد مبلغ لصالح رحلة $invoice_info->es_id",
                "transaction_type" => 4,
                "added_by" => Auth::user()->id,
                "crt_date" => date('Y-m-d'),
            ]);

            $update = Invoice::where('id', $id)->update([
                "invoice_money_pay" => $newMoneyPay,
            ]);

            return null;
        });

        if ($result) {
            return $result;
        }

        return redirect()->route('site.invoices');

    }


    
    
}
