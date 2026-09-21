<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
