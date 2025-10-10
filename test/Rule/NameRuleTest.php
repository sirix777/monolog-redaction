<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\NameRule;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

final class NameRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testNameRedaction(): void
    {
        $rule = new NameRule();
        $processor = new RedactorProcessor(['name' => $rule], false);
        $record = $this->createRecord(['name' => 'John'], 'Name redaction test');
        $processed = $processor($record);

        $this->assertSame('Jo***n', $processed->context['name']);
    }

    public function testLongNameRedaction(): void
    {
        $rule = new NameRule();
        $processor = new RedactorProcessor(['name' => $rule], false);
        $record = $this->createRecord(['name' => 'Alexander'], 'Long name redaction test');
        $processed = $processor($record);

        $this->assertSame('Al***r', $processed->context['name']);
    }

    public function testMultipleNamesRedaction(): void
    {
        $rule = new NameRule();
        $processor = new RedactorProcessor(['fullname' => $rule], false);
        $record = $this->createRecord(['fullname' => 'John Doe Smith'], 'Multiple names redaction test');
        $processed = $processor($record);

        $this->assertSame('Jo***n Do***e Sm***h', $processed->context['fullname']);
    }

    public function testNameRedactionInNestedStructures(): void
    {
        $rule = new NameRule();
        $processor = new RedactorProcessor([
            'user' => [
                'profile' => [
                    'name' => $rule,
                ],
            ],
        ], false);

        $record = $this->createRecord([
            'user' => [
                'profile' => [
                    'name' => 'Maria',
                ],
            ],
        ], 'Nested name redaction test');

        $processed = $processor($record);

        $this->assertSame('Ma***a', $processed->context['user']->profile->name);
    }

    public function testTooShortName(): void
    {
        $rule = new NameRule();
        $processor = new RedactorProcessor(['name' => $rule], false);
        $record = $this->createRecord(['name' => 'Jo'], 'Short name test');
        $processed = $processor($record);

        $this->assertSame('J*', $processed->context['name']);
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
