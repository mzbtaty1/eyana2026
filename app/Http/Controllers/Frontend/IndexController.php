<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use App\Models\{
Alert,
};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Models\AccountStatement;
class IndexController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $user = Auth::user();

    // ✅ كاش عدد الفواتير
    $invoices = Cache::remember('invoices_count_user_'.$user->id, 120, function () use ($user) {
        if ($user->account_type == 2) {
            return DB::table('invoices')->count();
        } else {
            return DB::table('invoices')->where('invoice_create_by', $user->id)->count();
        }
    });

    // ✅ كاش عدد السندات
    $bonds = Cache::remember('bonds_count_user_'.$user->id, 120, function () use ($user) {
        if ($user->account_type == 2) {
            return DB::table('bonds')->count();
        } else {
            return DB::table('bonds')->where('created_by', $user->id)->count();
        }
    });

    // ✅ كاش تنبيهات الإدارة
    $alerts = Cache::remember('alerts', 120, function () {
        return DB::table('alerts')->get();
    });

    // ✅ الموردين لملخص الحسابات
    $suppliers = Cache::remember('suppliers_index', 120, function () {
        return Supplier::where('in_index', 1)->get();
    });

    // ✅ الموردين كلهم لتنبية الأرصدة
    $allSuppliers = Cache::remember('suppliers_all', 120, function () {
        return Supplier::all();
    });

    // ✅ كاش أرصدة الموردين (Grouped)
$suppliersBalances = Cache::remember('suppliers_balances', 60, function () {
    return DB::table('account_statements')
        ->select(
            'supp_client_id',
            DB::raw('SUM(debit_balance) as total_debit'),
            DB::raw('SUM(credit_balance) as total_credit')
        )
        ->where('is_storage', '!=', 1)
        ->groupBy('supp_client_id')
        ->get()
        ->mapWithKeys(function ($row) {
            return [$row->supp_client_id => $row->total_debit - $row->total_credit];
        });
});


    return view('index', compact(
        'invoices',
        'bonds',
        'alerts',
        'suppliers',
        'allSuppliers',
        'suppliersBalances'
    ));
}

     public function test_page()
    { 
        
        return view('test_page');
    }
    
    public function change_mode()
    {
        
        
         $value = Cookie::get('site_mode');
        
        if($value == null){
             $create_cookie = Cookie::queue(Cookie::make('site_mode', "dark", 43200));
            return redirect()->back();
        }else{
//            dd($value);
            if($value == "light"){
                     $delete_cookie = Cookie::queue(Cookie::forget('site_mode'));
            $create_cookie = Cookie::queue(Cookie::make('site_mode', "dark", 43200));
            return redirect()->back();
            
            }else{ 
            $delete_cookie = Cookie::queue(Cookie::forget('site_mode'));
            $create_cookie = Cookie::queue(Cookie::make('site_mode', "light", 43200));
            return redirect()->back();      
            }
//                
//                
//                     $delete_cookie = Cookie::queue(Cookie::forget('site_mode'));
//            $create_cookie = Cookie::queue(Cookie::make('site_mode', "dark", 43200));
//            return redirect()->back();
//            
//            }
            
       
        }
        
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
