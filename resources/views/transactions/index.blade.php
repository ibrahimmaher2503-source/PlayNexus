@extends('layouts.app')

@section('title', __('transactions.page_title').' · PlayNexus')

@section('content')
    @php
        $format = static fn (int $minor, string $currency): string => number_format(intdiv(max(0, $minor), 100), 0, '.', ',').'.'.str_pad((string) (max(0, $minor) % 100), 2, '0', STR_PAD_LEFT).' '.$currency;
        $hasFilters = request()->hasAny(['branch_id', 'receipt_number', 'status', 'local_date', 'cashier_id']);
    @endphp
    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6" data-pn-transactions>
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('transactions.page_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('transactions.page_description') }}</p>
        </header>

        @if (session('success'))
            <p class="mt-5 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-3 font-semibold text-[var(--pn-success)]" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-3 text-[var(--pn-danger)]" role="alert">
                @foreach ($errors->all() as $error)<p class="mt-1 text-sm">{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($branches->isEmpty())
            <p class="mt-8 rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">{{ __('pos.no_branch') }}</p>
        @else
            <section class="mt-6" aria-labelledby="transaction-filters-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold" id="transaction-filters-heading">{{ __('transactions.apply_filters') }}</h2>
                        <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('transactions.table_caption') }}</p>
                    </div>
                </div>
                <form class="mt-4 grid gap-4 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5" method="GET" action="{{ route('transactions.index') }}">
                    <div>
                        <label class="block text-sm font-semibold" for="transaction-branch">{{ __('transactions.branch') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="transaction-branch" name="branch_id">
                            @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($selectedBranch?->id === $branch->id)>{{ $branch->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="transaction-receipt">{{ __('transactions.receipt_number') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="transaction-receipt" name="receipt_number" type="search" maxlength="50" value="{{ $filters['receipt_number'] ?? '' }}" placeholder="{{ __('transactions.receipt_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="transaction-status">{{ __('transactions.status') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="transaction-status" name="status">
                            <option value="">{{ __('transactions.all_statuses') }}</option>
                            @foreach (['paid', 'refunded'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('transactions.'.$status) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="transaction-date">{{ __('transactions.local_date') }}</label>
                        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="transaction-date" name="local_date" type="date" value="{{ $filters['local_date'] ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold" for="transaction-cashier">{{ __('transactions.cashier') }}</label>
                        <select class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="transaction-cashier" name="cashier_id">
                            <option value="">{{ __('transactions.all_cashiers') }}</option>
                            @foreach ($cashiers as $cashier)<option value="{{ $cashier->id }}" @selected((string) ($filters['cashier_id'] ?? '') === (string) $cashier->id)>{{ $cashier->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-5">
                        <button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-4 font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit">{{ __('transactions.apply_filters') }}</button>
                        <a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold hover:bg-[var(--pn-surface-subtle)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" href="{{ route('transactions.index', ['branch_id' => $selectedBranch?->id]) }}">{{ __('transactions.clear_filters') }}</a>
                    </div>
                </form>
            </section>

            <section class="mt-6" aria-labelledby="transaction-table-heading">
                <h2 class="sr-only" id="transaction-table-heading">{{ __('transactions.table_caption') }}</h2>
                @if ($transactions->isEmpty())
                    <div class="rounded-[14px] border border-dashed border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-6" role="status">
                        <p class="font-semibold">{{ __('transactions.empty') }}</p>
                        @if ($hasFilters)<a class="mt-3 inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold text-[var(--pn-primary)]" href="{{ route('transactions.index', ['branch_id' => $selectedBranch->id]) }}">{{ __('transactions.clear_filters') }}</a>@endif
                    </div>
                @else
                    <div class="overflow-x-auto rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] shadow-sm" role="region" aria-labelledby="transaction-table-heading" tabindex="0">
                        <table class="min-w-full divide-y divide-[var(--pn-border)] text-start">
                            <caption class="sr-only">{{ __('transactions.table_caption') }}</caption>
                            <thead class="bg-[var(--pn-surface-subtle)] text-sm font-semibold">
                                <tr>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('transactions.receipt') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('transactions.status') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('transactions.paid_at') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('transactions.cashier_label') }}</th>
                                    <th class="px-4 py-3 text-end" scope="col">{{ __('transactions.amount') }}</th>
                                    <th class="px-4 py-3 text-start" scope="col">{{ __('transactions.refund') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--pn-border)]">
                                @foreach ($transactions as $transaction)
                                    @php
                                        $payment = $transaction->payments->first();
                                        $refund = $transaction->refund;
                                        $presentation = $presentations->get($transaction->id);
                                        $localPaidAt = $transaction->paid_at?->setTimezone($selectedBranch->timezone);
                                    @endphp
                                    <tr class="align-top">
                                        <td class="px-4 py-4" data-label="{{ __('transactions.receipt') }}">
                                            <a class="font-mono font-bold text-[var(--pn-primary)] underline-offset-2 hover:underline" href="{{ route('receipts.show', $transaction->id) }}" dir="ltr"><bdi>{{ $transaction->receipt_number }}</bdi></a>
                                            <span class="mt-1 block text-xs text-[var(--pn-ink-muted)]" dir="ltr"><bdi>#{{ $transaction->id }}</bdi></span>
                                        </td>
                                        <td class="px-4 py-4" data-label="{{ __('transactions.status') }}"><span class="inline-flex rounded-full {{ $transaction->status === 'refunded' ? 'bg-[var(--pn-warning-soft)] text-[var(--pn-warning)]' : 'bg-[var(--pn-success-soft)] text-[var(--pn-success)]' }} px-3 py-1 text-sm font-semibold">{{ __('transactions.'.$transaction->status) }}</span></td>
                                        <td class="px-4 py-4" data-label="{{ __('transactions.paid_at') }}">@if ($localPaidAt)<time class="whitespace-nowrap tabular-nums" datetime="{{ $localPaidAt->toIso8601String() }}" dir="ltr"><bdi>{{ $localPaidAt->format('Y-m-d H:i') }}</bdi></time><span class="mt-1 block text-xs text-[var(--pn-ink-muted)]">{{ $selectedBranch->timezone }}</span>@else{{ __('receipts.missing') }}@endif</td>
                                        <td class="px-4 py-4" data-label="{{ __('transactions.cashier_label') }}">{{ $transaction->paidBy?->name ?? __('receipts.missing') }}</td>
                                        <td class="px-4 py-4 text-end font-semibold tabular-nums" data-label="{{ __('transactions.amount') }}" dir="ltr"><bdi>{{ $format((int) $transaction->paid_minor, $transaction->currency) }}</bdi></td>
                                        <td class="px-4 py-4" data-label="{{ __('transactions.refund') }}">
                                            @if ($refund)
                                                <details>
                                                    <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold text-[var(--pn-primary)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--pn-focus)]">{{ __('transactions.view_details') }}</summary>
                                                    <div class="mt-3 min-w-72 rounded-[10px] bg-[var(--pn-surface-subtle)] p-4">
                                                        <p class="font-semibold">{{ $refund->status === 'refunded' ? __('transactions.refund_refunded') : ($refund->status === 'approved' ? __('transactions.refund_approved') : __('transactions.refund_requested')) }}</p>
                                                        <dl class="mt-3 space-y-2 text-sm">
                                                            <div><dt class="font-semibold">{{ __('transactions.refund_reason') }}</dt><dd class="whitespace-pre-wrap">{{ $refund->reason }}</dd></div>
                                                            <div><dt class="font-semibold">{{ __('transactions.expected_version') }}</dt><dd class="font-mono" dir="ltr">{{ $refund->expected_order_lock_version }}</dd></div>
                                                            <div><dt class="font-semibold">{{ __('transactions.requested_by') }}</dt><dd>{{ $refund->requestedBy?->name ?? __('receipts.missing') }} · <span dir="ltr">{{ optional($refund->requested_at)->format('Y-m-d H:i') }}</span></dd></div>
                                                            <div><dt class="font-semibold">{{ __('transactions.request_idempotency') }}</dt><dd class="break-all font-mono text-xs" dir="ltr">{{ $refund->request_idempotency_key }}</dd></div>
                                                            @if ($refund->approved_at)<div><dt class="font-semibold">{{ __('transactions.approved_by') }}</dt><dd>{{ $refund->approvedBy?->name ?? __('receipts.missing') }} · <span dir="ltr">{{ $refund->approved_at->format('Y-m-d H:i') }}</span></dd></div>@endif
                                                            @if ($refund->executed_at)<div><dt class="font-semibold">{{ __('transactions.executed_by') }}</dt><dd>{{ $refund->executedBy?->name ?? __('receipts.missing') }} · <span dir="ltr">{{ $refund->executed_at->format('Y-m-d H:i') }}</span></dd></div><div><dt class="font-semibold">{{ __('transactions.execution_idempotency') }}</dt><dd class="break-all font-mono text-xs" dir="ltr">{{ $refund->execution_idempotency_key }}</dd></div>@endif
                                                        </dl>
                                                        @if ($presentation['can_approve'])
                                                            <form class="mt-4" method="POST" action="{{ route('refunds.approve', $refund->id) }}">@csrf<button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-3 text-sm font-semibold text-[var(--pn-surface)]" type="submit">{{ __('transactions.approve_refund') }}</button></form>
                                                        @elseif ($refund->status === 'requested')
                                                            <p class="mt-3 text-xs text-[var(--pn-ink-muted)]">{{ __('transactions.separate_approval') }}</p>
                                                        @endif
                                                        @if ($presentation['can_execute'])
                                                            <form class="mt-4" method="POST" action="{{ route('refunds.execute', $refund->id) }}">@csrf<input name="expected_order_lock_version" type="hidden" value="{{ $refund->expected_order_lock_version }}"><input name="idempotency_key" type="hidden" value="{{ $presentation['execution_key'] }}"><button class="inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-3 text-sm font-semibold text-[var(--pn-surface)]" type="submit">{{ __('transactions.execute_refund') }}</button></form>
                                                        @endif
                                                    </div>
                                                </details>
                                            @elseif ($presentation['eligibility']['eligible'])
                                                <details>
                                                    <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-[10px] border border-[var(--pn-border-strong)] px-3 text-sm font-semibold text-[var(--pn-primary)] outline-none focus-visible:ring-2 focus-visible:ring-[var(--pn-focus)]">{{ __('transactions.refund_eligibility') }}</summary>
                                                    <div class="mt-3 min-w-72 rounded-[10px] bg-[var(--pn-surface-subtle)] p-4">
                                                        <p class="font-semibold text-[var(--pn-success)]">{{ __('transactions.eligible') }}</p>
                                                        <p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('transactions.eligible_note') }}</p>
                                                        @if ($presentation['can_request'])
                                                            <form class="mt-4" method="POST" action="{{ route('refunds.request', $transaction->id) }}">
                                                                @csrf
                                                                <input name="expected_order_lock_version" type="hidden" value="{{ $transaction->lock_version }}">
                                                                <input name="idempotency_key" type="hidden" value="{{ $presentation['request_key'] }}">
                                                                <label class="block text-sm font-semibold" for="refund-reason-{{ $transaction->id }}">{{ __('transactions.refund_reason') }}<textarea class="mt-1 min-h-20 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-3" id="refund-reason-{{ $transaction->id }}" name="reason" maxlength="500" required placeholder="{{ __('transactions.reason_placeholder') }}"></textarea></label>
                                                                <button class="mt-3 inline-flex min-h-11 items-center rounded-[10px] bg-[var(--pn-primary)] px-3 text-sm font-semibold text-[var(--pn-surface)]" type="submit">{{ __('transactions.submit_request') }}</button>
                                                            </form>
                                                        @else
                                                            <p class="mt-3 text-xs text-[var(--pn-ink-muted)]">{{ __('transactions.cashier_no_approval') }}</p>
                                                        @endif
                                                    </div>
                                                </details>
                                            @else
                                                <span class="text-sm text-[var(--pn-ink-muted)]">{{ __('transactions.not_eligible') }}: {{ $presentation['eligibility']['reason'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($transactions->hasPages())
                    <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('transactions.pagination') }}">
                        <p class="text-sm text-[var(--pn-ink-muted)]">{{ __('transactions.page_position', ['current' => $transactions->currentPage(), 'last' => $transactions->lastPage()]) }}</p>
                        <div class="flex flex-wrap gap-2">
                            @if ($transactions->previousPageUrl())<a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold" href="{{ $transactions->previousPageUrl() }}">{{ __('transactions.previous') }}</a>@endif
                            @if ($transactions->nextPageUrl())<a class="inline-flex min-h-11 items-center rounded-[10px] border border-[var(--pn-border-strong)] px-4 font-semibold" href="{{ $transactions->nextPageUrl() }}">{{ __('transactions.next') }}</a>@endif
                        </div>
                    </nav>
                @endif
            </section>
        @endif
    </main>
@endsection
