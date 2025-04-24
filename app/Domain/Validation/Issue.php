<?php

declare(strict_types=1);

namespace App\Domain\Validation;

use App\Enums\FieldKey;
use App\Enums\IssueCode;
use App\Enums\IssueSeverity;

final readonly class Issue
{
    /**
     * @param  array<string, scalar|null>  $context  values the UI needs to explain the problem
     */
    public function __construct(
        public IssueCode $code,
        public IssueSeverity $severity,
        public ?FieldKey $field = null,
        public array $context = [],
    ) {}

    /** @param  array<string, scalar|null>  $context */
    public static function blocking(IssueCode $code, ?FieldKey $field = null, array $context = []): self
    {
        return new self($code, IssueSeverity::Blocking, $field, $context);
    }

    /** @param  array<string, scalar|null>  $context */
    public static function warning(IssueCode $code, ?FieldKey $field = null, array $context = []): self
    {
        return new self($code, IssueSeverity::Warning, $field, $context);
    }

    /** Same problem on the same field, regardless of the numbers in the message. */
    public function key(): string
    {
        return $this->code->value.':'.($this->field->value ?? '');
    }
}
