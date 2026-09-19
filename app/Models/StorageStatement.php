<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        "storage_id",
        "bond_id",
        "entry_type",
        "transaction_date",
        "description",
        "reference",
        "debit",
        "credit",
        "commission",
        "net_effect",
        "running_balance",
        "reversal_of_entry_id",
        "is_voided",
        "created_by",
    ];

    protected $casts = [
        "transaction_date" => "date",
        "debit" => "decimal:2",
        "credit" => "decimal:2",
        "commission" => "decimal:2",
        "net_effect" => "decimal:2",
        "running_balance" => "decimal:2",
        "is_voided" => "boolean",
    ];

    public function storage()
    {
        return $this->belongsTo(Storage::class);
    }

    public function bond()
    {
        return $this->belongsTo(Bond::class);
    }

    /**
     * Append a new immutable ledger row for a storage. net_effect and
     * running_balance are always derived here, never passed in, so every
     * row satisfies net_effect = credit - debit and
     * running_balance = previous running_balance + net_effect by
     * construction.
     *
     * IMPORTANT: the caller MUST already hold Storage::lockForUpdate() on
     * the same storage_id within the current DB transaction before calling
     * this. The lockForUpdate() below on the "latest entry" read is
     * defense in depth, not a substitute for that -- the authoritative
     * lock is the one already held on the Storage row.
     */
    public static function record(array $attributes): self
    {
        $previous = static::where('storage_id', $attributes['storage_id'])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $previousBalance = $previous ? (float) $previous->running_balance : 0.0;
        $debit  = round((float) ($attributes['debit'] ?? 0), 2);
        $credit = round((float) ($attributes['credit'] ?? 0), 2);
        $netEffect = round($credit - $debit, 2);

        return static::create(array_merge($attributes, [
            'debit' => $debit,
            'credit' => $credit,
            'net_effect' => $netEffect,
            'running_balance' => round($previousBalance + $netEffect, 2),
        ]));
    }

    /**
     * Void the current active ('bond', not yet voided) ledger entry for a
     * given bond and append an exact reversal of it (debit/credit
     * flipped). The original row is never deleted or amount-edited --
     * only its is_voided flag changes. Returns the reversal entry, or
     * null if this bond never had an active storage ledger entry to
     * reverse.
     */
    public static function reverseActiveEntryForBond(int $bondId, string $description, ?int $createdBy): ?self
    {
        $active = static::where('bond_id', $bondId)
            ->where('entry_type', 'bond')
            ->where('is_voided', false)
            ->lockForUpdate()
            ->first();

        if (!$active) {
            return null;
        }

        $active->update(['is_voided' => true]);

        return static::record([
            'storage_id' => $active->storage_id,
            'bond_id' => $bondId,
            'entry_type' => 'reversal',
            'transaction_date' => now()->toDateString(),
            'description' => $description,
            'reference' => $active->reference,
            'debit' => (float) $active->credit,
            'credit' => (float) $active->debit,
            'commission' => 0,
            'reversal_of_entry_id' => $active->id,
            'created_by' => $createdBy,
        ]);
    }
}
