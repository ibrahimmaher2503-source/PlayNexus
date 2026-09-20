<?php

namespace App\Http\Controllers;

use App\Models\NotificationMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OperationalNotificationController extends Controller
{
    public function index(Request $request)
    {
        $actor = User::query()->whereKey($request->user()->getAuthIdentifier())->where('status', 'active')->firstOrFail();
        $tenant = Tenant::query()->whereKey($actor->tenant_id)->where('is_active', true)->firstOrFail();
        $owner = DB::table('tenant_owners')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)->exists();
        $assignments = DB::table('branch_user')->where('tenant_id', $tenant->id)->where('user_id', $actor->id)
            ->where('is_active', true)->whereIn('role', ['branch_manager', 'reception_staff', 'reception', 'cashier'])->get(['branch_id', 'role']);
        abort_unless($owner || $assignments->isNotEmpty(), 403);
        $availablePurposes = $owner || $assignments->contains('role', 'branch_manager')
            ? ['session_ending', 'receipt']
            : array_values(array_unique($assignments->map(fn ($row) => $row->role === 'cashier' ? 'receipt' : 'session_ending')->all()));
        $branches = $actor->accessibleBranches()->where('branches.tenant_id', $tenant->id)
            ->when(! $owner, fn ($query) => $query->whereIn('branches.id', $assignments->pluck('branch_id')))
            ->orderBy('branches.name')->get();
        $filters = Validator::make($request->query(), [
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'purpose' => ['nullable', Rule::in($availablePurposes)],
            'status' => ['nullable', Rule::in(['queued', 'sent', 'delivered', 'failed_retryable', 'failed_permanent', 'stale'])],
        ])->validate();
        $branchIds = $branches->pluck('id');
        if (isset($filters['branch_id'])) {
            abort_unless($branchIds->contains((int) $filters['branch_id']), 404);
            $branchIds = collect([(int) $filters['branch_id']]);
        }
        $messages = NotificationMessage::query()->with('branch:id,name')->withCount('attempts')
            ->where('tenant_id', $tenant->id)->whereIn('branch_id', $branchIds)
            ->when(! $owner, function ($query) use ($assignments): void {
                $query->where(function ($scope) use ($assignments): void {
                    foreach ($assignments->groupBy('branch_id') as $branchId => $roles) {
                        $purposes = $roles->contains('role', 'branch_manager')
                            ? ['session_ending', 'receipt']
                            : array_values(array_unique($roles->map(fn ($row) => $row->role === 'cashier' ? 'receipt' : 'session_ending')->all()));
                        $scope->orWhere(fn ($branch) => $branch->where('branch_id', $branchId)->whereIn('purpose', $purposes));
                    }
                });
            })
            ->when(isset($filters['purpose']), fn ($query) => $query->where('purpose', $filters['purpose']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('notifications.index', compact('actor', 'tenant', 'branches', 'filters', 'messages', 'availablePurposes'));
    }
}
