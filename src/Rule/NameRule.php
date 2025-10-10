<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction\Rule;

use Sirix\Monolog\Redaction\RedactorProcessor;

use function preg_replace;

final class NameRule extends AbstractStartEndRule implements RedactionRuleInterface
{
    public function __construct()
    {
        parent::__construct(2, 2);
    }

    public function apply(string $value, RedactorProcessor $processor): string
    {
        $masked = preg_replace('/\b(\w{2})\w*(\w)\b/', '$1***$2', $value);

        if (null === $masked || $masked === $value) {
            return parent::apply($value, $processor);
        }

        return $masked;
    }
}
