<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Resources\DocumentRowResource;
use App\Models\Document;
use App\Services\DashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const PERIODS = [30, 90, 365];

    public function __invoke(Request $request, DashboardMetrics $metrics): Response
    {
        $days = in_array($request->integer('days'), self::PERIODS, true) ? $request->integer('days') : 90;
        $to = CarbonImmutable::today();

        $waiting = Document::query()
            ->with('category')
            ->withCount('openIssues')
            ->where('status', DocumentStatus::NeedsReview)
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'days' => $days,
            'metrics' => $metrics->summary($to->subDays($days - 1), $to),
            'waiting' => DocumentRowResource::collection($waiting)->resolve(),
        ]);
    }
}
