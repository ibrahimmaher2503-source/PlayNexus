<?php

namespace App\Http\Controllers;

use App\Actions\ProcessCashRefund;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CashRefundController extends Controller
{
    public function request(Request $request, int $order, ProcessCashRefund $action): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        $order = Order::query()->whereKey($order)->where('tenant_id', $tenant->getKey())->firstOrFail();
        $this->visibleBranch($actor, $tenant, $order->branch_id);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $data = Validator::make($request->all(), [
            'expected_order_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:1', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $result = $action->request($actor, $tenant, $order, (int) $data['expected_order_lock_version'], trim($data['reason']), $data['idempotency_key'], $this->requestId($request));

        if ($request->expectsJson()) {
            return response()->json($result, $result['created'] ? 201 : 200);
        }

        return to_route('receipts.show', $order)->with('success', __('transactions.refund_requested'));
    }

    public function approve(Request $request, int $refund, ProcessCashRefund $action): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        $refund = Refund::query()->whereKey($refund)->where('tenant_id', $tenant->getKey())->firstOrFail();
        $this->visibleBranch($actor, $tenant, $refund->branch_id);

        $result = $action->approve($actor, $tenant, $refund, $this->requestId($request));
        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return to_route('receipts.show', $refund->order_id)->with('success', __('transactions.refund_approved'));
    }

    public function execute(Request $request, int $refund, ProcessCashRefund $action): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        $refund = Refund::query()->whereKey($refund)->where('tenant_id', $tenant->getKey())->firstOrFail();
        $this->visibleBranch($actor, $tenant, $refund->branch_id);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $data = Validator::make($request->all(), [
            'expected_order_lock_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        $result = $action->execute($actor, $tenant, $refund, (int) $data['expected_order_lock_version'], $data['idempotency_key'], $this->requestId($request));
        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return to_route('receipts.show', $refund->order_id)->with('success', __('transactions.refund_refunded'));
    }

    private function context(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();

        return [$actor, $tenant];
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', Str::uuid());
    }

    private function visibleBranch(User $actor, Tenant $tenant, int $branchId): void
    {
        Branch::query()->whereKey($branchId)->where('tenant_id', $tenant->getKey())->where('is_active', true)->firstOrFail();
        abort_unless($actor->accessibleBranches()->whereKey($branchId)->exists(), 404);
    }
}
