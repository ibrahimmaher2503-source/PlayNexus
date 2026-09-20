<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use App\Support\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureSubscriptionAccess
{
    public function handle(Request $request, Closure $next, string $operation = 'auto'): Response
    {
        $user = $request->user();
        $tenant = $user ? app(TenantContext::class)->current($user) : null;

        abort_unless($tenant, 404);

        $access = SubscriptionAccess::forTenant($tenant);
        $owner = Gate::forUser($user)->allows('view', $tenant);
        $operation = $operation === 'auto' ? $this->operation($request) : $operation;

        if (! $access->allows($operation, $owner)) {
            throw new HttpException(402, 'An active subscription is required to continue.');
        }

        $request->attributes->set('subscription_access', $access);

        return $next($request);
    }

    private function operation(Request $request): string
    {
        if ($request->isMethodSafe()) {
            return $request->routeIs('reports.*') ? 'historical_reports' : 'read';
        }

        return 'write';
    }
}
