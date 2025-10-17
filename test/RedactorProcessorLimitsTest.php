<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction;

use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\OffsetRule;
use Sirix\Monolog\Redaction\Rule\StartEndRule;
use stdClass;

use function array_map;

final class RedactorProcessorLimitsTest extends TestCase
{
    use LogRecordTrait;

    public function testDefaultsKeepBehavior(): void
    {
        $processor = new RedactorProcessor([
            'password' => new OffsetRule(3),
            'token' => new OffsetRule(4),
        ], false);

        $record = $this->createRecord([
            'password' => 'secret123',
            'token' => 'abcd1234',
        ]);

        $processed = $processor($record);

        $this->assertSame('sec******', $processed->context['password']);
        $this->assertSame('abcd****', $processed->context['token']);
    }

    public function testMaxDepthOnArraysWithPlaceholder(): void
    {
        $processor = new RedactorProcessor([
            'user' => [
                'password' => new OffsetRule(3),
                'profile' => [
                    'email' => new StartEndRule(2, 2),
                ],
            ],
        ], false);

        $processor->setMaxDepth(1);
        $processor->setOverflowPlaceholder('…');

        $record = $this->createRecord([
            'user' => [
                'password' => 'supersecret',
                'profile' => [
                    'email' => 'alice@example.com',
                ],
            ],
        ]);

        $processed = $processor($record);

        $this->assertSame('…', $processed->context['user']);
    }

    public function testMaxDepthOnArraysWithoutPlaceholderKeepsOriginal(): void
    {
        $processor = new RedactorProcessor([
            'user' => [
                'password' => new OffsetRule(3),
            ],
        ], false);

        $processor->setMaxDepth(1);

        $original = [
            'user' => [
                'password' => 'supersecret',
                'other' => 'value',
            ],
        ];
        $record = $this->createRecord($original, convertNested: false);

        $processed = $processor($record);

        $this->assertIsArray($processed->context['user']);
        $this->assertSame($original['user'], $processed->context['user']);
    }

    public function testMaxDepthOnObjectsWithPlaceholder(): void
    {
        $profile = new stdClass();
        $profile->email = 'alice@example.com';

        $user = new stdClass();
        $user->username = 'alice';
        $user->password = 'supersecret';
        $user->profile = $profile;

        $processor = new RedactorProcessor([
            'user' => [
                'password' => new OffsetRule(3),
                'profile' => [
                    'email' => new StartEndRule(2, 2),
                ],
            ],
        ], false);

        $processor->setMaxDepth(1);
        $processor->setOverflowPlaceholder('CUT');

        $record = $this->createRecord(['user' => $user]);
        $processed = $processor($record);

        $this->assertSame('CUT', $processed->context['user']);
    }

    public function testMaxItemsPerContainerStopsFurtherItems(): void
    {
        $processor = new RedactorProcessor([
            'a' => new OffsetRule(2),
            'b' => new OffsetRule(2),
        ], false);

        $processor->setMaxItemsPerContainer(1);

        $record = $this->createRecord([
            'a' => 'abcdef',
            'b' => 'uvwxyz',
        ]);

        $processed = $processor($record);

        $this->assertSame('ab****', $processed->context['a']);
        $this->assertSame('uvwxyz', $processed->context['b']);
    }

    public function testMaxTotalNodesAppliesPlaceholderToCurrentItem(): void
    {
        $processor = new RedactorProcessor([
            'a' => new OffsetRule(2),
            'b' => new OffsetRule(2),
        ], false);

        $processor->setMaxTotalNodes(1);
        $processor->setOverflowPlaceholder('…');

        $events = [];
        $processor->setOnLimitExceededCallback(function(array $info) use (&$events): void {
            $events[] = $info;
        });

        $record = $this->createRecord([
            'a' => 'abcdef',
            'b' => 'uvwxyz',
        ]);

        $processed = $processor($record);

        $this->assertSame('ab****', $processed->context['a']);
        $this->assertSame('…', $processed->context['b']);

        $this->assertNotEmpty($events);
        $this->assertContains('maxTotalNodes', array_map(fn ($e) => $e['type'] ?? null, $events));
    }

    public function testCycleDetectionWithObjectsAndPlaceholder(): void
    {
        $a = new stdClass();
        $b = new stdClass();
        $a->name = 'A';
        $b->name = 'B';
        $a->peer = $b;
        $b->peer = $a; // cycle

        $processor = new RedactorProcessor([
            'name' => new OffsetRule(1),
        ], false);
        $processor->setOverflowPlaceholder('CUT');

        $events = [];
        $processor->setOnLimitExceededCallback(function(array $info) use (&$events): void {
            $events[] = $info;
        });

        $record = $this->createRecord(['obj' => $a]);
        $processed = $processor($record);

        $this->assertNotEmpty($events, 'Expected at least one limit/cycle event');
        $this->assertContains('cycle', array_map(fn ($e) => $e['type'] ?? null, $events));

        $this->assertInstanceOf(stdClass::class, $processed->context['obj']);
        $this->assertInstanceOf(stdClass::class, $processed->context['obj']->peer);
        $this->assertSame('CUT', $processed->context['obj']->peer->peer);
    }

    public function testCallbackInfoFieldsShape(): void
    {
        $processor = new RedactorProcessor([
            'a' => new OffsetRule(1),
            'b' => new OffsetRule(1),
        ], false);

        $processor->setMaxItemsPerContainer(1);

        $seen = null;
        $processor->setOnLimitExceededCallback(function(array $info) use (&$seen): void {
            $seen = $info;
        });

        $record = $this->createRecord(['a' => 'xx', 'b' => 'yy']);
        $processor($record);

        $this->assertIsArray($seen);
        $this->assertArrayHasKey('type', $seen);
        $this->assertArrayHasKey('depth', $seen);
        $this->assertArrayHasKey('nodesVisited', $seen);
        $this->assertIsInt($seen['depth']);
        $this->assertIsInt($seen['nodesVisited']);
    }
}
