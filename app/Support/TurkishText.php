<?php

namespace App\Support;

final class TurkishText
{
    private const TO_LOWER = [
        'I' => 'ı',
        'İ' => 'i',
        'Ş' => 'ş',
        'Ğ' => 'ğ',
        'Ü' => 'ü',
        'Ö' => 'ö',
        'Ç' => 'ç',
    ];

    private const TO_UPPER = [
        'i' => 'İ',
        'ı' => 'I',
        'ş' => 'Ş',
        'ğ' => 'Ğ',
        'ü' => 'Ü',
        'ö' => 'Ö',
        'ç' => 'Ç',
    ];

    public static function lower(string $value): string
    {
        return mb_strtolower(strtr($value, self::TO_LOWER), 'UTF-8');
    }

    public static function upper(string $value): string
    {
        return mb_strtoupper(strtr($value, self::TO_UPPER), 'UTF-8');
    }

    /**
     * Her kelimenin ilk harfini büyük yapar: "araç galerisi" → "Araç Galerisi".
     */
    public static function titleCase(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return '';
        }

        $words = explode(' ', self::lower($value));

        return implode(' ', array_map(function (string $word): string {
            if ($word === '') {
                return '';
            }

            $first = mb_substr($word, 0, 1, 'UTF-8');
            $rest = mb_substr($word, 1, null, 'UTF-8');

            return self::upper($first).$rest;
        }, $words));
    }
}
