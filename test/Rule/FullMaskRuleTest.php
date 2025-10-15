<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\FullMaskRule;
use Test\Sirix\Monolog\Redaction\LogRecordTrait;
use Test\Sirix\Monolog\Redaction\NestedArrayConversionTrait;

use function str_repeat;
use function strlen;

final class FullMaskRuleTest extends TestCase
{
    use NestedArrayConversionTrait;
    use LogRecordTrait;

    /**
     * @throws RedactorReflectionException
     */
    public function testFullMaskRule(): void
    {
        $rule = new FullMaskRule();
        $processor = new RedactorProcessor(['secret' => $rule], false);
        $record = $this->createRecord(['secret' => 'my_secret_value'], 'FullMaskRule test');
        $processed = $processor($record);

        $expected = str_repeat('*', strlen('my_secret_value'));
        $this->assertSame($expected, $processed->context['secret']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testCustomReplacement(): void
    {
        $rule = new FullMaskRule();
        $processor = new RedactorProcessor(['secret' => $rule], false);
        $processor->setReplacement('#');

        $record = $this->createRecord(['secret' => 'my_secret_value'], 'Custom replacement test');
        $processed = $processor($record);

        $expected = str_repeat('#', strlen('my_secret_value'));
        $this->assertSame($expected, $processed->context['secret']);
    }
}
