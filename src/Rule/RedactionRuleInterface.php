<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction\Rule;

use Sirix\Monolog\Redaction\RedactorProcessor;

interface RedactionRuleInterface
{
    public function apply(string $value, RedactorProcessor $processor): ?string;
}
