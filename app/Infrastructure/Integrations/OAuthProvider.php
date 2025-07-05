<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations;

use App\Models\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * Authorization-code flow with refresh tokens, shared by Google and Xero.
 */
abstract class OAuthProvider
{
    public function __construct(
        protected readonly Http $http,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
    ) {}

    abstract public function name(): string;

    abstract protected function authorizeEndpoint(): string;

    abstract protected function tokenEndpoint(): string;

    /** @return list<string> */
    abstract protected function scopes(): array;

    /** @return array<string, string> */
    protected function extraAuthorizeParams(): array
    {
        return [];
    }

    public function configured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function authorizeUrl(string $redirect, string $state): string
    {
        return $this->authorizeEndpoint().'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirect,
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            ...$this->extraAuthorizeParams(),
        ]);
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_at: CarbonImmutable} */
    public function exchange(string $code, string $redirect): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
        ]);
    }

    /** A valid access token for the stored connection, refreshed and saved when it is about to expire. */
    public function accessToken(Integration $integration): string
    {
        $credentials = $integration->credentials ?? [];
        $expires = $integration->expires_at;
        if (isset($credentials['access_token']) && $expires !== null && $expires->isAfter(now()->addMinute())) {
            return (string) $credentials['access_token'];
        }
        if (! isset($credentials['refresh_token'])) {
            throw new RuntimeException("{$this->name()} connection has expired, connect it again");
        }

        $tokens = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $credentials['refresh_token'],
        ]);
        $integration->update([
            'credentials' => [
                ...$credentials,
                'access_token' => $tokens['access_token'],
                // Xero rotates refresh tokens on every use; Google keeps the old one
                'refresh_token' => $tokens['refresh_token'] ?? $credentials['refresh_token'],
            ],
            'expires_at' => $tokens['expires_at'],
        ]);

        return $tokens['access_token'];
    }

    /**
     * @param  array<string, string>  $form
     * @return array{access_token: string, refresh_token: ?string, expires_at: CarbonImmutable}
     */
    private function tokenRequest(array $form): array
    {
        $response = $this->http->asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->timeout(20)
            ->post($this->tokenEndpoint(), $form)
            ->throw();

        return [
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_at' => CarbonImmutable::now()->addSeconds((int) $response->json('expires_in', 1800)),
        ];
    }
}
