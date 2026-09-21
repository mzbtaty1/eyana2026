<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'integer', 'exists:titles,id'],
            'travel_date' => ['required', 'date'],
            'time_departure' => ['required', 'string', 'max:50'],
            'total_price' => ['required', 'numeric'],
            'cost_price' => ['required', 'numeric'],
            'booking_id' => ['required', 'string', 'max:255'],
            'screen_id' => ['required', 'string', 'max:255'],
            'hold_finish_time' => ['required', 'date'],
            'hold_finish_time2' => ['required'],
            'places_available' => ['required', 'numeric'],
            'added_by' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'البيان',
            'travel_date' => 'تاريخ السفر',
            'time_departure' => 'موعد الاقلاع',
            'total_price' => 'السعر',
            'cost_price' => 'التكلفة',
            'booking_id' => 'رقم الحجز',
            'screen_id' => 'رقم الشاشة',
            'hold_finish_time' => 'وقت انتهاء الهولد',
            'places_available' => 'عدد الامكان',
            'added_by' => 'اسم الموظف',
        ];
    }
}
