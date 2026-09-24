<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Structural validation only -- mirrors StoreBondRequest, plus bond_id.
 * Does not change any of the money-movement/ledger logic in
 * BondsController::save_update().
 */
class UpdateBondRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->input('type_slctd') == 1) {
            return [
                'bond_id' => ['required', 'integer', 'exists:bonds,id'],
                'type_slctd' => ['required'],
                'storage_id' => ['required', 'integer', 'exists:storages,id'],
                'supp_id' => ['required', 'integer', 'exists:suppliers,id'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'money_way' => ['required'],
                'bank_id' => ['required_if:money_way,2', 'nullable', 'integer', 'exists:banks,id'],
                'crt_date' => ['required', 'date'],
            ];
        }

        return [
            'bond_id' => ['required', 'integer', 'exists:bonds,id'],
            'type_slctd' => ['required'],
            'storage_id2' => ['required', 'integer', 'exists:storages,id'],
            'supp_id2' => ['required', 'integer', 'exists:suppliers,id'],
            'amount2' => ['required', 'numeric', 'gt:0'],
            'money_way2' => ['required'],
            'bank_id2' => ['required_if:money_way2,2', 'nullable', 'integer', 'exists:banks,id'],
            'crt_date2' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        // amounts must be positive (a zero / negative voucher would move money the wrong way)
        return [
            'amount.gt' => 'برجاء إدخال مبلغ صحيح أكبر من صفر',
            'amount2.gt' => 'برجاء إدخال مبلغ صحيح أكبر من صفر',
        ];
    }

    public function attributes(): array
    {
        return [
            'storage_id' => 'الخزينة',
            'supp_id' => 'المورد',
            'amount' => 'المبلغ',
            'money_way' => 'طريقة الدفع',
            'bank_id' => 'البنك',
            'crt_date' => 'التاريخ',
            'storage_id2' => 'الخزينة',
            'supp_id2' => 'المورد',
            'amount2' => 'المبلغ',
            'money_way2' => 'طريقة الدفع',
            'bank_id2' => 'البنك',
            'crt_date2' => 'التاريخ',
        ];
    }
}
