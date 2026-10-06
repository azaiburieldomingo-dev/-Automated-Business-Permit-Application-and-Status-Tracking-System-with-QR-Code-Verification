<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an audit log entry. Metadata must never contain secrets.
     *
     * @param  array<string, mixed>  $meta
     */
    public function log(string $action, ?Model $subject = null, array $meta = [], ?User $actor = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta_json' => $meta !== [] ? $meta : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
