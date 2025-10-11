<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\EmailRule;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

final class EmailRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testEmailRedaction(): void
    {
        $rule = new EmailRule();
        $processor = new RedactorProcessor(['email' => $rule], false);
        $record = $this->createRecord(['email' => 'john.doe@example.com'], 'Email redaction test');
        $processed = $processor($record);

        $this->assertSame('joh****@example.com', $processed->context['email']);
    }

    public function testShortEmailRedaction(): void
    {
        $rule = new EmailRule();
        $processor = new RedactorProcessor(['email' => $rule], false);
        $record = $this->createRecord(['email' => 'joe@example.com'], 'Short email redaction test');
        $processed = $processor($record);

        $this->assertSame('joe****@example.com', $processed->context['email']);
    }

    public function testEmailRedactionInNestedStructures(): void
    {
        $rule = new EmailRule();
        $processor = new RedactorProcessor([
            'user' => [
                'contact' => [
                    'email' => $rule,
                ],
            ],
        ], false);

        $record = $this->createRecord([
            'user' => [
                'contact' => [
                    'email' => 'alice.smith@company.org',
                ],
            ],
        ], 'Nested email redaction test');

        $processed = $processor($record);

        $this->assertSame('ali****@company.org', $processed->context['user']->contact->email);
    }

    public function testNonEmailValue(): void
    {
        $rule = new EmailRule();
        $processor = new RedactorProcessor(['value' => $rule], false);
        $record = $this->createRecord(['value' => 'This is not an email'], 'Non-email value test');
        $processed = $processor($record);

        $this->assertSame('Thi*************mail', $processed->context['value']);
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
