<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction\Rule;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\NullRule;
use stdClass;

use function array_keys;
use function array_map;
use function count;
use function is_array;
use function range;

final class NullRuleTest extends TestCase
{
    public function testNullRule(): void
    {
        $rule = new NullRule();
        $processor = new RedactorProcessor(['password' => $rule], false);
        $record = $this->createRecord(['password' => 'secret123'], 'EmptyRule test');
        $processed = $processor($record);

        $this->assertNull($processed->context['password']);
    }

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
