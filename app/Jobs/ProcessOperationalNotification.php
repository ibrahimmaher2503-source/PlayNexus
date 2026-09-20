<?php

namespace App\Jobs;

use App\Models\NotificationAttempt;
use App\Models\NotificationMessage;
use App\Models\PlaySession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessOperationalNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $message = NotificationMessage::query()->whereKey($this->messageId)->lockForUpdate()->first();
            if (! $message || in_array($message->status, ['sent', 'delivered', 'failed_permanent', 'stale'], true)) {
                return;
            }
            if ($message->status === 'failed_retryable' && $message->scheduled_at->isFuture()) {
                return;
            }

            if ($message->purpose === 'session_ending') {
                $session = PlaySession::query()->where('tenant_id', $message->tenant_id)
                    ->whereKey($message->session_id)->lockForUpdate()->first();
                $expectedEnd = $message->payload_json['expected_end_at'] ?? null;
                if (! $session || $session->status !== 'active' || $session->expected_end_at?->utc()->toIso8601String() !== $expectedEnd) {
                    $message->forceFill(['status' => 'stale', 'stale_at' => now('UTC'), 'lock_version' => (int) $message->lock_version + 1])->save();

                    return;
                }
            }

            $attempt = (int) $message->attempt_count + 1;
            $now = now('UTC');
            $accepted = config('operational_notifications.local_outcome') === 'accepted';
            $max = (int) config('operational_notifications.max_attempts', 3);
            $status = $accepted ? 'sent' : ($attempt >= $max ? 'failed_permanent' : 'failed_retryable');
            $reference = $accepted ? 'local-'.$message->tenant_id.'-'.$message->getKey() : null;

            NotificationAttempt::query()->create([
                'tenant_id' => $message->tenant_id,
                'notification_message_id' => $message->getKey(),
                'attempt_number' => $attempt,
                'provider' => 'local_database',
                'started_at' => $now,
                'finished_at' => $now,
                'outcome' => $accepted ? 'sent' : $status,
                'provider_status_code' => $accepted ? 'accepted' : 'unavailable',
                'provider_message_id' => $reference,
                'error_code' => $accepted ? null : 'local_transport_unavailable',
                'error_summary_masked' => $accepted ? null : 'Local acceptance transport unavailable.',
            ]);

            $message->forceFill([
                'status' => $status,
                'attempt_count' => $attempt,
                'provider_message_id' => $reference,
                'sent_at' => $accepted ? $now : null,
                'failed_at' => $accepted ? null : $now,
                'scheduled_at' => $status === 'failed_retryable'
                    ? $now->copy()->addMinutes((int) config('operational_notifications.retry_backoff_minutes', 1) * $attempt)
                    : $message->scheduled_at,
                'lock_version' => (int) $message->lock_version + 1,
            ])->save();
        });
    }
}
