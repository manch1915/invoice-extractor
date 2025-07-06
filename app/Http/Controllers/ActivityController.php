<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $action = (string) $request->query('action', '');

        $events = AuditEvent::query()
            ->with('user:id,name')
            ->when($action !== '', fn ($q) => $q->where('action', 'like', $action.'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return Inertia::render('Activity/Index', [
            'events' => $events->through(fn (AuditEvent $e) => [
                'id' => $e->id,
                'action' => $e->action,
                'user' => $e->user?->name,
                'subject_type' => $e->subject_type,
                'subject_id' => $e->subject_id,
                'changes' => $e->changes,
                'created_at' => $e->created_at->toIso8601String(),
            ]),
            'filter' => $action,
        ]);
    }
}
