<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visa_name' => ['required', 'string', 'max:255'],
            'visa_price' => ['required', 'numeric'],
            'visa_ext_price' => ['required', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'visa_name' => 'اسم التأشيرة',
            'visa_price' => 'سعر التأشيرة',
            'visa_ext_price' => 'سعر التنفيذ',
        ];
    }
}
