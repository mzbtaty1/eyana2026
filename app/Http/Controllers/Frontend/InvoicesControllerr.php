<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Redirect;
use Illuminate\Support\Carbon;
use App\Models\{Supplier, Invoice, TicketUser, TicketVendor, Airline, AccountStatement , Log , TransactionBalance, Storage, Bond , User};
use Yajra\DataTables\Facades\DataTables;
use DB;

class InvoicesController extends Controller
{
    public function getInvoices(Request $request)
    {
        if(Auth::user()->account_type == 2){
            $query = Invoice::with([
                'ticketVendors.supplier',
                'beneficiaries',
                'users',
                'creator',
                'accountStatements'
            ]);
        } else {
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
    
    public function ajax()
    {
        return view('invoices.ajax');
    }
    
    /**
     * Display the new improved invoices page
     */
    public function indexNew()
    {
        return view('invoices.ajax-new');
    }
    
    public function lite()
    {
        // يجب إكمال هذه الدالة حسب المتطلبات
        return view('invoices.lite');
    }
    
    public function getInvoices3Months(Request $request)
    {
        if(Auth::user()->account_type == 2){
            $query = Invoice::with([
                'ticketVendors.supplier',
                'beneficiaries',
                'users',
                'creator',
                'accountStatements'
            ]);
        } else {
            $query = Invoice::with([
                'ticketVendors.supplier',
                'beneficiaries',
                'users',
                'creator',
                'accountStatements'
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

        $total = $query->count();
        $length = $request->input('length') > 0 ? $request->input('length') : 10000;
        $page = floor($request->input('start') / $length) + 1;

        // الترتيب النهائي حسب التاريخ
        $invoices = $query
            ->orderBy('updated_at', 'desc')
            ->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $invoices->items(),
        ]);
    }
    
    // باقي الدوال يجب تصحيحها بنفس النمط...
    
    // دالة مساعدة لتحديد نوع الفاتورة
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
    
    // دالة مساعدة لتحديد حالة الفاتورة
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
}