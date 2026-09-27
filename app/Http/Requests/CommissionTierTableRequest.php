<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A commission tier table with its tiers (create / update). Besides each field, the tiers
 * must form ONE continuous ordered range:
 *   - amounts >= 0 with at most 2 decimals, rate 0..100 %
 *   - a tier's upper limit ("to") is not below its lower limit ("from")
 *   - at most one tier without an upper limit (open-ended), and it is the last one
 *   - sorted by "from", each tier starts right after the previous one ends: after an upper
 *     limit U the next lower limit L must satisfy U < L <= U + 1 (so 1-10,000 then
 *     10,001-50,000 -- or 10,000.01 -- is continuous; L <= U is an overlap / duplicate,
 *     L > U + 1 a gap).
 * The future commission engine reads a tier as: previous upper limit < profit <= upper
 * limit (the first tier from its own lower limit), so decimals between 10,000 and 10,001
 * belong to the second tier.
 */
class CommissionTierTableRequest extends FormRequest
{
    const MAX_AMOUNT = '999999999999.99';   // decimal(14,2)

    public function authorize(): bool
    {
        return $this->user()->can(Permissions::COMMISSION_SETTINGS);
    }

    public function rules(): array
    {
        $amount = ['numeric', 'min:0', 'max:' . self::MAX_AMOUNT, 'regex:/^\d+(\.\d{1,2})?$/'];

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('commission_tier_tables', 'name')->ignore($this->route('id'))],
            'status' => ['required', 'in:0,1'],
            'tiers' => ['required', 'array', 'min:1', 'max:50'],
            'tiers.*.from' => array_merge(['required'], $amount),
            'tiers.*.to' => array_merge(['nullable'], $amount),
            'tiers.*.rate' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الجدول',
            'status' => 'الحالة',
            'tiers' => 'الشرائح',
            'tiers.*.from' => 'بداية الشريحة (من)',
            'tiers.*.to' => 'نهاية الشريحة (إلى)',
            'tiers.*.rate' => 'نسبة العمولة %',
        ];
    }

    public function messages(): array
    {
        return [
            'tiers.required' => 'يجب إدخال شريحة واحدة على الأقل.',
            'tiers.*.from.regex' => 'بداية الشريحة يجب أن تكون رقماً موجباً بحد أقصى رقمين عشريين.',
            'tiers.*.to.regex' => 'نهاية الشريحة يجب أن تكون رقماً موجباً بحد أقصى رقمين عشريين.',
            'tiers.*.rate.regex' => 'نسبة العمولة يجب أن تكون رقماً موجباً بحد أقصى رقمين عشريين.',
        ];
    }

    /** The whole-table checks, once every field is valid. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            foreach (self::structureErrors($this->input('tiers', [])) as $message) {
                $v->errors()->add('tiers', $message);
            }
        });
    }

    /**
     * Arabic messages for a tier list that is not one continuous ordered range (empty = valid).
     * Rows are numbered as the admin entered them.
     */
    public static function structureErrors(array $tiers): array
    {
        $rows = [];
        foreach (array_values($tiers) as $i => $t) {
            $to = $t['to'] ?? null;
            $rows[] = [
                'n' => $i + 1,
                'from' => self::cents($t['from']),
                'to' => ($to === null || $to === '') ? null : self::cents($to),
            ];
        }

        $errors = [];
        foreach ($rows as $r) {
            if ($r['to'] !== null && $r['to'] < $r['from']) {
                $errors[] = "الشريحة {$r['n']}: نهاية الشريحة أقل من بدايتها.";
            }
        }
        $open = array_values(array_filter($rows, fn ($r) => $r['to'] === null));
        if (count($open) > 1) {
            $errors[] = 'لا يسمح إلا بشريحة واحدة مفتوحة (بدون حد أعلى)، وتكون الشريحة الأخيرة.';
        }
        if ($errors) {
            return $errors;
        }

        usort($rows, fn ($a, $b) => $a['from'] <=> $b['from'] ?: $a['n'] <=> $b['n']);
        if ($open && end($rows)['n'] !== $open[0]['n']) {
            $errors[] = "الشريحة {$open[0]['n']} بدون حد أعلى ويجب أن تكون الشريحة الأخيرة (الأعلى).";
        }
        for ($i = 1; $i < count($rows); $i++) {
            $prev = $rows[$i - 1];
            $cur = $rows[$i];
            if ($prev['to'] === null) {
                continue; // reported above: the open-ended tier is not the last
            }
            if ($cur['from'] <= $prev['to']) {
                $errors[] = "الشريحة {$cur['n']} تتداخل مع الشريحة {$prev['n']}: يجب أن تبدأ بعد " . self::fmt($prev['to']) . '.';
            } elseif ($cur['from'] > $prev['to'] + 100) {
                $errors[] = "يوجد فراغ بين الشريحة {$prev['n']} (حتى " . self::fmt($prev['to']) . ") والشريحة {$cur['n']} (من " . self::fmt($cur['from']) . '): يجب أن تبدأ من ' . self::fmt($prev['to'] + 100) . '.';
            }
        }
        return $errors;
    }

    /** The tiers sorted by their lower limit, ready to store (from / to / rate as strings, order 1..n). */
    public function sortedTiers(): array
    {
        $tiers = array_values($this->input('tiers', []));
        usort($tiers, fn ($a, $b) => self::cents($a['from']) <=> self::cents($b['from']));
        $out = [];
        foreach ($tiers as $i => $t) {
            $to = $t['to'] ?? null;
            $out[] = [
                'from_amount' => self::money($t['from']),
                'to_amount' => ($to === null || $to === '') ? null : self::money($to),
                'rate' => self::money($t['rate']),
                'sort_order' => $i + 1,
            ];
        }
        return $out;
    }

    /** An amount in whole cents (exact comparisons, no float rounding). */
    private static function cents($value): int
    {
        [$int, $dec] = array_pad(explode('.', (string) $value, 2), 2, '');
        return (int) $int * 100 + (int) str_pad(substr($dec, 0, 2), 2, '0');
    }

    private static function money($value): string
    {
        $c = self::cents($value);
        return intdiv($c, 100) . '.' . str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function fmt(int $cents): string
    {
        return rtrim(rtrim(number_format($cents / 100, 2, '.', ','), '0'), '.');
    }
}
