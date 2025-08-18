<?php

declare(strict_types=1);

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
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

it('keeps the queue dashboard closed on the public demo', function () {
    $owner = owner();

    config(['invoices.demo.enabled' => false]);
    expect(Gate::forUser($owner)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(accountant())->allows('viewHorizon'))->toBeFalse();

    config(['invoices.demo.enabled' => true]);
    expect(Gate::forUser($owner)->allows('viewHorizon'))->toBeFalse();
});

it('ignores forwarded headers from addresses that are not a known proxy', function () {
    Route::get('/_client-ip', fn (Request $request) => $request->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeader('X-Forwarded-For', '1.2.3.4')
        ->get('/_client-ip')
        ->assertSee('203.0.113.7');

    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeader('X-Forwarded-For', '198.51.100.20')
        ->get('/_client-ip')
        ->assertSee('198.51.100.20');
});
