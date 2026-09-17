<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class InvoiceController extends Controller
{
    /**
     * Display the invoice management page
     */
    public function management()
    {
        return view('invoices.management');
    }

    /**
     * Update an existing invoice (preserving original creation date)
     */
    public function updateInvoice(Request $request)
    {
        try {
            $invoiceId = $request->input('id');
            
            // Get the original invoice to preserve creation date
            $originalInvoice = DB::table('invoices')->where('id', $invoiceId)->first();
            
            if (!$originalInvoice) {
                return back()->withErrors(['message' => 'الفاتورة غير موجودة']);
            }

            DB::beginTransaction();

            // Update invoice while preserving original invoice_date and created_at
            $updateData = [
                'invoice_travel_date' => $request->invoice_travel_date,
                'return_date' => $request->return_date,
                'from_location' => $request->from_location,
                'to_location' => $request->to_location,
                'invoice_airline' => $request->invoice_airline,
                'invoice_beneficiaries' => $request->invoice_beneficiaries,
                'invoice_section' => $request->invoice_section,
                'invoice_comments' => $request->invoice_comments,
                'invoice_currency' => $request->invoice_currency,
                'invoice_draft' => $request->invoice_draft,
                // IMPORTANT: Keep original dates for modifications
                'invoice_date' => $originalInvoice->invoice_date,
                'created_at' => $originalInvoice->created_at,
                'updated_at' => Carbon::now(), // Only update the modification timestamp
            ];

            // Handle file upload if provided
            if ($request->hasFile('myPoster')) {
                $file = $request->file('myPoster');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('storage/invoices'), $fileName);
                $updateData['invoice_ticket_file'] = 'storage/invoices/' . $fileName;
            }

            DB::table('invoices')->where('id', $invoiceId)->update($updateData);

            // Update ticket users data
            if ($request->has('ticket_info')) {
                $this->updateTicketUsers($originalInvoice->ticket_system_id, $request->ticket_info);
            }

            // Update vendor information
            if ($request->vendor_id) {
                $this->updateTicketVendor($originalInvoice->ticket_system_id, $request->vendor_id, $request->vendor_cost);
            }

            // Update account statements for modifications (keep original dates)
            $this->updateAccountStatementForModification($originalInvoice);

            DB::commit();

            Log::info('Invoice modified successfully', [
                'invoice_id' => $invoiceId,
                'es_id' => $originalInvoice->es_id,
                'original_date_preserved' => $originalInvoice->invoice_date,
                'modified_by' => Auth::id(),
                'modified_at' => Carbon::now()
            ]);

            return redirect()->route('site.invoices_management')
                ->with('success', 'تم تحديث الفاتورة بنجاح مع الحفاظ على تاريخ الإنشاء الأصلي');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Invoice update error: ' . $e->getMessage(), [
                'invoice_id' => $request->input('id'),
                'error' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors(['message' => 'حدث خطأ في تحديث الفاتورة: ' . $e->getMessage()]);
        }
    }

    /**
     * Reissue an invoice (creates new entry with execution date)
     */
    public function reissueInvoice(Request $request, $esId)
    {
        try {
            $originalInvoice = DB::table('invoices')->where('es_id', $esId)->first();
            
            if (!$originalInvoice) {
                return back()->withErrors(['message' => 'الفاتورة الأصلية غير موجودة']);
            }

            DB::beginTransaction();

            // Generate new ES ID for reissued invoice
            $newEsId = 'FLY-RS' . substr($esId, 6); // Replace prefix with FLY-RS
            $executionDate = Carbon::now();

            // Create new invoice record with execution date
            $reissueData = [
                'es_id' => $newEsId,
                'invoice_date' => $executionDate->format('Y-m-d'), // Use execution date
                'invoice_travel_date' => $originalInvoice->invoice_travel_date,
                'return_date' => $originalInvoice->return_date,
                'from_location' => $originalInvoice->from_location,
                'to_location' => $originalInvoice->to_location,
                'invoice_airline' => $originalInvoice->invoice_airline,
                'invoice_beneficiaries' => $originalInvoice->invoice_beneficiaries,
                'invoice_section' => $originalInvoice->invoice_section,
                'invoice_comments' => 'إعادة إصدار للفاتورة: ' . $esId,
                'invoice_currency' => $originalInvoice->invoice_currency,
                'invoice_draft' => $originalInvoice->invoice_draft,
                'invoice_create_by' => Auth::id(),
                'ticket_system_id' => $this->generateNewTicketSystemId(),
                'created_at' => $executionDate, // Execution timestamp
                'updated_at' => $executionDate,
            ];

            $newInvoiceId = DB::table('invoices')->insertGetId($reissueData);

            // Copy ticket users data
            $this->copyTicketUsers($originalInvoice->ticket_system_id, $reissueData['ticket_system_id']);

            // Copy vendor data
            $this->copyTicketVendor($originalInvoice->ticket_system_id, $reissueData['ticket_system_id']);

            // Create account statement entry with execution date
            $this->createAccountStatementForReissue($originalInvoice, $newEsId, $executionDate);

            DB::commit();

            Log::info('Invoice reissued successfully', [
                'original_es_id' => $esId,
                'new_es_id' => $newEsId,
                'execution_date' => $executionDate,
                'reissued_by' => Auth::id()
            ]);

            return redirect()->route('site.invoices_management')
                ->with('success', 'تم إعادة إصدار الفاتورة بنجاح بتاريخ التنفيذ: ' . $executionDate->format('d/m/Y H:i'));

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Invoice reissue error: ' . $e->getMessage(), [
                'es_id' => $esId,
                'error' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors(['message' => 'حدث خطأ في إعادة إصدار الفاتورة: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancel/Refund an invoice (creates cancellation entry with execution date)
     */
    public function cancelInvoice(Request $request, $esId)
    {
        try {
            $originalInvoice = DB::table('invoices')->where('es_id', $esId)->first();
            
            if (!$originalInvoice) {
                return back()->withErrors(['message' => 'الفاتورة الأصلية غير موجودة']);
            }

            DB::beginTransaction();

            $executionDate = Carbon::now();
            $newEsId = 'FLY-RD' . substr($esId, 6); // Replace prefix with FLY-RD

            // Create cancellation record with execution date
            $cancellationData = [
                'es_id' => $newEsId,
                'invoice_date' => $executionDate->format('Y-m-d'), // Use execution date
                'invoice_travel_date' => $originalInvoice->invoice_travel_date,
                'return_date' => $originalInvoice->return_date,
                'from_location' => $originalInvoice->from_location,
                'to_location' => $originalInvoice->to_location,
                'invoice_airline' => $originalInvoice->invoice_airline,
                'invoice_beneficiaries' => $originalInvoice->invoice_beneficiaries,
                'invoice_section' => $originalInvoice->invoice_section,
                'invoice_comments' => 'إلغاء للفاتورة: ' . $esId,
                'invoice_currency' => $originalInvoice->invoice_currency,
                'invoice_draft' => $originalInvoice->invoice_draft,
                'invoice_create_by' => Auth::id(),
                'ticket_system_id' => $this->generateNewTicketSystemId(),
                'invoice_status' => 3, // Cancelled status
                'created_at' => $executionDate, // Execution timestamp
                'updated_at' => $executionDate,
            ];

            $cancellationInvoiceId = DB::table('invoices')->insertGetId($cancellationData);

            // Create account statement entries for cancellation with execution date
            $this->createAccountStatementForCancellation($originalInvoice, $newEsId, $executionDate);

            // Update original invoice status
            DB::table('invoices')
                ->where('es_id', $esId)
                ->update([
                    'invoice_status' => 4, // Original cancelled
                    'updated_at' => $executionDate
                ]);

            DB::commit();

            Log::info('Invoice cancelled successfully', [
                'original_es_id' => $esId,
                'cancellation_es_id' => $newEsId,
                'execution_date' => $executionDate,
                'cancelled_by' => Auth::id()
            ]);

            return redirect()->route('site.invoices_management')
                ->with('success', 'تم إلغاء الفاتورة بنجاح بتاريخ التنفيذ: ' . $executionDate->format('d/m/Y H:i'));

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Invoice cancellation error: ' . $e->getMessage(), [
                'es_id' => $esId,
                'error' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors(['message' => 'حدث خطأ في إلغاء الفاتورة: ' . $e->getMessage()]);
        }
    }

    /**
     * Update account statement for modifications (preserves original dates)
     */
    private function updateAccountStatementForModification($originalInvoice)
    {
        try {
            // For modifications, we update the account statement but preserve the original created_at date
            $accountStatement = DB::table('account_statements')
                ->where('es_id', $originalInvoice->es_id)
                ->first();

            if ($accountStatement) {
                DB::table('account_statements')
                    ->where('es_id', $originalInvoice->es_id)
                    ->update([
                        'transaction_txt' => 'تعديل فاتورة: ' . $originalInvoice->es_id,
                        'updated_at' => Carbon::now(),
                        // Keep original created_at date
                        'created_at' => $accountStatement->created_at
                    ]);
            }

        } catch (Exception $e) {
            Log::error('Update account statement for modification error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create account statement for reissue (uses execution date)
     */
    private function createAccountStatementForReissue($originalInvoice, $newEsId, $executionDate)
    {
        try {
            // Get totals from ticket users
            $totalNet = $this->getTotalNetPrice($originalInvoice->ticket_system_id);
            $totalBought = $this->getTotalBoughtPrice($originalInvoice->ticket_system_id);

            // Create debit entry (what customer owes us)
            DB::table('account_statements')->insert([
                'es_id' => $newEsId,
                'supplier_id' => $originalInvoice->invoice_beneficiaries,
                'debit_balance' => $totalBought,
                'credit_balance' => 0,
                'transaction_type' => 1, // Invoice transaction
                'invoice_type' => $originalInvoice->invoice_section,
                'transaction_txt' => 'إعادة إصدار فاتورة: ' . $originalInvoice->es_id,
                'cumulative_balance' => $totalBought,
                'is_supp_account' => 0,
                'trans_storage' => 0,
                'added_by' => Auth::id(),
                'transaction_approved' => 0,
                'created_at' => $executionDate, // Use execution date
                'updated_at' => $executionDate,
            ]);

            Log::info('Account statement created for reissue', [
                'new_es_id' => $newEsId,
                'execution_date' => $executionDate
            ]);

        } catch (Exception $e) {
            Log::error('Create account statement for reissue error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create account statement for cancellation (uses execution date)
     */
    private function createAccountStatementForCancellation($originalInvoice, $newEsId, $executionDate)
    {
        try {
            // Get totals from original invoice
            $totalNet = $this->getTotalNetPrice($originalInvoice->ticket_system_id);
            $totalBought = $this->getTotalBoughtPrice($originalInvoice->ticket_system_id);

            // Create credit entry (refund to customer)
            DB::table('account_statements')->insert([
                'es_id' => $newEsId,
                'supplier_id' => $originalInvoice->invoice_beneficiaries,
                'debit_balance' => 0,
                'credit_balance' => $totalBought, // Amount refunded to customer
                'transaction_type' => 1, // Invoice transaction
                'invoice_type' => $originalInvoice->invoice_section,
                'transaction_txt' => 'إلغاء فاتورة: ' . $originalInvoice->es_id,
                'cumulative_balance' => -$totalBought,
                'is_supp_account' => 0,
                'trans_storage' => 0,
                'added_by' => Auth::id(),
                'transaction_approved' => 0,
                'created_at' => $executionDate, // Use execution date
                'updated_at' => $executionDate,
            ]);

            // Create debit entry (what vendor refunds to us)
            if ($totalNet > 0) {
                // Get vendor ID
                $vendorInfo = DB::table('ticket_vendors')
                    ->where('ticket_system_id', $originalInvoice->ticket_system_id)
                    ->first();

                if ($vendorInfo) {
                    DB::table('account_statements')->insert([
                        'es_id' => $newEsId,
                        'supplier_id' => $vendorInfo->vendor_id,
                        'debit_balance' => $totalNet, // Amount vendor refunds to us
                        'credit_balance' => 0,
                        'transaction_type' => 1,
                        'invoice_type' => $originalInvoice->invoice_section,
                        'transaction_txt' => 'استرداد من مورد - إلغاء فاتورة: ' . $originalInvoice->es_id,
                        'cumulative_balance' => $totalNet,
                        'is_supp_account' => 1, // Supplier account
                        'trans_storage' => 0,
                        'added_by' => Auth::id(),
                        'transaction_approved' => 0,
                        'created_at' => $executionDate, // Use execution date
                        'updated_at' => $executionDate,
                    ]);
                }
            }

            Log::info('Account statements created for cancellation', [
                'cancellation_es_id' => $newEsId,
                'execution_date' => $executionDate
            ]);

        } catch (Exception $e) {
            Log::error('Create account statement for cancellation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate new ticket system ID
     */
    private function generateNewTicketSystemId()
    {
        return 'TKT' . time() . rand(1000, 9999);
    }

    /**
     * Copy ticket users to new system
     */
    private function copyTicketUsers($originalSystemId, $newSystemId)
    {
        try {
            $originalUsers = DB::table('ticket_users')
                ->where('ticket_system_id', $originalSystemId)
                ->get();

            foreach ($originalUsers as $user) {
                DB::table('ticket_users')->insert([
                    'ticket_system_id' => $newSystemId,
                    'client_name' => $user->client_name,
                    'client_type' => $user->client_type,
                    'client_net_pice' => $user->client_net_pice,
                    'client_bought_price' => $user->client_bought_price,
                    'client_booking_id' => $user->client_booking_id,
                    'client_ticket_id' => $user->client_ticket_id,
                    'client_phone' => $user->client_phone,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

        } catch (Exception $e) {
            Log::error('Copy ticket users error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Copy ticket vendor to new system
     */
    private function copyTicketVendor($originalSystemId, $newSystemId)
    {
        try {
            $originalVendor = DB::table('ticket_vendors')
                ->where('ticket_system_id', $originalSystemId)
                ->first();

            if ($originalVendor) {
                DB::table('ticket_vendors')->insert([
                    'ticket_system_id' => $newSystemId,
                    'vendor_id' => $originalVendor->vendor_id,
                    'vendor_cost' => $originalVendor->vendor_cost,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

        } catch (Exception $e) {
            Log::error('Copy ticket vendor error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update ticket users data
     */
    private function updateTicketUsers($ticketSystemId, $ticketInfo)
    {
        try {
            // Delete existing users
            DB::table('ticket_users')->where('ticket_system_id', $ticketSystemId)->delete();

            // Insert updated users
            foreach ($ticketInfo as $info) {
                DB::table('ticket_users')->insert([
                    'ticket_system_id' => $ticketSystemId,
                    'client_name' => $info['name'],
                    'client_type' => $info['client_type'],
                    'client_net_pice' => $info['net_price'],
                    'client_bought_price' => $info['bought_price'],
                    'client_booking_id' => $info['book_id'],
                    'client_ticket_id' => $info['tikcet_id'],
                    'client_phone' => $info['client_phone'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

        } catch (Exception $e) {
            Log::error('Update ticket users error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update ticket vendor data
     */
    private function updateTicketVendor($ticketSystemId, $vendorId, $vendorCost)
    {
        try {
            DB::table('ticket_vendors')
                ->where('ticket_system_id', $ticketSystemId)
                ->update([
                    'vendor_id' => $vendorId,
                    'vendor_cost' => $vendorCost,
                    'updated_at' => Carbon::now(),
                ]);

        } catch (Exception $e) {
            Log::error('Update ticket vendor error: ' . $e->getMessage());
            throw $e;
        }
    }

    // Keep all existing methods from the original controller...
    public function getInvoicesData(Request $request)
    {
        try {
            // Get DataTables parameters
            $draw = intval($request->get('draw', 1));
            $start = intval($request->get('start', 0));
            $length = intval($request->get('length', 25));
            
            // Get search parameter
            $search = $request->get('search');
            $searchValue = isset($search['value']) ? trim($search['value']) : '';
            
            // Get custom filters
            $statusFilter = $request->get('status_filter', 'all');
            $periodFilter = $request->get('period_filter', '15days');
            $sectionFilter = $request->get('section_filter', 'all');
            
            Log::info('Search Request', [
                'search_value' => $searchValue,
                'status_filter' => $statusFilter,
                'period_filter' => $periodFilter,
                'section_filter' => $sectionFilter
            ]);
            
            // Build base query with all necessary joins for search
            $baseQuery = DB::table('invoices as i')
                ->select([
                    'i.id',
                    'i.es_id',
                    'i.created_at',
                    'i.invoice_date',
                    'i.invoice_travel_date',
                    'i.invoice_money_pay',
                    'i.from_location',
                    'i.to_location',
                    'i.invoice_section',
                    'i.invoice_create_by',
                    'i.invoice_beneficiaries',
                    'i.ticket_system_id',
                    'i.invoice_comments',
                    'i.invoice_status',
                    'i.invoice_shared',
                    'i.invoice_ticket_file'
                ]);

            // Apply period filter
            $this->applyPeriodFilter($baseQuery, $periodFilter);
            
            // Apply section filter
            if ($sectionFilter !== 'all') {
                $baseQuery->where('i.invoice_section', $sectionFilter);
            }
            
            // Get total records count (without any filters)
            $totalRecords = DB::table('invoices')->count();
            
            // Clone query for filtered count BEFORE applying search
            $countQuery = clone $baseQuery;
            
            // Apply search filter to both queries if search term exists
            if (!empty($searchValue)) {
                Log::info('Applying search filter', ['search_value' => $searchValue]);
                $this->applyLiveSearch($baseQuery, $searchValue);
                $this->applyLiveSearch($countQuery, $searchValue);
            }
            
            // Apply status filter after search
            if ($statusFilter !== 'all') {
                $this->applyStatusFilter($baseQuery, $statusFilter);
                $this->applyStatusFilter($countQuery, $statusFilter);
            }
            
            // Get filtered records count
            $filteredRecords = $countQuery->count();
            
            // Apply ordering
            $baseQuery->orderBy('i.invoice_date', 'desc')
                     ->orderBy('i.id', 'desc');
            
            // Apply pagination
            $invoices = $baseQuery->offset($start)->limit($length)->get();
            
            Log::info('Query Results', [
                'total_records' => $totalRecords,
                'filtered_records' => $filteredRecords,
                'returned_records' => $invoices->count(),
                'search_value' => $searchValue
            ]);
            
            // Process results for DataTables
            $data = [];
            foreach ($invoices as $invoice) {
                $data[] = $this->processInvoiceForTable($invoice);
            }
            
            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
                'success' => true
            ]);
            
        } catch (Exception $e) {
            Log::error('DataTables Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'draw' => intval($request->get('draw', 1)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'حدث خطأ في تحميل البيانات: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Apply enhanced live search across all relevant fields
     */
    private function applyLiveSearch($query, $searchValue)
    {
        try {
            $searchTerm = '%' . $searchValue . '%';
            
            $query->where(function($mainQuery) use ($searchTerm) {
                // Search in main invoice fields
                $mainQuery->where('i.es_id', 'LIKE', $searchTerm)
                         ->orWhere('i.from_location', 'LIKE', $searchTerm)
                         ->orWhere('i.to_location', 'LIKE', $searchTerm)
                         ->orWhere('i.invoice_comments', 'LIKE', $searchTerm);
                
                // Search in passenger names using subquery (with null check)
                $mainQuery->orWhereExists(function($subQuery) use ($searchTerm) {
                    $subQuery->select(DB::raw(1))
                             ->from('ticket_users as tu')
                             ->whereColumn('tu.ticket_system_id', 'i.ticket_system_id')
                             ->whereNotNull('i.ticket_system_id')
                             ->where(function($passengerQuery) use ($searchTerm) {
                                 $passengerQuery->where('tu.client_name', 'LIKE', $searchTerm)
                                               ->orWhere('tu.client_booking_id', 'LIKE', $searchTerm)
                                               ->orWhere('tu.client_ticket_id', 'LIKE', $searchTerm);
                             });
                });
                
                // Search in vendor names using subquery (with null check)
                $mainQuery->orWhereExists(function($subQuery) use ($searchTerm) {
                    $subQuery->select(DB::raw(1))
                             ->from('ticket_vendors as tv')
                             ->join('suppliers as s', 'tv.vendor_id', '=', 's.id')
                             ->whereColumn('tv.ticket_system_id', 'i.ticket_system_id')
                             ->whereNotNull('i.ticket_system_id')
                             ->where('s.name', 'LIKE', $searchTerm);
                });
                
                // Search in beneficiary names using subquery (with null check)
                $mainQuery->orWhereExists(function($subQuery) use ($searchTerm) {
                    $subQuery->select(DB::raw(1))
                             ->from('suppliers as s')
                             ->whereColumn('s.id', 'i.invoice_beneficiaries')
                             ->whereNotNull('i.invoice_beneficiaries')
                             ->where('s.name', 'LIKE', $searchTerm);
                });
                
                // Search in employee names using subquery (with null check)
                $mainQuery->orWhereExists(function($subQuery) use ($searchTerm) {
                    $subQuery->select(DB::raw(1))
                             ->from('users as u')
                             ->whereColumn('u.id', 'i.invoice_create_by')
                             ->whereNotNull('i.invoice_create_by')
                             ->where('u.name', 'LIKE', $searchTerm);
                });
            });
            
        } catch (Exception $e) {
            Log::error('Search query error: ' . $e->getMessage(), [
                'search_value' => $searchValue,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to simple search if complex search fails
            $query->where('i.es_id', 'LIKE', '%' . $searchValue . '%');
        }
    }

    /**
     * Process invoice data for table display
     */
    private function processInvoiceForTable($invoice)
    {
        try {
            // Get related data with error handling
            $passengerNames = $this->getPassengerNames($invoice->ticket_system_id);
            $bookingIds = $this->getBookingIds($invoice->ticket_system_id);
            $ticketIds = $this->getTicketIds($invoice->ticket_system_id);
            $vendorNames = $this->getVendorNames($invoice->ticket_system_id);
            $beneficiaryName = $this->getBeneficiaryName($invoice->invoice_beneficiaries);
            $employeeName = $this->getEmployeeName($invoice->invoice_create_by);
            
            // Calculate totals with error handling
            $totalNet = $this->getTotalNetPrice($invoice->ticket_system_id);
            $totalBought = $this->getTotalBoughtPrice($invoice->ticket_system_id);
            $paidAmount = floatval($invoice->invoice_money_pay ?? 0);
            $profit = $totalBought - $totalNet;
            
            // Determine payment status
            $paymentStatus = $this->getPaymentStatus($paidAmount, $totalBought);
            
            // Format dates - IMPORTANT: Show appropriate dates based on operation type
            $invoiceDate = $this->formatDate($invoice->invoice_date ?? $invoice->created_at);
            $travelDate = $this->formatDate($invoice->invoice_travel_date);
            
            // Get invoice type
            $invoiceType = $this->getInvoiceType($invoice->invoice_section ?? 1);
            
            return [
                'id' => $invoice->id,
                'es_id' => $invoice->es_id ?? 'غير محدد',
                'invoice_date' => $invoiceDate,
                'travel_date' => $travelDate,
                'vendor_names' => $vendorNames,
                'beneficiary_name' => $beneficiaryName,
                'total_net_price' => $totalNet,
                'total_bought_price' => $totalBought,
                'invoice_money_pay' => $paidAmount,
                'from_location' => $invoice->from_location ?? 'غير محدد',
                'to_location' => $invoice->to_location ?? 'غير محدد',
                'passenger_names' => $passengerNames,
                'booking_ids' => $bookingIds,
                'ticket_ids' => $ticketIds,
                'profit' => $profit,
                'invoice_type' => $invoiceType,
                'payment_status' => $paymentStatus,
                'invoice_status' => $invoice->invoice_status ?? 0,
                'employee_name' => $employeeName,
                'invoice_comments' => $invoice->invoice_comments ?? '',
                'invoice_ticket_file' => $invoice->invoice_ticket_file ?? null
            ];
        } catch (Exception $e) {
            Log::error('Process invoice error: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id ?? 'unknown',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Return basic data if processing fails
            return [
                'id' => $invoice->id,
                'es_id' => $invoice->es_id ?? 'غير محدد',
                'invoice_date' => $this->formatDate($invoice->invoice_date ?? $invoice->created_at),
                'travel_date' => $this->formatDate($invoice->invoice_travel_date),
                'vendor_names' => 'غير محدد',
                'beneficiary_name' => 'غير محدد',
                'total_net_price' => 0,
                'total_bought_price' => 0,
                'invoice_money_pay' => floatval($invoice->invoice_money_pay ?? 0),
                'from_location' => $invoice->from_location ?? 'غير محدد',
                'to_location' => $invoice->to_location ?? 'غير محدد',
                'passenger_names' => 'غير محدد',
                'booking_ids' => 'غير محدد',
                'ticket_ids' => 'غير محدد',
                'profit' => 0,
                'invoice_type' => $this->getInvoiceType($invoice->invoice_section ?? 1),
                'payment_status' => 0,
                'invoice_status' => $invoice->invoice_status ?? 0,
                'employee_name' => 'غير محدد',
                'invoice_comments' => $invoice->invoice_comments ?? '',
                'invoice_ticket_file' => null
            ];
        }
    }

    /**
     * Toggle confirm status of invoice
     */
    public function toggleConfirmStatus(Request $request)
    {
        try {
            $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
                'status' => 'required|integer|in:0,1'
            ]);

            $invoiceId = $request->invoice_id;
            $newStatus = $request->status;

            Log::info("Toggle invoice confirm status", [
                'invoice_id' => $invoiceId,
                'new_status' => $newStatus,
                'user_id' => Auth::id()
            ]);

            // Update invoice status
            $updated = DB::table('invoices')
                ->where('id', $invoiceId)
                ->update([
                    'invoice_status' => $newStatus,
                    'updated_at' => now()
                ]);

            if ($updated) {
                $statusText = $newStatus ? 'تم تأكيد الفاتورة بنجاح' : 'تم إلغاء تأكيد الفاتورة بنجاح';
                
                return response()->json([
                    'success' => true,
                    'message' => $statusText,
                    'data' => [
                        'invoice_id' => $invoiceId,
                        'invoice_status' => $newStatus
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في تحديث حالة الفاتورة'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Toggle confirm status error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث حالة الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete invoice
     */
    public function deleteInvoice(Request $request, $id)
    {
        try {
            // Try to find invoice by numeric ID first, then by es_id
            $invoice = null;
            
            if (is_numeric($id)) {
                $invoice = DB::table('invoices')->where('id', $id)->first();
            }
            
            // If not found by numeric ID or if ID is not numeric, try es_id
            if (!$invoice) {
                $invoice = DB::table('invoices')->where('es_id', $id)->first();
            }
            
            if (!$invoice) {
                Log::warning("Invoice not found", [
                    'search_id' => $id,
                    'is_numeric' => is_numeric($id)
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'الفاتورة غير موجودة'
                ], 404);
            }
            

            // P1.2: refuse to delete an invoice that has already received payment --
            // deleting it would silently orphan the paid amount in Storage/Bank with
            // no invoice, ledger row, or trace of where the money came from.
            if ((float) ($invoice->invoice_money_pay ?? 0) > 0) {
                Log::warning("Blocked delete of paid invoice", [
                    'invoice_id' => $invoice->id,
                    'es_id' => $invoice->es_id ?? 'N/A',
                    'invoice_money_pay' => $invoice->invoice_money_pay,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف فاتورة تم سداد مبلغ عليها. برجاء عمل مرتجع / استرجاع أولاً.'
                ], 422);
            }
            // Use the actual numeric ID for deletion
            $actualId = $invoice->id;

            Log::info("Delete invoice request", [
                'search_id' => $id,
                'actual_invoice_id' => $actualId,
                'es_id' => $invoice->es_id ?? 'N/A',
                'user_id' => Auth::id(),
                'ip' => $request->ip()
            ]);

            // Start a database transaction for data integrity
            DB::beginTransaction();

            try {
                // Get ticket system ID for related data cleanup
                $ticketSystemId = $invoice->ticket_system_id;
                $invoiceEsId = $invoice->es_id;

                // Delete related records first to maintain referential integrity
                if ($ticketSystemId) {
                    DB::table('ticket_users')->where('ticket_system_id', $ticketSystemId)->delete();
                    DB::table('ticket_vendors')->where('ticket_system_id', $ticketSystemId)->delete();
                }
                
                // Delete related account statements
                // Delete related account statements - they are linked by es_id field
                if ($invoiceEsId) {
                    // Delete account statements by es_id (this is how they are linked)
                    $deletedAccountStatements = DB::table('account_statements')
                        ->where('es_id', $invoiceEsId)
                        ->delete();
                    
                    Log::info("Deleted account statements", [
                        'invoice_es_id' => $invoiceEsId,
                        'deleted_count' => $deletedAccountStatements
                    ]);
                }
                
                
                // Delete the main invoice record
                $deleted = DB::table('invoices')->where('id', $actualId)->delete();

                if ($deleted) {
                    DB::commit();
                    
                    Log::info("Invoice deleted successfully", [
                        'search_id' => $id,
                        'actual_invoice_id' => $actualId,
                        'es_id' => $invoice->es_id ?? 'N/A',
                        'user_id' => Auth::id()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'تم حذف الفاتورة بنجاح',
                        'data' => [
                            'invoice_id' => $actualId,
                            'es_id' => $invoice->es_id ?? 'N/A'
                        ]
                    ]);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'فشل في حذف الفاتورة'
                    ], 500);
                }

            } catch (Exception $transactionError) {
                DB::rollBack();
                throw $transactionError;
            }

        } catch (Exception $e) {
            Log::error('Delete invoice error: ' . $e->getMessage(), [
                'search_id' => $id ?? 'unknown',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حذف الفاتورة: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods with improved error handling
    private function getPassengerNames($ticketSystemId)
    {
        if (!$ticketSystemId) return 'غير محدد';
        
        try {
            $names = DB::table('ticket_users')
                ->where('ticket_system_id', $ticketSystemId)
                ->whereNotNull('client_name')
                ->where('client_name', '!=', '')
                ->pluck('client_name')
                ->unique()
                ->implode(' / ');
            
            return $names ?: 'غير محدد';
        } catch (Exception $e) {
            Log::error('Get passenger names error: ' . $e->getMessage());
            return 'غير محدد';
        }
    }

    private function getBookingIds($ticketSystemId)
    {
        if (!$ticketSystemId) return 'غير محدد';
        
        try {
            $ids = DB::table('ticket_users')
                ->where('ticket_system_id', $ticketSystemId)
                ->whereNotNull('client_booking_id')
                ->where('client_booking_id', '!=', '')
                ->pluck('client_booking_id')
                ->unique()
                ->implode(' / ');
            
            return $ids ?: 'غير محدد';
        } catch (Exception $e) {
            Log::error('Get booking IDs error: ' . $e->getMessage());
            return 'غير محدد';
        }
    }

    private function getTicketIds($ticketSystemId)
    {
        if (!$ticketSystemId) return 'غير محدد';
        
        try {
            $ids = DB::table('ticket_users')
                ->where('ticket_system_id', $ticketSystemId)
                ->whereNotNull('client_ticket_id')
                ->where('client_ticket_id', '!=', '')
                ->pluck('client_ticket_id')
                ->unique()
                ->implode(' / ');
            
            return $ids ?: 'غير محدد';
        } catch (Exception $e) {
            Log::error('Get ticket IDs error: ' . $e->getMessage());
            return 'غير محدد';
        }
    }

    private function getVendorNames($ticketSystemId)
    {
        if (!$ticketSystemId) return 'غير محدد';
        
        try {
            $names = DB::table('ticket_vendors')
                ->join('suppliers', 'ticket_vendors.vendor_id', '=', 'suppliers.id')
                ->where('ticket_vendors.ticket_system_id', $ticketSystemId)
                ->whereNotNull('suppliers.name')
                ->where('suppliers.name', '!=', '')
                ->pluck('suppliers.name')
                ->unique()
                ->implode(' / ');
            
            return $names ?: 'غير محدد';
        } catch (Exception $e) {
            Log::error('Get vendor names error: ' . $e->getMessage());
            return 'غير محدد';
        }
    }

    private function getBeneficiaryName($beneficiaryId)
    {
        if (!$beneficiaryId) return 'غير محدد';
        
        try {
            $name = DB::table('suppliers')
                ->where('id', $beneficiaryId)
                ->value('name');
            
            return $name ?: 'غير محدد';
        } catch (Exception $e) {
            Log::error('Get beneficiary name error: ' . $e->getMessage());
            return 'غير محدد';
        }
    }

    private function getEmployeeName($employeeId)
    {
        if (!$employeeId) return 'غير محدد';
        
        try {
            $name = DB::table('users')
                ->where('id', $employeeId)
                ->value('name');
            
            return $name ?: 'غير محدد';
        } catch (Exception $e) {
            Log::error('Get employee name error: ' . $e->getMessage());
            return 'غير محدد';
        }
    }

    private function getTotalNetPrice($ticketSystemId)
    {
        if (!$ticketSystemId) return 0;
        
        try {
            // Try client_net_pice first, then client_net_price
            $total = DB::table('ticket_users')
                ->where('ticket_system_id', $ticketSystemId)
                ->sum('client_net_pice');
                
            if ($total == 0) {
                $total = DB::table('ticket_users')
                    ->where('ticket_system_id', $ticketSystemId)
                    ->sum('client_net_price');
            }
            
            return floatval($total);
        } catch (Exception $e) {
            Log::error('Get total net price error: ' . $e->getMessage());
            return 0;
        }
    }

    private function getTotalBoughtPrice($ticketSystemId)
    {
        if (!$ticketSystemId) return 0;
        
        try {
            $total = DB::table('ticket_users')
                ->where('ticket_system_id', $ticketSystemId)
                ->sum('client_bought_price');
            
            return floatval($total);
        } catch (Exception $e) {
            Log::error('Get total bought price error: ' . $e->getMessage());
            return 0;
        }
    }

    private function applyStatusFilter($query, $statusFilter)
    {
        try {
            switch ($statusFilter) {
                case '0': // Unpaid
                    $query->where(function($q) {
                        $q->where('i.invoice_money_pay', 0)
                          ->orWhereNull('i.invoice_money_pay');
                    });
                    break;
                case '1': // Partially paid
                    $query->where('i.invoice_money_pay', '>', 0);
                    break;
                case '2': // Fully paid
                    $query->where('i.invoice_money_pay', '>', 0);
                    break;
            }
        } catch (Exception $e) {
            Log::error('Apply status filter error: ' . $e->getMessage());
        }
    }

    private function getPaymentStatus($paidAmount, $totalAmount)
    {
        if ($paidAmount == 0) return 0; // Unpaid
        if ($paidAmount >= $totalAmount) return 2; // Fully paid
        return 1; // Partially paid
    }

    private function applyPeriodFilter($query, $period)
    {
        try {
            $now = now();
            
            switch ($period) {
                case '15days':
                    $query->where('i.created_at', '>=', $now->copy()->subDays(15));
                    break;
                case '1month':
                    $query->where('i.created_at', '>=', $now->copy()->subMonth());
                    break;
                case '3months':
                    $query->where('i.created_at', '>=', $now->copy()->subMonths(3));
                    break;
                case '6months':
                    $query->where('i.created_at', '>=', $now->copy()->subMonths(6));
                    break;
                case '1year':
                    $query->where('i.created_at', '>=', $now->copy()->subYear());
                    break;
                case 'all':
                    // No date filter
                    break;
            }
        } catch (Exception $e) {
            Log::error('Apply period filter error: ' . $e->getMessage());
        }
    }

    private function formatDate($date)
    {
        if (!$date) return 'غير محدد';
        
        try {
            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        } catch (Exception $e) {
            return 'غير محدد';
        }
    }

    private function getInvoiceType($sectionId)
    {
        $types = [
            1 => "فواتير الطيران",
            2 => "فواتير تأشيرات",
            3 => "فواتير سياحه داخليه",
            4 => "فواتير سياحه خارجيه",
            5 => "فواتير سياحه دينيه",
            6 => "فواتير تأمينات السفر",
            7 => "فواتير تحاليل السفر",
            8 => "فواتير نقل سياحى",
        ];

        return $types[$sectionId] ?? "فواتير الطيران";
    }
}