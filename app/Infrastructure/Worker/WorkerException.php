<?php

declare(strict_types=1);

namespace App\Infrastructure\Worker;

use RuntimeException;
use Throwable;

class WorkerException extends RuntimeException
{
    public bool $retryable = true;

    public static function unreachable(Throwable $previous): self
    {
        return new self('Document reader is not reachable', 0, $previous);
    }

    public static function failed(Throwable $previous): self
    {
        return new self('Document reader failed: '.$previous->getMessage(), 0, $previous);
    }

    public static function unreadable(string $reason): self
    {
        $e = new self("The file could not be read: {$reason}");
        $e->retryable = false;

        return $e;
    }
}
