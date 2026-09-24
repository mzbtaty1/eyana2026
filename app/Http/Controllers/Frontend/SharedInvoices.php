<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use App\Models\{Supplier, Invoice, TicketUser, TicketVendor, Airline, AccountStatement , Log , TransactionBalance};
use App\Services\InvoicePassengerLedger;

class SharedInvoices extends Controller
{
        public function shared_invoices(){
//         $invoices = Invoice::select('*')
//            ->where('invoice_shared', 1)
//            ->orderBy('id', 'DESC')
//            ->get();
//            
             if(Auth::user()->account_type == 2){
        $invoices = Invoice::select('*')
            ->orderBy('id', 'DESC')
            ->where('invoice_shared', 1)
            ->get();
        }else{
        
$invoices = Invoice::select('*')
            ->orderBy('id', 'DESC')
            ->where('invoice_shared', 1)
            ->where('invoice_create_by', Auth::user()->id)
            ->get();
        }
            
            

// dd($invoices)
        return view('invoices.shared.invoices', [
            "invoices" => $invoices,
        ]);
    }
    
    public function shared_invoices_create()
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

        return view('invoices.shared.shared_invoices_create', [
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]); 
    }
    public function shared_invoices_save(Request $request) {
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
            "invoice_create_by" => Auth::user()->id,
            "invoice_ticket_file" => $path,
            
"invoice_shared" => 1, 
"invoice_account_1" => $request->invoice_account_1, 
"invoice_account_1_comm"=> $request->invoice_account_1_comm ?? 7, 
"invoice_account_2" => $request->invoice_account_2, 
"invoice_account_2_comm" => $request->invoice_account_2_comm ?? 3,
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
            "added_by" => Auth::user()->id,
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
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);
        
        

        
        

        
  
        
        
        

        return redirect()->route('site.shared_invoices');
    }
    
    public function shared_invoices_reissue_create($es_id)
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

        return view('invoices.shared.reissue.create', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }
    
    public function shared_invoices_reissue_save(Request $request) {
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

"invoice_shared" => 1,
"invoice_account_1" => $request->invoice_account_1,
"invoice_account_1_comm"=> 5,
"invoice_account_2" => $request->invoice_account_2,
"invoice_account_2_comm" => 5,
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
            "invoice_date" => $request->invoice_date,
            "debit_balance" => $total_client_bought_price,
            "credit_balance" => 0,
            "ledger_net_effect" => $total_client_bought_price,
            "transaction_txt" => " تعديل / اعادة اصدار " . $newEsId,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        return redirect()->route('site.shared_invoices');
    }

    public function shared_invoices_refund($id){
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

        return view('invoices.shared.refund.create', [
            "invoice_info" => $invoice_info,
            "vendors" => $vendors,
            "users" => $users,
            "suppliers" => $suppliers,
            "my_suppliers" => $my_suppliers,
            "airlines" => $airlines,
        ]);
    }
    
    function shared_invoices_refund_save(Request $request){
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

        // Refund mode (explicit): full = entered amounts split equally between all
        // passengers; single = entered amounts belong only to the selected passenger.
        $refund_mode = $request->refund_mode === 'single' ? 'single' : 'full';
        $refund_users = TicketUser::where('ticket_system_id', $invoice_info->ticket_system_id)->orderBy('id')->get();
        if ($refund_mode === 'single') {
            $refund_users = $refund_users->where('id', (int) $request->refund_passenger_id)->values();
            if ($refund_users->isEmpty()) {
                return Redirect::back()->withErrors(['msg' => 'برجاء اختيار الراكب المسترد من الجدول']);
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

        // This refund books the supplier DEBIT = net_pice_total and the client
        // CREDIT = bought_price_total (see the ledger rows below).
        InvoicePassengerLedger::createRefundPassengers($refund_users, $system_id, $request->net_pice_total, $request->bought_price_total);
        $refund_passenger_txt = $refund_mode === 'single' ? " - الراكب: " . $refund_users[0]->client_name : "";

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

            "invoice_shared" => 1,
"invoice_account_1" => $invoice_info->invoice_account_1,
"invoice_account_1_comm"=> 5,
"invoice_account_2" => $invoice_info->invoice_account_2,
"invoice_account_2_comm" => 5,
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
            "invoice_date" => $invoice_info->invoice_date,
            "debit_balance" => $request->net_pice_total,
            "credit_balance" => 0,
            "ledger_net_effect" => $request->net_pice_total,
            "transaction_txt" => " مرتجع الفاتورة " . $newEsId . $refund_passenger_txt,
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
            "credit_balance" => $request->bought_price_total,
            "ledger_net_effect" => -$request->bought_price_total,
            "transaction_txt" => " مرتجع الفاتورة " . $newEsId . $refund_passenger_txt,
            "transaction_type" => 1,
            "added_by" => Auth::user()->id,
            "crt_date" => date('Y-m-d'),
        ]);

        return redirect()->route('site.shared_invoices');
    }
    
    
}
