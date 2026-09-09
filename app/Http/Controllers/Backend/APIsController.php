<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{
    Supplier,
    Invoice, 
    TicketUser,
    TicketVendor, 
    Airline,
    AccountStatement,
    Log,
    SubStorage,
};


class APIsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function get_last_row($sup_id , $id)
    {
        $id = (int) $id;
      
        $get_col = AccountStatement::select('*')
            ->where('supp_client_id' , $sup_id)
            ->orderBy('id','ASC')
            ->where('id', '<', $id)->get();

        if(count($get_col) == 0){
            return response()->json([
    'st_code' => 404,
]);
        }else{
         
            return response()->json([
    'st_code' => 200,
    'amount' => $get_col->debit_balance + $get_col->credit_balance,
]); 
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
