<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;

class UnsupportedFileException extends RuntimeException
{
    public static function forType(string $filename, string $detected): self
    {
        return new self("{$filename} is {$detected}, not a PDF or an image");
    }
}
