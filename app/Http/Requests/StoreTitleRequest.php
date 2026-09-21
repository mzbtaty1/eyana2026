<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Matches the extension allowlist MarketingController::title_store()
            // itself checks (jpg/png/jpeg/webp/pdf) -- kept in sync so validation
            // doesn't reject anything the controller would otherwise accept.
            'myPoster' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf'],
            'bk_color' => ['required', 'string', 'max:20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'البيان',
            'myPoster' => 'اللوجو',
            'bk_color' => 'لون خلفية العرض',
        ];
    }
}
