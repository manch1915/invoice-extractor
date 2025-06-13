<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roleName(),
                    'can_manage' => $user->can('manage-settings'),
                ] : null,
            ],
            'locale' => app()->getLocale(),
            'demo' => (bool) config('invoices.demo.enabled'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'queue_count' => fn () => $user
                ? Document::query()->where('status', DocumentStatus::NeedsReview)->count()
                : 0,
        ];
    }
}
