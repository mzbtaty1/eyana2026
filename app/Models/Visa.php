<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Visa type («التأشيرات»), separate from airlines. A visa invoice is an ordinary invoice
 * with invoice_section 2 whose invoice_airline holds the visa type's name (the name it
 * had when the invoice was saved -- the same way airlines are stored on invoices).
 * status: 1 = active (offered on new visa invoices), 0 = disabled (kept for history).
 */
class Visa extends Model
{
    use HasFactory;

    /** invoices.invoice_section of visa invoices («فواتير تأشيرات»). */
    const SECTION = 2;

    protected $fillable = [
"visa_name",
"visa_price",
"visa_ext_price",
"status",
    ];

    /** Visa invoices saved with this visa type's current name. */
    public function invoicesCount(): int
    {
        return DB::table('invoices')->where('invoice_section', self::SECTION)->where('invoice_airline', $this->visa_name)->count();
    }

    /** Number of visa invoices per visa type name, for many types in one query. */
    public static function invoiceCounts(): array
    {
        return DB::table('invoices')->where('invoice_section', self::SECTION)
            ->groupBy('invoice_airline')->selectRaw('invoice_airline AS name, COUNT(*) AS n')
            ->pluck('n', 'name')->all();
    }
}
