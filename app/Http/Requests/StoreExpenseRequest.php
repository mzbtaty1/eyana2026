<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'debit_opening_balance' => ['required', 'numeric'],
            'opening_credit_balance' => ['nullable', 'numeric'],
            'status' => ['required', 'in:0,1'],
            'type' => ['required', 'in:1,2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم المصروف',
            'debit_opening_balance' => 'الرصيد الافتتاحي المدين',
            'opening_credit_balance' => 'رصيد افتتاحى الدائن',
            'status' => 'الحالة',
            'type' => 'النوع',
        ];
    }
}
