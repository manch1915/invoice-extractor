<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations;

use RuntimeException;

/**
 * Xero OAuth and bills (ACCPAY invoices). Apps registered after March 2026 only get granular
 * scopes, so this asks for accounting.invoices rather than the old accounting.transactions.
 */
class Xero extends OAuthProvider
{
    private const API = 'https://api.xero.com/api.xro/2.0';

    public function name(): string
    {
        return 'Xero';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://login.xero.com/identity/connect/authorize';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://identity.xero.com/connect/token';
    }

    protected function scopes(): array
    {
        return ['openid', 'profile', 'email', 'offline_access', 'accounting.invoices', 'accounting.contacts', 'accounting.settings.read'];
    }

    /** @return array{id: string, name: string} the organisation the user just connected */
    public function firstTenant(string $token): array
    {
        $tenants = $this->http->withToken($token)->timeout(20)->get('https://api.xero.com/connections')->throw()->json();
        $tenant = is_array($tenants) ? ($tenants[0] ?? null) : null;
        if (! is_array($tenant)) {
            throw new RuntimeException('No Xero organisation was connected');
        }

        return ['id' => (string) $tenant['tenantId'], 'name' => (string) ($tenant['tenantName'] ?? 'Xero')];
    }

    /**
     * Creates draft bills. Returns Xero's InvoiceID for each bill, in the order they were sent.
     *
     * @param  list<array<string, mixed>>  $bills
     * @return list<string|null>
     */
    public function createBills(string $token, string $tenantId, array $bills): array
    {
        $response = $this->http->withToken($token)
            ->withHeaders(['xero-tenant-id' => $tenantId, 'Accept' => 'application/json'])
            ->timeout(60)
            ->post(self::API.'/Invoices?summarizeErrors=false', ['Invoices' => $bills])
            ->throw();

        $errors = [];
        $ids = [];
        foreach ((array) $response->json('Invoices', []) as $i => $invoice) {
            $ids[] = $invoice['InvoiceID'] ?? null;
            foreach ($invoice['ValidationErrors'] ?? [] as $error) {
                $errors[] = '#'.($i + 1).': '.($error['Message'] ?? 'validation error');
            }
        }
        if ($errors !== []) {
            throw new RuntimeException('Xero refused some bills. '.implode('; ', array_slice($errors, 0, 5)));
        }

        return $ids;
    }
}
