<?php

declare(strict_types=1);

namespace Sirix\Monolog\Redaction;

use InvalidArgumentException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Sirix\Monolog\Redaction\Rule\RedactionRuleInterface;
use Traversable;

use function array_map;
use function array_merge;
use function file_exists;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_scalar;
use function iterator_to_array;

final class RedactorProcessor implements ProcessorInterface
{
    /** @var array<string, array<string, mixed>|RedactionRuleInterface> */
    private array $rules = [];

    private string $replacement = '*';
    private string $template = '%s';
    private ?int $lengthLimit = null;

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $customRules
     */
    public function __construct(array $customRules = [], bool $useDefaultRules = true)
    {
        $baseRules = $useDefaultRules ? $this->loadDefaultRules() : [];
        $mergedRules = array_merge($baseRules, $customRules);

        $this->rules = array_map(fn ($rule) => $this->validateRule($rule), $mergedRules);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->processValue($record->context, $this->rules));
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

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processValue(mixed $value, array $rules): mixed
    {
        if (is_scalar($value)) {
            foreach ($rules as $rule) {
                if ($rule instanceof RedactionRuleInterface) {
                    return $rule->apply((string) $value, $this);
                }
            }

            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->processChild($key, $item, $rules);
            }

            return $value;
        }

        if (is_object($value)) {
            $keys = $value instanceof Traversable
                ? iterator_to_array($value)
                : get_object_vars($value);

            foreach ($keys as $key => $item) {
                $value->{$key} = $this->processChild($key, $item, $rules);
            }

            return $value;
        }

        return $value;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processChild(int|string $key, mixed $item, array $rules): mixed
    {
        $ruleOrRules = $rules[$key] ?? null;

        if ($ruleOrRules instanceof RedactionRuleInterface && is_scalar($item)) {
            return $ruleOrRules->apply((string) $item, $this);
        }

        if (is_array($ruleOrRules) && (is_array($item) || is_object($item))) {
            return $this->processValue($item, $ruleOrRules);
        }

        if (is_array($item) || is_object($item)) {
            return $this->processValue($item, $rules);
        }

        return $item;
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
