<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction;

use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\OffsetRule;
use stdClass;

final class RedactorProcessorObjectViewModeTest extends TestCase
{
    use LogRecordTrait;

    public function testDefaultObjectViewModeIsCopy(): void
    {
        $processor = new RedactorProcessor([], false);

        $this->assertSame(ObjectViewModeEnum::Copy, $processor->getObjectViewMode());
    }

    public function testPublicArrayModeBuildsArrayFromPublicPropsOnly(): void
    {
        $obj = new class {
            public string $username = 'alice';
            public string $password = 'supersecret';
            private string $secret = 'hidden';

            public function getSecret(): string
            {
                return $this->secret;
            }
        };

        $processor = new RedactorProcessor([
            'user' => [
                'password' => new OffsetRule(3),
                'secret' => new OffsetRule(2),
            ],
        ], false);

        $processor->setObjectViewMode(ObjectViewModeEnum::PublicArray);

        $record = $this->createRecord(['user' => $obj], convertNested: false);
        $processed = $processor($record);

        $this->assertIsArray($processed->context['user']);
        $this->assertSame('alice', $processed->context['user']['username']);
        $this->assertSame('sup********', $processed->context['user']['password']);
        $this->assertArrayNotHasKey('secret', $processed->context['user']);

        $this->assertSame('supersecret', $obj->password);
        $this->assertSame('hidden', $obj->getSecret());
    }

    public function testSkipModeMasksObjectsAsString(): void
    {
        $user = new stdClass();
        $user->username = 'bob';
        $user->password = 'verysecret';

        $processor = new RedactorProcessor([
            'user' => [
                'password' => new OffsetRule(3),
            ],
        ], false);

        $processor->setObjectViewMode(ObjectViewModeEnum::Skip);

        $record = $this->createRecord(['user' => $user], convertNested: false);
        $processed = $processor($record);

        $this->assertIsString($processed->context['user']);
        $this->assertSame('[object stdClass]', $processed->context['user']);
    }
}
