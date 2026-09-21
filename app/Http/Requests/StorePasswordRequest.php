<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:500'],
            'user' => ['required', 'string', 'max:255'],
            'pass' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'url' => 'رابط الموقع',
            'user' => 'البريد الالكتروني / اسم الدخول',
            'pass' => 'كلمة السر',
        ];
    }
}
