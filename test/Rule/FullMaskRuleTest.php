<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\FullMaskRule;
use stdClass;

use function array_keys;
use function array_map;
use function count;
use function is_array;
use function range;
use function str_repeat;
use function strlen;

final class FullMaskRuleTest extends TestCase
{
    public function testFullMaskRule(): void
    {
        $rule = new FullMaskRule();
        $processor = new RedactorProcessor(['secret' => $rule], false);
        $record = $this->createRecord(['secret' => 'my_secret_value'], 'FullMaskRule test');
        $processed = $processor($record);

        $expected = str_repeat('*', strlen('my_secret_value'));
        $this->assertSame($expected, $processed->context['secret']);
    }

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

    private function convertNested(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $obj = new stdClass();
                    foreach ($value as $k => $v) {
                        $obj->{$k} = is_array($v) ? $this->convertNested($v) : $v;
                    }
                    $value = $obj;
                } else {
                    $value = array_map(fn ($v) => is_array($v) ? $this->convertNested($v) : $v, $value);
                }
            }
        }

        return $data;
    }

    private function isAssoc(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
