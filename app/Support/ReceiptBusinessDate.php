<?php

namespace App\Support;

use App\Models\Order;
use DateTimeZone;

final class ReceiptBusinessDate
{
    /**
     * The receipt timezone is a commercial fact. Do not fall back to the
     * mutable branch configuration when determining same-business-day rules.
     */
    public static function timezone(Order $order): ?string
    {
        $receipt = $order->receipt_snapshot_json;
        $timezone = is_array($receipt) ? ($receipt['branch_timezone'] ?? null) : null;

        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : null;
    }
}
