<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\OffsetRule;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

final class OffsetRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    /**
     * @throws RedactorReflectionException
     */
    public function testSimpleOffsetRule(): void
    {
        $rule = new OffsetRule(3);
        $processor = new RedactorProcessor(['password' => $rule], false);
        $record = $this->createRecord(['username' => 'alice', 'password' => 'secret123'], 'User login');
        $processed = $processor($record);

        $this->assertSame('sec******', $processed->context['password']);
        $this->assertSame('alice', $processed->context['username']); // Unaffected field
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testOffsetRuleInNestedArray(): void
    {
        $processor = new RedactorProcessor([
            'user' => [
                'password' => new OffsetRule(2),
                'token' => new OffsetRule(4),
            ],
        ], false);

        $record = $this->createRecord([
            'user' => [
                'username' => 'bob',
                'password' => 'secret123',
                'token' => 'abcd1234',
            ],
        ], 'Nested user');

        $processed = $processor($record);

        $this->assertSame('se*******', $processed->context['user']->password);
        $this->assertSame('abcd****', $processed->context['user']->token);
        $this->assertSame('bob', $processed->context['user']->username);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testNegativeOffset(): void
    {
        $rule = new OffsetRule(-2);
        $processor = new RedactorProcessor(['password' => $rule], false);
        $record = $this->createRecord(['password' => 'secret123'], 'Negative offset test');
        $processed = $processor($record);

        $this->assertSame('*******23', $processed->context['password']);
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
