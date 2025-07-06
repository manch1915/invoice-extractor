<?php

declare(strict_types=1);

use App\Models\Integration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => seedBasics());

it('accepts documents over the API and returns where to poll', function () {
    Queue::fake();
    Sanctum::actingAs(owner(), ['documents:write', 'documents:read']);

    $response = $this->postJson('/api/documents', ['files' => [UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf')]]);

    $response->assertAccepted()->assertJsonPath('data.0.status', 'queued');
    $this->getJson($response->json('data.0.url'))->assertOk()->assertJsonPath('data.status', 'queued');
});

it('refuses API calls without the right ability', function () {
    Sanctum::actingAs(owner(), ['documents:read']);

    $this->postJson('/api/documents', ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertForbidden();
});

it('shows a new API token once', function () {
    $this->actingAs(owner())->post('/settings/tokens', ['name' => 'ERP sync'])->assertRedirect('/settings');

    expect(session('new_token'))->toContain('|')
        ->and(owner()->tokens()->count())->toBe(1);
});

it('keeps settings away from the accountant', function () {
    $this->actingAs(accountant())->get('/settings')->assertForbidden();
});

it('rejects an OAuth callback with a forged state', function () {
    config(['invoices.xero.client_id' => 'id', 'invoices.xero.client_secret' => 'secret']);

    $this->actingAs(owner())
        ->withSession(['oauth_state.xero' => 'expected'])
        ->get('/integrations/xero/callback?code=abc&state=forged')
        ->assertRedirect('/settings')
        ->assertSessionHas('error');

    expect(Integration::count())->toBe(0);
});

it('does not let demo visitors plug in real accounts', function () {
    config(['invoices.demo.enabled' => true, 'invoices.demo.integrations' => false, 'invoices.google.client_id' => 'id', 'invoices.google.client_secret' => 's']);

    $this->actingAs(owner())->get('/integrations/google/connect')->assertRedirect('/settings')->assertSessionHas('error');
});
