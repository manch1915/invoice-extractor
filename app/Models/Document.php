<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Minor;
use App\Enums\CategorySource;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatus;
use App\Enums\IssueSeverity;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property DocumentStatus $status
 * @property DocumentSource $source
 * @property ?CategorySource $category_source
 * @property ?array<string, string> $source_meta
 * @property ?list<string> $warnings
 * @property ?CarbonImmutable $issue_date
 * @property ?CarbonImmutable $due_date
 * @property ?CarbonImmutable $processed_at
 * @property ?CarbonImmutable $reviewed_at
 * @property ?CarbonImmutable $exported_at
 * @property ?int $subtotal
 * @property ?int $tax_amount
 * @property ?int $total
 * @property ?string $tax_rate
 */
#[Fillable([
    'status', 'source', 'source_meta', 'original_filename', 'mime_type', 'size', 'storage_path', 'sha256',
    'doc_type', 'language', 'engine', 'text_source', 'ocr_quality', 'page_count', 'confidence', 'processing_ms',
    'warnings', 'error', 'vendor_id', 'category_id', 'category_source', 'suggested_category',
    'vendor_name', 'vendor_tax_id', 'buyer_name', 'buyer_tax_id', 'document_number', 'issue_date', 'due_date',
    'currency', 'subtotal', 'tax_rate', 'tax_amount', 'total', 'duplicate_of_id', 'auto_approved',
    'review_seconds', 'uploaded_by', 'reviewed_by', 'processed_at', 'reviewed_at', 'exported_at',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'source' => DocumentSource::class,
            'category_source' => CategorySource::class,
            'source_meta' => 'array',
            'warnings' => 'array',
            'issue_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'tax_rate' => 'decimal:2',
            'ocr_quality' => 'float',
            'confidence' => 'float',
            'auto_approved' => 'boolean',
            'processed_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'exported_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Document, $this> */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    /** @return HasMany<DocumentPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(DocumentPage::class)->orderBy('number');
    }

    /** @return HasMany<DocumentField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class);
    }

    /** @return HasMany<LineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(LineItem::class)->orderBy('position');
    }

    /** @return HasMany<DocumentIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(DocumentIssue::class);
    }

    /** @return HasMany<DocumentIssue, $this> */
    public function openIssues(): HasMany
    {
        return $this->issues()->whereNull('resolved_at');
    }

    /** @return BelongsToMany<Export, $this> */
    public function exports(): BelongsToMany
    {
        return $this->belongsToMany(Export::class)->withPivot('external_id');
    }

    public function hasBlockingIssues(): bool
    {
        return $this->openIssues()->where('severity', IssueSeverity::Blocking->value)->exists();
    }

    public function totalDecimal(): ?string
    {
        return $this->total === null ? null : Minor::toDecimal($this->total);
    }

    /** @param  Builder<Document>  $query */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', '!=', DocumentStatus::Rejected->value);
    }
}
