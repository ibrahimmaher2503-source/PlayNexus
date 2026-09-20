<?php

namespace App\Support;

use DateTimeInterface;
use InvalidArgumentException;

final class SessionQuoteCalculator
{
    private const EXTENSION_UNIT_SECONDS = 1800;

    // ponytail: one-year source snapshot ceiling; raise only with matching pricing validation.
    private const MAX_SECONDS = 31_536_000;

    // ponytail: preserves the existing nine-major-digit money ceiling; raise with source validation.
    private const MAX_MINOR = 99_999_999_900;

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array<string, mixed>>  $adjustments
     * @return array<string, int|string>
     */
    public static function calculate(array $snapshot, DateTimeInterface $startedAt, DateTimeInterface $asOf, array $adjustments = []): array
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
        [$extensionUnits, $adjustmentMinor] = self::adjustments($adjustments);
        $extensionSeconds = self::multiply($extensionUnits, self::EXTENSION_UNIT_SECONDS);
        $overtimeBoundary = self::add($included, $extensionSeconds);
        $overtimeSeconds = $elapsed > $overtimeBoundary ? $elapsed - $overtimeBoundary : 0;
        $overtimeUnits = intdiv($overtimeSeconds, $overtimeUnit)
            + ($overtimeSeconds % $overtimeUnit === 0 ? 0 : 1);
        $overtimeTotal = self::multiply($overtimeUnits, $overtimePrice);
        $extensionPrice = self::multiply($extensionUnits, $overtimePrice);
        $subtotal = self::add($basePrice, $overtimeTotal);
        $subtotal = self::add($subtotal, $extensionPrice);
        $subtotal = self::addSigned($subtotal, $adjustmentMinor);
        if ($subtotal < 0) {
            throw new InvalidArgumentException('Adjusted amount is out of bounds.');
        }

        if ($taxMode === 'exclusive') {
            $tax = self::roundHalfUp(self::multiply($subtotal, $taxRate), 10_000);
            $net = $subtotal;
            $total = self::add($subtotal, $tax);
        } else {
            $total = $subtotal;
            $tax = self::roundHalfUp(self::multiply($total, $taxRate), 10_000 + $taxRate);
            $net = $total - $tax;
        }

        $quote = [
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

        if ($adjustments !== []) {
            $quote['extension_units'] = $extensionUnits;
            $quote['extension_seconds'] = $extensionSeconds;
            $quote['extension_price_minor'] = $extensionPrice;
            $quote['adjustment_minor'] = $adjustmentMinor;
        }

        return $quote;
    }

    /** @param array<int, array<string, mixed>> $adjustments */
    private static function adjustments(array $adjustments): array
    {
        $extensionUnits = 0;
        $adjustmentMinor = 0;
        foreach ($adjustments as $adjustment) {
            $extension = $adjustment['extension_units'] ?? null;
            $charge = $adjustment['adjustment_minor'] ?? null;
            if (! is_int($extension) || $extension < 0 || $extension > 48 || ! is_int($charge) || $charge < -99_999_999_900 || $charge > 99_999_999_900) {
                throw new InvalidArgumentException('Malformed session adjustment.');
            }
            $extensionUnits = self::add($extensionUnits, $extension);
            $adjustmentMinor = self::addSigned($adjustmentMinor, $charge);
        }

        return [$extensionUnits, $adjustmentMinor];
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

    private static function addSigned(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new InvalidArgumentException('Pricing amount is out of bounds.');
        }
        if ($right < 0 && $left < PHP_INT_MIN - $right) {
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
