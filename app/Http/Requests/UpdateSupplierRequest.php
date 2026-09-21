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
            'debit_opening_balance' => ['required', 'numeric'],
            'opening_credit_balance' => ['required', 'numeric'],
            'status' => ['required', 'in:0,1'],
            'type' => ['required', 'in:1,2'],
            'acc_type' => ['required', 'in:1,2'],
            'in_index' => ['required', 'in:0,1'],
            'in_stat' => ['required', 'in:0,1'],
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
