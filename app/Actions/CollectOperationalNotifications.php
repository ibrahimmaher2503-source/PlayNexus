<?php

namespace App\Actions;

use App\Jobs\ProcessOperationalNotification;
use App\Models\Guardian;
use App\Models\NotificationMessage;
use App\Models\Order;
use App\Models\PlaySession;

class CollectOperationalNotifications
{
    /** @return array{created: int, dispatched: int} */
    public function run(): array
    {
        $created = 0;
        $dispatched = 0;
        $due = now('UTC')->addMinutes((int) config('operational_notifications.ending_alert_minutes', 10));

        PlaySession::query()->where('status', 'active')->where('expected_end_at', '<=', $due)
            ->orderBy('id')->chunkById(100, function ($sessions) use (&$created, &$dispatched): void {
                foreach ($sessions as $session) {
                    [$message, $wasCreated] = $this->store(
                        tenantId: (int) $session->tenant_id,
                        branchId: (int) $session->branch_id,
                        guardianId: (int) $session->guardian_id,
                        sessionId: (int) $session->id,
                        orderId: null,
                        purpose: 'session_ending',
                        dedupeKey: 'session-ending:'.$session->id.':'.$session->expected_end_at->utc()->format('YmdHis'),
                        payload: ['session_id' => (int) $session->id, 'expected_end_at' => $session->expected_end_at->utc()->toIso8601String()],
                    );
                    $created += (int) $wasCreated;
                    if ($wasCreated && $message->status === 'queued') {
                        ProcessOperationalNotification::dispatch($message->getKey());
                        $dispatched++;
                    }
                }
            });

        Order::query()->whereIn('status', ['paid', 'refunded'])->whereNotNull('receipt_number')
            ->orderBy('id')->chunkById(100, function ($orders) use (&$created, &$dispatched): void {
                foreach ($orders as $order) {
                    [$message, $wasCreated] = $this->store(
                        tenantId: (int) $order->tenant_id,
                        branchId: (int) $order->branch_id,
                        guardianId: $order->guardian_id === null ? null : (int) $order->guardian_id,
                        sessionId: $order->session_id === null ? null : (int) $order->session_id,
                        orderId: (int) $order->id,
                        purpose: 'receipt',
                        dedupeKey: 'receipt:'.$order->id,
                        payload: [
                            'order_id' => (int) $order->id,
                            'receipt_number' => (string) $order->receipt_number,
                            'amount_minor' => (int) $order->paid_minor,
                            'currency' => (string) $order->currency,
                        ],
                    );
                    $created += (int) $wasCreated;
                    if ($wasCreated && $message->status === 'queued') {
                        ProcessOperationalNotification::dispatch($message->getKey());
                        $dispatched++;
                    }
                }
            });

        NotificationMessage::query()->where('status', 'failed_retryable')
            ->where('attempt_count', '<', (int) config('operational_notifications.max_attempts', 3))
            ->where('scheduled_at', '<=', now('UTC'))->pluck('id')->each(function (int $id) use (&$dispatched): void {
                ProcessOperationalNotification::dispatch($id);
                $dispatched++;
            });

        return compact('created', 'dispatched');
    }

    /** @return array{NotificationMessage, bool} */
    private function store(int $tenantId, int $branchId, ?int $guardianId, ?int $sessionId, ?int $orderId, string $purpose, string $dedupeKey, array $payload): array
    {
        $guardian = $guardianId === null ? null : Guardian::query()
            ->where('tenant_id', $tenantId)->whereKey($guardianId)->where('status', 'active')->first();
        $destination = $guardian?->phone_e164;
        $message = NotificationMessage::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'dedupe_key' => $dedupeKey],
            [
                'branch_id' => $branchId,
                'guardian_id' => $guardianId,
                'session_id' => $sessionId,
                'order_id' => $orderId,
                'channel' => 'database',
                'purpose' => $purpose,
                'template_key' => $purpose,
                'template_version' => '1',
                'locale' => $guardian?->preferred_locale ?: 'ar',
                'destination_encrypted' => $destination,
                'destination_masked' => $this->mask($destination),
                'payload_json' => $payload,
                'status' => $destination ? 'queued' : 'failed_permanent',
                'scheduled_at' => now('UTC'),
                'failed_at' => $destination ? null : now('UTC'),
            ],
        );

        return [$message, $message->wasRecentlyCreated];
    }

    private function mask(?string $destination): ?string
    {
        return $destination === null ? null : str_repeat('•', max(0, mb_strlen($destination) - 4)).mb_substr($destination, -4);
    }
}
