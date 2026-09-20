<?php

namespace App\Http\Controllers;

use App\Actions\SettlePendingSession;
use App\Models\Branch;
use App\Models\PlaySession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CashSettlementController extends Controller
{
    public function store(Request $request, PlaySession $session, SettlePendingSession $settle): JsonResponse
    {
        $actor = User::query()->findOrFail($request->user()->getAuthIdentifier());
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        abort_unless((int) $session->tenant_id === (int) $tenant->getKey(), 404);
        $branch = Branch::query()
            ->whereKey($session->branch_id)
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->first();
        abort_if($branch === null || ! $actor->accessibleBranches()->whereKey($branch->getKey())->exists(), 404);
        Gate::forUser($actor)->authorize('settle', $session);

        $request->merge([
            'idempotency_key' => $request->header('Idempotency-Key', $request->input('idempotency_key')),
        ]);
        $data = Validator::make($request->all(), [
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Za-z]{3}\z/'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        $result = $settle->handle(
            $actor,
            $tenant,
            $session,
            (int) $data['expected_lock_version'],
            (int) $data['amount_minor'],
            strtoupper((string) $data['currency']),
            (string) $data['idempotency_key'],
            (string) $request->attributes->get('request_id', Str::uuid()),
        );

        return response()->json($result, $result['created'] ? 201 : 200)
            ->header('Idempotent-Replayed', $result['created'] ? 'false' : 'true');
    }
}
