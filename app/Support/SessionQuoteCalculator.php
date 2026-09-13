<?php

namespace App\Support;

use DateTimeInterface;
use InvalidArgumentException;

final class SessionQuoteCalculator
{
    // ponytail: one-year source snapshot ceiling; raise only with matching pricing validation.
    private const MAX_SECONDS = 31_536_000;

    // ponytail: preserves the existing nine-major-digit money ceiling; raise with source validation.
    private const MAX_MINOR = 99_999_999_900;

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{elapsed_seconds:int, included_seconds:int, overtime_seconds:int, overtime_units:int, base_price_minor:int, overtime_price_minor:int, subtotal_minor:int, net_minor:int, tax_minor:int, total_minor:int, tax_rate_bps:int, tax_mode:string, currency:string}
     */
    public static function calculate(array $snapshot, DateTimeInterface $startedAt, DateTimeInterface $asOf): array
    {
        $baseDuration = self::integer($snapshot, 'base_duration_seconds', 1, self::MAX_SECONDS);
        $grace = self::integer($snapshot, 'grace_period_seconds', 0, self::MAX_SECONDS);
        $overtimeUnit = self::integer($snapshot, 'overtime_unit_seconds', 1, self::MAX_SECONDS);
        $basePrice = self::integer($snapshot, 'price_minor', 0, self::MAX_MINOR);
        $overtimePrice = self::integer($snapshot, 'overtime_price_minor', 0, self::MAX_MINOR);
        $taxRate = self::integer($snapshot, 'tax_rate_bps', 0, 10_000);

        $taxMode = $snapshot['tax_mode'] ?? null;
        $currency = $snapshot['currency'] ?? null;
        if (! is_string($taxMode) || ! in_array($taxMode, ['exclusive', 'inclusive'], true)) {
            throw new InvalidArgumentException('Malformed tax mode.');
        }
        if (! is_string($currency) || preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new InvalidArgumentException('Malformed currency.');
        }

        $startedTimestamp = $startedAt->getTimestamp();
        $asOfTimestamp = $asOf->getTimestamp();
        if ($asOfTimestamp <= $startedTimestamp) {
            $elapsed = 0;
        } else {
            $elapsed = $asOfTimestamp - $startedTimestamp;
            if (! is_int($elapsed)) {
                throw new InvalidArgumentException('Elapsed time is out of bounds.');
            }
        }

        $included = $baseDuration + $grace;
        $overtimeSeconds = $elapsed > $included ? $elapsed - $included : 0;
        $overtimeUnits = intdiv($overtimeSeconds, $overtimeUnit)
            + ($overtimeSeconds % $overtimeUnit === 0 ? 0 : 1);
        $overtimeTotal = self::multiply($overtimeUnits, $overtimePrice);
        $subtotal = self::add($basePrice, $overtimeTotal);

        if ($taxMode === 'exclusive') {
            $tax = self::roundHalfUp(self::multiply($subtotal, $taxRate), 10_000);
            $net = $subtotal;
            $total = self::add($subtotal, $tax);
        } else {
            $total = $subtotal;
            $tax = self::roundHalfUp(self::multiply($total, $taxRate), 10_000 + $taxRate);
            $net = $total - $tax;
        }

        return [
            'elapsed_seconds' => $elapsed,
            'included_seconds' => $included,
            'overtime_seconds' => $overtimeSeconds,
            'overtime_units' => $overtimeUnits,
            'base_price_minor' => $basePrice,
            'overtime_price_minor' => $overtimePrice,
            'subtotal_minor' => $subtotal,
            'net_minor' => $net,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'tax_rate_bps' => $taxRate,
            'tax_mode' => $taxMode,
            'currency' => $currency,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private static function integer(array $snapshot, string $key, int $minimum, int $maximum): int
    {
        $value = $snapshot[$key] ?? null;
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('Malformed pricing snapshot.');
        }

        return $value;
    }

    private static function multiply(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw new InvalidArgumentException('Pricing amount is out of bounds.');
        }

        return $left * $right;
    }

    private static function add(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new InvalidArgumentException('Pricing amount is out of bounds.');
        }

        return $left + $right;
    }

    private static function roundHalfUp(int $numerator, int $denominator): int
    {
        $whole = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        return $whole + ($remainder * 2 >= $denominator ? 1 : 0);
    }
}
