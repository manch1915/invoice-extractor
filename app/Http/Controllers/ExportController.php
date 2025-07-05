<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\ExportStatus;
use App\Enums\ExportTarget;
use App\Infrastructure\Integrations\GoogleSheets;
use App\Infrastructure\Integrations\Xero;
use App\Models\Document;
use App\Models\Export;
use App\Models\Integration;
use App\Models\User;
use App\Services\Exports\ExportService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(GoogleSheets $google, Xero $xero): Response
    {
        $exports = Export::query()->with('user:id,name')->latest()->limit(50)->get();
        $integrations = Integration::query()->get()->keyBy('provider');

        return Inertia::render('Exports/Index', [
            'ready' => Document::query()->where('status', DocumentStatus::Ready)->count(),
            'ready_total' => Document::query()->where('status', DocumentStatus::Ready)->selectRaw('currency, sum(total) as total')->groupBy('currency')->pluck('total', 'currency'),
            'targets' => [
                ['key' => ExportTarget::Xlsx->value, 'available' => true],
                ['key' => ExportTarget::OneC->value, 'available' => true],
                ['key' => ExportTarget::GoogleSheets->value, 'available' => $integrations->has(Integration::GOOGLE), 'configured' => $google->configured(), 'account' => $integrations->get(Integration::GOOGLE)?->account_name],
                ['key' => ExportTarget::Xero->value, 'available' => $integrations->has(Integration::XERO), 'configured' => $xero->configured(), 'account' => $integrations->get(Integration::XERO)?->account_name],
            ],
            'exports' => $exports->map(fn (Export $e) => [
                'id' => $e->id,
                'target' => $e->target->value,
                'status' => $e->status->value,
                'documents' => $e->document_count,
                'user' => $e->user?->name,
                'created_at' => $e->created_at?->toIso8601String(),
                'download' => $e->file_path ? route('exports.download', $e->id) : null,
                'external_url' => $e->external_url,
                'error' => $e->error,
            ]),
            'busy' => $exports->contains(fn (Export $e) => in_array($e->status, [ExportStatus::Queued, ExportStatus::Running], true)),
        ]);
    }

    public function store(Request $request, ExportService $exports): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', Rule::enum(ExportTarget::class)],
            'documents' => ['nullable', 'array', 'max:500'],
            'documents.*' => ['string', 'size:26'],
        ]);

        $ids = $data['documents'] ?? Document::query()->where('status', DocumentStatus::Ready)->pluck('id')->all();
        /** @var User $user */
        $user = $request->user();
        $exports->start(ExportTarget::from($data['target']), array_values(array_map('strval', $ids)), $user);

        return to_route('exports.index')->with('success', __('exports.started'));
    }

    public function download(Export $export, Filesystem $disk): StreamedResponse
    {
        abort_if($export->file_path === null || ! $disk->exists($export->file_path), 404);
        $name = 'invoices-'.$export->created_at?->format('Y-m-d-His').'.'.pathinfo($export->file_path, PATHINFO_EXTENSION);

        return $disk->download($export->file_path, $name);
    }
}
