<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuditSensitiveDenial;
use App\Http\Middleware\EnsureAccountMfa;
use App\Http\Middleware\EnsureBranchAccess;
use App\Http\Middleware\EnsurePlatformAccess;
use App\Http\Middleware\EnsureSubscriptionAccess;
use App\Http\Middleware\EnsureSubscriptionLimit;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', AssignRequestId::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->alias([
            'branch.access' => EnsureBranchAccess::class,
            'account.mfa' => EnsureAccountMfa::class,
            'security.audit-denials' => AuditSensitiveDenial::class,
            'platform.access' => EnsurePlatformAccess::class,
            'tenant.access' => EnsureTenantAccess::class,
            'subscription.access' => EnsureSubscriptionAccess::class,
            'subscription.limit' => EnsureSubscriptionLimit::class,
        ]);
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureTenantAccess::class,
        );
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/app');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $problem = static function (Request $request, int $status, string $code, string $message, ?array $errors = null): JsonResponse {
            $body = [
                'code' => $code,
                'message' => $message,
                'request_id' => $request->attributes->get('request_id', (string) Str::uuid()),
            ];
            if ($errors !== null) {
                $body['errors'] = $errors;
            }

            return response()->json($body, $status)->header('X-Request-ID', $body['request_id']);
        };

        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $request->expectsJson()
            ? $problem($request, 401, 'unauthenticated', Response::$statusTexts[401])
            : null);
        $exceptions->render(fn (AuthorizationException $exception, Request $request) => $request->expectsJson()
            ? $problem($request, 403, 'forbidden', Response::$statusTexts[403])
            : null);
        $exceptions->render(fn (ValidationException $exception, Request $request) => $request->expectsJson()
            ? $problem($request, 422, 'validation_failed', $exception->getMessage(), $exception->errors())
            : null);
        $exceptions->render(fn (ModelNotFoundException $exception, Request $request) => $request->expectsJson()
            ? $problem($request, 404, 'not_found', Response::$statusTexts[404])
            : null);
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($problem): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                409 => 'conflict',
                429 => 'rate_limited',
                default => 'request_failed',
            };

            return $problem($request, $status, $code, $exception->getMessage() ?: (Response::$statusTexts[$status] ?? 'Request failed'));
        });
    })->create();
