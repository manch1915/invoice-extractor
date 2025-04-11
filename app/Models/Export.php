<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExportStatus;
use App\Enums\ExportTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property ExportTarget $target
 * @property ExportStatus $status
 * @property ?CarbonImmutable $finished_at
 */
#[Fillable(['target', 'status', 'document_count', 'file_path', 'external_url', 'error', 'user_id', 'finished_at'])]
class Export extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'target' => ExportTarget::class,
            'status' => ExportStatus::class,
            'finished_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsToMany<Document, $this> */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class)->withPivot('external_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
