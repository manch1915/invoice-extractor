<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Category;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Numbers for the dashboard. Amounts in other currencies are converted with the configured
 * rates so categories can be compared on one scale.
 */
class DashboardMetrics
{
    private const COUNTED = [DocumentStatus::Ready, DocumentStatus::Exported, DocumentStatus::NeedsReview];

    /** @return array<string, mixed> */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $documents = $this->counted($from, $to)
            ->with('category:id,code,name_en,name_ru,color')
            ->withCount('lineItems')
            ->get(['id', 'status', 'category_id', 'vendor_id', 'vendor_name', 'currency', 'total', 'issue_date',
                'auto_approved', 'review_seconds', 'reviewed_at', 'created_at']);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'base_currency' => $this->baseCurrency(),
            'totals' => $this->totals($documents),
            'by_category' => $this->byCategory($documents),
            'by_vendor' => $this->byVendor($documents),
            'by_month' => $this->byMonth($documents, $from, $to),
            'automation' => $this->automation($documents),
        ];
    }

    /** @return Builder<Document> */
    private function counted(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Document::query()
            ->whereIn('status', self::COUNTED)
            ->whereNotNull('total')
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()]);
    }

    private function baseCurrency(): string
    {
        return (string) config('invoices.base_currency');
    }

    /** Converts minor units of any currency to minor units of the base currency. */
    public function toBase(int $minor, ?string $currency): int
    {
        $rates = (array) config('invoices.rates');
        $rate = (float) ($rates[$currency ?? ''] ?? 1.0) / (float) ($rates[$this->baseCurrency()] ?? 1.0);

        return (int) round($minor * $rate);
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<string, int|float>
     */
    private function totals(Collection $documents): array
    {
        $spend = $documents->sum(fn (Document $d) => $this->toBase((int) $d->total, $d->currency));
        $seconds = $documents->sum(function (Document $d) {
            $manual = (int) config('invoices.manual_entry.seconds_per_document')
                + (int) $d->getAttribute('line_items_count') * (int) config('invoices.manual_entry.seconds_per_line');

            return max(0, $manual - $d->review_seconds);
        });

        return [
            'documents' => $documents->count(),
            'spend' => $spend,
            'vendors' => $documents->pluck('vendor_id')->filter()->unique()->count(),
            'hours_saved' => round($seconds / 3600, 1),
            'review_seconds' => $this->medianReviewSeconds($documents),
        ];
    }

    /** @param  Collection<int, Document>  $documents */
    private function medianReviewSeconds(Collection $documents): ?int
    {
        $times = $documents->filter(fn (Document $d) => $d->reviewed_at !== null && $d->review_seconds > 0)
            ->pluck('review_seconds')->sort()->values();
        if ($times->isEmpty()) {
            return null;
        }

        return (int) $times->get(intdiv($times->count(), 2));
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function byCategory(Collection $documents): array
    {
        return $documents
            ->groupBy(fn (Document $d) => $d->category_id ?? 0)
            ->map(function (Collection $group) {
                /** @var Document $first */
                $first = $group->first();
                $category = $first->category;

                return [
                    'code' => $category instanceof Category ? $category->code : null,
                    'name_en' => $category instanceof Category ? $category->name_en : null,
                    'name_ru' => $category instanceof Category ? $category->name_ru : null,
                    'color' => $category instanceof Category ? $category->color : null,
                    'amount' => $group->sum(fn (Document $d) => $this->toBase((int) $d->total, $d->currency)),
                    'documents' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function byVendor(Collection $documents): array
    {
        return $documents
            ->filter(fn (Document $d) => $d->vendor_id !== null)
            ->groupBy('vendor_id')
            ->map(fn (Collection $group) => [
                'id' => $group->first()?->vendor_id,
                'name' => $group->first()?->vendor_name,
                'amount' => $group->sum(fn (Document $d) => $this->toBase((int) $d->total, $d->currency)),
                'documents' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return list<array{month: string, amount: int}>
     */
    private function byMonth(Collection $documents, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];
        for ($m = $from->startOfMonth(); $m <= $to; $m = $m->addMonth()) {
            $months[$m->format('Y-m')] = 0;
        }
        foreach ($documents as $d) {
            $key = $d->issue_date?->format('Y-m');
            if ($key !== null && isset($months[$key])) {
                $months[$key] += $this->toBase((int) $d->total, $d->currency);
            }
        }

        return array_map(fn (string $month, int $amount) => ['month' => $month, 'amount' => $amount], array_keys($months), $months);
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<string, int>
     */
    private function automation(Collection $documents): array
    {
        return [
            'automatic' => $documents->filter(fn (Document $d) => $d->auto_approved && $d->reviewed_at === null)->count(),
            'reviewed' => $documents->filter(fn (Document $d) => $d->reviewed_at !== null)->count(),
            'waiting' => $documents->filter(fn (Document $d) => $d->status === DocumentStatus::NeedsReview)->count(),
        ];
    }
}
