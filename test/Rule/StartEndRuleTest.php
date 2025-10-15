<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\StartEndRule;
use Test\Sirix\Monolog\Redaction\LogRecordTrait;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

final class StartEndRuleTest extends TestCase
{
    use NestedArrayConversionTrait;
    use LogRecordTrait;

    /**
     * @throws RedactorReflectionException
     */
    public function testPartialMaskWithStartEndVisible(): void
    {
        $processor = new RedactorProcessor([
            'superpupersecret' => new StartEndRule(5, 4),
            'objecthere' => [
                'secret' => new StartEndRule(2, 3),
                'secret2' => new StartEndRule(2, 0),
            ],
        ], false);

        $record = $this->createRecord([
            'superpupersecret' => 'superpupersecret',
            'objecthere' => [
                'secret' => 'donttellanyone',
                'secret2' => 'donttellanyone2',
            ],
        ], 'Partial mask test');

        $processed = $processor($record);

        $this->assertSame('super*******cret', $processed->context['superpupersecret']);
        $this->assertSame('do*********one', $processed->context['objecthere']->secret);
        $this->assertSame('do*************', $processed->context['objecthere']->secret2);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testCustomTemplate(): void
    {
        $rule = new StartEndRule(2, 3);
        $processor = new RedactorProcessor(['secret' => $rule], false);
        $processor->setTemplate('%s(redacted)');

        $record = $this->createRecord(['secret' => 'my_secret_value'], 'Sensitive');
        $processed = $processor($record);

        $this->assertSame('my**********(redacted)', $processed->context['secret']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testZeroVisibleStart(): void
    {
        $rule = new StartEndRule(0, 2);
        $processor = new RedactorProcessor(['secret' => $rule], false);
        $record = $this->createRecord(['secret' => 'my_secret_value'], 'Zero start test');
        $processed = $processor($record);

        $this->assertSame('*************ue', $processed->context['secret']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testZeroVisibleEnd(): void
    {
        $rule = new StartEndRule(2, 0);
        $processor = new RedactorProcessor(['secret' => $rule], false);
        $record = $this->createRecord(['secret' => 'my_secret_value'], 'Zero end test');
        $processed = $processor($record);

        $this->assertSame('my*************', $processed->context['secret']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testShortValues(): void
    {
        $rule = new StartEndRule(2, 2);
        $processor = new RedactorProcessor(['short' => $rule], false);

        $cases = [
            ['value' => 'to', 'expected' => 't*'],
            ['value' => 'tom', 'expected' => 't**'],
            ['value' => 't', 'expected' => 't'],
            ['value' => null, 'expected' => null],
        ];

        foreach ($cases as $i => $case) {
            $record = $this->createRecord(['short' => $case['value']], 'Short value test ' . ($i + 1));
            $processed = $processor($record);

            $this->assertSame($case['expected'], $processed->context['short'], "Failed on case #{$i}");
        }
    }
}
