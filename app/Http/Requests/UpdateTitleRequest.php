<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:titles,id'],
            'title' => ['required', 'string', 'max:255'],
            // Optional on update -- MarketingController::save_update() keeps
            // the existing png_icon when no new file is uploaded.
            'myPoster' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf'],
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
