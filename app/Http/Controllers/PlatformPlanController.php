<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanStatusRequest;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlatformPlanController extends Controller
{
    public function index(): View
    {
        return view('platform.plans.index', [
            'plans' => Plan::query()->orderBy('status')->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $plan = DB::transaction(function () use ($actor, $data): Plan {
            $plan = new Plan;
            $plan->forceFill($this->planPayload($data, $data['status'] ?? 'active'))->save();
            $this->audit($actor->getKey(), 'plan.created', $plan, null, $this->snapshot($plan), $data['reason']);

            return $plan;
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->snapshot($plan)], 201);
        }

        return to_route('platform.plans.index')->with('success', __('platform.plans.created'));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $updated = DB::transaction(function () use ($actor, $data, $plan): Plan {
            $locked = Plan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            $locked->forceFill($this->planPayload($data, (string) $locked->getAttribute('status')))->save();
            $this->audit($actor->getKey(), 'plan.updated', $locked, $before, $this->snapshot($locked), $data['reason']);

            return $locked;
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->snapshot($updated)]);
        }

        return to_route('platform.plans.index')->with('success', __('platform.plans.updated'));
    }

    public function updateStatus(PlanStatusRequest $request, Plan $plan): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $updated = DB::transaction(function () use ($actor, $data, $plan): array {
            $locked = Plan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $locked->getAttribute('status') !== $data['expected_status']) {
                throw new HttpException(409, __('platform.plans.conflict'));
            }

            if ((string) $locked->getAttribute('status') === $data['status']) {
                return [$locked, false];
            }

            $before = $this->snapshot($locked);
            $locked->forceFill(['status' => $data['status']])->save();
            $this->audit($actor->getKey(), 'plan.status.changed', $locked, $before, $this->snapshot($locked), $data['reason']);

            return [$locked, true];
        });

        [$updatedPlan, $changed] = $updated;
        if ($request->expectsJson()) {
            return response()->json(['data' => $this->snapshot($updatedPlan), 'changed' => $changed]);
        }

        return to_route('platform.plans.index')->with('success', __($changed ? 'platform.plans.status_updated' : 'platform.plans.no_change'));
    }

    /** @return array<string, mixed> */
    private function planPayload(array $data, string $status): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $status,
            'limits_json' => $data['limits'],
            'features_json' => [],
            'monthly_price_minor' => (int) $data['monthly_price_minor'],
            'annual_price_minor' => (int) $data['annual_price_minor'],
            'annual_discount_bps' => (int) ($data['annual_discount_bps'] ?? 0),
            'currency' => 'EGP',
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(Plan $plan): array
    {
        return [
            'id' => $plan->getKey(),
            'code' => $plan->getAttribute('code'),
            'name' => $plan->getAttribute('name'),
            'status' => $plan->getAttribute('status'),
            'limits' => $plan->getAttribute('limits_json') ?? [],
            'description' => $plan->getAttribute('description'),
            'monthly_price_minor' => (int) $plan->getAttribute('monthly_price_minor'),
            'annual_price_minor' => (int) $plan->getAttribute('annual_price_minor'),
            'annual_discount_bps' => (int) $plan->getAttribute('annual_discount_bps'),
            'currency' => $plan->getAttribute('currency') ?? 'EGP',
        ];
    }

    private function audit(int $actorId, string $action, Plan $plan, ?array $before, array $after, string $reason): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_user_id' => $actorId,
            'target_tenant_id' => null,
            'action' => $action,
            'subject_type' => 'plan',
            'subject_id' => (string) $plan->getKey(),
            'outcome' => 'success',
            'reason_code' => $reason,
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now('UTC'),
        ]);
    }
}
