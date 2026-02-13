<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function log(
        Request $request,
        string $action,
        string $auditableType,
        int $auditableId,
        ?array $before = null,
        ?array $after = null
    ): void {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}

