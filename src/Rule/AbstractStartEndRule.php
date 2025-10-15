<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction\Rule;

use Sirix\Monolog\Redaction\RedactorProcessor;

use function max;
use function min;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

abstract class AbstractStartEndRule
{
    private const DEFAULT_TEMPLATE = '%s';

    public function __construct(private readonly int $visibleStart, private readonly int $visibleEnd) {}

    public function apply(string $value, RedactorProcessor $processor): string
    {
        $length = strlen($value);
        if (0 === $length) {
            return $value;
        }

        if ($length <= $this->visibleStart + $this->visibleEnd) {
            return substr($value, 0, 1) . str_repeat($processor->getReplacement(), $length - 1);
        }

        $visibleStart = min($this->visibleStart, $length);
        $visibleEnd = min($this->visibleEnd, $length - $visibleStart);
        $hiddenLength = max(0, $length - $visibleStart - $visibleEnd);

        $hidden = str_repeat($processor->getReplacement(), $hiddenLength);
        $placeholder = sprintf($processor->getTemplate(), $hidden);

        $result = substr($value, 0, $visibleStart);

        $isDefaultTemplate = self::DEFAULT_TEMPLATE === $processor->getTemplate();
        $result .= $placeholder;

        if ($isDefaultTemplate && $visibleEnd > 0) {
            $result .= substr($value, -$visibleEnd);
        }

        if (null !== $processor->getLengthLimit()) {
            return substr($result, 0, $processor->getLengthLimit());
        }

        return $result;
    }
}
