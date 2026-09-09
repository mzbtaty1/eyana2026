<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\Invoice;

class ProfitsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
//        $invoices = Invoice::select('*')->where('invoice_shared' , 0)->get();
         if(Auth::user()->account_type == 2){
        $invoices = Invoice::select('*')
            ->orderBy('id', 'DESC')
//            ->where('invoice_shared',  0)
            ->get();
        }else{
        
$invoices = Invoice::select('*')
            ->orderBy('id', 'DESC')
            ->where('invoice_shared',  0)
            ->where('invoice_create_by', Auth::user()->id)
            ->get();
        }
//        $shared_invoices = Invoice::select('*')->where('invoice_shared' , 1);
//        $shared_invoices = $shared_invoices->where('invoice_account_1' , Auth::user()->id);
//        $shared_invoices = $shared_invoices->orWhere('invoice_account_2' , Auth::user()->id);
//        $shared_invoices = $shared_invoices->get();
        
        return view('profits.all' , [
            "invoices" => $invoices,
//            "shared_invoices" => $shared_invoices,
        ]);
        
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
