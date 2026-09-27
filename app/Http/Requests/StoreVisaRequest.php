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
            'status' => ['required', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'visa_name' => 'اسم التأشيرة',
            'status' => 'الحالة',
        ];
    }
}
