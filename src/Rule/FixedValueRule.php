<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction\Rule;

use Sirix\Monolog\Redaction\RedactorProcessor;

final class FixedValueRule implements RedactionRuleInterface
{
    public function __construct(private readonly string $value) {}

    public function apply(string $value, RedactorProcessor $processor): string
    {
        return $this->value;
    }
}
