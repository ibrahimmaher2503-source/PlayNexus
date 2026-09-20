<?php

namespace App\Http\Middleware;

use App\Support\AuthenticationAudit;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class AuditSensitiveDenial
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $reason = $this->reason($exception);
            $this->audit($request, $reason);

            throw $exception;
        }

        $reason = match ($response->getStatusCode()) {
            403 => 'forbidden',
            404 => 'hidden_or_missing',
            default => null,
        };
        $this->audit($request, $reason);

        return $response;
    }

    private function reason(Throwable $exception): ?string
    {
        if ($exception instanceof AuthorizationException) {
            return 'forbidden';
        }
        if ($exception instanceof ModelNotFoundException) {
            return 'hidden_or_missing';
        }
        if ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [403, 404], true)) {
            return $exception->getStatusCode() === 403 ? 'forbidden' : 'hidden_or_missing';
        }

        return null;
    }

    private function audit(Request $request, ?string $reason): void
    {
        $user = $request->user();
        if ($reason === null || ! $user) {
            return;
        }

        try {
            $user->tenant_id === null
                ? AuthenticationAudit::platformDenial($request, $user, $reason)
                : AuthenticationAudit::tenantDenial($request, $user, $reason);
        } catch (Throwable $auditFailure) {
            Log::error('security.denial_audit_failed', [
                'request_id' => $request->attributes->get('request_id'),
                'exception' => $auditFailure::class,
            ]);
        }
    }
}
