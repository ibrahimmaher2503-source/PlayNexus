<?php

namespace App\Support;

final class PhoneNormalizer
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtr(trim($value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $value = preg_replace('/[\s().-]+/', '', $value) ?? '';

        if (preg_match('/^01[0125]\d{8}$/', $value) === 1) {
            return '+20'.substr($value, 1);
        }

        if (preg_match('/^\+20(1[0125]\d{8})$/', $value, $matches) === 1) {
            return '+20'.$matches[1];
        }

        if (preg_match('/^0020(1[0125]\d{8})$/', $value, $matches) === 1) {
            return '+20'.$matches[1];
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
