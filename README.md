# Monolog Redaction Processor

Monolog processor for redacting sensitive information in logs.

This library provides a Processor for Monolog 3 that traverses your log context (arrays, objects, iterables) and masks sensitive values using pluggable rules. It ships with a sensible set of default rules (card data, emails, names, IPs, etc.) and lets you add your own or override per key.

- PHP 8.1–8.4
- Monolog ^3.0
- License: MIT

## Installation

Install via Composer:

```
composer require sirix/monolog-redaction
```

## Quick start

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Sirix\Monolog\Redaction\RedactorProcessor;
use Sirix\Monolog\Redaction\Rule\StartEndRule;
use Sirix\Monolog\Redaction\Rule\EmailRule;
use Sirix\Monolog\Redaction\Rule\NameRule;

$logger = new Logger('app');
$logger->pushHandler(new StreamHandler('php://stdout'));

// By default, the processor loads built‑in rules. You can pass custom rules below.
$processor = new RedactorProcessor([
    // Overwrite or add rules per key
    'card_number' => new StartEndRule(6, 4),
    'user' => [ // nested structure rules
        'email' => new EmailRule(),
        'name'  => new NameRule(),
    ],
]);

// Optional tuning
$processor->setReplacement('*');       // character used to mask
$processor->setTemplate('%s');          // how the mask is rendered; e.g. '[%s]' to wrap
$processor->setLengthLimit(null);       // limit the resulting masked string length (or null for no limit)

$logger->pushProcessor($processor);

$logger->info('User checkout', [
    'card_number' => '1234567890123456',
    'user' => [
        'email' => 'john.doe@example.com',
        'name'  => 'John Doe',
        'phone' => '+44123456789012',
    ],
]);
```

Example output (stdout):

```
[info] app: User checkout {"card_number":"123456******3456","user":{"email":"joh****@example.com","name":"J*** D**","phone":"+4412****12"}}
```

Note: Exact output format depends on your handler/formatter. The masking shown reflects the default rules plus the ones configured above.

## How it works

- The processor recursively walks through scalars in your context and applies a rule when a key matches.
- Scalars at the top level (no key) are processed by all rules that operate on plain strings.
- For arrays/objects, you can specify nested rule maps that apply to the child structure.

## Default rules

By default, `RedactorProcessor` loads a curated set of rules for common sensitive fields (card numbers/PAN, CVV, expiry, names, emails, phone, IPs, addresses, tokens, 3‑D Secure fields, etc.). See `src/Rule/Default/default_rules.php` for the complete list.

To disable default rules and use only your own:

```php
$processor = new RedactorProcessor(customRules: [], useDefaultRules: false);
```

## Built‑in rule types

These rules live under `Sirix\Monolog\Redaction\Rule` and can be combined as needed:

- StartEndRule($visibleStart, $visibleEnd): masks the middle part of a string, keeping given number of characters at the start/end.
- EmailRule: masks the local part of an email, keeping the first 3 characters and the full domain.
- PhoneRule: masks digits in the middle of a phone number, keeping the first 4 and last 2 digits when possible.
- FullMaskRule: replaces the entire value with the replacement character(s).
- FixedValueRule($replacement): always outputs the provided constant string (e.g., `*` or `**/****`).
- NameRule: masks personal names leaving just initials and/or a few characters as defined by the rule.
- NullRule: sets the value to null.

If you need a custom masking strategy, implement `RedactionRuleInterface`:

```php
use Sirix\Monolog\Redaction\Rule\RedactionRuleInterface;
use Sirix\Monolog\Redaction\RedactorProcessor;

final class MyRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactorProcessor $processor): ?string
    {
        // Return the masked string, or null to indicate no change
        return '***';
    }
}
```

## Processor options

- setReplacement(string $char): character used to construct masks (default `*`).
- setTemplate(string $template): a `sprintf` template applied to the mask string (default `'%s'`). For example, `'[%s]'` wraps mask in brackets.
- setLengthLimit(?int $limit): if set, truncates the resulting masked value to at most this length.
- setProcessObjects(bool $processObjects): controls whether objects should be processed (default `true`). When set to `false`, objects are left untouched during redaction.

These options are used by rules that build masks based on hidden length (e.g., StartEndRule, PhoneRule).

## Testing & QA

This repository includes a PHPUnit test suite and tooling configs.

- Run tests: `composer test`
- Static analysis: `composer phpstan`
- Code style check: `composer cs-check`
- Auto‑fix style: `composer cs-fix`

## Versioning

- PHP: ~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0
- Monolog: ^3.0

## License

MIT © Sirix
