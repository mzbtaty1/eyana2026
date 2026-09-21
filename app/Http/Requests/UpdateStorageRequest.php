<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "name" is intentionally not validated here: the edit form's name input
 * is `disabled`, so browsers never submit it, and
 * StoragesController::update() correspondingly never writes it (its
 * "name" line is commented out). Unchanged, pre-existing behavior.
 */
class UpdateStorageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:storages,id'],
            'type' => ['required', 'in:1,2'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'bank_number' => ['nullable', 'string', 'max:100'],
            'balance' => ['required', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'النوع',
            'bank_id' => 'الحساب البنكي',
            'bank_number' => 'رقم الحساب',
            'balance' => 'الرصيد',
        ];
    }
}
