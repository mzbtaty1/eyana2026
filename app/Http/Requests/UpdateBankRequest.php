<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:banks,id'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_balance' => ['required', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bank_name' => 'اسم البنك',
            'bank_balance' => 'رصيد البنك',
        ];
    }
}
