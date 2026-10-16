<?php

namespace OmniConva\Laravel\Data;

/** @internal Prices travel as decimal strings ("1500.00"). */
final class Money
{
    public static function format(int|float|string|null $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        if (is_string($amount)) {
            $amount = str_replace(',', '', trim($amount));
            if (! is_numeric($amount)) {
                return null;
            }
        }

        return number_format((float) $amount, 2, '.', '');
    }

    public static function toFloat(mixed $amount): float
    {
        return is_numeric($amount) ? (float) $amount : 0.0;
    }
}
