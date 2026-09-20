<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRecord;
use App\Models\Branch;
use App\Models\PlaySession;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Policies\ApprovalRecordPolicy;
use App\Policies\PlaySessionPolicy;
use App\Policies\PosPolicy;
use App\Support\PhoneNormalizer;
use App\Support\TicketEligibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    private const TYPES = ['food_beverage', 'merchandise', 'play_add_on'];

    public function index(Request $request): View
    {
        [$actor, $tenant] = $this->context($request);
        $policy = app(PosPolicy::class);
        abort_unless($policy->viewAny($actor), 403);
        $branches = $this->branches($actor, $tenant, $policy);
        $selectedBranch = $this->selectedBranch($request, $branches);
        $search = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');

        $products = Product::query()->where('tenant_id', $tenant->getKey())->where('status', 'active')
            ->when($selectedBranch, fn (Builder $query) => $query->where(function (Builder $query) use ($selectedBranch): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $selectedBranch->getKey());
            }), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%');
            }))
            ->when(in_array($type, self::TYPES, true), fn (Builder $query) => $query->where('type', $type))
            ->orderBy('name')->get();
        $ticketTypes = $selectedBranch ? TicketType::query()->with('pricingRule:id,tax_rate_bps,tax_mode,currency,status')
            ->where('tenant_id', $tenant->getKey())->where('branch_id', $selectedBranch->getKey())->where('status', 'active')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            }))->orderBy('name')->get() : collect();
        $manageableBranches = $branches->filter(fn (Branch $branch): bool => $policy->manage($actor, $branch))->values();
        $canManageTenantWide = $selectedBranch && $policy->manage($actor, new Product(['tenant_id' => $tenant->getKey(), 'branch_id' => null]), $selectedBranch);
        $canTransact = $selectedBranch && $policy->createOrder($actor, $selectedBranch);
        $canRequestDiscount = $selectedBranch && DB::table('branch_user')->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $selectedBranch->getKey())->where('user_id', $actor->getKey())
            ->where('role', 'cashier')->where('is_active', true)->exists();
        $ticketFamilies = $canTransact ? $this->ticketFamilies($tenant) : collect();
        $serviceDateMin = $selectedBranch?->timezone ? now($selectedBranch->timezone)->toDateString() : null;
        $pendingSessions = $this->pendingSessions($actor, $tenant, $selectedBranch);
        $discountApprovals = $canTransact ? $this->discountApprovals($tenant, $selectedBranch) : collect();
        $approvalPolicy = app(ApprovalRecordPolicy::class);

        return view('pos.index', compact('actor', 'tenant', 'branches', 'selectedBranch', 'products', 'ticketTypes', 'ticketFamilies', 'serviceDateMin', 'manageableBranches', 'canManageTenantWide', 'canTransact', 'canRequestDiscount', 'pendingSessions', 'discountApprovals', 'approvalPolicy', 'search', 'type'));
    }

    public function storeProduct(Request $request): RedirectResponse|JsonResponse
    {
        [$actor, $tenant] = $this->context($request);
        $data = Validator::make($request->all(), [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->getKey())->where('is_active', true))],
            'sku' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9-]+\z/'],
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'type' => ['required', Rule::in(self::TYPES)],
            'price_egp' => ['required', 'string', 'regex:/\A\d{1,9}(?:\.\d{1,2})?\z/'],
            'tenant_wide' => ['sometimes', 'boolean'],
        ])->validate();
        $branch = Branch::query()->whereKey($data['branch_id'])->where('tenant_id', $tenant->getKey())->where('is_active', true)->firstOrFail();
        $tenantWide = (bool) ($data['tenant_wide'] ?? false);
        $candidate = new Product(['tenant_id' => $tenant->getKey(), 'branch_id' => $tenantWide ? null : $branch->getKey()]);
        abort_unless(app(PosPolicy::class)->manage($actor, $candidate, $branch), 403);
        $sku = strtoupper(trim($data['sku']));
        $product = DB::transaction(function () use ($actor, $tenant, $branch, $data, $sku, $tenantWide): ?Product {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
            $lockedBranch = Branch::query()->whereKey($branch->getKey())->where('tenant_id', $lockedTenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
            $candidate = new Product(['tenant_id' => $lockedTenant->getKey(), 'branch_id' => $tenantWide ? null : $lockedBranch->getKey()]);
            abort_unless(app(PosPolicy::class)->manage($actor, $candidate, $lockedBranch), 403);
            $exists = Product::query()->where('tenant_id', $tenant->getKey())->whereRaw('UPPER(sku) = ?', [$sku])
                ->where(function (Builder $query) use ($lockedBranch, $tenantWide): void {
                    $query->when($tenantWide, fn (Builder $query) => $query->whereNull('branch_id'), fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $lockedBranch->getKey()));
                })->exists();
            if ($exists) {
                return null;
            }
            $product = Product::query()->create([
                'tenant_id' => $lockedTenant->getKey(), 'branch_id' => $tenantWide ? null : $lockedBranch->getKey(), 'sku' => $sku,
                'name' => trim($data['name']), 'type' => $data['type'], 'price_minor' => $this->moneyToMinor($data['price_egp']),
                'currency' => $lockedBranch->currency, 'tax_rate_bps' => $lockedBranch->tax_rate_bps, 'tax_mode' => $lockedBranch->tax_mode, 'status' => 'active',
            ]);
            $this->audit($lockedTenant, $lockedBranch, $actor, 'product.created', $product);

            return $product;
        });

        if (! $product) {
            return $this->failed($request, 'sku', __('pos.validation_failed'), 409);
        }

        if ($request->expectsJson()) {
            return response()->json(['product_id' => $product->getKey(), 'message' => __('pos.created')], 201);
        }

        return to_route('pos.index', ['branch_id' => $branch->getKey()])->with('success', __('pos.created'));
    }

    public function retireProduct(Request $request, int $product): RedirectResponse|JsonResponse
    {
        [$actor, $tenant] = $this->context($request);
        $branches = $this->branches($actor, $tenant, app(PosPolicy::class));
        $branchId = $request->input('branch_id', $request->session()->get('branch_id'));
        $contextBranch = $branchId === null
            ? $branches->first()
            : $branches->firstWhere('id', (int) $branchId);
        abort_unless($contextBranch, 404);
        $target = Product::query()->where('tenant_id', $tenant->getKey())->whereKey($product)->where('status', 'active')->firstOrFail();
        abort_unless(app(PosPolicy::class)->manage($actor, $target, $contextBranch), 403);
        DB::transaction(function () use ($actor, $tenant, $target, $contextBranch): void {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
            $lockedContextBranch = Branch::query()->whereKey($contextBranch->getKey())->where('tenant_id', $lockedTenant->getKey())->where('is_active', true)->lockForUpdate()->firstOrFail();
            $locked = Product::query()->where('tenant_id', $lockedTenant->getKey())->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                return;
            }
            abort_unless(app(PosPolicy::class)->manage($actor, $locked, $lockedContextBranch), 403);
            $locked->forceFill(['status' => 'retired', 'archived_at' => now('UTC'), 'lock_version' => $locked->lock_version + 1])->save();
            $this->audit($lockedTenant, $locked->branch()->first() ?: $lockedContextBranch, $actor, 'product.retired', $locked);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('pos.retired')]);
        }

        return to_route('pos.index', ['branch_id' => $contextBranch->getKey()])->with('success', __('pos.retired'));
    }

    public function quote(Request $request): JsonResponse
    {
        [$actor, $tenant] = $this->context($request);
        abort_unless(app(PosPolicy::class)->viewAny($actor), 403);
        $data = Validator::make($request->all(), [
            'branch_id' => ['required', 'integer'],
            'guardian_id' => ['nullable', 'integer', 'min:1'],
            'child_id' => ['nullable', 'integer', 'min:1'],
            'service_date' => ['nullable', 'date_format:Y-m-d'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['nullable', 'integer', 'required_without:lines.*.ticket_type_id'],
            'lines.*.ticket_type_id' => ['nullable', 'integer', 'required_without:lines.*.product_id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'lines.*.guardian_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.child_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.service_date' => ['nullable', 'date_format:Y-m-d'],
            'lines.*.unit_price_minor' => ['prohibited'],
            'lines.*.tax_minor' => ['prohibited'],
            'lines.*.currency' => ['prohibited'],
        ])->validate();
        $branch = $this->branches($actor, $tenant, app(PosPolicy::class))->firstWhere('id', (int) $data['branch_id']);
        abort_unless($branch, 404);
        $lines = [];
        foreach ($data['lines'] as $line) {
            $inputLine = $line;
            $hasProduct = isset($line['product_id']) && $line['product_id'] !== null;
            $item = $hasProduct
                ? Product::query()->where('tenant_id', $tenant->getKey())->where(function (Builder $query) use ($branch): void {
                    $query->whereNull('branch_id')->orWhere('branch_id', $branch->getKey());
                })->where('status', 'active')->whereKey($line['product_id'])->first()
                : TicketType::query()->with('pricingRule')->where('tenant_id', $tenant->getKey())->where('branch_id', $branch->getKey())->where('status', 'active')->whereHas('pricingRule', fn (Builder $query) => $query->where('tenant_id', $tenant->getKey())->where('branch_id', $branch->getKey())->where('status', 'active'))->whereKey($line['ticket_type_id'])->first();
            if (! $item) {
                return response()->json(['message' => __('pos.invalid_item')], 409);
            }
            $quantity = (int) $line['quantity'];
            $rate = $hasProduct ? (int) $item->tax_rate_bps : (int) ($item->pricingRule?->tax_rate_bps ?? 0);
            $mode = $hasProduct ? $item->tax_mode : ($item->pricingRule?->tax_mode ?? 'exclusive');
            $currency = $hasProduct ? $item->currency : ($item->pricingRule?->currency ?? $item->currency);
            if ($currency !== $branch->currency || (! $hasProduct && $item->currency !== $branch->currency)) {
                return response()->json(['message' => __('pos.currency_mismatch')], 409);
            }
            $unit = (int) $item->price_minor;
            $base = $unit * $quantity;
            $tax = $mode === 'inclusive' ? intdiv($base * $rate + intdiv(10000 + $rate, 2), 10000 + $rate) : intdiv($base * $rate + 5000, 10000);
            $line = ['item_kind' => $hasProduct ? 'product' : 'ticket', 'item_id' => $item->getKey(), 'description' => $item->name, 'quantity' => $quantity, 'unit_price_minor' => $unit, 'tax_minor' => $tax, 'line_total_minor' => $mode === 'inclusive' ? $base : $base + $tax, 'currency' => $currency];
            if (! $hasProduct) {
                $guardianId = (int) ($inputLine['guardian_id'] ?? $data['guardian_id'] ?? 0);
                $childId = (int) ($inputLine['child_id'] ?? $data['child_id'] ?? 0);
                $serviceDate = $inputLine['service_date'] ?? $data['service_date'] ?? null;
                if ($guardianId < 1 || $childId < 1 || ! is_string($serviceDate) || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $serviceDate)) {
                    return response()->json(['message' => __('pos.ticket_details_required')], 409);
                }
                if (($data['guardian_id'] ?? null) !== null && (int) $data['guardian_id'] !== $guardianId) {
                    return response()->json(['message' => __('pos.ticket_family_unavailable')], 409);
                }
                $eligible = TicketEligibility::familyQuery($tenant)
                    ->where('guardian_child.guardian_id', $guardianId)
                    ->where('guardian_child.child_id', $childId)
                    ->exists();
                if (! $eligible) {
                    return response()->json(['message' => __('pos.ticket_family_unavailable')], 409);
                }
                $line += ['guardian_id' => $guardianId, 'child_id' => $childId, 'service_date' => $serviceDate];
            }
            $lines[] = $line;
        }
        $subtotal = array_sum(array_map(fn (array $line): int => $line['unit_price_minor'] * $line['quantity'], $lines));
        $tax = array_sum(array_column($lines, 'tax_minor'));
        $total = array_sum(array_column($lines, 'line_total_minor'));

        return response()->json(['currency' => $branch->currency, 'lines' => $lines, 'subtotal_minor' => $subtotal, 'discount_minor' => 0, 'tax_minor' => $tax, 'total_minor' => $total, 'payment_method' => 'cash', 'status' => 'draft']);
    }

    /** @return array{User, Tenant} */
    private function context(Request $request): array
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();

        return [$actor, $tenant];
    }

    private function branches(User $actor, Tenant $tenant, PosPolicy $policy)
    {
        return $actor->accessibleBranches()->where('branches.tenant_id', $tenant->getKey())->where('branches.is_active', true)->orderBy('branches.name')->get()->filter(fn (Branch $branch): bool => $policy->viewBranch($actor, $branch))->values();
    }

    private function ticketFamilies(Tenant $tenant)
    {
        return TicketEligibility::familyQuery($tenant)
            ->select([
                'guardian_child.guardian_id', 'guardian_child.child_id',
                'guardians.full_name as guardian_name', 'guardians.phone_e164',
                'children.full_name as child_name',
            ])
            ->orderBy('guardians.full_name')->orderBy('children.full_name')->get()
            ->map(fn (object $family): array => [
                'guardian_id' => (int) $family->guardian_id,
                'child_id' => (int) $family->child_id,
                'guardian_name' => (string) $family->guardian_name,
                'child_name' => (string) $family->child_name,
                'guardian_phone' => PhoneNormalizer::mask((string) $family->phone_e164),
            ]);
    }

    private function selectedBranch(Request $request, $branches): ?Branch
    {
        $id = $request->query('branch_id', $request->session()->get('branch_id'));

        return $branches->firstWhere('id', (int) $id) ?: $branches->first();
    }

    private function pendingSessions(User $actor, Tenant $tenant, ?Branch $branch)
    {
        if ($branch === null) {
            return collect();
        }

        return PlaySession::query()
            ->with([
                'child:id,tenant_id,full_name',
                'guardian:id,tenant_id,full_name,phone_e164',
            ])
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->where('status', 'pending_payment')
            ->orderBy('checkout_prepared_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (PlaySession $session): bool => app(PlaySessionPolicy::class)->settle($actor, $session))
            ->values();
    }

    private function discountApprovals(Tenant $tenant, ?Branch $branch)
    {
        if ($branch === null) {
            return collect();
        }

        return ApprovalRecord::query()
            ->with(['order.items', 'requestedBy:id,name', 'approvedBy:id,name'])
            ->where('tenant_id', $tenant->getKey())
            ->where('branch_id', $branch->getKey())
            ->whereIn('status', ['requested', 'approved', 'rejected'])
            ->latest('id')
            ->limit(20)
            ->get();
    }

    private function moneyToMinor(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function failed(Request $request, string $field, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], $status);
        }

        return to_route('pos.index')->withErrors([$field => $message])->withInput();
    }

    private function audit(Tenant $tenant, Branch $branch, User $actor, string $action, Product $product): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'actor_user_id' => $actor->getKey(), 'actor_type' => 'user',
            'action' => $action, 'subject_type' => 'product', 'subject_id' => (string) $product->getKey(), 'outcome' => 'success', 'reason_code' => 'catalog_change',
            'before_json' => null, 'after_json' => json_encode(['product_id' => (string) $product->getKey(), 'status' => $product->status], JSON_THROW_ON_ERROR), 'request_id' => (string) str()->uuid(), 'occurred_at' => now('UTC'),
        ]);
    }
}
