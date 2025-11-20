<?php

namespace App\Traits;

use App\Models\AuthAuditLog;

trait LogsAuthEvents
{
    protected function logAuthEvent(string $action, ?int $userId = null, array $meta = []): void
    {
        AuthAuditLog::create([
            'user_id'    => $userId,
            'action'     => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => $meta,
        ]);
    }
}
