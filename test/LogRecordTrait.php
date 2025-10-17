<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;

trait LogRecordTrait
{
    use NestedArrayConversionTrait;

    private function createRecord(array $context, string $message = 'Test', bool $convertNested = true): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: $message,
            context: $convertNested ? $this->convertNested($context) : $context,
            extra: []
        );
    }
}
