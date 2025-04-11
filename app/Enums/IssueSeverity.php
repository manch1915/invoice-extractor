<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueSeverity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
}
