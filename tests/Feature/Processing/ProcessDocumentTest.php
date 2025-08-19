<?php

declare(strict_types=1);

use App\Enums\CategorySource;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Enums\FieldKey;
use App\Enums\IssueCode;
use App\Infrastructure\Worker\WorkerException;
use App\Jobs\ProcessDocument;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Document;
use App\Services\Documents\DocumentIngestor;
use App\Services\Documents\UnsupportedFileException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => seedBasics());

function ingest(string $contents = 'pdf bytes', string $name = 'invoice.pdf'): Document
{
    return app(DocumentIngestor::class)->ingest(pdfBytes($contents), $name, DocumentSource::Upload, owner());
}

it('reads a clean invoice and approves it without a person', function () {
    fakeWorker(workerFixture('kanc-0929'));

    $document = ingest()->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->auto_approved)->toBeTrue()
        ->and($document->vendor_name)->toBe('ООО «КанцМаркет»')
        ->and($document->document_number)->toBe('КМ-21688')
        ->and($document->issue_date->format('Y-m-d'))->toBe('2026-09-29')
        ->and($document->total)->toBe(6_002_00)
        ->and($document->vendor->tax_id)->toBe('7719053389')
        ->and($document->category->code)->toBe('office')
        ->and($document->category_source)->toBe(CategorySource::Keyword)
        ->and($document->lineItems)->toHaveCount(3)
        ->and($document->openIssues)->toHaveCount(0);

    Storage::disk('local')->assertExists("originals/{$document->id}.pdf");
    Storage::disk('local')->assertExists("pages/{$document->id}/1.webp");
    expect($document->fields()->where('key', 'total')->first()->bbox)->toHaveCount(4);
});

it('sends the category list to the worker', function () {
    fakeWorker(workerFixture('kanc-0929'));

    ingest();

    Http::assertSent(function (Request $request) {
        $categories = collect($request->data())->firstWhere('name', 'categories');

        return str_contains((string) ($categories['contents'] ?? ''), '"code":"raw_materials"');
    });
});

it('holds the second copy of an invoice as a duplicate', function () {
    fakeWorker(workerFixture('kanc-0929'));

    $first = ingest('scan one')->refresh();
    $second = ingest('scan two, different file')->refresh();

    expect($first->status)->toBe(DocumentStatus::Ready)
        ->and($second->status)->toBe(DocumentStatus::NeedsReview)
        ->and($second->duplicate_of_id)->toBe($first->id)
        ->and($second->openIssues->pluck('code')->all())->toBe([IssueCode::Duplicate]);
});

it('sends an invoice with a broken INN to review', function () {
    fakeWorker(workerFixture('upaksnab-0918'));

    $document = ingest()->refresh();

    expect($document->status)->toBe(DocumentStatus::NeedsReview)
        ->and($document->openIssues->pluck('code')->all())->toContain(IssueCode::InvalidTaxId);
});

it('sends doubtful fields to review even when the numbers add up', function () {
    $payload = workerFixture('bakeware-0728');
    $payload['fields']['total']['confidence'] = 0.41;
    fakeWorker($payload);

    $document = ingest()->refresh();

    expect($document->status)->toBe(DocumentStatus::NeedsReview)
        ->and($document->openIssues)->toHaveCount(0)
        ->and($document->fields()->where('key', FieldKey::Total->value)->first()->status->value)->toBe('review');
});

it('uses what it learned about a vendor before anything else', function () {
    fakeWorker(workerFixture('bakeware-0728'));
    $first = ingest('first')->refresh();
    $marketing = Category::query()->where('code', 'marketing')->firstOrFail();
    CategoryRule::create(['vendor_id' => $first->vendor_id, 'category_id' => $marketing->id, 'source' => 'learned']);

    $second = ingest('second')->refresh();

    expect($second->category->code)->toBe('marketing')
        ->and($second->category_source)->toBe(CategorySource::VendorRule)
        ->and(CategoryRule::first()->hits)->toBe(1);
});

it('takes the model suggestion when no rule matches', function () {
    $payload = workerFixture('hoffmann-0818');
    $payload['line_items'] = [];
    $payload['category'] = ['code' => 'maintenance', 'confidence' => 0.7];
    fakeWorker($payload);

    $document = ingest()->refresh();

    expect($document->category->code)->toBe('maintenance')
        ->and($document->category_source)->toBe(CategorySource::Llm);
});

it('marks unreadable files as failed without retrying', function () {
    fakeWorker(Http::response(['detail' => 'not a readable image'], 422));

    $document = ingest('garbage', 'photo.jpg')->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toContain('not a readable image');
});

it('puts the document back in the queue when the worker is down', function () {
    Queue::fake();
    fakeWorker(Http::response('upstream error', 503));
    $document = ingest();

    expect(fn () => app()->call([new ProcessDocument($document->id), 'handle']))->toThrow(WorkerException::class)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Queued)
        ->and($document->error)->toContain('Document reader failed');
});

it('refuses a file whose bytes are not a PDF or an image, whatever its name says', function () {
    Queue::fake();

    expect(fn () => app(DocumentIngestor::class)->ingest('<html><script>alert(1)</script></html>', 'invoice.pdf', DocumentSource::Email))
        ->toThrow(UnsupportedFileException::class);
    expect(Document::count())->toBe(0);
});

it('stores the type read from the bytes', function () {
    Queue::fake();

    expect(ingest()->mime_type)->toBe('application/pdf');
});
