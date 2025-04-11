<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentSource: string
{
    case Upload = 'upload';
    case Email = 'email';
    case Api = 'api';
    case Demo = 'demo';
}
