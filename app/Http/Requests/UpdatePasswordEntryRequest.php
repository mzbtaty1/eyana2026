<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Named "...EntryRequest" (not UpdatePasswordRequest) to avoid confusion
 * with the app's own account-password change flow -- this validates edits
 * to a stored third-party credential (url/user/pass), not Auth::user()'s
 * own login password.
 */
class UpdatePasswordEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:passwords,id'],
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
