<?php

namespace Tests\Unit;

use App\Support\SessionQuoteCalculator;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SessionQuoteCalculatorTest extends TestCase
{
    public function test_elapsed_at_base_plus_grace_has_no_overtime(): void
    {
        $quote = $this->calculate(4200);

        $this->assertSame([
            'elapsed_seconds' => 4200,
            'included_seconds' => 4200,
            'overtime_seconds' => 0,
            'overtime_units' => 0,
            'base_price_minor' => 15000,
            'overtime_price_minor' => 7500,
            'subtotal_minor' => 15000,
            'net_minor' => 15000,
            'tax_minor' => 0,
            'total_minor' => 15000,
            'tax_rate_bps' => 0,
            'tax_mode' => 'exclusive',
            'currency' => 'EGP',
        ], $quote);
    }

    public function test_one_second_after_included_time_rounds_to_one_overtime_unit(): void
    {
        $quote = $this->calculate(4201);

        $this->assertSame(1, $quote['overtime_seconds']);
        $this->assertSame(1, $quote['overtime_units']);
        $this->assertSame(7500, $quote['overtime_price_minor']);
        $this->assertSame(22500, $quote['subtotal_minor']);
    }

    public function test_one_second_past_one_overtime_unit_rounds_to_two_units(): void
    {
        $quote = $this->calculate(6001);

        $this->assertSame(1801, $quote['overtime_seconds']);
        $this->assertSame(2, $quote['overtime_units']);
        $this->assertSame(7500, $quote['overtime_price_minor']);
        $this->assertSame(30000, $quote['subtotal_minor']);
    }

    public function test_exclusive_fourteen_percent_tax_is_rounded_once_from_subtotal(): void
    {
        $quote = $this->calculate(4201, ['tax_rate_bps' => 1400, 'tax_mode' => 'exclusive']);

        $this->assertSame(22500, $quote['subtotal_minor']);
        $this->assertSame(22500, $quote['net_minor']);
        $this->assertSame(3150, $quote['tax_minor']);
        $this->assertSame(25650, $quote['total_minor']);
        $this->assertSame(1400, $quote['tax_rate_bps']);
        $this->assertSame('exclusive', $quote['tax_mode']);
    }

    public function test_inclusive_fourteen_percent_tax_extracts_half_up_without_changing_total(): void
    {
        $quote = $this->calculate(4201, ['tax_rate_bps' => 1400, 'tax_mode' => 'inclusive']);

        $this->assertSame(22500, $quote['subtotal_minor']);
        $this->assertSame(19737, $quote['net_minor']);
        $this->assertSame(2763, $quote['tax_minor']);
        $this->assertSame(22500, $quote['total_minor']);
        $this->assertSame('inclusive', $quote['tax_mode']);
    }

    public function test_zero_tax_rate_is_exact_for_both_tax_modes(): void
    {
        foreach (['exclusive', 'inclusive'] as $mode) {
            $quote = $this->calculate(6001, ['tax_rate_bps' => 0, 'tax_mode' => $mode]);

            $this->assertSame(30000, $quote['subtotal_minor']);
            $this->assertSame(30000, $quote['net_minor']);
            $this->assertSame(0, $quote['tax_minor']);
            $this->assertSame(30000, $quote['total_minor']);
        }
    }

    public function test_elapsed_before_start_is_clamped_to_zero(): void
    {
        $quote = $this->calculate(-1);

        $this->assertSame(0, $quote['elapsed_seconds']);
        $this->assertSame(4200, $quote['included_seconds']);
        $this->assertSame(0, $quote['overtime_seconds']);
        $this->assertSame(0, $quote['overtime_units']);
        $this->assertSame(15000, $quote['subtotal_minor']);
    }

    public function test_malformed_snapshot_fails_closed(): void
    {
        $invalidSnapshots = [
            ['price_minor' => 15000],
            ['price_minor' => -1, 'base_duration_seconds' => 3600, 'grace_period_seconds' => 600, 'overtime_unit_seconds' => 1800, 'overtime_price_minor' => 7500, 'tax_rate_bps' => 0, 'tax_mode' => 'exclusive', 'currency' => 'EGP'],
            array_merge($this->snapshot(), ['tax_mode' => 'unknown']),
            array_merge($this->snapshot(), ['overtime_unit_seconds' => 0]),
        ];

        foreach ($invalidSnapshots as $snapshot) {
            try {
                SessionQuoteCalculator::calculate($snapshot, $this->time(0), $this->time(1));
                $this->fail('Malformed snapshots must be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @param array<string, int|string> $overrides */
    private function calculate(int $elapsedSeconds, array $overrides = []): array
    {
        $startedAt = $this->time(0);
        $asOf = $this->time($elapsedSeconds);

        return SessionQuoteCalculator::calculate(array_merge($this->snapshot(), $overrides), $startedAt, $asOf);
    }

    /** @return array<string, int|string> */
    private function snapshot(): array
    {
        return [
            'price_minor' => 15000,
            'base_duration_seconds' => 3600,
            'grace_period_seconds' => 600,
            'overtime_unit_seconds' => 1800,
            'overtime_price_minor' => 7500,
            'tax_rate_bps' => 0,
            'tax_mode' => 'exclusive',
            'currency' => 'EGP',
        ];
    }

    private function time(int $seconds): DateTimeInterface
    {
        return (new DateTimeImmutable('2026-09-13 12:00:00 UTC'))->modify(($seconds >= 0 ? '+' : '').$seconds.' seconds');
    }
}
