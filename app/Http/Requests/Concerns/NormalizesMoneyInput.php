<?php

namespace App\Http\Requests\Concerns;

/**
 * Money fields typed on an Arabic keyboard: Arabic-Indic (٠-٩) and Persian (۰-۹)
 * digits become 0-9 and the Arabic decimal separator (٫) becomes '.', so «١٢٫٥» is
 * 12.5. Nothing is ever dropped: commas (1,500 vs 12,5 is ambiguous), spaces and
 * any other character stay as typed and fail validation with a message, instead of
 * being stripped into a different number (the old «12,5» -> 125).
 *
 * The using FormRequest lists its fields in $moneyFields.
 */
trait NormalizesMoneyInput
{
    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach ($this->moneyFields as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = self::normalizeMoney($this->input($field));
            }
        }
        $this->merge($normalized);
    }

    public static function normalizeMoney(string $value): string
    {
        return trim(strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.',
        ]));
    }
}
