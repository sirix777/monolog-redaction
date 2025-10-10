<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction\Rule;

use Sirix\Monolog\Redaction\RedactorProcessor;

use function str_repeat;
use function strlen;

final class FullMaskRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactorProcessor $processor): string
    {
        return str_repeat($processor->getReplacement(), strlen($value));
    }
}
