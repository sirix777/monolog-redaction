<?php

declare(strict_types=1);

namespace Test\Sirix\Monolog\Redaction;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\OffsetRule;
use Sirix\Monolog\Redaction\Rule\StartEndRule;
use stdClass;

enum Method: string
{
    case GET = 'GET';
    case POST = 'POST';
}

final class RedactorProcessorTest extends TestCase
{
    /**
     * @throws RedactorReflectionException
     */
    public function testProcessesSimpleKey(): void
    {
        $processor = new RedactorProcessor([
            'password' => new OffsetRule(3),
            'token' => new OffsetRule(4),
        ], false);

        $record = $this->createRecord([
            'username' => 'alice',
            'password' => 'secret123',
            'credentials' => [
                'password' => 'supersecret',
                'token' => 'abcd1234',
            ],
        ]);

        $processed = $processor($record);

        $this->assertSame('sec******', $processed->context['password']);
        $this->assertSame('alice', $processed->context['username']);
        $this->assertSame('sup********', $processed->context['credentials']['password']);
        $this->assertSame('abcd****', $processed->context['credentials']['token']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testProcessesNestedArray(): void
    {
        $processor = new RedactorProcessor([
            'user' => [
                'password' => new OffsetRule(2),
                'token' => new OffsetRule(4),
            ],
        ], false);

        $userArray = [
            'user' => [
                'username' => 'bob',
                'password' => 'secret123',
                'token' => 'abcd1234',
            ],
        ];

        $record = $this->createRecord($userArray);

        $processed = $processor($record);

        $this->assertSame('se*******', $processed->context['user']['password']);
        $this->assertSame('abcd****', $processed->context['user']['token']);
        $this->assertSame('bob', $processed->context['user']['username']);
        $this->assertSame('secret123', $userArray['user']['password']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testProcessesNestedArrayWithTopLevelRules(): void
    {
        $processor = new RedactorProcessor([
            'password' => new OffsetRule(2),
            'token' => new OffsetRule(4),
        ], false);

        $record = $this->createRecord([
            'user' => [
                'username' => 'bob',
                'password' => 'secret123',
                'token' => 'abcd1234',
            ],
        ]);

        $processed = $processor($record);

        $this->assertSame('se*******', $processed->context['user']['password']);
        $this->assertSame('abcd****', $processed->context['user']['token']);
        $this->assertSame('bob', $processed->context['user']['username']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testProcessesObjectProperties(): void
    {
        $user = new stdClass();
        $user->username = 'carol';
        $user->password = 'mypass';

        $processor = new RedactorProcessor([
            'password' => new OffsetRule(2),
        ], false);

        $record = $this->createRecord(['user' => $user]);

        $processed = $processor($record);

        $this->assertInstanceOf(stdClass::class, $processed->context['user']);
        $this->assertSame('my****', $processed->context['user']->password);
        $this->assertSame('carol', $processed->context['user']->username);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testProcessesPartialMaskWithStartEndRule(): void
    {
        $processor = new RedactorProcessor([
            'secret' => new StartEndRule(2, 3),
        ], false);

        $record = $this->createRecord([
            'secret' => 'my_secret_value',
        ]);

        $processor->setTemplate('%s(redacted)');
        $processed = $processor($record);

        $this->assertSame('my**********(redacted)', $processed->context['secret']);
    }

    public function testHandlesNestedObjectsAndArrays(): void
    {
        $nested = new stdClass();
        $nested->field1 = 'abcdef';
        $nested->field2 = '123456';

        $processor = new RedactorProcessor([
            'nested' => [
                'field1' => new OffsetRule(2),
                'field2' => new OffsetRule(3),
            ],
        ], false);

        $record = $this->createRecord(['nested' => $nested]);
        $processed = $processor($record);

        $this->assertSame('ab****', $processed->context['nested']->field1);
        $this->assertSame('123***', $processed->context['nested']->field2);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testAllowsDisablingDefaultRules(): void
    {
        $processor = new RedactorProcessor([], false);

        $record = $this->createRecord([
            'password' => 'mypassword',
            'token' => 'abcd',
        ]);

        $processed = $processor($record);

        $this->assertSame('mypassword', $processed->context['password']);
        $this->assertSame('abcd', $processed->context['token']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testDoesNotModifyEnumOrFail(): void
    {
        $processor = new RedactorProcessor([], false);

        $record = $this->createRecord([
            'method' => Method::POST,
        ]);

        $processed = $processor($record);

        $this->assertSame(Method::POST, $processed->context['method']);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testSkipsReadonlyProperty(): void
    {
        $obj = new class {
            public readonly string $name;
            public string $token = 'abcd1234';

            public function __construct()
            {
                $this->name = 'readonly';
            }
        };

        $processor = new RedactorProcessor([
            'token' => new OffsetRule(2),
        ], false);

        $record = $this->createRecord(['obj' => $obj]);

        $processed = $processor($record);

        $this->assertSame('readonly', $processed->context['obj']->name);
        $this->assertSame('ab******', $processed->context['obj']->token);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testDisablesObjectProcessing(): void
    {
        $user = new stdClass();
        $user->username = 'dave';
        $user->password = 'supersecret';

        $processor = new RedactorProcessor([
            'password' => new OffsetRule(3),
        ], false);

        $processor->setProcessObjects(false);

        $record = $this->createRecord(['user' => $user]);
        $processed = $processor($record);

        $this->assertInstanceOf(stdClass::class, $processed->context['user']);
        $this->assertSame('supersecret', $processed->context['user']->password);
        $this->assertSame('dave', $processed->context['user']->username);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testEnablesObjectProcessing(): void
    {
        $user = new stdClass();
        $user->username = 'eve';
        $user->password = 'topsecret';

        $processor = new RedactorProcessor([
            'password' => new OffsetRule(4),
        ], false);

        $processor->setProcessObjects(true);

        $record = $this->createRecord(['user' => $user]);
        $processed = $processor($record);

        $this->assertInstanceOf(stdClass::class, $processed->context['user']);
        $this->assertSame('tops*****', $processed->context['user']->password);
        $this->assertSame('eve', $processed->context['user']->username);
        $this->assertSame('topsecret', $user->password);
    }

    /**
     * @throws RedactorReflectionException
     */
    public function testDeeplyNestedObjectCloningAndImmutability(): void
    {
        $profile = new class {
            public string $email = 'alice@example.com';
            private string $token = 'abcd1234';
            public readonly string $readonlyField;

            public function __construct()
            {
                $this->readonlyField = 'cannotChange';
            }

            public function getToken(): string
            {
                return $this->token;
            }
        };

        $user = new class($profile) {
            public string $username = 'alice';
            public string $password = 'supersecret';

            public function __construct(public readonly object $profile) {}
        };

        $data = new class($user) {
            public array $meta = ['trace' => 'xyz123'];

            public function __construct(public object $user) {}
        };

        $processor = new RedactorProcessor([
            'readonlyField' => new StartEndRule(2, 2),
            'user' => [
                'password' => new OffsetRule(3),
                'profile' => [
                    'email' => new StartEndRule(2, 2),
                    'token' => new OffsetRule(4),
                ],
            ],
            'meta' => [
                'trace' => new OffsetRule(2),
            ],
        ], false);

        $record = $this->createRecord(['data' => $data]);

        $processed = $processor($record);

        $this->assertSame('supersecret', $data->user->password, 'Original password must remain intact');
        $this->assertSame('abcd1234', $data->user->profile->getToken(), 'Original token must remain intact');
        $this->assertSame('xyz123', $data->meta['trace'], 'Original meta must remain intact');

        $processedUser = $processed->context['data']->user;
        $processedProfile = $processedUser->profile;

        $this->assertSame('sup********', $processedUser->password);
        $this->assertSame('al*************om', $processedProfile->email);
        $this->assertSame('abcd****', $processedProfile->token);
        $this->assertSame('ca********ge', $processedProfile->readonlyField);
        $this->assertSame('xy****', $processed->context['data']->meta['trace']);

        $this->assertNotSame($data, $processed->context['data']);
        $this->assertNotSame($data->user, $processedUser);
        $this->assertNotSame($data->user->profile, $processedProfile);
    }

    private function createRecord(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: $context,
            extra: []
        );
    }
}
