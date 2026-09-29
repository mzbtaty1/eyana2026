<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\{
    IndexController,
    AirlineController,
    InvoicesController,
    InvoiceFullReportController,
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

Route::middleware(['auth', 'check_status', 'can:settings.manage'])->get('/make_artisan' , function(){
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

Route::get('/test', [IndexController::class , 'test'])->middleware(['auth', 'check_status', 'can:settings.manage'])->name('site.test');

Route::middleware(['auth' , 'check_status'])->group(function(){
  
    Route::get('/', [IndexController::class , 'index'])->name('site.index');
    Route::get('/change_mode', [IndexController::class , 'change_mode'])->name('site.change_mode');
    Route::get('/invoices/search', [InvoiceController::class, 'searchInvoices'])->middleware('can:reports.all')->name('invoices.search'); 
    
    Route::get('/suppliers', [SuppliersController::class , 'index'])->name('site.suppliers');
    Route::get('/suppliers/create', [SuppliersController::class , 'create'])->name('site.suppliers_create');
    Route::post('/suppliers/save', [SuppliersController::class , 'save'])->name('site.suppliers_save');
    Route::get('/suppliers/{id}', [SuppliersController::class , 'edit'])->name('site.suppliers_edit');
    Route::get('/suppliers/{id}/overview', [SuppliersController::class , 'overview'])->name('site.suppliers_overview');
    Route::get('/suppliers/{id}/overview/invoices', [SuppliersController::class , 'overviewInvoices'])->name('site.suppliers_overview_invoices');
    Route::post('/suppliers/{id}/delete', [SuppliersController::class , 'delete'])->name('site.suppliers_delete');
    Route::post('/suppliers/update', [SuppliersController::class , 'update'])->name('site.suppliers_update');
     
    Route::middleware('can:finance.manage')->get('/customers', [CustomersController::class , 'index'])->name('site.customers');
    Route::middleware('can:finance.manage')->get('/customers/create', [CustomersController::class , 'create'])->name('site.customers_create');
    Route::middleware('can:finance.manage')->post('/customers/save', [CustomersController::class , 'save'])->name('site.customers_save');
    Route::middleware('can:finance.manage')->get('/customers/{id}', [CustomersController::class , 'edit'])->name('site.customers_edit');
    Route::middleware('can:finance.manage')->post('/customers/{id}/delete', [CustomersController::class , 'delete'])->name('site.customers_delete');
    Route::middleware('can:finance.manage')->post('/customers/update', [CustomersController::class , 'update'])->name('site.customers_update');
    
    Route::middleware('can:settings.manage')->get('/alerts', [AlertsController::class , 'index'])->name('site.alerts');
    Route::middleware('can:settings.manage')->get('/alerts/create', [AlertsController::class , 'create'])->name('site.alerts_create');
    Route::middleware('can:settings.manage')->post('/alerts/save', [AlertsController::class , 'save'])->name('site.alerts_save');
    Route::middleware('can:settings.manage')->get('/alerts/{id}', [AlertsController::class , 'edit'])->name('site.alerts_edit');
    Route::middleware('can:settings.manage')->post('/alerts/{id}/delete', [AlertsController::class , 'delete'])->name('site.alerts_delete');
    Route::middleware('can:settings.manage')->post('/alerts/update', [AlertsController::class , 'update'])->name('site.alerts_update');
    
    Route::middleware('can:finance.manage')->get('/expenses', [ExpensesController::class , 'index'])->name('site.expenses');
    Route::middleware('can:finance.manage')->get('/expenses/create', [ExpensesController::class , 'create'])->name('site.expenses_create');
    Route::middleware('can:finance.manage')->post('/expenses/save', [ExpensesController::class , 'save'])->name('site.expenses_save');
    Route::middleware('can:finance.manage')->get('/expenses/{id}', [ExpensesController::class , 'edit'])->name('site.expenses_edit');
    Route::middleware('can:finance.manage')->post('/expenses/{id}/delete', [ExpensesController::class , 'delete'])->name('site.expenses_delete');
    Route::middleware('can:finance.manage')->post('/expenses/update', [ExpensesController::class , 'update'])->name('site.expenses_update');
    
    Route::get('/visas', [VisaController::class , 'index'])->name('site.visas');
    Route::get('/visas/create', [VisaController::class , 'create'])->name('site.visas_create');
    Route::post('/visas/save', [VisaController::class , 'save'])->name('site.visas_save');
    Route::get('/visas/{id}', [VisaController::class , 'edit'])->name('site.visas_edit');
    Route::post('/visas/{id}/delete', [VisaController::class , 'delete'])->name('site.visas_delete');
    Route::post('/visas/{id}/status', [VisaController::class , 'status'])->name('site.visas_status');
    Route::post('/visas/update', [VisaController::class , 'update'])->name('site.visas_update');
    
    Route::middleware('can:settings.manage')->get('/airlines', [AirlineController::class , 'index'])->name('site.airlines');
    Route::middleware('can:settings.manage')->get('/airlines/create', [AirlineController::class , 'create'])->name('site.airlines_create');
    Route::middleware('can:settings.manage')->post('/airlines/save', [AirlineController::class , 'save'])->name('site.airlines_save');
    Route::middleware('can:settings.manage')->get('/airlines/{id}', [AirlineController::class , 'edit'])->name('site.airlines_edit');
    Route::middleware('can:settings.manage')->post('/airlines/{id}/delete', [AirlineController::class , 'delete'])->name('site.airlines_delete');
    Route::middleware('can:settings.manage')->post('/airlines/update', [AirlineController::class , 'update'])->name('site.airlines_update');
    
    Route::middleware('can:invoices.view')->get('/invoices', [InvoicesController::class, 'index'])->name('site.invoices');
    // the same list limited to flight invoices (invoice_section 1) / visa invoices (section 2)
    Route::middleware('can:invoices.view')->get('/invoices/flight', [InvoicesController::class, 'index'])->defaults('kind', 'flight')->name('site.invoices_flight');
    Route::middleware('can:invoices.view')->get('/invoices/visa', [InvoicesController::class, 'index'])->defaults('kind', 'visa')->name('site.invoices_visa');
    Route::get('/get_invoices_info', [InvoicesController::class, 'getInvoices'])->name('site.invoices_json');
    Route::get('/get_invoices_info_3months', [InvoicesController::class, 'getInvoices3Months'])->name('site.invoices_json_3months');
    // Server-side DataTables data for the main invoices list (all invoices incl. shared).
    Route::middleware('can:invoices.view')->get('/invoices/list-data', [InvoicesController::class, 'invoicesListData'])->name('site.invoices_list_data');

    Route::get('/invoices/ajax', [InvoicesController::class, 'ajax'])->name('site.invoices_ajax');
    Route::get('/invoices/lite', [InvoicesController::class, 'lite'])->name('site.invoices_lite');

    Route::get('/invoices/ajax2', [InvoicesController::class, 'ajax2'])->name('site.invoices_ajax2');
    Route::get('/invoices/ajax-3', [InvoicesController::class, 'ajax3'])->name('site.invoices_ajax-3');
    Route::middleware('can:invoices.view')->get('/invoices-info/{id}', [InvoicesController::class, 'invoice_info'])->name('site.invoice_info');

    // Invoice management routes with search functionality
    Route::middleware('can:invoices.view')->get('/invoices/management', [InvoiceController::class, 'management'])->name('invoices.management');
    Route::middleware('can:invoices.view')->get('/invoices/data', [InvoiceController::class, 'getInvoicesData'])->name('invoices.data');
    Route::get('/get-invoices-management', [InvoiceController::class, 'getInvoicesManagement'])->middleware('can:reports.all')->name('invoices.data.management');
    Route::middleware('can:invoices.confirm')->post('/invoices/toggle-confirm', [InvoiceController::class, 'toggleConfirmStatus'])->name('invoices.toggle-confirm');
    Route::delete('/invoices/{id}/delete', [InvoiceController::class, 'deleteInvoice'])->name('invoices.delete');

    Route::get('/invoices/daily-report' , [InvoicesController::class , 'daily_report'])->name('site.invoices_daily_report');
    
    // Detailed invoice report (read-only, App\Services\InvoiceFullReport); the old search form's POST shows the same report.
    Route::middleware('can:reports.own')->get('/invoices/full-report' , [InvoiceFullReportController::class , 'index'])->name('site.invoices_full_report');
    Route::middleware('can:reports.own')->post('/invoices/full-report' , [InvoiceFullReportController::class , 'legacy'])->name('site.invoices_full_report_get');
    Route::middleware('can:reports.own')->get('/invoices/full-report/data' , [InvoiceFullReportController::class , 'data'])->name('site.invoices_full_report_data');
    Route::middleware('can:reports.own')->get('/invoices/full-report/groups' , [InvoiceFullReportController::class , 'groups'])->name('site.invoices_full_report_groups');
    Route::middleware('can:reports.own')->get('/invoices/full-report/excel' , [InvoiceFullReportController::class , 'excel'])->name('site.invoices_full_report_excel');
    Route::middleware('can:reports.own')->get('/invoices/full-report/print' , [InvoiceFullReportController::class , 'print'])->name('site.invoices_full_report_print');
    
    Route::get('/invoices/reissue' , [InvoicesController::class , 'invoices_reissue'])->name('site.invoices_reissue');
    Route::post('/invoices/reissue' , [InvoicesController::class , 'invoices_reissue_get'])->name('site.invoices_reissue');
    Route::get('/invoices/reissue/{id}' , [InvoicesController::class , 'invoices_reissue_create'])->name('site.invoices_reissue_create');
    Route::post('/invoices/reissue/save' , [InvoicesController::class , 'invoices_reissue_save'])->name('site.invoices_reissue_save');
    // Visa Invoice re-issue (section 2): visa fields only, saved through invoices_reissue_save
    Route::post('/invoices/reissue/visa/save' , [InvoicesController::class , 'reissueVisaSave'])->name('site.invoices_reissue_visa_save');
    Route::middleware('can:invoices.create')->get('/invoices/create' , [InvoicesController::class , 'create'])->name('site.invoices_create');
    // same Add Invoice form, customer fixed to the Counter Customer (config eyana.counter_customer_ids)
    Route::middleware('can:invoices.create')->get('/invoices/create/counter' , [InvoicesController::class , 'create'])->defaults('counter', true)->name('site.invoices_create_counter');
    // Visa Invoice (invoice_section 2): the invoice type chooser's second choice, normal and Counter Customer
    Route::middleware('can:invoices.create')->get('/invoices/create/visa' , [InvoicesController::class , 'createVisa'])->name('site.invoices_create_visa');
    Route::middleware('can:invoices.create')->get('/invoices/create/counter/visa' , [InvoicesController::class , 'createVisa'])->defaults('counter', true)->name('site.invoices_create_counter_visa');
    Route::middleware('can:invoices.create')->post('/invoices/visa/save' , [InvoicesController::class , 'storeVisa'])->name('site.invoices_visa_save');
    Route::middleware('can:invoices.edit')->post('/invoices/visa/save-update' , [InvoicesController::class , 'saveVisaUpdate'])->name('site.invoices_visa_save_update');
    Route::middleware('can:invoices.create')->post('/invoices/save' , [InvoicesController::class , 'store'])->name('site.invoices_save');
    Route::middleware('can:invoices.view')->get('/invoices/{id}/show' , [InvoicesController::class , 'show'])->name('site.invoices_show');
    
    Route::middleware('can:invoices.edit')->get('/invoices/{id}/edit' , [InvoicesController::class , 'edit_invoice'])->name('site.invoices_edit');
    Route::middleware('can:invoices.edit')->post('/invoices/save_update' , [InvoicesController::class , 'save_update'])->name('site.invoices_save_update');
    // Edit ONE passenger/ticket of an invoice (the other passengers stay unchanged).
    Route::middleware('can:invoices.edit')->get('/invoices/{id}/edit-passenger' , [InvoicesController::class , 'edit_passenger'])->name('site.invoices_edit_passenger');
    Route::middleware('can:invoices.edit')->post('/invoices/edit-passenger/save' , [InvoicesController::class , 'save_passenger'])->name('site.invoices_save_passenger');
    
    Route::get('/invoices/{id}/confirm' , [InvoicesController::class , 'confirm'])->name('site.invoices_confirm');
    Route::post('/invoices/confirm_save' , [InvoicesController::class , 'confirm_save'])->name('site.invoices_confirm_save');
    Route::get('/invoices/refund/{id}' , [InvoicesController::class , 'invoices_refund'])->name('site.invoices_refund');
    Route::post('/invoices/refund/save' , [InvoicesController::class , 'invoices_refund_save'])->name('site.invoices_refund_save');
    
    Route::get('/invoices/pay-part/{id}' , [InvoicesController::class , 'pay_part'])->name('site.invoices_pay_part');
    Route::post('/invoices/pay-part/save' , [InvoicesController::class , 'pay_part_save'])->name('site.pay_part_save');

    Route::middleware('can:reports.own')->get('/invoices/employee-log' , [InvoicesController::class , 'employee_log'])->name('site.employee_log');
    Route::middleware('can:reports.own')->post('/invoices/employee-log/view' , [InvoicesController::class , 'employee_log_view'])->name('site.employee_log_view');
         
    Route::middleware('can:reports.own')->get('/invoices/employee-log/print' , [InvoicesController::class , 'employee_log_print'])->name('site.employee_log_print');
    // «تقرير عمولات الموظفين» (Employees step D): read-only; an employee only ever sees their own
    Route::middleware('can:commission.view_own')->get('/reports/employee-commission' , [\App\Http\Controllers\Frontend\EmployeeCommissionReportController::class , 'index'])->name('site.employee_commission_report');
    Route::middleware('can:invoices.confirm')->get('/invoices/{id}/approve' , [InvoicesController::class , 'invoices_approve'])->name('site.invoices_approve');
    Route::get('/invoices/air-cairo/calc' , [InvoicesController::class , 'air_cairo_calc'])->name('site.air_cairo_calc');
    
    // Fixed: Add proper DELETE route for invoice removal
    Route::delete('/invoices/{id}/remove', [InvoiceController::class, 'deleteInvoice'])->name('invoices.remove');
    // Keep existing GET routes for backward compatibility
    Route::get('/invoices/{id}/remove' , [InvoicesController::class , 'invoices_remove'])->name('site.invoices_remove');
    // deleting is a POST (CSRF) -- it was a GET link
    Route::post('/invoices/{id}/remove/send' , [InvoicesController::class , 'invoices_remove_send'])->name('site.invoices_remove_send');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement' , [AccountsController::class , 'accounts_statement'])->name('site.accounts_statement');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/{id}/approve' , [AccountsController::class , 'accounts_statement_approve'])->name('site.accounts_statement_approve');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/print/{invoice_beneficiaries}/{date_from?}/{date_to?}/{transaction_type?}' , [AccountsController::class , 'accounts_statement_print_all'])->name('site.accounts_statement_print_all');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/excel/{invoice_beneficiaries?}/{date_from?}/{date_to?}/{transaction_type?}' , [AccountsController::class , 'accounts_statement_print_excel'])->name('site.accounts_statement_print_excel');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/custom' , [AccountsController::class , 'accounts_statement_custom_get'])->name('site.accounts_statement_custom_get');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/suppliers' , [AccountsController::class , 'accounts_statement_suppliers_get'])->name('site.accounts_statement_suppliers_get');
    
    Route::middleware('can:finance.manage')->post('/accounts-statement/custom/get' , [AccountsController::class , 'accounts_statement_custom_view'])->name('site.accounts_statement_custom_view');
    
    Route::middleware('can:finance.manage')->post('/accounts-statement/suppliers/get' , [AccountsController::class , 'accounts_statement_suppliers_view'])->name('site.accounts_statement_suppliers_view');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/custom/print/{sup_stauts}/{from?}/{to?}' , [AccountsController::class , 'accounts_statement_custom_print'])->name('site.accounts_statement_custom_print');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/suppliers/print/{sup_stauts}/{from?}/{to?}' , [AccountsController::class , 'accounts_statement_suppliers_print'])->name('site.accounts_statement_suppliers_print');
    
    Route::middleware('can:finance.manage')->post('/accounts-statement/search' , [AccountsController::class , 'accounts_statement_search'])->name('site.accounts_statement_search');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/{id}' , [AccountsController::class , 'accounts_statement_get'])->name('site.accounts_statement_get');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/{id}/print' , [AccountsController::class , 'accounts_statement_print'])->name('site.accounts_statement_print');
    
    Route::middleware('can:finance.manage')->get('/accounts-statement/transaction/all' , [AccountsController::class , 'accounts_statement_trans_all'])->name('site.accounts_statement_trans_all');
    
    Route::middleware('can:finance.manage')->post('/accounts-statement/transaction/get_all' , [AccountsController::class , 'accounts_statement_trans_get_all'])->name('site.accounts_statement_trans_get_all');
    
    Route::middleware('can:settings.manage')->get('/marketing-prices' , [MarketingController::class , 'index'])->name('site.marketing_prices');
    Route::middleware('can:settings.manage')->get('/marketing-prices/print' , [MarketingController::class , 'print_all'])->name('site.marketing_prices_print');
    Route::middleware('can:settings.manage')->post('/marketing-prices/{id}/delete' , [MarketingController::class , 'delete'])->name('site.marketing_prices_delete');
    Route::middleware('can:settings.manage')->get('/marketing-prices/all' , [MarketingController::class , 'all'])->name('site.marketing_prices_all');
    Route::middleware('can:settings.manage')->get('/marketing-prices/create' , [MarketingController::class , 'create'])->name('site.marketing_prices_create');
    Route::middleware('can:settings.manage')->post('/marketing-prices/save' , [MarketingController::class , 'store'])->name('site.marketing_prices_store');
    Route::middleware('can:settings.manage')->get('/marketing-prices/titles' , [MarketingController::class , 'titles'])->name('site.marketing_titles');
    Route::middleware('can:settings.manage')->get('/marketing-prices/titles/{id}' , [MarketingController::class , 'titles_edit'])->name('site.marketing_titles_edit');
    Route::middleware('can:settings.manage')->post('/marketing-prices/titles/save_update' , [MarketingController::class , 'save_update'])->name('site.marketing_save_update');
    Route::middleware('can:settings.manage')->post('/marketing-prices/titles/{id}/remove' , [MarketingController::class , 'titles_remove'])->name('site.marketing_titles_remove');
    Route::middleware('can:settings.manage')->get('/marketing-prices/title/create' , [MarketingController::class , 'title_create'])->name('site.marketing_title_create');
    Route::middleware('can:settings.manage')->post('/marketing-prices/title/save' , [MarketingController::class , 'title_store'])->name('site.marketing_title_store');
    Route::middleware('can:settings.manage')->get('/marketing-prices/{id}' , [MarketingController::class , 'show'])->name('site.marketing_prices_show');
    Route::middleware('can:settings.manage')->post('/marketing-prices/update_save' , [MarketingController::class , 'update_save'])->name('site.marketing_update_save');
     
    Route::middleware('can:finance.manage')->get('/banks' , [BankController::class , 'index'])->name('site.banks');
    Route::middleware('can:finance.manage')->get('/banks/create' , [BankController::class , 'create'])->name('site.banks_create');
    Route::middleware('can:finance.manage')->post('/banks/save' , [BankController::class , 'save'])->name('site.banks_save');
    Route::middleware('can:finance.manage')->get('/banks/{id}' , [BankController::class , 'edit'])->name('site.banks_edit');
    Route::middleware('can:finance.manage')->post('/banks/{id}/delete' , [BankController::class , 'delete'])->name('site.banks_delete');
    Route::middleware('can:finance.manage')->get('/banks/{id}/account-statement' , [BankController::class , 'bank_account_transactions'])->name('site.bank_account_transactions');
    Route::middleware('can:finance.manage')->post('/banks/update' , [BankController::class , 'update'])->name('site.banks_update');
    
    Route::middleware('can:finance.manage')->get('/storages' , [StoragesController::class , 'index'])->name('site.storages');

    Route::middleware('can:finance.manage')->get('/storages/accounts-statement' , [StoragesController::class , 'acc_all'])->name('site.acc_all');
    Route::middleware('can:finance.manage')->post('/storages/accounts-statement' , [StoragesController::class , 'acc_get'])->name('site.acc_all');
    Route::get('/storages/accounts-statement/{id}' , [StoragesController::class , 'acc_view'])->middleware('can:finance.manage')->name('site.acc_view');
    Route::middleware('can:finance.manage')->get('/storages/accounts-statement/print/{storage_id}/{date_from?}/{date_to?}/{transaction_type?}' , [StoragesController::class , 'stor_acc_print'])->name('site.stor_acc_print');
    
    Route::middleware('can:finance.manage')->get('/storages/create' , [StoragesController::class , 'create'])->name('site.storages_create');
    Route::middleware('can:finance.manage')->post('/storages/save' , [StoragesController::class , 'save'])->name('site.storages_save');
    Route::middleware('can:finance.manage')->get('/storages/{id}' , [StoragesController::class , 'edit'])->name('site.storages_edit');
    Route::middleware('can:finance.manage')->post('/storages/{id}/delete' , [StoragesController::class , 'delete'])->name('site.storages_delete');
    Route::middleware('can:finance.manage')->post('/storages/update' , [StoragesController::class , 'update'])->name('site.storages_update');
    
    Route::middleware('can:finance.manage')->get('/sub-storages' , [StoragesController::class , 'sub_index'])->name('site.sub_storages');
    Route::middleware('can:finance.manage')->get('/sub-storages/create' , [StoragesController::class , 'sub_create'])->name('site.sub_storages_create');
    Route::middleware('can:finance.manage')->post('/sub-storages/save' , [StoragesController::class , 'sub_save'])->name('site.sub_storages_save');
    Route::middleware('can:finance.manage')->get('/sub-storages/{id}' , [StoragesController::class , 'sub_edit'])->name('site.sub_storages_edit');
    Route::middleware('can:finance.manage')->post('/sub-storages/{id}/delete' , [StoragesController::class , 'sub_delete'])->name('site.sub_storages_delete');
    Route::middleware('can:finance.manage')->post('/sub-storages/update' , [StoragesController::class , 'sub_update'])->name('site.sub_storages_update');

    Route::middleware('can:settings.manage')->get('/collectors' , [CollectorsController::class , 'index'])->name('site.collectors');
    Route::middleware('can:settings.manage')->get('/collectors/create' , [CollectorsController::class , 'create'])->name('site.collectors_create');
    Route::middleware('can:settings.manage')->post('/collectors/save' , [CollectorsController::class , 'save'])->name('site.collectors_save');
    Route::middleware('can:settings.manage')->get('/collectors/{id}' , [CollectorsController::class , 'edit'])->name('site.collectors_edit');
    Route::middleware('can:settings.manage')->post('/collectors/{id}/delete' , [CollectorsController::class , 'delete'])->name('site.collectors_delete');
    Route::middleware('can:settings.manage')->post('/collectors/update' , [CollectorsController::class , 'update'])->name('site.collectors_update');
    
    Route::middleware('can:finance.manage')->get('/bonds' , [BondsController::class , 'index'])->name('site.bonds');
    Route::middleware('can:finance.manage')->get('/bonds/daily-report' , [BondsController::class , 'daily_report'])->name('site.bonds_daily_report');
    Route::middleware('can:finance.manage')->get('/bonds/add' , [BondsController::class , 'create'])->name('site.bonds_create');
    Route::middleware('can:bonds.save')->post('/bonds/save' , [BondsController::class , 'save'])->name('site.bonds_save');
    Route::middleware('can:finance.manage')->get('/bonds/{id}' , [BondsController::class , 'edit'])->name('site.bonds_edit');
    Route::middleware('can:finance.manage')->post('/bonds/{id}/delete' , [BondsController::class , 'delete'])->name('site.bonds_delete');
    Route::post('/bonds/update' , [BondsController::class , 'update'])->middleware('can:finance.manage')->name('site.bonds_update');
    Route::middleware('can:finance.manage')->get('/bonds/{id}/print' , [BondsController::class , 'print_one'])->name('site.bonds_print_one');
    Route::middleware('can:finance.manage')->get('/bonds/{id}/edit' , [BondsController::class , 'edit'])->name('site.bonds_edit');
    Route::middleware('can:finance.manage')->post('/bonds/save_update' , [BondsController::class , 'save_update'])->name('site.bonds_save_update');
    Route::middleware('can:finance.manage')->get('/bonds/{id}/approve' , [BondsController::class , 'bonds_approve'])->name('site.bonds_approve');
   
    Route::get('/shared-invoices' , [SharedInvoices::class , 'shared_invoices'])->name('site.shared_invoices');
    Route::middleware('can:invoices.create')->get('/shared-invoices/create' , [SharedInvoices::class , 'shared_invoices_create'])->name('site.shared_invoices_create');
    Route::middleware('can:invoices.create')->post('/shared-invoices/save' , [SharedInvoices::class , 'shared_invoices_save'])->name('site.shared_invoices_save');
    
    Route::get('/shared-invoices/reissue/{id}' , [SharedInvoices::class , 'shared_invoices_reissue_create'])->name('site.shared_invoices_reissue_create');
    Route::post('/shared-invoices/reissue/save' , [SharedInvoices::class , 'shared_invoices_reissue_save'])->name('site.shared_invoices_reissue_save');
    
    Route::get('/shared-invoices/refund/{id}' , [SharedInvoices::class , 'shared_invoices_refund'])->name('site.shared_invoices_refund');
    Route::post('/shared-invoices/refund/save' , [SharedInvoices::class , 'shared_invoices_refund_save'])->name('site.shared_invoices_refund_save');
    
    Route::middleware('can:reports.own')->get('/profits',[ProfitsController::class , 'index'])->name('site.profits');
    
    // «شرائح العمولات» (Employees step B): commission tier tables -- admin only (commission.settings)
    Route::middleware('can:commission.settings')->prefix('settings/commission-tiers')->group(function () {
        $c = \App\Http\Controllers\Frontend\CommissionTierController::class;
        Route::get('/', [$c, 'index'])->name('site.commission_tiers');
        Route::get('/create', [$c, 'create'])->name('site.commission_tiers_create');
        Route::post('/', [$c, 'store'])->name('site.commission_tiers_store');
        Route::get('/{id}', [$c, 'show'])->whereNumber('id')->name('site.commission_tiers_show');
        Route::get('/{id}/edit', [$c, 'edit'])->whereNumber('id')->name('site.commission_tiers_edit');
        Route::post('/{id}', [$c, 'update'])->whereNumber('id')->name('site.commission_tiers_update');
        Route::post('/{id}/status', [$c, 'status'])->whereNumber('id')->name('site.commission_tiers_status');
        Route::post('/{id}/delete', [$c, 'destroy'])->whereNumber('id')->name('site.commission_tiers_delete');
    });

    // «صرف عمولات الموظفين» (Employees step E): finance.manage (admin); an employee's own history: commission.view_own
    Route::prefix('commissions')->group(function () {
        $c = \App\Http\Controllers\Frontend\CommissionPayoutController::class;
        Route::middleware('can:finance.manage')->get('/payouts', [$c, 'index'])->name('site.commission_payouts');
        Route::middleware('can:finance.manage')->get('/payouts/print', [$c, 'print'])->name('site.commission_payouts_print');
        Route::middleware('can:finance.manage')->get('/payouts/excel', [$c, 'excel'])->name('site.commission_payouts_excel');
        Route::middleware('can:finance.manage')->get('/payouts/preview', [$c, 'preview'])->name('site.commission_payouts_preview');
        Route::middleware('can:finance.manage')->post('/payouts', [$c, 'store'])->name('site.commission_payouts_store');
        Route::middleware('can:finance.manage')->post('/payouts/{id}/reverse', [$c, 'reverse'])->whereNumber('id')->name('site.commission_payouts_reverse');
        Route::middleware('can:finance.manage')->get('/payouts/{id}/receipt', [$c, 'receipt'])->whereNumber('id')->name('site.commission_payouts_receipt');
        Route::middleware('can:commission.view_own')->get('/my-payouts', [$c, 'mine'])->name('site.commission_payouts_mine');
    });

    // Internal tourism («السياحة الداخلية»): bookings, programs, report, operation lists
    Route::prefix('tourism')->group(function () {
        $b = \App\Http\Controllers\Frontend\TourismBookingsController::class;
        Route::middleware('can:tourism.view')->get('/bookings', [$b, 'index'])->name('site.tourism_bookings');
        Route::middleware('can:tourism.create')->get('/bookings/create', [$b, 'create'])->name('site.tourism_bookings_create');
        Route::middleware('can:tourism.create')->post('/bookings', [$b, 'store'])->name('site.tourism_bookings_store');
        Route::middleware('can:tourism.view')->get('/bookings/{id}', [$b, 'show'])->whereNumber('id')->name('site.tourism_bookings_show');
        Route::middleware('can:tourism.edit')->get('/bookings/{id}/edit', [$b, 'edit'])->whereNumber('id')->name('site.tourism_bookings_edit');
        Route::middleware('can:tourism.edit')->post('/bookings/{id}', [$b, 'update'])->whereNumber('id')->name('site.tourism_bookings_update');
        Route::middleware('can:tourism.edit')->post('/bookings/{id}/delete', [$b, 'destroy'])->whereNumber('id')->name('site.tourism_bookings_delete');
        Route::middleware('can:tourism.confirm')->post('/bookings/{id}/confirm', [$b, 'confirm'])->whereNumber('id')->name('site.tourism_bookings_confirm');
        Route::middleware('can:tourism.cancel')->post('/bookings/{id}/items/{itemId}/cancel', [$b, 'cancelItem'])->whereNumber(['id', 'itemId'])->name('site.tourism_bookings_cancel_item');
        Route::middleware('can:tourism.cancel')->post('/bookings/{id}/cancel', [$b, 'cancel'])->whereNumber('id')->name('site.tourism_bookings_cancel');
        Route::middleware('can:tourism.view')->post('/bookings/{id}/receive', [$b, 'receive'])->whereNumber('id')->name('site.tourism_bookings_receive');
        Route::middleware('can:finance.manage')->post('/bookings/{id}/refund', [$b, 'refund'])->whereNumber('id')->name('site.tourism_bookings_refund');
        Route::middleware('can:finance.manage')->post('/bookings/{id}/pay-supplier', [$b, 'paySupplier'])->whereNumber('id')->name('site.tourism_bookings_pay_supplier');
        Route::middleware('can:finance.manage')->post('/bookings/{id}/vouchers/{bondId}/reverse', [$b, 'reverseVoucher'])->whereNumber(['id', 'bondId'])->name('site.tourism_bookings_reverse_voucher');
        Route::middleware('can:tourism.view')->get('/bookings/{id}/print/{doc}', [$b, 'print'])->whereNumber('id')->name('site.tourism_bookings_print');

        $p = \App\Http\Controllers\Frontend\TourismProgramsController::class;   // tourism.programs (controller)
        Route::get('/programs', [$p, 'index'])->name('site.tourism_programs');
        Route::get('/programs/create', [$p, 'create'])->name('site.tourism_programs_create');
        Route::post('/programs', [$p, 'store'])->name('site.tourism_programs_store');
        Route::get('/programs/{id}/edit', [$p, 'edit'])->whereNumber('id')->name('site.tourism_programs_edit');
        Route::post('/programs/{id}', [$p, 'update'])->whereNumber('id')->name('site.tourism_programs_update');
        Route::post('/programs/{id}/status', [$p, 'status'])->whereNumber('id')->name('site.tourism_programs_status');

        $r = \App\Http\Controllers\Frontend\TourismReportController::class;
        Route::middleware('can:reports.own')->get('/report', [$r, 'index'])->name('site.tourism_report');
        Route::middleware('can:reports.own')->get('/report/print', [$r, 'print'])->name('site.tourism_report_print');
        Route::middleware('can:reports.own')->get('/report/excel', [$r, 'excel'])->name('site.tourism_report_excel');
        Route::middleware('can:tourism.view')->get('/operations/{type}', [$r, 'operations'])->name('site.tourism_operations');
    });

    Route::get('/my-account' , [UserController::class , 'index'])->name('site.my_account');
    Route::post('/my-account/save' , [UserController::class , 'save'])->name('site.my_account_saves');
    
    Route::middleware('can:employees.manage')->get('/admins' , [UserController::class , 'admins'])->name('site.admins');
    
    Route::middleware('can:employees.manage')->get('/admins/{id}/edit' , [UserController::class , 'admins_edit'])->name('site.admins_edit');
    Route::middleware('can:employees.manage')->post('/admins/save_update' , [UserController::class , 'admins_save_update'])->name('site.admins_save_update');
    
    Route::middleware('can:employees.manage')->post('/admins/{id}/remove' , [UserController::class , 'admins_remove'])->name('site.admins_remove');

    Route::middleware('can:employees.manage')->get('/admins/create' , [UserController::class , 'admins_create'])->name('site.admins_create');
    Route::middleware('can:employees.manage')->post('/admins/save' , [UserController::class , 'admins_save'])->name('site.admins_save');
    
    Route::middleware('can:settings.manage')->get('/passwords' , [UserController::class , 'password'])->name('site.password');
    Route::middleware('can:settings.manage')->get('/passwords/create' , [UserController::class , 'password_create'])->name('site.password_create');
    Route::middleware('can:settings.manage')->post('/passwords/save' , [UserController::class , 'password_save'])->name('site.password_save');
    Route::middleware('can:settings.manage')->get('/passwords/{id}/edit' , [UserController::class , 'password_edit'])->name('site.password_edit');
    Route::middleware('can:settings.manage')->post('/passwords/update' , [UserController::class , 'password_update'])->name('site.password_update');
    Route::middleware('can:settings.manage')->post('/passwords/{id}/delete' , [UserController::class , 'password_delete'])->name('site.password_delete');
    
    Route::middleware('can:finance.manage')->get('/api/supp_info/{id}' , function($id){
        
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

Route::middleware(['auth', 'check_status', 'can:settings.manage'])->get('/testpage', [IndexController::class , 'test_page'])->name('site.test_page');
