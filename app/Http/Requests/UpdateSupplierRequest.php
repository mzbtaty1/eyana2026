<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
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
            'limit_balance' => ['nullable', 'numeric'],
            'debit_opening_balance' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'opening_credit_balance' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'status' => ['required', 'in:0,1'],
            'type' => ['required', 'in:1,2'],
            'acc_type' => ['required', 'in:1,2'],
            'in_index' => ['required', 'in:0,1'],
            'in_stat' => ['required', 'in:0,1'],
        ];
    }

    /**
     * Opening balances: up to 2 decimal places, never negative -- a credit
     * balance goes in the credit field, not as a negative debit (and vice versa).
     */
    public function messages(): array
    {
        return [
            'debit_opening_balance.numeric' => 'الرصيد الافتتاحي المدين يجب أن يكون رقماً (مثال: 1500 أو 1500.50)',
            'debit_opening_balance.decimal' => 'الرصيد الافتتاحي المدين: رقمان عشريان كحد أقصى (مثال: 1500.50)',
            'debit_opening_balance.min' => 'الرصيد الافتتاحي المدين لا يمكن أن يكون سالباً - إذا كان الرصيد دائناً اكتبه في خانة الرصيد الافتتاحي الدائن',
            'opening_credit_balance.numeric' => 'الرصيد الافتتاحي الدائن يجب أن يكون رقماً (مثال: 1500 أو 1500.50)',
            'opening_credit_balance.decimal' => 'الرصيد الافتتاحي الدائن: رقمان عشريان كحد أقصى (مثال: 1500.50)',
            'opening_credit_balance.min' => 'الرصيد الافتتاحي الدائن لا يمكن أن يكون سالباً - إذا كان الرصيد مديناً اكتبه في خانة الرصيد الافتتاحي المدين',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم المورد',
            'email' => 'الايميل',
            'address' => 'العنوان',
            'phone_1' => 'رقم الهاتف 1',
            'phone_2' => 'رقم الهاتف 2',
            'limit_balance' => 'الحد الائتماني المسموح',
            'debit_opening_balance' => 'الرصيد الافتتاحي المدين',
            'opening_credit_balance' => 'رصيد افتتاحى الدائن',
            'status' => 'الحالة',
            'type' => 'النوع',
            'acc_type' => 'نوع الحساب',
            'in_index' => 'اظهار كملخص في الصفحة الرئيسية',
            'in_stat' => 'عرض في كشف الحساب الشركات المخصص',
        ];
    }
}
