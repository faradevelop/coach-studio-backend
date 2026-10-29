<?php

namespace App\Support;

/**
 * Normalizes Persian/Arabic digits and Arabic letter variants so search
 * and stored data match.
 */
final class TextNormalizer
{
    private const MAP = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک',
    ];

    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $converted = strtr($value, self::MAP);

        return trim(preg_replace('/\s+/u', ' ', $converted) ?? $converted);
    }

    /** For stored names: ONLY ي→ی and ك→ک. Digits are never touched. */
    public static function normalizeLetters(?string $value): ?string
    {
        return $value === null ? null : strtr($value, ['ي' => 'ی', 'ك' => 'ک']);
    }

    /**
     * LIKE patterns (escaped with '!') for one search word, covering Latin,
     * Persian and Arabic-Indic digits. Stored values are never modified.
     * The first pattern is always the Latin-digit form.
     *
     * @return array<int,string>
     */
    public static function likePatterns(string $token): array
    {
        $digits = array_map('strval', range(0, 9));
        $latin = strtr($token, array_combine(self::PERSIAN_DIGITS, $digits) + array_combine(self::ARABIC_DIGITS, $digits));

        $variants = [
            $latin,
            str_replace($digits, self::PERSIAN_DIGITS, $latin),
            str_replace($digits, self::ARABIC_DIGITS, $latin),
        ];

        return array_values(array_unique(array_map(
            fn (string $v) => '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $v) . '%',
            $variants
        )));
    }
}
