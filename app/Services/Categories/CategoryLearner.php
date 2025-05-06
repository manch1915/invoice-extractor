<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * When a person sets a category, the next document from the same vendor gets it automatically.
 */
class CategoryLearner
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function learn(Document $document, Category $category, User $user): ?CategoryRule
    {
        if ($document->vendor_id === null) {
            return null;
        }

        $rule = CategoryRule::query()->firstOrNew(['vendor_id' => $document->vendor_id]);
        $previous = $rule->exists ? $rule->category_id : null;
        if ($previous === $category->id) {
            return $rule;
        }

        $rule->fill([
            'category_id' => $category->id,
            'source' => CategoryRule::SOURCE_LEARNED,
            'created_by' => $user->id,
        ])->save();

        $this->audit->record('rule.learned', $rule, [
            'vendor' => $document->vendor_name,
            'category' => ['from' => $previous, 'to' => $category->id],
        ], $user);

        return $rule;
    }
}
