<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Services\TenantContext;
use App\Support\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureSubscriptionLimit
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = $request->user();
        $tenant = $user ? app(TenantContext::class)->current($user) : null;

        abort_unless($tenant, 404);

        /** @var SubscriptionAccess $access */
        $access = $request->attributes->get('subscription_access')
            ?? SubscriptionAccess::forTenant($tenant);

        if (! $access->allows('write')) {
            throw new HttpException(402, 'An active subscription is required to continue.');
        }

        $current = match (strtolower(trim($resource))) {
            'branch', 'branches' => Branch::query()->where('tenant_id', $tenant->getKey())->count(),
            'user', 'users', 'staff' => User::query()->where('tenant_id', $tenant->getKey())->count(),
            default => throw new HttpException(500, 'Unsupported subscription limit.'),
        };

        if (! $access->canCreate($resource, $current)) {
            throw new HttpException(402, 'Upgrade required to create another resource.');
        }

        return $next($request);
    }
}
