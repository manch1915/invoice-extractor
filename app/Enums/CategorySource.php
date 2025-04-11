<?php

declare(strict_types=1);

namespace App\Enums;

enum CategorySource: string
{
    case VendorRule = 'rule';
    case Keyword = 'keyword';
    case Llm = 'llm';
    case User = 'user';
}
