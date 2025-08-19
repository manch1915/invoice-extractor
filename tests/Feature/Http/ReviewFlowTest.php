<?php

declare(strict_types=1);

use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Enums\FieldStatus;
use App\Enums\IssueCode;
use App\Models\AuditEvent;
use App\Models\CategoryRule;
use App\Models\Document;
use App\Services\Documents\DocumentIngestor;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedBasics();
    config(['invoices.demo.enabled' => true]);
});

function upaksnab(): Document
{
    fakeWorker(workerFixture('upaksnab-0918'));

    return app(DocumentIngestor::class)->ingest(pdfBytes('upaksnab'), 'upaksnab.pdf', DocumentSource::Upload, accountant())->refresh();
}

it('lets a visitor into the demo with one click', function () {
    $this->post('/demo/accountant')->assertRedirect('/');
    $this->assertAuthenticatedAs(accountant());
});

it('keeps the demo buttons closed when the demo is off', function () {
    config(['invoices.demo.enabled' => false]);

    $this->post('/demo/owner')->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get('/documents')->assertRedirect('/login');
});

it('lists documents waiting for review', function () {
    $document = upaksnab();

    $this->actingAs(accountant())
        ->get('/documents?status=needs_review')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Index')
            ->where('counts.needs_review', 1)
            ->where('documents.data.0.id', $document->id)
            ->where('documents.data.0.issues_count', 1));
});

it('shows the review screen with fields, boxes and problems', function () {
    $document = upaksnab();

    $this->actingAs(accountant())
        ->get("/documents/{$document->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Review')
            ->where('document.issues.0.code', IssueCode::InvalidTaxId->value)
            ->has('document.fields', 12)
            ->where('queue.position', 1));
});

it('fixes a value, re-runs the checks and keeps an audit trail', function () {
    $document = upaksnab();

    $this->actingAs(accountant())
        ->patch("/documents/{$document->id}/fields/vendor_tax_id", ['value' => '7728 510 345'])
        ->assertRedirect();

    $document->refresh();
    $field = $document->fields()->where('key', 'vendor_tax_id')->first();
    expect($field->value)->toBe('7728510345')
        ->and($field->status)->toBe(FieldStatus::Corrected)
        ->and($field->original_value)->toBe('7728510344')
        ->and($document->openIssues->pluck('code')->map->value->all())->toBe([])
        ->and(AuditEvent::where('action', 'field.updated')->first()->changes)->toMatchArray(['from' => '7728510344', 'to' => '7728510345']);
});

it('rejects a value that cannot be an amount', function () {
    $document = upaksnab();

    $this->actingAs(accountant())
        ->patch("/documents/{$document->id}/fields/total", ['value' => 'двенадцать'])
        ->assertSessionHasErrors('value');
});

it('recomputes a net amount that was only derived when the tax is corrected', function () {
    $payload = workerFixture('kanc-0929');
    $payload['fields']['subtotal'] = ['value' => '4501.67', 'raw' => null, 'confidence' => 0.7, 'page' => null, 'bbox' => null];
    $payload['fields']['tax_amount']['value'] = '1500.33';
    fakeWorker($payload);
    $document = app(DocumentIngestor::class)->ingest(pdfBytes('kanc'), 'kanc.pdf', DocumentSource::Upload, accountant())->refresh();

    $this->actingAs(accountant())->patch("/documents/{$document->id}/fields/tax_amount", ['value' => '1000,33']);

    expect($document->refresh()->subtotal)->toBe(5_001_67);
});

it('will not approve while a problem blocks it, then approves and learns the category', function () {
    $document = upaksnab();
    $this->actingAs(accountant());

    $this->post("/documents/{$document->id}/approve")->assertSessionHasErrors('document');
    expect($document->refresh()->status)->toBe(DocumentStatus::NeedsReview);

    $issue = $document->openIssues()->firstOrFail();
    $this->post("/documents/{$document->id}/issues/{$issue->id}/resolve");
    $this->post("/documents/{$document->id}/approve", ['seconds' => 42])->assertRedirect('/documents?status=needs_review');

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->review_seconds)->toBe(42)
        ->and($document->reviewer->is(accountant()))->toBeTrue()
        ->and(CategoryRule::where('vendor_id', $document->vendor_id)->exists())->toBeTrue();
});

it('lets only the owner delete documents', function () {
    $document = upaksnab();

    $this->actingAs(accountant())->delete("/documents/{$document->id}")->assertForbidden();
    $this->actingAs(owner())->delete("/documents/{$document->id}")->assertRedirect('/documents');

    expect(Document::count())->toBe(0);
});

it('switches the interface language', function () {
    $this->actingAs(accountant())->post('/locale', ['locale' => 'en']);

    expect(accountant()->locale)->toBe('en');
});

it('serves an original only as the type it was checked to be', function () {
    $document = upaksnab();

    $this->actingAs(accountant())->get("/documents/{$document->id}/original")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // a row stored with a type the intake would refuse today is handed out as a download
    $document->forceFill(['mime_type' => 'text/html'])->save();

    $response = $this->actingAs(accountant())->get("/documents/{$document->id}/original")->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});
