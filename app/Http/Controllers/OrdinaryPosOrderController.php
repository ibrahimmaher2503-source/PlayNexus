<?php

namespace App\Http\Controllers;

use App\Actions\ProcessOrdinaryPosOrder;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OrdinaryPosOrderController extends Controller
{
    public function store(Request $request, ProcessOrdinaryPosOrder $action): JsonResponse
    {
        [$actor, $tenant] = $this->context($request);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $this->normalizeItems($request);
        $data = Validator::make($request->all(), $this->orderRules($tenant))->validate();
        $data['items'] = array_values($data['items']);

        $result = $action->create($actor, $tenant, $data, $this->requestId($request));

        return response()->json($result, $result['created'] ? 201 : 200)
            ->header('Idempotent-Replayed', $result['created'] ? 'false' : 'true');
    }

    public function pay(Request $request, int $order, ProcessOrdinaryPosOrder $action): JsonResponse
    {
        [$actor, $tenant] = $this->context($request);
        $target = Order::query()->where('tenant_id', $tenant->id)->whereKey($order)->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->whereKey($target->branch_id)->where('is_active', true)->first();
        abort_unless($branch && $actor->accessibleBranches()->whereKey($branch->id)->exists(), 404);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key'))]);
        $data = Validator::make($request->all(), [
            'expected_order_lock_version' => ['required', 'integer', 'min:1'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Za-z]{3}\z/'],
            'method' => ['nullable', Rule::in(['cash'])],
            'idempotency_key' => ['required', 'uuid'],
            'approval_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $data['currency'] = strtoupper((string) $data['currency']);

        $result = $action->pay($actor, $tenant, $target, $data, $this->requestId($request));

        return response()->json($result, $result['created'] ? 201 : 200)
            ->header('Idempotent-Replayed', $result['created'] ? 'false' : 'true');
    }

    /** @return array<string, mixed> */
    private function orderRules(Tenant $tenant): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'uuid'],
            'guardian_id' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array'],
            'items.*.item_kind' => ['nullable', Rule::in(['product', 'ticket'])],
            'items.*.product_id' => ['nullable', 'integer', 'min:1'],
            'items.*.ticket_type_id' => ['nullable', 'integer', 'min:1'],
            'items.*.item_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.guardian_id' => ['nullable', 'integer', 'min:1'],
            'items.*.child_id' => ['nullable', 'integer', 'min:1'],
            'items.*.service_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.unit_price_minor' => ['prohibited'],
            'items.*.tax_minor' => ['prohibited'],
            'items.*.line_total_minor' => ['prohibited'],
            'items.*.currency' => ['prohibited'],
            'discount_minor' => ['prohibited'],
            'discount_reason' => ['prohibited'],
            'approval_id' => ['prohibited'],
        ];
    }

    private function normalizeItems(Request $request): void
    {
        $items = $request->input('items');
        if (! is_array($items) && is_array($request->input('lines'))) {
            $items = $request->input('lines');
        }
        if (! is_array($items)) {
            return;
        }
        $guardianId = $request->input('guardian_id');
        $childId = $request->input('child_id');
        $serviceDate = $request->input('service_date');
        $items = array_map(static function (mixed $item) use ($guardianId, $childId, $serviceDate): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (! isset($item['item_kind'])) {
                $item['item_kind'] = isset($item['product_id']) ? 'product' : (isset($item['ticket_type_id']) ? 'ticket' : null);
            }
            if (isset($item['item_id']) && ! isset($item[$item['item_kind'].'_id'])) {
                $item[$item['item_kind'].'_id'] = $item['item_id'];
            }
            if ($item['item_kind'] === 'ticket') {
                $item['guardian_id'] ??= $guardianId;
                $item['child_id'] ??= $childId;
                $item['service_date'] ??= $serviceDate;
            }

            return $item;
        }, $items);
        $request->merge(['items' => $items]);
    }

    /** @return array{User, Tenant} */
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
}
