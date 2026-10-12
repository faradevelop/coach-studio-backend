<?php

namespace App\Support;

/**
 * Normalizes Persian/Arabic digits and Arabic letter variants so search
 * and stored data match. (The backend had no existing normalization.)
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

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $converted = strtr($value, self::MAP);

        return trim(preg_replace('/\s+/u', ' ', $converted) ?? $converted);
    }
}
