<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldKey;
use App\Enums\IssueCode;
use App\Enums\IssueSeverity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property IssueCode $code
 * @property IssueSeverity $severity
 * @property ?FieldKey $field
 * @property ?array<string, scalar|null> $context
 * @property ?CarbonImmutable $resolved_at
 */
#[Fillable(['document_id', 'code', 'severity', 'field', 'context', 'resolved_at', 'resolved_by'])]
class DocumentIssue extends Model
{
    protected function casts(): array
    {
        return [
            'code' => IssueCode::class,
            'severity' => IssueSeverity::class,
            'field' => FieldKey::class,
            'context' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function key(): string
    {
        return $this->code->value.':'.($this->field->value ?? '');
    }
}
