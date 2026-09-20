<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AuthenticationAudit
{
    public static function tenant(Request $request, User $user, string $action, string $outcome, string $reason): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $user->tenant_id,
            'branch_id' => null,
            'actor_user_id' => $user->id,
            'actor_type' => 'user',
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => (string) $user->id,
            'outcome' => $outcome,
            'reason_code' => $reason,
            'before_json' => null,
            'after_json' => null,
            'request_id' => self::requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }

    public static function platform(Request $request, User $user, string $action, string $outcome, string $reason): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_user_id' => $user->id,
            'target_tenant_id' => null,
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => (string) $user->id,
            'outcome' => $outcome,
            'reason_code' => $reason,
            'before_json' => null,
            'after_json' => null,
            'request_id' => self::requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }

    public static function unresolved(Request $request, string $action, string $email): void
    {
        Log::warning($action, [
            'request_id' => self::requestId($request),
            'identity_hash' => hash('sha256', Str::lower(trim($email))),
        ]);
    }

    public static function tenantDenial(Request $request, User $user, string $reason): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $user->tenant_id,
            'branch_id' => null,
            'actor_user_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'security.request_denied',
            'subject_type' => 'route',
            'subject_id' => self::routeFingerprint($request),
            'outcome' => 'failure',
            'reason_code' => $reason,
            'before_json' => null,
            'after_json' => null,
            'request_id' => self::requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }

    public static function platformDenial(Request $request, User $user, string $reason): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_user_id' => $user->id,
            'target_tenant_id' => null,
            'action' => 'security.request_denied',
            'subject_type' => 'route',
            'subject_id' => self::routeFingerprint($request),
            'outcome' => 'failure',
            'reason_code' => $reason,
            'before_json' => null,
            'after_json' => null,
            'request_id' => self::requestId($request),
            'occurred_at' => now('UTC'),
        ]);
    }

    private static function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', Str::uuid());
    }

    private static function routeFingerprint(Request $request): string
    {
        return substr(hash('sha256', (string) $request->route()?->getName().'|'.$request->path()), 0, 26);
    }
}
