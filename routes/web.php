<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\{
    IndexController,
    AirlineController,
    InvoicesController,
    CustomersController,
    SuppliersController,
    MarketingController,
    AccountsController,
    BankController,
    CollectorsController,
    ExpensesController,
    UserController,
    SharedInvoices,
    ProfitsController,
    AlertsController,
    VisaController,
};
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Frontend\MoneyArea\{
    StoragesController,
    BondsController,
};
use App\Http\Controllers\Backend\{
    APIsController,
};

Route::get('/make_artisan' , function(){
    Artisan::call('storage:link');
});

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/test', [IndexController::class , 'test'])->name('site.test');

Route::middleware(['auth' , 'check_status'])->group(function(){
  
    Route::get('/', [IndexController::class , 'index'])->name('site.index');
    Route::get('/change_mode', [IndexController::class , 'change_mode'])->name('site.change_mode');
    Route::get('/invoices/search', [InvoiceController::class, 'searchInvoices'])->name('invoices.search'); 
    
    Route::get('/suppliers', [SuppliersController::class , 'index'])->name('site.suppliers');
    Route::get('/suppliers/create', [SuppliersController::class , 'create'])->name('site.suppliers_create');
    Route::post('/suppliers/save', [SuppliersController::class , 'save'])->name('site.suppliers_save');
    Route::get('/suppliers/{id}', [SuppliersController::class , 'edit'])->name('site.suppliers_edit');
    Route::post('/suppliers/{id}/delete', [SuppliersController::class , 'delete'])->name('site.suppliers_delete');
    Route::post('/suppliers/update', [SuppliersController::class , 'update'])->name('site.suppliers_update');
     
    Route::get('/customers', [CustomersController::class , 'index'])->name('site.customers');
    Route::get('/customers/create', [CustomersController::class , 'create'])->name('site.customers_create');
    Route::post('/customers/save', [CustomersController::class , 'save'])->name('site.customers_save');
    Route::get('/customers/{id}', [CustomersController::class , 'edit'])->name('site.customers_edit');
    Route::get('/customers/{id}/delete', [CustomersController::class , 'delete'])->name('site.customers_delete');
    Route::post('/customers/update', [CustomersController::class , 'update'])->name('site.customers_update');
    
    Route::get('/alerts', [AlertsController::class , 'index'])->name('site.alerts');
    Route::get('/alerts/create', [AlertsController::class , 'create'])->name('site.alerts_create');
    Route::post('/alerts/save', [AlertsController::class , 'save'])->name('site.alerts_save');
    Route::get('/alerts/{id}', [AlertsController::class , 'edit'])->name('site.alerts_edit');
    Route::get('/alerts/{id}/delete', [AlertsController::class , 'delete'])->name('site.alerts_delete');
    Route::post('/alerts/update', [AlertsController::class , 'update'])->name('site.alerts_update');
    
    Route::get('/expenses', [ExpensesController::class , 'index'])->name('site.expenses');
    Route::get('/expenses/create', [ExpensesController::class , 'create'])->name('site.expenses_create');
    Route::post('/expenses/save', [ExpensesController::class , 'save'])->name('site.expenses_save');
    Route::get('/expenses/{id}', [ExpensesController::class , 'edit'])->name('site.expenses_edit');
    Route::get('/expenses/{id}/delete', [ExpensesController::class , 'delete'])->name('site.expenses_delete');
    Route::post('/expenses/update', [ExpensesController::class , 'update'])->name('site.expenses_update');
    
    Route::get('/visas', [VisaController::class , 'index'])->name('site.visas');
    Route::get('/visas/create', [VisaController::class , 'create'])->name('site.visas_create');
    Route::post('/visas/save', [VisaController::class , 'save'])->name('site.visas_save');
    Route::get('/visas/{id}', [VisaController::class , 'edit'])->name('site.visas_edit');
    Route::get('/visas/{id}/delete', [VisaController::class , 'delete'])->name('site.visas_delete');
    Route::post('/visas/update', [VisaController::class , 'update'])->name('site.visas_update');
    
    Route::get('/airlines', [AirlineController::class , 'index'])->name('site.airlines');
    Route::get('/airlines/create', [AirlineController::class , 'create'])->name('site.airlines_create');
    Route::post('/airlines/save', [AirlineController::class , 'save'])->name('site.airlines_save');
    Route::get('/airlines/{id}', [AirlineController::class , 'edit'])->name('site.airlines_edit');
    Route::get('/airlines/{id}/delete', [AirlineController::class , 'delete'])->name('site.airlines_delete');
    Route::post('/airlines/update', [AirlineController::class , 'update'])->name('site.airlines_update');
    
    Route::get('/invoices', [InvoicesController::class, 'index'])->name('site.invoices');
    Route::get('/get_invoices_info', [InvoicesController::class, 'getInvoices'])->name('site.invoices_json');
    Route::get('/get_invoices_info_3months', [InvoicesController::class, 'getInvoices3Months'])->name('site.invoices_json_3months');

    Route::get('/invoices/ajax', [InvoicesController::class, 'ajax'])->name('site.invoices_ajax');
    Route::get('/invoices/lite', [InvoicesController::class, 'lite'])->name('site.invoices_lite');

    Route::get('/invoices/ajax2', [InvoicesController::class, 'ajax2'])->name('site.invoices_ajax2');
    Route::get('/invoices/ajax-3', [InvoicesController::class, 'ajax3'])->name('site.invoices_ajax-3');
    Route::get('/invoices-info/{id}', [InvoicesController::class, 'invoice_info'])->name('site.invoice_info');

    // Invoice management routes with search functionality
    Route::get('/invoices/management', [InvoiceController::class, 'management'])->name('invoices.management');
    Route::get('/invoices/data', [InvoiceController::class, 'getInvoicesData'])->name('invoices.data');
    Route::get('/get-invoices-management', [InvoiceController::class, 'getInvoicesManagement'])->name('invoices.data.management');
    Route::post('/invoices/toggle-confirm', [InvoiceController::class, 'toggleConfirmStatus'])->name('invoices.toggle-confirm');
    Route::delete('/invoices/{id}/delete', [InvoiceController::class, 'deleteInvoice'])->name('invoices.delete');

    Route::get('/invoices/daily-report' , [InvoicesController::class , 'daily_report'])->name('site.invoices_daily_report');
    
    Route::get('/invoices/full-report' , [InvoicesController::class , 'full_report'])->name('site.invoices_full_report');
    Route::post('/invoices/full-report' , [InvoicesController::class , 'full_report_get'])->name('site.invoices_full_report_get');
    
    Route::get('/invoices/reissue' , [InvoicesController::class , 'invoices_reissue'])->name('site.invoices_reissue');
    Route::post('/invoices/reissue' , [InvoicesController::class , 'invoices_reissue_get'])->name('site.invoices_reissue');
    Route::get('/invoices/reissue/{id}' , [InvoicesController::class , 'invoices_reissue_create'])->name('site.invoices_reissue_create');
    Route::post('/invoices/reissue/save' , [InvoicesController::class , 'invoices_reissue_save'])->name('site.invoices_reissue_save');
    Route::get('/invoices/create' , [InvoicesController::class , 'create'])->name('site.invoices_create');
    Route::post('/invoices/save' , [InvoicesController::class , 'store'])->name('site.invoices_save');
    Route::get('/invoices/{id}/show' , [InvoicesController::class , 'show'])->name('site.invoices_show');
    
    Route::get('/invoices/{id}/edit' , [InvoicesController::class , 'edit_invoice'])->name('site.invoices_edit');
    Route::post('/invoices/save_update' , [InvoicesController::class , 'save_update'])->name('site.invoices_save_update');
    
    Route::get('/invoices/{id}/confirm' , [InvoicesController::class , 'confirm'])->name('site.invoices_confirm');
    Route::post('/invoices/confirm_save' , [InvoicesController::class , 'confirm_save'])->name('site.invoices_confirm_save');
    Route::get('/invoices/refund/{id}' , [InvoicesController::class , 'invoices_refund'])->name('site.invoices_refund');
    Route::post('/invoices/refund/save' , [InvoicesController::class , 'invoices_refund_save'])->name('site.invoices_refund_save');
    
    Route::get('/invoices/pay-part/{id}' , [InvoicesController::class , 'pay_part'])->name('site.invoices_pay_part');
    Route::post('/invoices/pay-part/save' , [InvoicesController::class , 'pay_part_save'])->name('site.pay_part_save');

    Route::get('/invoices/employee-log' , [InvoicesController::class , 'employee_log'])->name('site.employee_log');
    Route::post('/invoices/employee-log/view' , [InvoicesController::class , 'employee_log_view'])->name('site.employee_log_view');
         
    Route::get('/invoices/employee-log/print' , [InvoicesController::class , 'employee_log_print'])->name('site.employee_log_print');
    Route::get('/invoices/{id}/approve' , [InvoicesController::class , 'invoices_approve'])->name('site.invoices_approve');
    Route::get('/invoices/air-cairo/calc' , [InvoicesController::class , 'air_cairo_calc'])->name('site.air_cairo_calc');
    
    // Fixed: Add proper DELETE route for invoice removal
    Route::delete('/invoices/{id}/remove', [InvoiceController::class, 'deleteInvoice'])->name('invoices.remove');
    // Keep existing GET routes for backward compatibility
    Route::get('/invoices/{id}/remove' , [InvoicesController::class , 'invoices_remove'])->name('site.invoices_remove');
    Route::get('/invoices/{id}/remove/send' , [InvoicesController::class , 'invoices_remove_send'])->name('site.invoices_remove_send');
    
    Route::get('/accounts-statement' , [AccountsController::class , 'accounts_statement'])->name('site.accounts_statement');
    
    Route::get('/accounts-statement/{id}/approve' , [AccountsController::class , 'accounts_statement_approve'])->name('site.accounts_statement_approve');
    
    Route::get('/accounts-statement/print/{invoice_beneficiaries}/{date_from?}/{date_to?}/{transaction_type?}' , [AccountsController::class , 'accounts_statement_print_all'])->name('site.accounts_statement_print_all');
    
    Route::get('/accounts-statement/excel/{invoice_beneficiaries?}/{date_from?}{date_to?}/{transaction_type?}' , [AccountsController::class , 'accounts_statement_print_excel'])->name('site.accounts_statement_print_excel');
    
    Route::get('/accounts-statement/custom' , [AccountsController::class , 'accounts_statement_custom_get'])->name('site.accounts_statement_custom_get');
    
    Route::get('/accounts-statement/suppliers' , [AccountsController::class , 'accounts_statement_suppliers_get'])->name('site.accounts_statement_suppliers_get');
    
    Route::post('/accounts-statement/custom/get' , [AccountsController::class , 'accounts_statement_custom_view'])->name('site.accounts_statement_custom_view');
    
    Route::post('/accounts-statement/suppliers/get' , [AccountsController::class , 'accounts_statement_suppliers_view'])->name('site.accounts_statement_suppliers_view');
    
    Route::get('/accounts-statement/custom/print/{sup_stauts}/{from?}/{to?}' , [AccountsController::class , 'accounts_statement_custom_print'])->name('site.accounts_statement_custom_print');
    
    Route::get('/accounts-statement/suppliers/print/{sup_stauts}/{from?}/{to?}' , [AccountsController::class , 'accounts_statement_suppliers_print'])->name('site.accounts_statement_suppliers_print');
    
    Route::post('/accounts-statement/search' , [AccountsController::class , 'accounts_statement_search'])->name('site.accounts_statement_search');
    
    Route::get('/accounts-statement/{id}' , [AccountsController::class , 'accounts_statement_get'])->name('site.accounts_statement_get');
    
    Route::get('/accounts-statement/{id}/print' , [AccountsController::class , 'accounts_statement_print'])->name('site.accounts_statement_print');
    
    Route::get('/accounts-statement/transaction/all' , [AccountsController::class , 'accounts_statement_trans_all'])->name('site.accounts_statement_trans_all');
    
    Route::post('/accounts-statement/transaction/get_all' , [AccountsController::class , 'accounts_statement_trans_get_all'])->name('site.accounts_statement_trans_get_all');
    
    Route::get('/marketing-prices' , [MarketingController::class , 'index'])->name('site.marketing_prices');
    Route::get('/marketing-prices/print' , [MarketingController::class , 'print_all'])->name('site.marketing_prices_print');
    Route::get('/marketing-prices/{id}/delete' , [MarketingController::class , 'delete'])->name('site.marketing_prices_delete');
    Route::get('/marketing-prices/all' , [MarketingController::class , 'all'])->name('site.marketing_prices_all');
    Route::get('/marketing-prices/create' , [MarketingController::class , 'create'])->name('site.marketing_prices_create');
    Route::post('/marketing-prices/save' , [MarketingController::class , 'store'])->name('site.marketing_prices_store');
    Route::get('/marketing-prices/titles' , [MarketingController::class , 'titles'])->name('site.marketing_titles');
    Route::get('/marketing-prices/titles/{id}' , [MarketingController::class , 'titles_edit'])->name('site.marketing_titles_edit');
    Route::post('/marketing-prices/titles/save_update' , [MarketingController::class , 'save_update'])->name('site.marketing_save_update');
    Route::get('/marketing-prices/titles/{id}/remove' , [MarketingController::class , 'titles_remove'])->name('site.marketing_titles_remove');
    Route::get('/marketing-prices/title/create' , [MarketingController::class , 'title_create'])->name('site.marketing_title_create');
    Route::post('/marketing-prices/title/save' , [MarketingController::class , 'title_store'])->name('site.marketing_title_store');
    Route::get('/marketing-prices/{id}' , [MarketingController::class , 'show'])->name('site.marketing_prices_show');
    Route::post('/marketing-prices/update_save' , [MarketingController::class , 'update_save'])->name('site.marketing_update_save');
     
    Route::get('/banks' , [BankController::class , 'index'])->name('site.banks');
    Route::get('/banks/create' , [BankController::class , 'create'])->name('site.banks_create');
    Route::post('/banks/save' , [BankController::class , 'save'])->name('site.banks_save');
    Route::get('/banks/{id}' , [BankController::class , 'edit'])->name('site.banks_edit');
    Route::post('/banks/{id}/delete' , [BankController::class , 'delete'])->name('site.banks_delete');
    Route::get('/banks/{id}/account-statement' , [BankController::class , 'bank_account_transactions'])->name('site.bank_account_transactions');
    Route::post('/banks/update' , [BankController::class , 'update'])->name('site.banks_update');
    
    Route::get('/storages' , [StoragesController::class , 'index'])->name('site.storages');

    Route::get('/storages/accounts-statement' , [StoragesController::class , 'acc_all'])->name('site.acc_all');
    Route::post('/storages/accounts-statement' , [StoragesController::class , 'acc_get'])->name('site.acc_all');
    Route::get('/storages/accounts-statement/{id}' , [StoragesController::class , 'acc_view'])->name('site.acc_view');
    Route::get('/storages/accounts-statement/print/{storage_id}/{date_from?}/{date_to?}/{transaction_type?}' , [StoragesController::class , 'stor_acc_print'])->name('site.stor_acc_print');
    
    Route::get('/storages/create' , [StoragesController::class , 'create'])->name('site.storages_create');
    Route::post('/storages/save' , [StoragesController::class , 'save'])->name('site.storages_save');
    Route::get('/storages/{id}' , [StoragesController::class , 'edit'])->name('site.storages_edit');
    Route::get('/storages/{id}/delete' , [StoragesController::class , 'delete'])->name('site.storages_delete');
    Route::post('/storages/update' , [StoragesController::class , 'update'])->name('site.storages_update');
    
    Route::get('/sub-storages' , [StoragesController::class , 'sub_index'])->name('site.sub_storages');
    Route::get('/sub-storages/create' , [StoragesController::class , 'sub_create'])->name('site.sub_storages_create');
    Route::post('/sub-storages/save' , [StoragesController::class , 'sub_save'])->name('site.sub_storages_save');
    Route::get('/sub-storages/{id}' , [StoragesController::class , 'sub_edit'])->name('site.sub_storages_edit');
    Route::get('/sub-storages/{id}/delete' , [StoragesController::class , 'sub_delete'])->name('site.sub_storages_delete');
    Route::post('/sub-storages/update' , [StoragesController::class , 'sub_update'])->name('site.sub_storages_update');

    Route::get('/collectors' , [CollectorsController::class , 'index'])->name('site.collectors');
    Route::get('/collectors/create' , [CollectorsController::class , 'create'])->name('site.collectors_create');
    Route::post('/collectors/save' , [CollectorsController::class , 'save'])->name('site.collectors_save');
    Route::get('/collectors/{id}' , [CollectorsController::class , 'edit'])->name('site.collectors_edit');
    Route::get('/collectors/{id}/delete' , [CollectorsController::class , 'delete'])->name('site.collectors_delete');
    Route::post('/collectors/update' , [CollectorsController::class , 'update'])->name('site.collectors_update');
    
    Route::get('/bonds' , [BondsController::class , 'index'])->name('site.bonds');
    Route::get('/bonds/daily-report' , [BondsController::class , 'daily_report'])->name('site.bonds_daily_report');
    Route::get('/bonds/add' , [BondsController::class , 'create'])->name('site.bonds_create');
    Route::post('/bonds/save' , [BondsController::class , 'save'])->name('site.bonds_save');
    Route::get('/bonds/{id}' , [BondsController::class , 'edit'])->name('site.bonds_edit');
    Route::post('/bonds/{id}/delete' , [BondsController::class , 'delete'])->name('site.bonds_delete');
    Route::post('/bonds/update' , [BondsController::class , 'update'])->name('site.bonds_update');
    Route::get('/bonds/{id}/print' , [BondsController::class , 'print_one'])->name('site.bonds_print_one');
    Route::get('/bonds/{id}/edit' , [BondsController::class , 'edit'])->name('site.bonds_edit');
    Route::post('/bonds/save_update' , [BondsController::class , 'save_update'])->name('site.bonds_save_update');
    Route::get('/bonds/{id}/approve' , [BondsController::class , 'bonds_approve'])->name('site.bonds_approve');
   
    Route::get('/shared-invoices' , [SharedInvoices::class , 'shared_invoices'])->name('site.shared_invoices');
    Route::get('/shared-invoices/create' , [SharedInvoices::class , 'shared_invoices_create'])->name('site.shared_invoices_create');
    Route::post('/shared-invoices/save' , [SharedInvoices::class , 'shared_invoices_save'])->name('site.shared_invoices_save');
    
    Route::get('/shared-invoices/reissue/{id}' , [SharedInvoices::class , 'shared_invoices_reissue_create'])->name('site.shared_invoices_reissue_create');
    Route::post('/shared-invoices/reissue/save' , [SharedInvoices::class , 'shared_invoices_reissue_save'])->name('site.shared_invoices_reissue_save');
    
    Route::get('/shared-invoices/refund/{id}' , [SharedInvoices::class , 'shared_invoices_refund'])->name('site.shared_invoices_refund');
    Route::post('/shared-invoices/refund/save' , [SharedInvoices::class , 'shared_invoices_refund_save'])->name('site.shared_invoices_refund_save');
    
    Route::get('/profits',[ProfitsController::class , 'index'])->name('site.profits');
    
    Route::get('/my-account' , [UserController::class , 'index'])->name('site.my_account');
    Route::post('/my-account/save' , [UserController::class , 'save'])->name('site.my_account_saves');
    
    Route::get('/admins' , [UserController::class , 'admins'])->name('site.admins');
    
    Route::get('/admins/{id}/edit' , [UserController::class , 'admins_edit'])->name('site.admins_edit');
    Route::post('/admins/save_update' , [UserController::class , 'admins_save_update'])->name('site.admins_save_update');
    
    Route::get('/admins/{id}/remove' , [UserController::class , 'admins_remove'])->name('site.admins_remove');

    Route::get('/admins/create' , [UserController::class , 'admins_create'])->name('site.admins_create');
    Route::post('/admins/save' , [UserController::class , 'admins_save'])->name('site.admins_save');
    
    Route::get('/passwords' , [UserController::class , 'password'])->name('site.password');
    Route::get('/passwords/create' , [UserController::class , 'password_create'])->name('site.password_create');
    Route::post('/passwords/save' , [UserController::class , 'password_save'])->name('site.password_save');
    Route::get('/passwords/{id}/edit' , [UserController::class , 'password_edit'])->name('site.password_edit');
    Route::post('/passwords/update' , [UserController::class , 'password_update'])->name('site.password_update');
    Route::post('/passwords/{id}/delete' , [UserController::class , 'password_delete'])->name('site.password_delete');
    
    Route::get('/api/supp_info/{id}' , function($id){
        
         $trsnactions = App\Models\AccountStatement::select('*')
                          ->where('supp_client_id' , $id)
                          ->where('is_storage', '!=' , 1);
            $trsnactions = $trsnactions->get();
                      $total_credit = 0;
                      $total_debit = 0;
                      
                    foreach($trsnactions as $trsnaction){
                        $total_credit += $trsnaction->credit_balance;
                        $total_debit += $trsnaction->debit_balance;
                    } 
        
        return response()->json([
    'st_code' => 200,
    'balance' => number_format($total_debit - $total_credit , 2),
]);
        
//        dd();
    });
    
});

Auth::routes();
Route::get('/auth/login' , function(){
    abort(404);
});
Route::post('/auth/login', [App\Http\Controllers\Auth\LoginController::class, 'do_login'])->name('fmx_login');
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/logout', [App\Http\Controllers\HomeController::class, 'logout'])->name('logout');

Route::get('/testpage', [IndexController::class , 'test_page'])->name('site.test_page');
