<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PricingRulePolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PricingRuleController extends Controller
{
    private const MAX_MONEY_MAJOR = 999999999;

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $policy = app(PricingRulePolicy::class);
        $branches = $actor->accessibleBranches()
            ->where('branches.tenant_id', $tenant->getKey())
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->orderBy('branches.id')
            ->get()
            ->filter(fn (Branch $branch): bool => $policy->viewBranch($actor, $branch))
            ->values();

        $rulesQuery = PricingRule::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereIn('branch_id', $branches->modelKeys());
        $selectedBranchId = $request->query('branch_id', $request->session()->get('branch_id'));
        if (is_string($selectedBranchId) && ctype_digit($selectedBranchId)) {
            $selectedBranchId = (int) $selectedBranchId;
            if ($branches->contains('id', $selectedBranchId)) {
                $rulesQuery->where('branch_id', $selectedBranchId);
            } else {
                $rulesQuery->whereRaw('1 = 0');
            }
        }
        $rules = $rulesQuery
            ->orderBy('branch_id')
            ->orderBy('code')
            ->orderByDesc('version')
            ->get();

        $manageableBranches = $branches
            ->filter(fn (Branch $branch): bool => $policy->canCreateBranch($actor, $branch))
            ->values();
        $canManage = $manageableBranches->isNotEmpty();

        return view('pricing.index', compact('actor', 'tenant', 'branches', 'manageableBranches', 'rules', 'canManage'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        [$actor, $tenant] = $this->authorizedContext($request);
        $this->normalizeInputs($request);
        $validator = $this->validator($request, $tenant);

        if ($validator->fails()) {
            return $this->validationResponse($request, $validator);
        }

        $data = $validator->validated();
        $rule = DB::transaction(function () use ($actor, $tenant, $data): ?PricingRule {
            $lockedTenant = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $lockedBranch = Branch::query()
                ->whereKey($data['branch_id'])
                ->where('tenant_id', $lockedTenant->getKey())
                ->lockForUpdate()
                ->first();

            abort_unless($lockedBranch && $lockedBranch->is_active, 404);
            $candidate = new PricingRule(['branch_id' => $lockedBranch->getKey()]);
            Gate::forUser($lockedActor)->authorize('create', $candidate);

            if (PricingRule::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('branch_id', $lockedBranch->getKey())
                ->where('code', $data['code'])
                ->exists()) {
                return null;
            }

            $rule = new PricingRule([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $lockedBranch->getKey(),
                'code' => $data['code'],
                'name' => $data['name'],
                'version' => 1,
                'billing_mode' => 'fixed_duration',
                'base_duration_seconds' => (int) $data['base_duration_minutes'] * 60,
                'base_price_minor' => $this->moneyToMinor($data['base_price_egp']),
                'grace_period_seconds' => 600,
                'overtime_unit_seconds' => 1800,
                'overtime_price_minor' => $this->moneyToMinor($data['overtime_price_egp']),
                'currency' => 'EGP',
                'tax_rate_bps' => $lockedBranch->tax_rate_bps,
                'tax_mode' => $lockedBranch->tax_mode,
                'status' => 'active',
                'created_by_user_id' => $lockedActor->getKey(),
            ]);
            $rule->save();

            DB::table('audit_logs')->insert([
                'tenant_id' => $lockedTenant->getKey(),
                'branch_id' => $lockedBranch->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'actor_type' => 'user',
                'action' => 'pricing.rule.created',
                'subject_type' => 'pricing_rule',
                'subject_id' => (string) $rule->getKey(),
                'outcome' => 'success',
                'reason_code' => 'setup_change',
                'before_json' => null,
                'after_json' => json_encode(['pricing_rule_id' => (string) $rule->getKey()], JSON_THROW_ON_ERROR),
                'request_id' => (string) Str::uuid(),
                'occurred_at' => now('UTC'),
            ]);

            return $rule;
        });

        if ($rule === null) {
            return $this->conflictResponse($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('pricing.created'),
                'pricing_rule_id' => $rule->getKey(),
            ], 201);
        }

        return to_route('pricing.index')->with('success', __('pricing.created'));
    }

    /** @return array{User, Tenant} */
    private function authorizedContext(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()
            ->whereKey($actor->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('viewAny', PricingRule::class);

        return [$actor, $tenant];
    }

    private function normalizeInputs(Request $request): void
    {
        foreach (['code', 'name', 'base_price_egp', 'overtime_price_egp'] as $key) {
            $value = $request->input($key);
            if (is_string($value)) {
                $request->merge([$key => trim($value)]);
            }
        }

        if (is_string($request->input('code'))) {
            $request->merge(['code' => Str::upper($request->input('code'))]);
        }
    }

    private function validator(Request $request, Tenant $tenant)
    {
        return Validator::make($request->all(), [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenant->getKey())
                    ->where('is_active', true)),
            ],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'base_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'base_price_egp' => ['required', 'string', $this->moneyRule()],
            'overtime_price_egp' => ['required', 'string', $this->moneyRule()],
        ], [
            'branch_id.required' => __('pricing.branch_required'),
            'branch_id.integer' => __('pricing.branch_unavailable'),
            'branch_id.exists' => __('pricing.branch_unavailable'),
            'code.required' => __('pricing.validation.code_required'),
            'code.string' => __('pricing.validation.code_invalid'),
            'code.max' => __('pricing.validation.code_invalid'),
            'code.regex' => __('pricing.validation.code_invalid'),
            'name.required' => __('pricing.validation.name_required'),
            'name.string' => __('pricing.validation.name_invalid'),
            'name.min' => __('pricing.validation.name_invalid'),
            'name.max' => __('pricing.validation.name_invalid'),
            'base_duration_minutes.required' => __('pricing.validation.duration_required'),
            'base_duration_minutes.integer' => __('pricing.validation.duration_invalid'),
            'base_duration_minutes.min' => __('pricing.validation.duration_invalid'),
            'base_duration_minutes.max' => __('pricing.validation.duration_invalid'),
            'base_price_egp.required' => __('pricing.validation.base_price_required'),
            'base_price_egp.string' => __('pricing.validation.base_price_invalid'),
            'overtime_price_egp.required' => __('pricing.validation.overtime_price_required'),
            'overtime_price_egp.string' => __('pricing.validation.overtime_price_invalid'),
        ]);
    }

    private function moneyRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || ! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $value)) {
                $fail($attribute === 'base_price_egp'
                    ? __('pricing.validation.base_price_invalid')
                    : __('pricing.validation.overtime_price_invalid'));

                return;
            }

            $whole = (int) explode('.', $value, 2)[0];
            if ($whole > self::MAX_MONEY_MAJOR) {
                $fail($attribute === 'base_price_egp'
                    ? __('pricing.validation.base_price_invalid')
                    : __('pricing.validation.overtime_price_invalid'));
            }
        };
    }

    private function moneyToMinor(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function validationResponse(Request $request, $validator): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('pricing.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        return to_route('pricing.index')->withErrors($validator)->withInput();
    }

    private function conflictResponse(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('pricing.conflict')], 409);
        }

        return to_route('pricing.index')
            ->withErrors(['code' => __('pricing.conflict')])
            ->withInput();
    }
}
