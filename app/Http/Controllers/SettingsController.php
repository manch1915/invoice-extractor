<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Integrations\GoogleSheets;
use App\Infrastructure\Integrations\Xero;
use App\Models\Integration;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsController extends Controller
{
    public function index(Request $request, GoogleSheets $google, Xero $xero): Response
    {
        Gate::authorize('manage-settings');
        /** @var User $user */
        $user = $request->user();
        $integrations = Integration::query()->get()->keyBy('provider');

        $describe = fn (string $provider, bool $configured) => [
            'provider' => $provider,
            'configured' => $configured,
            'connected' => $integrations->has($provider),
            'account' => $integrations->get($provider)?->account_name,
            'connected_at' => $integrations->get($provider)?->created_at?->toIso8601String(),
        ];

        return Inertia::render('Settings/Index', [
            'integrations' => [
                $describe(Integration::GOOGLE, $google->configured()),
                $describe(Integration::XERO, $xero->configured()),
            ],
            'mailbox' => [
                'address' => config('invoices.mailbox.address'),
                'configured' => filled(config('invoices.mailbox.host')),
            ],
            'tokens' => $user->tokens()->latest()->get()->map(fn (PersonalAccessToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'new_token' => $request->session()->get('new_token'),
            'api_url' => url('/api/documents'),
            'engine' => config('invoices.worker.engine'),
            'demo_locked' => config('invoices.demo.enabled') && ! config('invoices.demo.integrations'),
        ]);
    }

    public function createToken(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        /** @var User $user */
        $user = $request->user();
        $token = $user->createToken($data['name'], ['documents:write', 'documents:read']);
        $audit->record('token.created', $user, ['name' => $data['name']], $user);

        // the plain token is shown exactly once
        return to_route('settings')->with('new_token', $token->plainTextToken);
    }

    public function deleteToken(Request $request, int $token, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-settings');
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->whereKey($token)->delete();
        $audit->record('token.revoked', $user, ['id' => $token], $user);

        return to_route('settings')->with('success', __('settings.token_revoked'));
    }
}
