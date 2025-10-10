<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction\Rule;

use Sirix\Monolog\Redaction\RedactorProcessor;

final class NullRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactorProcessor $processor): ?string
    {
        return null;
    }
}
