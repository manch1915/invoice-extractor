<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldStatus: string
{
    case Auto = 'auto';
    case NeedsReview = 'review';
    case Confirmed = 'confirmed';
    case Corrected = 'corrected';

    public function isHumanChecked(): bool
    {
        return $this === self::Confirmed || $this === self::Corrected;
    }
}
