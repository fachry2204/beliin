<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function record(string $action, string $module, ?Model $reference = null, ?array $old = null, ?array $new = null): void
    {
        ActivityLog::create(['user_id' => auth()->id(), 'action' => $action, 'module' => $module, 'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->getKey(), 'old_data' => $old, 'new_data' => $new, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent()]);
    }
}
