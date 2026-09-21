<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubStorageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:sub_storages,id'],
            'main_storage' => ['required', 'integer', 'exists:storages,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:1,2'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'bank_number' => ['nullable', 'string', 'max:100'],
            'balance' => ['required', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'main_storage' => 'الخزينة الرئيسية',
            'name' => 'اسم الخزنة',
            'type' => 'النوع',
            'bank_id' => 'الحساب البنكي',
            'bank_number' => 'رقم الحساب',
            'balance' => 'الرصيد',
        ];
    }
}
