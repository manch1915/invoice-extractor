<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\FieldKey;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentIssue;
use App\Models\User;
use App\Services\Documents\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer over ReviewService: validate the input, call the service, go back.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $review) {}

    public function updateField(Request $request, Document $document, FieldKey $key): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['nullable', 'string', 'max:255'],
            'bbox' => ['nullable', 'array', 'size:4'],
            'bbox.*' => ['numeric', 'between:0,1'],
            'page' => ['nullable', 'integer', 'min:1', 'required_with:bbox'],
        ]);

        $bbox = isset($data['bbox']) ? array_map('floatval', array_values($data['bbox'])) : null;
        $this->review->updateField($document, $key, $data['value'] ?? null, $this->user($request), $bbox, $data['page'] ?? null);

        return back();
    }

    public function confirmField(Request $request, Document $document, FieldKey $key): RedirectResponse
    {
        $this->review->confirmField($document, $key, $this->user($request));

        return back();
    }

    public function setCategory(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id']]);
        $this->review->setCategory($document, Category::query()->findOrFail((int) $data['category_id']), $this->user($request));

        return back();
    }

    public function resolveIssue(Request $request, Document $document, DocumentIssue $issue): RedirectResponse
    {
        abort_unless($issue->document_id === $document->id, 404);
        $this->review->resolveIssue($document, $issue, $this->user($request));

        return back();
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate(['seconds' => ['nullable', 'integer', 'min:0']]);
        $this->review->approve($document, $this->user($request), (int) ($data['seconds'] ?? 0));

        return $this->next($document)->with('success', __('review.approved', ['number' => $document->document_number ?? '']));
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->review->reject($document, $this->user($request), $data['reason'] ?? null);

        return $this->next($document)->with('success', __('review.rejected'));
    }

    public function reopen(Request $request, Document $document): RedirectResponse
    {
        $this->review->reopen($document, $this->user($request));

        return back();
    }

    /** After a decision the reviewer lands on the next document waiting, or back on the list. */
    private function next(Document $current): RedirectResponse
    {
        $next = Document::query()
            ->where('status', DocumentStatus::NeedsReview)
            ->whereKeyNot($current->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        return $next
            ? to_route('documents.show', $next)
            : to_route('documents.index', ['status' => 'needs_review']);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
