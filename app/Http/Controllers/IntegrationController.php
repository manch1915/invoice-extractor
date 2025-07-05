<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Integrations\GoogleSheets;
use App\Infrastructure\Integrations\OAuthProvider;
use App\Infrastructure\Integrations\Xero;
use App\Models\Integration;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Connect and disconnect Google and Xero. The state parameter ties the callback to this session.
 */
class IntegrationController extends Controller
{
    public function __construct(
        private readonly GoogleSheets $google,
        private readonly Xero $xero,
        private readonly AuditLogger $audit,
    ) {}

    public function connect(Request $request, string $provider): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $client = $this->client($provider);
        abort_unless($client->configured(), 404);
        if (config('invoices.demo.enabled') && ! config('invoices.demo.integrations')) {
            return to_route('settings')->with('error', __('settings.demo_locked'));
        }

        $state = Str::random(40);
        $request->session()->put("oauth_state.{$provider}", $state);

        return redirect()->away($client->authorizeUrl($this->redirect($provider), $state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $client = $this->client($provider);
        $expected = $request->session()->pull("oauth_state.{$provider}");

        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state')) || ! $request->filled('code')) {
            return to_route('settings')->with('error', __('settings.connect_failed', ['provider' => $client->name()]));
        }

        try {
            $tokens = $client->exchange((string) $request->query('code'), $this->redirect($provider));
            $settings = [];
            $account = $client->name();
            if ($client instanceof Xero) {
                $tenant = $client->firstTenant($tokens['access_token']);
                $settings['tenant_id'] = $tenant['id'];
                $account = $tenant['name'];
            }
        } catch (Throwable $e) {
            report($e);

            return to_route('settings')->with('error', __('settings.connect_failed', ['provider' => $client->name()]));
        }

        $integration = Integration::query()->updateOrCreate(['provider' => $provider], [
            'credentials' => ['access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token']],
            'expires_at' => $tokens['expires_at'],
            'account_name' => $account,
            'settings' => $settings,
            'connected_by' => $request->user()?->id,
        ]);
        $this->audit->record('integration.connected', $integration, ['provider' => $provider], $request->user());

        return to_route('settings')->with('success', __('settings.connected', ['provider' => $client->name()]));
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $integration = Integration::query()->where('provider', $provider)->first();
        if ($integration) {
            $this->audit->record('integration.disconnected', $integration, ['provider' => $provider], $request->user());
            $integration->delete();
        }

        return to_route('settings')->with('success', __('settings.disconnected', ['provider' => $this->client($provider)->name()]));
    }

    private function client(string $provider): OAuthProvider
    {
        return match ($provider) {
            Integration::GOOGLE => $this->google,
            Integration::XERO => $this->xero,
            default => abort(404),
        };
    }

    private function redirect(string $provider): string
    {
        return route('integrations.callback', $provider);
    }
}
