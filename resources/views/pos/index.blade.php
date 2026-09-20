@extends('layouts.app')

@section('title', __('pos.page_title').' · PlayNexus')

@section('content')
    @php
        $format = static fn (int $minor): string => number_format(intdiv(max(0, $minor), 100), 0, '.', ',').'.'.str_pad((string) (max(0, $minor) % 100), 2, '0', STR_PAD_LEFT);
        $selectedBranchId = $selectedBranch?->id;
    @endphp
    <main class="mx-auto min-h-screen max-w-[1440px] px-4 py-6 sm:px-6" data-pn-pos>
        <header class="border-b border-[var(--pn-border)] pb-5">
            <p class="text-sm font-semibold text-[var(--pn-primary)]">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('pos.page_title') }}</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[var(--pn-ink-muted)]">{{ __('pos.page_description') }}</p>
        </header>

        @if (session('success'))
            <p class="mt-5 rounded-[10px] border border-[var(--pn-success)] bg-[var(--pn-success-soft)] p-3 font-semibold text-[var(--pn-success)]" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div class="mt-5 rounded-[10px] border border-[var(--pn-danger)] bg-[var(--pn-danger-soft)] p-3 text-[var(--pn-danger)]" role="alert">
                <p class="font-semibold">{{ __('pos.validation_failed') }}</p>
                @foreach ($errors->all() as $error)<p class="mt-1 text-sm">{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($branches->isEmpty())
            <p class="mt-8 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-6">{{ __('pos.no_branch') }}</p>
        @else
            <form class="mt-5 flex flex-wrap items-end gap-3" method="GET" action="{{ route('pos.index') }}">
                <label class="block min-w-56 text-sm font-semibold">{{ __('pos.branch') }}
                    <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" name="branch_id" onchange="this.form.submit()">
                        @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($branch->id === $selectedBranchId)>{{ $branch->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block min-w-64 text-sm font-semibold">{{ __('pos.search') }}
                    <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" name="q" value="{{ $search }}" type="search">
                </label>
                <label class="block min-w-48 text-sm font-semibold">{{ __('pos.filter_type') }}
                    <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" name="type" onchange="this.form.submit()">
                        <option value="">{{ __('pos.all_types') }}</option>
                        @foreach (['food_beverage', 'merchandise', 'play_add_on'] as $itemType)<option value="{{ $itemType }}" @selected($type === $itemType)>{{ __('pos.'.$itemType) }}</option>@endforeach
                    </select>
                </label>
            </form>

            <div class="mt-6 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section class="rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="pos-catalog-heading">
                    <div class="flex items-center justify-between gap-3 border-b border-[var(--pn-border)] pb-4"><h2 class="text-xl font-bold" id="pos-catalog-heading">{{ __('pos.catalog') }}</h2><span class="text-sm text-[var(--pn-ink-muted)]">{{ $selectedBranch?->currency }}</span></div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-catalog>
                        @foreach ($products as $product)
                            <article class="flex min-h-36 flex-col justify-between rounded-[10px] border border-[var(--pn-border)] p-4" data-catalog-item data-name="{{ strtolower($product->name.' '.$product->sku) }}" data-type="{{ $product->type }}">
                                <div><p class="font-semibold" dir="auto">{{ $product->name }}</p>@if ($product->branch_id === null)<p class="mt-1 text-xs font-semibold text-[var(--pn-primary)]">{{ __('pos.all_branches') }}</p>@endif<p class="mt-1 font-mono text-xs text-[var(--pn-ink-muted)]" dir="ltr"><bdi>{{ $product->sku }}</bdi></p><p class="mt-3 text-lg font-bold tabular-nums" dir="ltr"><bdi>{{ $format($product->price_minor) }} {{ $product->currency }}</bdi></p></div>
                                <button class="mt-4 inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-3 text-sm font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-add data-kind="product" data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->price_minor }}">{{ __('pos.add') }}</button>
                            </article>
                        @endforeach
                        @foreach ($ticketTypes as $ticket)
                            @php
                                $rule = $ticket->pricingRule;
                            @endphp
                            @if ($rule && $rule->status === 'active' && $rule->currency === $selectedBranch?->currency)
                                <article class="flex min-h-36 flex-col justify-between rounded-[10px] border border-[var(--pn-border)] p-4" data-catalog-item data-name="{{ strtolower($ticket->name.' '.$ticket->code) }}" data-type="ticket">
                                    <div><p class="font-semibold" dir="auto">{{ $ticket->name }}</p><p class="mt-1 font-mono text-xs text-[var(--pn-ink-muted)]" dir="ltr"><bdi>{{ $ticket->code }}</bdi></p><p class="mt-3 text-lg font-bold tabular-nums" dir="ltr"><bdi>{{ $format($ticket->price_minor) }} {{ $ticket->currency }}</bdi></p></div>
                                    <button class="mt-4 inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-3 text-sm font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="button" data-add data-kind="ticket" data-id="{{ $ticket->id }}" data-name="{{ $ticket->name }}" data-price="{{ $ticket->price_minor }}">{{ __('pos.add') }}</button>
                                </article>
                            @endif
                        @endforeach
                        @if ($products->isEmpty() && $ticketTypes->isEmpty())<p class="text-sm text-[var(--pn-ink-muted)]">{{ __('pos.empty_catalog') }}</p>@endif
                    </div>
                </section>

                <aside class="sticky top-20 rounded-[14px] border border-[var(--pn-border-strong)] bg-[var(--pn-surface)] p-5 shadow-sm" aria-labelledby="pos-cart-heading" data-cash-order data-order-url="{{ route('pos.orders.store') }}" data-payment-url="{{ route('pos.orders.payments.store', ['order' => '__ORDER__']) }}" data-approval-url="{{ route('discount-approvals.request', ['order' => '__ORDER__']) }}" data-receipt-url="{{ route('receipts.show', ['order' => '__ORDER__']) }}">
                    <div class="flex items-center justify-between gap-3"><h2 class="text-xl font-bold" id="pos-cart-heading">{{ __('pos.cart') }}</h2><span class="text-xs font-semibold text-[var(--pn-ink-muted)]">{{ $selectedBranch?->currency }}</span></div>
                    <div class="mt-4 space-y-3" data-cart aria-live="polite"><p class="text-sm text-[var(--pn-ink-muted)]">{{ __('pos.empty_cart') }}</p></div>
                    <form class="mt-5 border-t border-[var(--pn-border)] pt-4" method="POST" action="{{ route('pos.quote') }}" data-quote-form>
                        @csrf<input name="branch_id" type="hidden" value="{{ $selectedBranchId }}"><div class="mt-4 hidden rounded-[10px] border border-[var(--pn-border-strong)] bg-[var(--pn-primary-soft)] p-3" data-ticket-context>
                            <h3 class="text-sm font-bold">{{ __('pos.ticket_details') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('pos.ticket_details_help') }}</p>
                            <label class="mt-3 block text-sm font-semibold" for="pos-ticket-family">{{ __('pos.select_ticket_family') }}
                                <select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pos-ticket-family" data-ticket-family>
                                    <option value="">{{ __('pos.select_ticket_family_prompt') }}</option>
                                    @foreach ($ticketFamilies as $family)
                                        <option value="{{ $family['guardian_id'] }}:{{ $family['child_id'] }}" data-guardian-id="{{ $family['guardian_id'] }}" data-child-id="{{ $family['child_id'] }}">{{ $family['guardian_name'] }} — {{ $family['child_name'] }} · {{ $family['guardian_phone'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if ($ticketFamilies->isEmpty())<p class="mt-2 text-sm text-[var(--pn-danger)]">{{ __('pos.no_ticket_families') }}</p>@endif
                            <label class="mt-3 block text-sm font-semibold" for="pos-service-date">{{ __('pos.service_date') }}
                                <input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] bg-[var(--pn-surface)] px-3 focus:border-[var(--pn-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" id="pos-service-date" data-service-date min="{{ $serviceDateMin }}" type="date">
                            </label>
                            <p class="mt-2 hidden rounded-[10px] bg-[var(--pn-danger-soft)] p-2 text-sm text-[var(--pn-danger)]" data-ticket-context-message role="alert"></p>
                        </div><div data-hidden-ticket-facts></div><div data-hidden-lines></div>
                        <dl class="space-y-2 text-sm"><div class="flex justify-between"><dt>{{ __('pos.subtotal') }}</dt><dd data-subtotal dir="ltr">0.00 {{ $selectedBranch?->currency }}</dd></div><div class="flex justify-between"><dt>{{ __('pos.tax') }}</dt><dd data-tax dir="ltr">0.00 {{ $selectedBranch?->currency }}</dd></div><div class="flex justify-between border-t border-[var(--pn-border)] pt-3 text-lg font-bold"><dt>{{ __('pos.total') }}</dt><dd data-total dir="ltr">0.00 {{ $selectedBranch?->currency }}</dd></div></dl>
                        <p class="mt-3 hidden rounded-[10px] bg-[var(--pn-danger-soft)] p-3 text-sm text-[var(--pn-danger)]" data-quote-message role="alert"></p>
                        <button class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)] hover:bg-[var(--pn-primary-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--pn-focus)]" type="submit" data-quote-submit>{{ __('pos.calculate') }}</button>
                    </form>
                    @if ($canTransact)
                    <section class="mt-4 border-t border-[var(--pn-border)] pt-4" aria-labelledby="pos-order-heading">
                        <h3 class="text-sm font-bold" id="pos-order-heading">{{ __('pos.order_flow_notice') }}</h3>
                        <p class="mt-2 hidden rounded-[10px] bg-[var(--pn-success-soft)] p-3 text-sm text-[var(--pn-success)]" data-order-message role="status"></p>
                        <p class="mt-2 hidden rounded-[10px] bg-[var(--pn-primary-soft)] p-3 text-sm font-semibold" data-frozen-total><span>{{ __('pos.frozen_total') }}:</span> <b data-frozen-total-value dir="ltr"></b></p>
                        <button class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)] disabled:cursor-not-allowed disabled:opacity-50" type="button" data-create-order disabled>{{ __('pos.create_order') }}</button>
                        <button class="mt-3 hidden min-h-11 w-full rounded-[10px] border-2 border-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-primary)] disabled:cursor-not-allowed disabled:opacity-50" type="button" data-confirm-payment disabled>{{ __('pos.confirm_cash') }}</button>
                        @if ($canRequestDiscount)<form class="mt-4 hidden space-y-3 rounded-[10px] border border-[var(--pn-border)] p-3" data-discount-form>
                            <p class="text-sm font-bold">{{ __('pos.request_discount') }}</p>
                            <label class="block text-sm font-semibold" for="discount-amount">{{ __('pos.discount_amount') }}<input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" id="discount-amount" inputmode="decimal" placeholder="0.00" required data-discount-amount></label>
                            <label class="block text-sm font-semibold" for="discount-reason">{{ __('pos.discount_reason') }}<textarea class="mt-1 min-h-20 w-full rounded-[10px] border border-[var(--pn-border)] p-3" id="discount-reason" maxlength="500" required data-discount-reason></textarea></label>
                            <p class="hidden rounded-[10px] bg-[var(--pn-danger-soft)] p-3 text-sm text-[var(--pn-danger)]" data-discount-message role="alert"></p>
                            <button class="inline-flex min-h-11 w-full items-center justify-center rounded-[10px] border border-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-primary)]" type="submit" data-discount-submit>{{ __('pos.send_for_approval') }}</button>
                        </form>@endif
                        <a class="mt-3 hidden min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-success)] px-4 text-sm font-semibold text-[var(--pn-success)]" data-receipt-link target="_blank" rel="noopener">{{ __('pos.view_receipt') }}</a>
                    </section>
                    @else
                        <p class="mt-4 border-t border-[var(--pn-border)] pt-4 text-xs leading-5 text-[var(--pn-ink-muted)]">{{ __('pos.cashier_only') }}</p>
                    @endif
                    <p class="mt-4 rounded-[10px] bg-[var(--pn-warning-soft)] p-3 text-xs leading-5 text-[var(--pn-warning)]">{{ __('pos.cash_ready_notice') }}</p>
                </aside>
            </div>

            <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="pos-pending-heading" data-pending-queue>
                <div class="flex items-center justify-between gap-3 border-b border-[var(--pn-border)] pb-4"><h2 class="text-xl font-bold" id="pos-pending-heading">{{ __('pos.pending_payments') }}</h2><span class="text-sm text-[var(--pn-ink-muted)]">{{ $selectedBranch?->name }}</span></div>
                @if ($pendingSessions->isEmpty())
                    <p class="mt-4 text-sm text-[var(--pn-ink-muted)]">{{ __('pos.pending_empty') }}</p>
                @else
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($pendingSessions as $session)
                            @php
                                $snapshot = is_array($session->checkout_snapshot_json) ? $session->checkout_snapshot_json : [];
                                $frozenAmount = (int) ($session->checkout_amount_due_minor ?? data_get($snapshot, 'total_minor', 0));
                                $frozenCurrency = (string) data_get($snapshot, 'currency', $selectedBranch?->currency);
                            @endphp
                            <article class="rounded-[10px] border border-[var(--pn-border)] p-4" data-pending-session data-settle-url="{{ route('sessions.settle-cash', ['session' => $session->id]) }}" data-amount-minor="{{ $frozenAmount }}" data-currency="{{ $frozenCurrency }}" data-lock-version="{{ $session->lock_version }}">
                                <p class="font-semibold" dir="auto">{{ __('pos.pending_customer') }}: {{ $session->child?->full_name ?: __('pos.session_number', ['id' => $session->child_id]) }}</p>
                                <p class="mt-1 text-sm text-[var(--pn-ink-muted)]" dir="ltr">{{ __('pos.guardian_phone') }}: <bdi>{{ $session->guardian?->maskedPhone() ?: __('pos.not_available') }}</bdi></p>
                                <p class="mt-3 text-lg font-bold tabular-nums" dir="ltr">{{ __('pos.frozen_total') }}: <bdi>{{ $format($frozenAmount) }} {{ $frozenCurrency }}</bdi></p>
                                <p class="mt-1 text-xs text-[var(--pn-ink-muted)]">{{ __('pos.version', ['version' => $session->lock_version]) }}</p>
                                <p class="mt-3 hidden rounded-[10px] bg-[var(--pn-danger-soft)] p-3 text-sm text-[var(--pn-danger)]" data-settle-message role="alert"></p>
                                <p class="mt-3 hidden rounded-[10px] bg-[var(--pn-success-soft)] p-3 text-sm text-[var(--pn-success)]" data-settle-success role="status"></p>
                                <button class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-[10px] border-2 border-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-primary)] disabled:cursor-not-allowed disabled:opacity-50" type="button" data-settle>{{ __('pos.confirm_cash') }}</button>
                                <a class="mt-3 hidden min-h-11 items-center justify-center rounded-[10px] border border-[var(--pn-success)] px-4 text-sm font-semibold text-[var(--pn-success)]" data-settle-receipt target="_blank" rel="noopener">{{ __('pos.view_receipt') }}</a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="discount-approvals-heading">
                <div class="flex items-center justify-between gap-3 border-b border-[var(--pn-border)] pb-4"><div><h2 class="text-xl font-bold" id="discount-approvals-heading">{{ __('pos.discount_approvals') }}</h2><p class="mt-1 text-sm text-[var(--pn-ink-muted)]">{{ __('pos.discount_approvals_help') }}</p></div><span class="text-sm text-[var(--pn-ink-muted)]">{{ $selectedBranch?->name }}</span></div>
                @if ($discountApprovals->isEmpty())
                    <p class="mt-4 text-sm text-[var(--pn-ink-muted)]">{{ __('pos.discount_approvals_empty') }}</p>
                @else
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        @foreach ($discountApprovals as $approval)
                            @php
                                $expired = $approval->expires_at?->isPast() && in_array($approval->status, ['requested', 'approved'], true);
                                $status = $expired ? 'expired' : $approval->status;
                                $approvedTotal = max(1, (int) $approval->order->total_minor - (int) $approval->discount_minor);
                            @endphp
                            <article class="rounded-[10px] border border-[var(--pn-border)] p-4" data-approved-order data-order-id="{{ $approval->order_id }}" data-version="{{ $approval->expected_order_lock_version }}" data-amount-minor="{{ $approvedTotal }}" data-currency="{{ $approval->currency }}" data-approval-id="{{ $approval->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-bold">{{ __('pos.order_number', ['id' => $approval->order_id]) }}</p><span class="rounded-full bg-[var(--pn-primary-soft)] px-3 py-1 text-xs font-semibold">{{ __('pos.discount_status_'.$status) }}</span></div>
                                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm"><div><dt class="text-[var(--pn-ink-muted)]">{{ __('pos.discount_amount') }}</dt><dd class="font-bold" dir="ltr">{{ $format($approval->discount_minor) }} {{ $approval->currency }}</dd></div><div><dt class="text-[var(--pn-ink-muted)]">{{ __('pos.total_after_discount') }}</dt><dd class="font-bold" dir="ltr">{{ $format($approvedTotal) }} {{ $approval->currency }}</dd></div></dl>
                                <p class="mt-3 text-sm"><span class="font-semibold">{{ __('pos.requested_by') }}:</span> {{ $approval->requestedBy?->name }}</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm text-[var(--pn-ink-muted)]">{{ $approval->reason }}</p>
                                @if (!$expired && $approval->status === 'requested' && $approvalPolicy->approve($actor, $approval))
                                    <div class="mt-4 grid gap-2 sm:grid-cols-2"><form method="POST" action="{{ route('discount-approvals.approve', $approval) }}">@csrf<button class="min-h-11 w-full rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)]" type="submit">{{ __('pos.approve_discount') }}</button></form><form method="POST" action="{{ route('discount-approvals.reject', $approval) }}">@csrf<input class="min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3 text-sm" name="reason" required maxlength="500" placeholder="{{ __('pos.rejection_reason') }}"><button class="mt-2 min-h-11 w-full rounded-[10px] border border-[var(--pn-danger)] px-4 text-sm font-semibold text-[var(--pn-danger)]" type="submit">{{ __('pos.reject_discount') }}</button></form></div>
                                @elseif (!$expired && $approval->status === 'approved' && $approvalPolicy->consume($actor, $approval))
                                    <p class="mt-3 hidden rounded-[10px] bg-[var(--pn-danger-soft)] p-3 text-sm text-[var(--pn-danger)]" data-approved-message role="alert"></p>
                                    <button class="mt-4 min-h-11 w-full rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)]" type="button" data-pay-approved>{{ __('pos.pay_approved_order') }}</button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($selectedBranch && $manageableBranches->contains('id', $selectedBranch->id))
                <section class="mt-6 rounded-[14px] border border-[var(--pn-border)] bg-[var(--pn-surface)] p-5 sm:p-6" aria-labelledby="pos-manage-heading">
                    <h2 class="text-xl font-bold" id="pos-manage-heading">{{ __('pos.manage_catalog') }}</h2>
                    <form class="mt-4 grid gap-3 md:grid-cols-5" method="POST" action="{{ route('pos.products.store') }}">
                        @csrf <input name="branch_id" type="hidden" value="{{ $selectedBranch->id }}"><label class="text-sm font-semibold">{{ __('pos.sku') }}<input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" name="sku" required></label><label class="text-sm font-semibold">{{ __('pos.name') }}<input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" name="name" required></label><label class="text-sm font-semibold">{{ __('pos.type') }}<select class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" name="type">@foreach (['food_beverage', 'merchandise', 'play_add_on'] as $itemType)<option value="{{ $itemType }}">{{ __('pos.'.$itemType) }}</option>@endforeach</select></label><label class="text-sm font-semibold">{{ __('pos.price_egp') }}<input class="mt-1 min-h-11 w-full rounded-[10px] border border-[var(--pn-border)] px-3" name="price_egp" inputmode="decimal" required></label>@if ($canManageTenantWide)<label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input class="h-5 w-5" name="tenant_wide" value="1" type="checkbox">{{ __('pos.all_branches') }}</label>@endif<button class="min-h-11 self-end rounded-[10px] bg-[var(--pn-primary)] px-4 text-sm font-semibold text-[var(--pn-surface)]" type="submit">{{ __('pos.save_item') }}</button>
                    </form>
                        @if ($products->isNotEmpty())<div class="mt-5 divide-y divide-[var(--pn-border)] border-t border-[var(--pn-border)]">@foreach ($products as $product)<div class="flex flex-wrap items-center justify-between gap-3 py-3"><span><span class="font-semibold">{{ $product->name }}</span> @if ($product->branch_id === null)<span class="text-xs font-semibold text-[var(--pn-primary)]">{{ __('pos.all_branches') }}</span>@endif <span class="font-mono text-xs text-[var(--pn-ink-muted)]">{{ $product->sku }}</span></span><form method="POST" action="{{ route('pos.products.retire', $product->id) }}">@csrf<input name="branch_id" type="hidden" value="{{ $selectedBranch->id }}"><button class="min-h-11 rounded-[10px] border border-[var(--pn-danger)] px-3 text-sm font-semibold text-[var(--pn-danger)]" type="submit">{{ __('pos.retire') }}</button></form></div>@endforeach</div>@endif
                </section>
            @endif
        @endif
    </main>
    <script>
        (() => {
            const root = document.querySelector('[data-pn-pos]');
            if (!root) return;

            const orderPanel = root.querySelector('[data-cash-order]');
            const currency = @json($selectedBranch?->currency);
            const emptyCart = @json(__('pos.empty_cart'));
            const invalidItem = @json(__('pos.invalid_item'));
            const requestFailed = @json(__('pos.request_failed'));
            const processing = @json(__('pos.processing'));
            const orderCreated = @json(__('pos.order_created'));
            const paymentPosted = @json(__('pos.payment_posted'));
            const discountRequested = @json(__('pos.discount_requested'));
            const discountInvalid = @json(__('pos.discount_invalid'));
            const ticketDetailsRequired = @json(__('pos.ticket_details_required'));
            const removeLabel = @json(__('pos.remove'));
            const quantityLabel = @json(__('pos.quantity'));
            const cart = new Map();
            let quoteReady = false;
            let orderKey = null;
            let paymentKey = null;
            let orderState = null;
            const ticketFamily = root.querySelector('[data-ticket-family]');
            const serviceDate = root.querySelector('[data-service-date]');
            const ticketContext = root.querySelector('[data-ticket-context]');
            const ticketContextMessage = root.querySelector('[data-ticket-context-message]');
            const money = (minor, code = currency) => `${Math.floor(Math.max(0, Number(minor)) / 100).toLocaleString('en-US')}.${String(Math.max(0, Number(minor)) % 100).padStart(2, '0')} ${code || ''}`.trim();
            const element = (tag, className, text) => {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;

                return node;
            };
            const uuid = () => {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
                    const value = Math.floor(Math.random() * 16);
                    const nibble = char === 'x' ? value : (value & 0x3) | 0x8;
                    return nibble.toString(16);
                });
            };
            const csrf = () => root.querySelector('input[name="_token"]')?.value || '';
            const jsonRequest = async (url, options) => {
                try {
                    const response = await fetch(url, options);
                    const body = await response.text();
                    let data = {};
                    try { data = body ? JSON.parse(body) : {}; } catch (error) { data = {}; }
                    return { response, data };
                } catch (error) {
                    return { response: null, data: {} };
                }
            };
            const message = (node, text, visible = true) => {
                if (!node) return;
                node.textContent = text;
                node.classList.toggle('hidden', !visible);
            };
            const busy = (button, value) => {
                if (!button) return;
                if (!button.dataset.idleLabel) button.dataset.idleLabel = button.textContent;
                button.disabled = value;
                button.textContent = value ? processing : button.dataset.idleLabel;
            };
            const clearOrderSurface = () => {
                const frozen = orderPanel?.querySelector('[data-frozen-total]');
                const confirm = orderPanel?.querySelector('[data-confirm-payment]');
                const receipt = orderPanel?.querySelector('[data-receipt-link]');
                const discount = orderPanel?.querySelector('[data-discount-form]');
                frozen?.classList.add('hidden');
                confirm?.classList.add('hidden');
                if (confirm) confirm.disabled = true;
                receipt?.classList.add('hidden');
                discount?.classList.add('hidden');
                message(orderPanel?.querySelector('[data-order-message]'), '', false);
            };
            const invalidateOrder = () => {
                quoteReady = false;
                orderKey = null;
                paymentKey = null;
                orderState = null;
                clearOrderSurface();
            };
            const ticketFacts = () => {
                const hasTicket = [...cart.values()].some((line) => line.kind === 'ticket');
                const option = ticketFamily?.selectedOptions?.[0];
                const guardianId = option?.dataset.guardianId || '';
                const childId = option?.dataset.childId || '';
                const date = serviceDate?.value || '';

                return { hasTicket, guardianId, childId, serviceDate: date, complete: !hasTicket || (guardianId !== '' && childId !== '' && date !== '') };
            };
            const syncTicketContext = () => {
                const facts = ticketFacts();
                ticketContext?.classList.toggle('hidden', !facts.hasTicket);
                if (ticketFamily) ticketFamily.required = facts.hasTicket;
                if (serviceDate) serviceDate.required = facts.hasTicket;
                message(ticketContextMessage, facts.hasTicket && !facts.complete ? ticketDetailsRequired : '', facts.hasTicket && !facts.complete);
            };
            const hiddenInput = (name, value) => {
                const input = element('input');
                input.type = 'hidden';
                input.name = name;
                input.value = String(value);
                return input;
            };
            const draw = () => {
                const target = root.querySelector('[data-cart]');
                const hidden = root.querySelector('[data-hidden-lines]');
                const hiddenFacts = root.querySelector('[data-hidden-ticket-facts]');
                target.replaceChildren();
                hidden.replaceChildren();
                hiddenFacts?.replaceChildren();
                syncTicketContext();
                const facts = ticketFacts();
                if (facts.hasTicket && facts.complete) {
                    hiddenFacts?.append(hiddenInput('guardian_id', facts.guardianId), hiddenInput('child_id', facts.childId), hiddenInput('service_date', facts.serviceDate));
                }

                if (!cart.size) {
                    target.append(element('p', 'text-sm text-[var(--pn-ink-muted)]', emptyCart));
                } else {
                    [...cart.values()].forEach((line) => {
                        const row = element('div', 'flex items-center justify-between gap-2');
                        const name = element('span', 'min-w-0 truncate text-sm font-semibold', line.name);
                        const label = element('label', 'flex items-center gap-1 text-xs');
                        label.append(element('span', 'sr-only', quantityLabel));
                        const input = element('input', 'h-10 w-16 rounded-[10px] border border-[var(--pn-border)] px-2');
                        input.type = 'number';
                        input.min = '1';
                        input.max = '100';
                        input.value = String(line.quantity);
                        input.dataset.quantity = line.key;
                        const remove = element('button', 'min-h-10 rounded-[10px] border border-[var(--pn-border)] px-2 text-xs font-semibold text-[var(--pn-danger)]', removeLabel);
                        remove.type = 'button';
                        remove.dataset.remove = line.key;
                        remove.setAttribute('aria-label', `${removeLabel}: ${line.name}`);
                        label.append(input);
                        row.append(name, label, remove);
                        target.append(row);
                    });

                    [...cart.values()].forEach((line, i) => {
                        const field = line.kind === 'product' ? 'product_id' : 'ticket_type_id';
                        const item = element('input');
                        item.type = 'hidden';
                        item.name = `lines[${i}][${field}]`;
                        item.value = String(line.id);
                        const quantity = element('input');
                        quantity.type = 'hidden';
                        quantity.name = `lines[${i}][quantity]`;
                        quantity.value = String(line.quantity);
                        hidden.append(item, quantity);
                        if (line.kind === 'ticket' && facts.complete) {
                            hidden.append(
                                hiddenInput(`lines[${i}][guardian_id]`, facts.guardianId),
                                hiddenInput(`lines[${i}][child_id]`, facts.childId),
                                hiddenInput(`lines[${i}][service_date]`, facts.serviceDate),
                            );
                        }
                    });
                }

                const subtotal = [...cart.values()].reduce((sum, line) => sum + (line.price * line.quantity), 0);
                root.querySelector('[data-subtotal]').textContent = money(subtotal);
                root.querySelector('[data-tax]').textContent = money(0);
                root.querySelector('[data-total]').textContent = money(subtotal);
                const create = orderPanel?.querySelector('[data-create-order]');
                const quote = root.querySelector('[data-quote-submit]');
                if (quote) quote.disabled = !cart.size || !facts.complete;
                if (create) create.disabled = !quoteReady || !cart.size || !facts.complete || orderState !== null;
            };
            const orderItems = () => {
                const facts = ticketFacts();

                return [...cart.values()].map((line) => ({
                    item_kind: line.kind,
                    ...(line.kind === 'product' ? { product_id: Number(line.id) } : { ticket_type_id: Number(line.id), guardian_id: Number(facts.guardianId), child_id: Number(facts.childId), service_date: facts.serviceDate }),
                    quantity: line.quantity,
                }));
            };
            const createOrder = async () => {
                const facts = ticketFacts();
                if (!orderPanel || !quoteReady || !cart.size || !facts.complete) return;
                const button = orderPanel.querySelector('[data-create-order]');
                const notice = orderPanel.querySelector('[data-order-message]');
                orderKey ||= uuid();
                busy(button, true);
                message(notice, '', false);
                const { response, data } = await jsonRequest(orderPanel.dataset.orderUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'Idempotency-Key': orderKey },
                    body: JSON.stringify({ branch_id: Number(root.querySelector('input[name="branch_id"]')?.value), guardian_id: facts.hasTicket ? Number(facts.guardianId) : undefined, child_id: facts.hasTicket ? Number(facts.childId) : undefined, service_date: facts.hasTicket ? facts.serviceDate : undefined, items: orderItems(), idempotency_key: orderKey }),
                });
                if (!response || !response.ok) {
                    busy(button, false);
                    message(notice, data.message || requestFailed);
                    return;
                }
                orderState = { id: Number(data.order_id), version: Number(data.lock_version), amount: Number(data.total_minor), currency: data.currency || currency };
                const frozen = orderPanel.querySelector('[data-frozen-total]');
                const frozenValue = orderPanel.querySelector('[data-frozen-total-value]');
                frozenValue.textContent = money(orderState.amount, orderState.currency);
                frozen.classList.remove('hidden');
                message(notice, orderCreated);
                button.classList.add('hidden');
                paymentKey = null;
                const confirm = orderPanel.querySelector('[data-confirm-payment]');
                confirm.classList.remove('hidden');
                confirm.disabled = false;
                orderPanel.querySelector('[data-discount-form]')?.classList.remove('hidden');
            };
            const requestDiscount = async (form) => {
                if (!orderPanel || !orderState) return;
                const button = form.querySelector('[data-discount-submit]');
                const notice = form.querySelector('[data-discount-message]');
                const value = form.querySelector('[data-discount-amount]')?.value.trim() || '';
                const match = value.match(/^(\d{1,9})(?:\.(\d{1,2}))?$/);
                const discount = match ? (Number(match[1]) * 100) + Number((match[2] || '').padEnd(2, '0')) : 0;
                const reason = form.querySelector('[data-discount-reason]')?.value.trim() || '';
                if (discount < 1 || discount >= orderState.amount || !reason) {
                    message(notice, discountInvalid);
                    return;
                }
                busy(button, true);
                message(notice, '', false);
                const { response, data } = await jsonRequest(orderPanel.dataset.approvalUrl.replace('__ORDER__', String(orderState.id)), {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ expected_order_lock_version: orderState.version, discount_minor: discount, reason, payload: { items: orderItems().map((line) => ({ item_kind: line.item_kind, item_id: line.product_id || line.ticket_type_id, quantity: line.quantity, ...(line.item_kind === 'ticket' ? { guardian_id: line.guardian_id, child_id: line.child_id, service_date: line.service_date } : {}) })) } }),
                });
                if (!response || !response.ok) {
                    busy(button, false);
                    message(notice, data.message || requestFailed);
                    return;
                }
                message(orderPanel.querySelector('[data-order-message]'), discountRequested);
                form.classList.add('hidden');
                orderPanel.querySelector('[data-confirm-payment]').disabled = true;
            };
            const payOrder = async () => {
                if (!orderPanel || !orderState) return;
                const button = orderPanel.querySelector('[data-confirm-payment]');
                const notice = orderPanel.querySelector('[data-order-message]');
                paymentKey ||= uuid();
                busy(button, true);
                const { response, data } = await jsonRequest(orderPanel.dataset.paymentUrl.replace('__ORDER__', String(orderState.id)), {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'Idempotency-Key': paymentKey },
                    body: JSON.stringify({ expected_order_lock_version: orderState.version, amount_minor: orderState.amount, currency: orderState.currency, method: 'cash', idempotency_key: paymentKey }),
                });
                if (!response || !response.ok) {
                    busy(button, false);
                    message(notice, data.message || requestFailed);
                    return;
                }
                message(notice, paymentPosted);
                button.disabled = true;
                const receipt = orderPanel.querySelector('[data-receipt-link]');
                receipt.href = orderPanel.dataset.receiptUrl.replace('__ORDER__', String(data.order_id || orderState.id));
                receipt.classList.remove('hidden');
                receipt.classList.add('inline-flex');
            };
            const settleSession = async (card) => {
                const button = card.querySelector('[data-settle]');
                const error = card.querySelector('[data-settle-message]');
                const success = card.querySelector('[data-settle-success]');
                card.dataset.settleKey ||= uuid();
                busy(button, true);
                message(error, '', false);
                const { response, data } = await jsonRequest(card.dataset.settleUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'Idempotency-Key': card.dataset.settleKey },
                    body: JSON.stringify({ expected_lock_version: Number(card.dataset.lockVersion), amount_minor: Number(card.dataset.amountMinor), currency: card.dataset.currency, idempotency_key: card.dataset.settleKey }),
                });
                if (!response || !response.ok) {
                    busy(button, false);
                    message(error, data.message || requestFailed);
                    return;
                }
                message(success, paymentPosted);
                button.disabled = true;
                const receipt = card.querySelector('[data-settle-receipt]');
                receipt.href = orderPanel.dataset.receiptUrl.replace('__ORDER__', String(data.order_id));
                receipt.classList.remove('hidden');
                receipt.classList.add('inline-flex');
            };
            const payApprovedOrder = async (card) => {
                const button = card.querySelector('[data-pay-approved]');
                const notice = card.querySelector('[data-approved-message]');
                card.dataset.paymentKey ||= uuid();
                busy(button, true);
                message(notice, '', false);
                const { response, data } = await jsonRequest(orderPanel.dataset.paymentUrl.replace('__ORDER__', card.dataset.orderId), {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'Idempotency-Key': card.dataset.paymentKey },
                    body: JSON.stringify({ expected_order_lock_version: Number(card.dataset.version), amount_minor: Number(card.dataset.amountMinor), currency: card.dataset.currency, method: 'cash', approval_id: Number(card.dataset.approvalId), idempotency_key: card.dataset.paymentKey }),
                });
                if (!response || !response.ok) {
                    busy(button, false);
                    message(notice, data.message || requestFailed);
                    return;
                }
                window.location.assign(orderPanel.dataset.receiptUrl.replace('__ORDER__', String(data.order_id || card.dataset.orderId)));
            };
            root.addEventListener('click', async (event) => {
                const target = event.target instanceof Element ? event.target : null;
                const remove = target?.closest('[data-remove]');
                const add = target?.closest('[data-add]');
                const create = target?.closest('[data-create-order]');
                const confirm = target?.closest('[data-confirm-payment]');
                const settle = target?.closest('[data-settle]');
                const approved = target?.closest('[data-pay-approved]');
                if (remove) {
                    invalidateOrder();
                    cart.delete(remove.dataset.remove);
                    draw();
                } else if (add) {
                    invalidateOrder();
                    const key = `${add.dataset.kind}:${add.dataset.id}`;
                    const line = cart.get(key) || { key, kind: add.dataset.kind, id: add.dataset.id, name: add.dataset.name, price: Number(add.dataset.price), quantity: 0 };
                    line.quantity = Math.min(100, line.quantity + 1);
                    cart.set(key, line);
                    draw();
                } else if (create) {
                    await createOrder();
                } else if (confirm) {
                    await payOrder();
                } else if (settle) {
                    await settleSession(settle.closest('[data-pending-session]'));
                } else if (approved) {
                    await payApprovedOrder(approved.closest('[data-approved-order]'));
                }
            });
            root.addEventListener('change', (event) => {
                const ticketField = event.target instanceof Element ? event.target.closest('[data-ticket-family], [data-service-date]') : null;
                if (ticketField) {
                    invalidateOrder();
                    draw();
                    return;
                }
                const input = event.target instanceof Element ? event.target.closest('[data-quantity]') : null;
                if (!input) return;
                const line = cart.get(input.dataset.quantity);
                if (!line) return;
                invalidateOrder();
                line.quantity = Math.max(1, Math.min(100, Number(input.value) || 1));
                draw();
            });
            root.addEventListener('submit', async (event) => {
                const discountForm = event.target instanceof Element ? event.target.closest('[data-discount-form]') : null;
                if (discountForm) {
                    event.preventDefault();
                    await requestDiscount(discountForm);
                    return;
                }
                const form = event.target instanceof Element ? event.target.closest('[data-quote-form]') : null;
                if (!form) return;
                event.preventDefault();
                const facts = ticketFacts();
                if (!facts.complete) {
                    syncTicketContext();
                    message(root.querySelector('[data-quote-message]'), ticketDetailsRequired);
                    return;
                }
                invalidateOrder();
                const submit = form.querySelector('[data-quote-submit]');
                const notice = root.querySelector('[data-quote-message]');
                busy(submit, true);
                message(notice, '', false);
                const { response, data } = await jsonRequest(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) });
                busy(submit, false);
                if (!response || !response.ok) {
                    message(notice, data.message || invalidItem);
                    return;
                }
                root.querySelector('[data-subtotal]').textContent = money(data.subtotal_minor, data.currency || currency);
                root.querySelector('[data-tax]').textContent = money(data.tax_minor, data.currency || currency);
                root.querySelector('[data-total]').textContent = money(data.total_minor, data.currency || currency);
                quoteReady = true;
                draw();
            });
            draw();
        })();
    </script>
@endsection
