<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\FixedValueRule;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

final class FixedValueRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    /**
     * @throws RedactorReflectionException
     */
    public function testFixedValueRule(): void
    {
        $rule = new FixedValueRule('REDACTED');
        $processor = new RedactorProcessor(['token' => $rule], false);
        $record = $this->createRecord(['token' => 'abcd1234'], 'FixedValueRule test');
        $processed = $processor($record);

        $this->assertSame('REDACTED', $processed->context['token']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testCustomReplacementValue(): void
    {
        $rule = new FixedValueRule('CUSTOM_VALUE');
        $processor = new RedactorProcessor(['secret' => $rule], false);
        $record = $this->createRecord(['secret' => 'sensitive-data'], 'Custom value test');
        $processed = $processor($record);

        $this->assertSame('CUSTOM_VALUE', $processed->context['secret']);
    }

    private function createRecord(array $context, string $message = 'Test'): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: $message,
            context: $this->convertNested($context),
            extra: []
        );
    }
}
