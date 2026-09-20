<?php

namespace App\Http\Controllers;

use App\Actions\DiscountApprovalAction;
use App\Models\ApprovalRecord;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ApprovalRecordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DiscountApprovalController extends Controller
{
    public function request(Request $request, int $order, DiscountApprovalAction $action): JsonResponse
    {
        [$actor, $tenant] = $this->context($request);
        $order = Order::query()->whereKey($order)->where('tenant_id', $tenant->id)->firstOrFail();
        $this->branch($actor, $tenant, $order->branch_id);
        abort_unless(app(ApprovalRecordPolicy::class)->request($actor, $order), 403);
        $request->merge(['reason' => is_string($request->input('reason')) ? trim($request->input('reason')) : $request->input('reason')]);
        $data = Validator::make($request->all(), [
            'expected_order_lock_version' => ['required', 'integer', 'min:1'],
            'discount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:1', 'max:500'],
            'payload' => ['required', 'array'],
        ])->validate();

        return response()->json($action->request($actor, $tenant, $order, (int) $data['expected_order_lock_version'], (int) $data['discount_minor'], $data['payload'], trim($data['reason']), $this->requestId($request)), 201);
    }

    public function approve(Request $request, int $approval, DiscountApprovalAction $action): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        $record = ApprovalRecord::query()->whereKey($approval)->where('tenant_id', $tenant->id)->firstOrFail();
        $this->branch($actor, $tenant, $record->branch_id);

        $result = $action->approve($actor, $tenant, $record, $this->requestId($request));

        return $request->expectsJson()
            ? response()->json($result)
            : to_route('pos.index', ['branch_id' => $record->branch_id])->with('success', __('pos.discount_approved'));
    }

    public function reject(Request $request, int $approval, DiscountApprovalAction $action): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->context($request);
        $record = ApprovalRecord::query()->whereKey($approval)->where('tenant_id', $tenant->id)->firstOrFail();
        $this->branch($actor, $tenant, $record->branch_id);
        $request->merge(['reason' => is_string($request->input('reason')) ? trim($request->input('reason')) : $request->input('reason')]);
        $data = Validator::make($request->all(), ['reason' => ['required', 'string', 'min:1', 'max:500']])->validate();

        $result = $action->reject($actor, $tenant, $record, trim($data['reason']), $this->requestId($request));

        return $request->expectsJson()
            ? response()->json($result)
            : to_route('pos.index', ['branch_id' => $record->branch_id])->with('success', __('pos.discount_rejected'));
    }

    public function consume(Request $request, int $approval): JsonResponse
    {
        throw new HttpException(409, 'Discount approvals are consumed only by cash payment posting.');
    }

    /** @return array{User, Tenant} */
    private function context(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());

        return [$actor, Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail()];
    }

    private function branch(User $actor, Tenant $tenant, int $branchId): Branch
    {
        $branch = Branch::query()->whereKey($branchId)->where('tenant_id', $tenant->id)->where('is_active', true)->firstOrFail();
        abort_unless($actor->accessibleBranches()->whereKey($branch->id)->exists(), 404);

        return $branch;
    }

    private function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', Str::uuid());
    }
}
