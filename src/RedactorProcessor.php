<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction;

use InvalidArgumentException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use ReflectionClass;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\Rule\Default\DefaultRules;
use Sirix\Monolog\Redaction\Rule\RedactionRuleInterface;
use stdClass;
use UnitEnum;

use function array_map;
use function array_merge;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_scalar;
use function property_exists;

final class RedactorProcessor implements ProcessorInterface
{
    /** @var array<string, array<string, mixed>|RedactionRuleInterface> */
    private array $rules = [];

    private string $replacement = '*';
    private string $template = '%s';
    private ?int $lengthLimit = null;
    private bool $processObjects = true;

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $customRules
     */
    public function __construct(array $customRules = [], bool $useDefaultRules = true)
    {
        $this->rules = array_map(
            fn ($rule) => $this->validateRule($rule),
            array_merge($useDefaultRules ? $this->loadDefaultRules() : [], $customRules)
        );
    }

    /**
     * @throws RedactorReflectionException
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $this->processValue($context, $this->rules);

        return $record->with(context: $context);
    }

    public function setReplacement(string $replacement): void
    {
        $this->replacement = $replacement;
    }

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setLengthLimit(?int $lengthLimit): void
    {
        $this->lengthLimit = $lengthLimit;
    }

    public function getLengthLimit(): ?int
    {
        return $this->lengthLimit;
    }

    public function setProcessObjects(bool $processObjects): void
    {
        $this->processObjects = $processObjects;
    }

    public function isProcessObjects(): bool
    {
        return $this->processObjects;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @throws RedactorReflectionException
     */
    private function processValue(mixed &$value, array $rules): void
    {
        if (is_array($value)) {
            foreach ($value as $key => &$item) {
                $this->processChild($key, $item, $rules);
            }
            unset($item);

            return;
        }

        if (is_object($value)) {
            if (! $this->isProcessObjects() || $value instanceof UnitEnum) {
                return;
            }

            $value = $this->createMaskedCopy($value, $rules);
        }
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @throws RedactorReflectionException
     */
    private function createMaskedCopy(object $object, array $rules): object
    {
        $copy = new stdClass();
        $ref = new ReflectionClass($object);

        foreach ($ref->getProperties() as $prop) {
            $name = $prop->getName();
            $value = $prop->getValue($object);

            $copy->{$name} = $this->maskValue($name, $value, $rules);
        }

        foreach (get_object_vars($object) as $name => $value) {
            if (! property_exists($copy, $name)) {
                $copy->{$name} = $this->maskValue($name, $value, $rules);
            }
        }

        return $copy;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @throws RedactorReflectionException
     */
    private function maskValue(string $key, mixed $value, array $rules): mixed
    {
        if (is_scalar($value)) {
            if ($this->hasRuleFor($key, $rules)) {
                return $this->applyRule($key, $value, $rules);
            }

            if ($this->hasRuleFor($key, $this->rules)) {
                return $this->applyRule($key, $value, $this->rules);
            }

            return $value;
        }

        if (is_array($value)) {
            $subRules = $rules[$key] ?? [];
            $this->processValue($value, is_array($subRules) ? $subRules : []);

            return $value;
        }

        if (is_object($value)) {
            $subRules = $rules[$key] ?? [];

            return $this->createMaskedCopy($value, is_array($subRules) ? $subRules : []);
        }

        return $value;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function hasRuleFor(string $key, array $rules): bool
    {
        return isset($rules[$key]) && $rules[$key] instanceof RedactionRuleInterface;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function applyRule(string $key, mixed $value, array $rules): mixed
    {
        $rule = $rules[$key];
        if ($rule instanceof RedactionRuleInterface && is_scalar($value)) {
            return $rule->apply((string) $value, $this);
        }

        return $value;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @throws RedactorReflectionException
     */
    private function processChild(int|string $key, mixed &$item, array $rules): void
    {
        $ruleOrRules = $rules[$key] ?? null;

        if ($ruleOrRules instanceof RedactionRuleInterface) {
            if (null === $item) {
                return;
            }

            $item = $ruleOrRules->apply((string) $item, $this);

            return;
        }

        if (is_array($ruleOrRules) && (is_array($item) || is_object($item))) {
            $this->processValue($item, $ruleOrRules);

            return;
        }

        if (is_array($item) || is_object($item)) {
            $this->processValue($item, $rules);
        }
    }

    /**
     * @return array<string, array<string, mixed>|RedactionRuleInterface>|RedactionRuleInterface
     */
    private function validateRule(mixed $rule): array|RedactionRuleInterface
    {
        if (! ($rule instanceof RedactionRuleInterface) && ! is_array($rule)) {
            throw new InvalidArgumentException('All sensitive keys must be RedactionRule or nested array');
        }

        return $rule;
    }

    /**
     * @return array<string, RedactionRuleInterface>
     */
    private function loadDefaultRules(): array
    {
        return DefaultRules::getAll();
    }
}
