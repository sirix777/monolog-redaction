<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\PhoneRule;
use Test\Sirix\Monolog\Redaction\LogRecordTrait;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

final class PhoneRuleTest extends TestCase
{
    use NestedArrayConversionTrait;
    use LogRecordTrait;

    /**
     * @throws RedactorReflectionException
     */
    public function testPhoneNumberRedaction(): void
    {
        $rule = new PhoneRule();
        $processor = new RedactorProcessor(['phone' => $rule], false);
        $record = $this->createRecord(['phone' => '1234567890'], 'Phone redaction test');
        $processed = $processor($record);

        $this->assertSame('1234****90', $processed->context['phone']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testInternationalPhoneNumberRedaction(): void
    {
        $rule = new PhoneRule();
        $processor = new RedactorProcessor(['phone' => $rule], false);
        $record = $this->createRecord(['phone' => '+44123456789012'], 'International phone redaction test');
        $processed = $processor($record);

        $this->assertSame('+4412****12', $processed->context['phone']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testFormattedPhoneNumberRedaction(): void
    {
        $rule = new PhoneRule();
        $processor = new RedactorProcessor(['phone' => $rule], false);
        $record = $this->createRecord(['phone' => '123-456-7890'], 'Formatted phone redaction test');
        $processed = $processor($record);

        $this->assertSame('123-******90', $processed->context['phone']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testPhoneRedactionInNestedStructures(): void
    {
        $rule = new PhoneRule();
        $processor = new RedactorProcessor([
            'user' => [
                'contact' => [
                    'phone' => $rule,
                ],
            ],
        ], false);

        $record = $this->createRecord([
            'user' => [
                'contact' => [
                    'phone' => '9876543210',
                ],
            ],
        ], 'Nested phone redaction test');

        $processed = $processor($record);

        $this->assertSame('9876****10', $processed->context['user']->contact->phone);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testNonPhoneValue(): void
    {
        $rule = new PhoneRule();
        $processor = new RedactorProcessor(['value' => $rule], false);
        $record = $this->createRecord(['value' => 'This is not a phone number'], 'Non-phone value test');
        $processed = $processor($record);

        $this->assertSame('This********************er', $processed->context['value']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testShortPhoneNumber(): void
    {
        $rule = new PhoneRule();
        $processor = new RedactorProcessor(['phone' => $rule], false);
        $record = $this->createRecord(['phone' => '12345'], 'Short phone number test');
        $processed = $processor($record);

        $this->assertSame('1****', $processed->context['phone']);
    }
}
