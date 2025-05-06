<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Enums\CategorySource;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Document;
use Illuminate\Support\Collection;

/**
 * Picks an expense category. Order of trust: what people taught us about this vendor,
 * then keyword rules, then the model's suggestion, then category keywords.
 */
class CategoryResolver
{
    public function resolve(Document $document, ?string $suggestedCode): ?CategoryChoice
    {
        if ($document->vendor_id !== null) {
            $rule = CategoryRule::query()->with('category')->where('vendor_id', $document->vendor_id)->first();
            if ($rule?->category) {
                $rule->increment('hits', 1, ['last_matched_at' => now()]);

                return new CategoryChoice($rule->category, CategorySource::VendorRule);
            }
        }

        $haystack = $this->haystack($document);

        $patternRule = CategoryRule::query()
            ->with('category')
            ->whereNull('vendor_id')
            ->whereNotNull('pattern')
            ->get()
            ->first(fn (CategoryRule $r) => $r->category && str_contains($haystack, mb_strtolower((string) $r->pattern)));
        if ($patternRule?->category) {
            $patternRule->increment('hits', 1, ['last_matched_at' => now()]);

            return new CategoryChoice($patternRule->category, CategorySource::Keyword);
        }

        if ($suggestedCode !== null) {
            $suggested = Category::query()->where('code', $suggestedCode)->first();
            if ($suggested) {
                return new CategoryChoice($suggested, CategorySource::Llm);
            }
        }

        $byKeyword = $this->byCategoryKeywords($haystack);

        return $byKeyword ? new CategoryChoice($byKeyword, CategorySource::Keyword) : null;
    }

    private function haystack(Document $document): string
    {
        $parts = [$document->vendor_name ?? ''];
        foreach ($document->lineItems as $item) {
            $parts[] = $item->description;
        }

        return mb_strtolower(implode(' ', $parts));
    }

    private function byCategoryKeywords(string $haystack): ?Category
    {
        /** @var Collection<int, Category> $categories */
        $categories = Category::query()->whereNotNull('keywords')->get();
        $best = null;
        $bestScore = 0;
        foreach ($categories as $category) {
            $score = 0;
            foreach ($category->keywords ?? [] as $keyword) {
                if ($keyword !== '' && str_contains($haystack, mb_strtolower((string) $keyword))) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$category, $score];
            }
        }

        return $best;
    }
}
