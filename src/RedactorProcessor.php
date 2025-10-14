<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction;

use InvalidArgumentException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use ReflectionClass;
use ReflectionProperty;
use Sirix\Monolog\Redaction\Exception\RedactorReflectionException;
use Sirix\Monolog\Redaction\Rule\RedactionRuleInterface;
use Traversable;
use UnitEnum;

use function array_merge;
use function file_exists;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_int;
use function is_object;
use function is_scalar;
use function iterator_to_array;
use function mb_substr;

final class RedactorProcessor implements ProcessorInterface
{
    /** @var array<string, array<string, mixed>|RedactionRuleInterface> */
    private array $rules = [];

    /** @var array<int, RedactionRuleInterface> */
    private array $globalRules = [];

    private string $replacement = '*';
    private string $template = '%s';
    private ?int $lengthLimit = null;
    private bool $processObjects = true;

    /** @var array<class-string, ReflectionClass<object>> */
    private static array $reflectionCache = [];

    /**
     * @param array<int|string, array<string, mixed>|RedactionRuleInterface> $customRules
     */
    public function __construct(array $customRules = [], bool $useDefaultRules = true)
    {
        $baseRules = $useDefaultRules ? $this->loadDefaultRules() : [];
        $mergedRules = array_merge($baseRules, $customRules);

        foreach ($mergedRules as $key => $rule) {
            $rule = $this->validateRule($rule);

            if (is_int($key)) {
                if ($rule instanceof RedactionRuleInterface) {
                    $this->globalRules[] = $rule;
                } else {
                    throw new InvalidArgumentException('Global rules must be RedactionRuleInterface instances');
                }
            } else {
                $this->rules[$key] = $rule;
            }
        }
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
        if (is_scalar($value)) {
            foreach ($this->globalRules as $rule) {
                $value = $this->truncate($rule->apply((string) $value, $this));
            }

            return;
        }

        if (is_array($value)) {
            if ([] === $rules) {
                return;
            }

            foreach ($value as $key => &$item) {
                $this->processChild($key, $item, $rules);
            }
            unset($item);

            return;
        }

        if (is_object($value)) {
            if (! $this->processObjects || $value instanceof UnitEnum) {
                return;
            }

            $this->processObject($value, $rules);
        }
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
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @throws RedactorReflectionException
     */
    private function processObject(object $object, array $rules): void
    {
        $ref = self::$reflectionCache[$object::class] ??= new ReflectionClass($object::class);

        $processedNames = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            $processedNames[] = $name;

            $currentValue = $prop->getValue($object);

            if ($prop->isReadOnly()) {
                if (is_array($currentValue) || is_object($currentValue)) {
                    $this->processValue($currentValue, $rules);
                }

                continue;
            }

            $this->processChild($name, $currentValue, $rules);
            $prop->setValue($object, $currentValue);
        }

        $dynamicProps = get_object_vars($object);
        foreach ($dynamicProps as $name => $currentValue) {
            if (in_array($name, $processedNames, true)) {
                continue;
            }

            $this->processChild($name, $currentValue, $rules);
            $object->{$name} = $currentValue;
        }

        if ($object instanceof Traversable) {
            $array = iterator_to_array($object);
            foreach ($array as $key => &$item) {
                $this->processChild($key, $item, $rules);
            }
            unset($item);
        }
    }

    private function truncate(?string $value): string
    {
        if (null === $value) {
            return '';
        }

        if (null !== $this->lengthLimit && $this->lengthLimit > 0) {
            return mb_substr($value, 0, $this->lengthLimit);
        }

        return $value;
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
        $defaultRulesFile = __DIR__ . '/Rule/Default/default_rules.php';
        if (! file_exists($defaultRulesFile)) {
            return [];
        }

        /** @var array<string, RedactionRuleInterface> $rules */
        $rules = require $defaultRulesFile;

        return $rules;
    }
}
