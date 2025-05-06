<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /** @param  array<string, mixed>  $changes */
    public function record(string $action, Model $subject, array $changes = [], ?User $user = null): AuditEvent
    {
        return AuditEvent::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => (string) $subject->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'ip' => $this->request?->ip(),
            'created_at' => now(),
        ]);
    }
}
