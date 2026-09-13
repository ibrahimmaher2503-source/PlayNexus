<?php

namespace Tests\Unit;

use App\Support\SessionQuoteCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SessionQuoteAdjustmentTest extends TestCase
{
    public function test_extensions_and_charge_adjustments_are_summed_deterministically(): void
    {
        $quote = SessionQuoteCalculator::calculate($this->snapshot(), $this->time(0), $this->time(1800), [
            ['extension_units' => 1, 'adjustment_minor' => 1000],
            ['extension_units' => 1, 'adjustment_minor' => -250],
        ]);

        $this->assertSame(2, $quote['extension_units']);
        $this->assertSame(3600, $quote['extension_seconds']);
        $this->assertSame(15000, $quote['extension_price_minor']);
        $this->assertSame(750, $quote['adjustment_minor']);
        $this->assertSame(0, $quote['overtime_units']);
        $this->assertSame(30750, $quote['subtotal_minor']);
    }

    public function test_extension_time_moves_the_overtime_boundary(): void
    {
        $atBoundary = SessionQuoteCalculator::calculate($this->snapshot(), $this->time(0), $this->time(6000), [
            ['extension_units' => 1, 'adjustment_minor' => 0],
        ]);
        $this->assertSame(0, $atBoundary['overtime_seconds']);
        $this->assertSame(0, $atBoundary['overtime_units']);
        $this->assertSame(22500, $atBoundary['subtotal_minor']);

        $afterBoundary = SessionQuoteCalculator::calculate($this->snapshot(), $this->time(0), $this->time(6001), [
            ['extension_units' => 1, 'adjustment_minor' => 0],
        ]);
        $this->assertSame(1, $afterBoundary['overtime_seconds']);
        $this->assertSame(1, $afterBoundary['overtime_units']);
        $this->assertSame(30000, $afterBoundary['subtotal_minor']);
    }

    public function test_malformed_or_negative_result_adjustments_fail_closed(): void
    {
        foreach ([
            [['extension_units' => -1, 'adjustment_minor' => 0]],
            [['extension_units' => 49, 'adjustment_minor' => 0]],
            [['extension_units' => 0, 'adjustment_minor' => '1']],
            [['extension_units' => 0, 'adjustment_minor' => -16000]],
        ] as $adjustments) {
            $this->expectException(\InvalidArgumentException::class);
            SessionQuoteCalculator::calculate($this->snapshot(), $this->time(0), $this->time(4200), $adjustments);
        }
    }

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

    private function time(int $seconds): DateTimeImmutable
    {
        return (new DateTimeImmutable('2026-09-13 12:00:00 UTC'))->modify(($seconds >= 0 ? '+' : '').$seconds.' seconds');
    }
}
