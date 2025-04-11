<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Ready = 'ready';
    case Exported = 'exported';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function isEditable(): bool
    {
        return in_array($this, [self::NeedsReview, self::Ready], true);
    }

    public function isExportable(): bool
    {
        return in_array($this, [self::Ready, self::Exported], true);
    }
}
