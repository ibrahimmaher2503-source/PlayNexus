<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('receipts.title') }} {{ $receipt_number }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <style>
            @media print {
                nav, aside, body > a, #receipt-actions { display: none !important; }
                body { background: white !important; }
                #receipt-print-artifact { max-width: none !important; margin: 0 !important; border: 0 !important; box-shadow: none !important; }
            }
        </style>
    </head>
    <body class="bg-[var(--pn-canvas)] text-[var(--pn-ink)] antialiased">
        <main id="receipt-print-artifact" class="mx-auto my-8 max-w-2xl rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6 shadow-sm sm:p-8">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[var(--pn-border)] pb-5">
                <div>
                    <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $receipt['branch_name'] ?? 'PlayNexus' }}</p>
                    <h1 class="mt-1 text-2xl font-bold">{{ __('receipts.title') }}</h1>
                    <p class="mt-1 font-mono text-lg font-bold" dir="ltr"><bdi>{{ $receipt_number }}</bdi></p>
                </div>
                <div id="receipt-actions" class="flex gap-2">
                    <button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)]">{{ __('receipts.print') }}</button>
                </div>
            </header>

            <div class="mt-6 grid items-start gap-6 sm:grid-cols-[160px_minmax(0,1fr)]">
                <div class="grid place-items-center rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3" data-pn-ticket-qr data-qr-payload="{{ $qr_payload }}">
                    <canvas class="block aspect-square w-full max-w-full" width="320" height="320" data-pn-ticket-qr-canvas aria-label="{{ __('receipts.verification_reference') }}"></canvas>
                    <p class="mt-2 hidden text-center text-xs text-[var(--pn-danger)]" data-pn-ticket-qr-fallback>{{ __('receipts.qr_unavailable') }}</p>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.verification_reference') }}</dt><dd class="mt-1 break-all font-mono" dir="ltr"><bdi>{{ $verification_reference ?: __('receipts.missing') }}</bdi></dd></div>
                    <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.cashier') }}</dt><dd class="mt-1 font-semibold">{{ $receipt['cashier_name'] ?? __('receipts.missing') }}</dd></div>
                    <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.seller') }}</dt><dd class="mt-1 font-semibold">{{ $receipt['seller_name'] ?? __('receipts.missing') }}</dd></div>
                    <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.status') }}</dt><dd class="mt-1 font-semibold">{{ $order_status }}</dd></div>
                    @if ($order->receipt_issued_at)
                        @php($receiptTimezone = $receipt['branch_timezone'] ?? $order->branch->timezone)
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.issued_at') }}</dt><dd class="mt-1" dir="ltr"><time datetime="{{ $order->receipt_issued_at->toIso8601String() }}">{{ $order->receipt_issued_at->setTimezone($receiptTimezone)->format('Y-m-d H:i') }} {{ $receiptTimezone }}</time></dd></div>
                    @endif
                </dl>
            </div>

            <div class="mt-6 overflow-x-auto border-y border-[var(--pn-border)]">
                <table class="min-w-full text-start text-sm">
                    <thead><tr class="border-b border-[var(--pn-border)]"><th class="px-2 py-3">{{ __('receipts.item') }}</th><th class="px-2 py-3 text-end">{{ __('receipts.total') }}</th></tr></thead>
                    <tbody>
                        @foreach (($receipt['items'] ?? []) as $item)
                            <tr class="border-b border-[var(--pn-border)] last:border-0">
                                <td class="px-2 py-3">{{ $item['description'] ?? __('receipts.missing') }} × {{ $item['quantity'] ?? 1 }}</td>
                                <td class="px-2 py-3 text-end tabular-nums" dir="ltr"><bdi>{{ number_format(((int) ($item['line_total_minor'] ?? 0)) / 100, 2) }} {{ $receipt['currency'] ?? 'EGP' }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <dl class="ms-auto mt-5 grid max-w-xs gap-2 text-sm">
                <div class="flex justify-between gap-4"><dt>{{ __('receipts.subtotal') }}</dt><dd class="tabular-nums" dir="ltr"><bdi>{{ number_format(((int) ($receipt['subtotal_minor'] ?? 0)) / 100, 2) }}</bdi></dd></div>
                <div class="flex justify-between gap-4"><dt>{{ __('receipts.tax') }}</dt><dd class="tabular-nums" dir="ltr"><bdi>{{ number_format(((int) ($receipt['tax_minor'] ?? 0)) / 100, 2) }}</bdi></dd></div>
                <div class="flex justify-between gap-4 border-t border-[var(--pn-border)] pt-2 font-bold"><dt>{{ __('receipts.paid') }}</dt><dd class="tabular-nums" dir="ltr"><bdi>{{ number_format(((int) ($receipt['paid_minor'] ?? 0)) / 100, 2) }} {{ $receipt['currency'] ?? 'EGP' }}</bdi></dd></div>
            </dl>

            @if ($refund)
                <p class="mt-6 rounded-[10px] border border-[var(--pn-warning)] bg-[var(--pn-warning-soft)] p-3 text-sm font-semibold">
                    {{ __('receipts.refund_status') }}: {{ $refund->status }} · {{ number_format(((int) $order->refunded_minor) / 100, 2) }} {{ $receipt['currency'] ?? 'EGP' }}
                </p>
            @endif

            <section class="mt-6 border-t border-[var(--pn-border)] pt-6" aria-labelledby="refund-heading">
                <h2 class="text-xl font-bold" id="refund-heading">{{ __('receipts.refund_eligibility') }}</h2>
                @if ($refund)
                    <p class="mt-2 inline-flex rounded-full bg-[var(--pn-warning-soft)] px-3 py-1 text-sm font-semibold">{{ $refund->status === 'refunded' ? __('transactions.refund_refunded') : ($refund->status === 'approved' ? __('transactions.refund_approved') : __('transactions.refund_requested')) }}</p>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.reason') }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $refund->reason }}</dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.expected_version') }}</dt><dd class="mt-1 font-mono" dir="ltr">{{ $refund->expected_order_lock_version }}</dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.requested_by') }}</dt><dd class="mt-1">{{ $refund->requestedBy?->name ?? __('receipts.missing') }} · <span dir="ltr">{{ optional($refund->requested_at)->format('Y-m-d H:i') }}</span></dd></div>
                        <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.request_idempotency') }}</dt><dd class="mt-1 break-all font-mono text-xs" dir="ltr">{{ $refund->request_idempotency_key }}</dd></div>
                        @if ($refund->approved_at)
                            <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.approved_by') }}</dt><dd class="mt-1">{{ $refund->approvedBy?->name ?? __('receipts.missing') }} · <span dir="ltr">{{ $refund->approved_at->format('Y-m-d H:i') }}</span></dd></div>
                        @endif
                        @if ($refund->executed_at)
                            <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.executed_by') }}</dt><dd class="mt-1">{{ $refund->executedBy?->name ?? __('receipts.missing') }} · <span dir="ltr">{{ $refund->executed_at->format('Y-m-d H:i') }}</span></dd></div>
                            <div><dt class="font-semibold text-[var(--pn-ink-muted)]">{{ __('receipts.execution_idempotency') }}</dt><dd class="mt-1 break-all font-mono text-xs" dir="ltr">{{ $refund->execution_idempotency_key }}</dd></div>
                        @endif
                    </dl>
                    @if ($canApprove)
                        <form class="mt-5" method="POST" action="{{ route('refunds.approve', $refund->getKey()) }}">
                            @csrf
                            <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)]" type="submit">{{ __('receipts.approve_refund') }}</button>
                        </form>
                    @elseif ($refund->status === 'requested')
                        <p class="mt-4 text-sm text-[var(--pn-ink-muted)]">{{ __('receipts.separate_approval') }}</p>
                    @endif
                    @if ($canExecute)
                        <form class="mt-5" method="POST" action="{{ route('refunds.execute', $refund->getKey()) }}">
                            @csrf
                            <input name="expected_order_lock_version" type="hidden" value="{{ $refund->expected_order_lock_version }}">
                            <input name="idempotency_key" type="hidden" value="{{ $executionKey }}">
                            <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)]" type="submit">{{ __('receipts.execute_refund') }}</button>
                        </form>
                        <p class="mt-2 text-sm text-[var(--pn-ink-muted)]">{{ __('receipts.execution_note') }}</p>
                    @endif
                @elseif ($eligibility['eligible'])
                    <p class="mt-2 font-semibold text-[var(--pn-success)]">{{ __('receipts.eligible') }}</p>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('receipts.eligible_note') }}</p>
                    @if ($canRequest)
                        <form class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" method="POST" action="{{ route('refunds.request', $order->getKey()) }}">
                            @csrf
                            <input name="expected_order_lock_version" type="hidden" value="{{ $order->lock_version }}">
                            <input name="idempotency_key" type="hidden" value="{{ $requestKey }}">
                            <label class="text-sm font-semibold sm:col-span-2" for="refund-reason">{{ __('receipts.reason') }}
                                <textarea class="mt-1 min-h-20 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3" id="refund-reason" name="reason" maxlength="500" required placeholder="{{ __('receipts.reason_placeholder') }}"></textarea>
                            </label>
                            <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] sm:col-start-2" type="submit">{{ __('receipts.submit_request') }}</button>
                        </form>
                    @else
                        <p class="mt-4 text-sm text-[var(--pn-ink-muted)]">{{ __('receipts.cashier_no_approval') }}</p>
                    @endif
                @else
                    <p class="mt-2 font-semibold text-[var(--pn-danger)]">{{ __('receipts.not_eligible') }}</p>
                    <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ $eligibility['reason'] }}</p>
                @endif
            </section>
        </main>
    </body>
</html>
