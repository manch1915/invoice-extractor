<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Http\Requests\UploadDocumentsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DocumentDetailResource;
use App\Http\Resources\DocumentRowResource;
use App\Jobs\ProcessDocument;
use App\Models\Category;
use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentIngestor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const TABS = ['all', 'needs_review', 'ready', 'exported', 'processing', 'failed', 'rejected'];

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('status'), self::TABS, true) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('q', ''));
        $category = $request->integer('category') ?: null;

        $documents = Document::query()
            ->with('category')
            ->withCount('openIssues')
            ->when($tab === 'all', fn ($q) => $q->visible())
            ->when($tab === 'processing', fn ($q) => $q->whereIn('status', [DocumentStatus::Queued, DocumentStatus::Processing]))
            ->when(! in_array($tab, ['all', 'processing'], true), fn ($q) => $q->where('status', $tab))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('vendor_name', 'like', "%{$search}%")
                ->orWhere('document_number', 'like', "%{$search}%")
                ->orWhere('original_filename', 'like', "%{$search}%")))
            ->when($category, fn ($q) => $q->where('category_id', $category))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = Document::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Documents/Index', [
            'documents' => DocumentRowResource::collection($documents),
            'counts' => $counts,
            'filters' => ['status' => $tab, 'q' => $search, 'category' => $category],
            'categories' => CategoryResource::collection(Category::query()->orderBy('name_en')->get())->resolve(),
            'mailbox' => config('invoices.mailbox.address'),
            'busy' => Document::query()->whereIn('status', [DocumentStatus::Queued, DocumentStatus::Processing])->exists(),
        ]);
    }

    public function store(UploadDocumentsRequest $request, DocumentIngestor $ingestor): RedirectResponse
    {
        $files = Arr::wrap($request->file('files', []));
        foreach ($files as $file) {
            $ingestor->fromUpload($file, DocumentSource::Upload, $request->user());
        }

        return back()->with('success', trans_choice('documents.uploaded', count($files), ['count' => count($files)]));
    }

    public function show(Document $document): Response
    {
        $document->load(['fields', 'lineItems', 'issues', 'pages', 'category', 'vendor', 'reviewer']);

        $queue = Document::query()
            ->where('status', DocumentStatus::NeedsReview)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id');
        $found = $queue->search($document->id);
        $position = $found === false ? null : (int) $found;

        return Inertia::render('Documents/Review', [
            'document' => DocumentDetailResource::make($document)->resolve(),
            'categories' => CategoryResource::collection(Category::query()->orderBy('name_en')->get())->resolve(),
            'queue' => [
                'total' => $queue->count(),
                'position' => $position === null ? null : $position + 1,
                'next' => $position === null ? $queue->first() : $queue->get($position + 1),
                'previous' => $position === null || $position === 0 ? null : $queue->get($position - 1),
            ],
        ]);
    }

    public function original(Document $document, Filesystem $disk): StreamedResponse
    {
        return $disk->response($document->storage_path, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    public function page(Document $document, int $number, Filesystem $disk): StreamedResponse
    {
        $page = $document->pages()->where('number', $number)->firstOrFail();

        return $disk->response($page->image_path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=604800, immutable',
        ]);
    }

    public function reprocess(Document $document, AuditLogger $audit, Request $request): RedirectResponse
    {
        abort_if(in_array($document->status, [DocumentStatus::Exported, DocumentStatus::Processing], true), 409);

        $document->update(['status' => DocumentStatus::Queued, 'error' => null]);
        $audit->record('document.reprocessed', $document, [], $request->user());
        ProcessDocument::dispatch($document->id);

        return back()->with('success', __('documents.reprocessing'));
    }

    public function destroy(Document $document, Filesystem $disk, AuditLogger $audit, Request $request): RedirectResponse
    {
        Gate::authorize('delete-documents');

        $disk->delete($document->storage_path);
        $disk->deleteDirectory("pages/{$document->id}");
        $audit->record('document.deleted', $document, ['file' => $document->original_filename], $request->user());
        $document->delete();

        return to_route('documents.index')->with('success', __('documents.deleted'));
    }
}
