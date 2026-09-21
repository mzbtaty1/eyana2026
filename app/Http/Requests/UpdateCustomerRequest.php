<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone_1' => ['required', 'string', 'max:30'],
            'phone_2' => ['nullable', 'string', 'max:30'],
            'passport_id' => ['nullable', 'string', 'max:50'],
            'passport_expiration_date' => ['nullable', 'date'],
            'debit_opening_balance' => ['required', 'numeric'],
            'opening_credit_balance' => ['nullable', 'numeric'],
            'status' => ['required', 'in:0,1'],
            'type' => ['required', 'in:1,2'],
            'acc_type' => ['required', 'in:1,2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم العميل',
            'email' => 'الايميل',
            'address' => 'العنوان',
            'phone_1' => 'رقم الهاتف 1',
            'phone_2' => 'رقم الهاتف 2',
            'passport_id' => 'رقم الباسبور',
            'passport_expiration_date' => 'تاريخ انتهاء الباسبور',
            'debit_opening_balance' => 'الرصيد الافتتاحي المدين',
            'opening_credit_balance' => 'رصيد افتتاحى الدائن',
            'status' => 'الحالة',
            'type' => 'النوع',
            'acc_type' => 'نوع الحساب',
        ];
    }
}
