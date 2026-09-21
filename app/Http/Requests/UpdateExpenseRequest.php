<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only validates the fields the edit form actually submits (name +
 * balances). status/type/phone_1 are hardcoded server-side in
 * ExpensesController::update() regardless of input -- unchanged,
 * pre-existing behavior, not touched here.
 */
class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:255'],
            'debit_opening_balance' => ['required', 'numeric'],
            'opening_credit_balance' => ['nullable', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم المصروف',
            'debit_opening_balance' => 'الرصيد الافتتاحي المدين',
            'opening_credit_balance' => 'رصيد افتتاحى الدائن',
        ];
    }
}
