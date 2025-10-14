<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction\Exception;

use Exception;
use ReflectionException;
use Throwable;

class RedactorReflectionException extends Exception
{
    public function __construct(
        string $message = 'An error occurred during reflection in the redactor processor',
        ?Throwable $previous = null,
        int $code = 0
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromReflectionException(
        ReflectionException $exception,
        string $message = 'An error occurred during reflection in the redactor processor'
    ): self {
        return new self($message, $exception, $exception->getCode());
    }
}
