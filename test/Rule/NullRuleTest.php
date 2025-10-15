<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\NullRule;
use Test\Sirix\Monolog\Redaction\LogRecordTrait;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

final class NullRuleTest extends TestCase
{
    use NestedArrayConversionTrait;
    use LogRecordTrait;

    /**
     * @throws RedactorReflectionException
     */
    public function testNullRule(): void
    {
        $rule = new NullRule();
        $processor = new RedactorProcessor(['password' => $rule], false);
        $record = $this->createRecord(['password' => 'secret123'], 'EmptyRule test');
        $processed = $processor($record);

        $this->assertNull($processed->context['password']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testNullRuleWithNestedData(): void
    {
        $processor = new RedactorProcessor([
            'credentials' => [
                'apiKey' => new NullRule(),
                'username' => new NullRule(),
            ],
        ], false);

        $record = $this->createRecord([
            'credentials' => [
                'apiKey' => 'abc123xyz',
                'username' => 'admin',
                'domain' => 'example.com',
            ],
        ], 'Nested null rule test');

        $processed = $processor($record);

        $this->assertNull($processed->context['credentials']->apiKey);
        $this->assertNull($processed->context['credentials']->username);
        $this->assertSame('example.com', $processed->context['credentials']->domain);
    }
}
