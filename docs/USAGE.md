# Usage

Inject `Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface`.

All Word markers below assume the **default** bundle config (see [CONFIGURATION.md](CONFIGURATION.md)). If you change delimiters in YAML, every character typed in Word must match.

```yaml
# config/packages/nowo_word_template.yaml
nowo_word_template:
    timeout: '%env(int:PROCESS_TIMEOUT)%'   # optional; default 180
    macro_opening: '${'
    macro_closing: '}'
    conditional_if_opening: '${#if'
    conditional_if_closing: '}'
    conditional_endif_opening: '${#endif'
    conditional_endif_closing: '}'
```

With those defaults, scalar placeholders look like `${client_name}` and conditionals like `${#if annex}` … `${#endif annex}`.

---

## Table of contents

- [Scalars and nested keys](#scalars-and-nested-keys)
- [One table with several columns (`TableRows`)](#one-table-with-several-columns-tablerows)
- [Several independent tables in the same `.docx`](#several-independent-tables-in-the-same-docx)
- [One conditional block](#one-conditional-block)
- [Several sibling conditionals in the same document](#several-sibling-conditionals-in-the-same-document)
- [Nested conditionals](#nested-conditionals)
- [Conditionals by data type](#conditionals-by-data-type)
  - [Rule zero: pass a real `bool`](#rule-zero-pass-a-real-bool)
  - [`bool` and `?bool`](#bool-and-bool)
  - [`int` and `?int`](#int-and-int)
  - [`float`](#float)
  - [`string` and `?string`](#string-and-string)
  - [`null` and optional values](#null-and-optional-values)
  - [Arrays and collections](#arrays-and-collections)
  - [`DateTimeInterface`](#datetimeinterface)
  - [Backed enums](#backed-enums)
  - [Doctrine entities and relations](#doctrine-entities-and-relations)
  - [Symfony forms and request parameters](#symfony-forms-and-request-parameters)
  - ["else": two blocks with opposite conditions](#else-two-blocks-with-opposite-conditions)
  - [One of N (switch)](#one-of-n-switch)
  - [Combining conditions: `&&`, `||`, nesting or flattening](#combining-conditions---nesting-or-flattening)
  - [Same block name used several times](#same-block-name-used-several-times)
  - [What happens when…](#what-happens-when)
  - [Where markers can go in Word](#where-markers-can-go-in-word)
- [Expression conditionals](#expression-conditionals) *(1.8, opt-in)*
  - [Enable expression conditionals](#enable-expression-conditionals)
  - [First example](#first-example)
  - [Named block or expression? The classification rule](#named-block-or-expression-the-classification-rule)
  - [What the expression can read](#what-the-expression-can-read)
  - [Operators](#operators)
  - [Expressions by data type](#expressions-by-data-type)
  - [Functions reference](#functions-reference)
  - [Nesting, "else" and one of N](#nesting-else-and-one-of-n)
  - [When to compute in PHP, when in the template](#when-to-compute-in-php-when-in-the-template)
  - [Errors](#errors)
  - [Security](#security)
- [Loops](#loops) *(1.8, opt-in)*
  - [Enable loops](#enable-loops)
  - [Repeat paragraphs](#repeat-paragraphs)
  - [Repeat table rows](#repeat-table-rows)
  - [Loop variables](#loop-variables)
  - [Maps, keys and functions](#maps-keys-and-functions)
  - [Conditions inside a loop and nested loops](#conditions-inside-a-loop-and-nested-loops)
  - [Empty lists](#empty-lists)
  - [Loop errors and limits](#loop-errors-and-limits)
- [Combined example (scalars + 2 tables + 2 conditionals)](#combined-example-scalars--2-tables--2-conditionals)
- [Context types (summary)](#context-types-summary)
- [Listing placeholders and conditional blocks](#listing-placeholders-and-conditional-blocks)
- [Combining with HtmlToWordBundle](#combining-with-htmltowordbundle)
- [Forms in host apps and demos (REQ-TWIG-005)](#forms-in-host-apps-and-demos-req-twig-005)

## Scalars and nested keys

**In Word** (plain text in a paragraph or table cell — do not split the macro across different font runs if you can avoid it):

```text
Contract for ${client.name} in ${client.city}.
Title: ${offer_title}
```

**In PHP:**

```php
$processor->process($templatePath, [
    'offer_title' => 'Maintenance 2026',
    'client' => [
        'name' => 'ACME',
        'city' => 'Madrid',
    ],
]);
```

Nested arrays are flattened to dot keys (`client.name`, `client.city`).

---

## One table with several columns (`TableRows`)

One physical template row → **one** `TableRows`. The first argument (`rowVariable`) is the **anchor** PHPWord uses for `cloneRow`; it must be a placeholder that appears in that row.

**In Word** — insert a 2-column table. Header row is normal text. Data row (the one that will be cloned) contains only the macros:

| TIPO RIESGO | CAPITAL MAXIMO |
|-------------|----------------|
| `${GARANTIAARRAY}` | `${LIMITE_ARRAY}` |

Type exactly `${GARANTIAARRAY}` and `${LIMITE_ARRAY}` (defaults `${` / `}`). Prefer one complete macro per cell / run.

**In PHP** — still a **single** `TableRows`; every column key goes in each row map. Anchor = `GARANTIAARRAY` (must exist in the row; `LIMITE_ARRAY` would also work as anchor):

```php
use Nowo\WordTemplateBundle\Model\TableRows;

$processor->process($templatePath, [
    'garantias' => new TableRows('GARANTIAARRAY', [
        [
            'GARANTIAARRAY' => 'Acopios de materiales',
            'LIMITE_ARRAY'  => 1000,
        ],
        [
            'GARANTIAARRAY' => 'Aduanas',
            'LIMITE_ARRAY'  => 20000,
        ],
    ]),
]);
```

After merge, Word gets two data rows: `Acopios…` / `1000` and `Aduanas` / `20000`.

**Wrong** (will throw `Can not clone row…`): two `TableRows` for the **same** physical row, e.g. one with only `GARANTIAARRAY` and another with only `LIMITE_ARRAY`.

---

## Several independent tables in the same `.docx`

Each table needs its **own** template row and a **distinct** anchor name.

**In Word** — two separate tables:

Table 1 — Garantías

| TIPO RIESGO | CAPITAL MAXIMO |
|-------------|----------------|
| `${GARANTIAARRAY}` | `${LIMITE_ARRAY}` |

Table 2 — Coberturas

| CODE | DESCRIPTION |
|--------|-------------|
| `${COVER_CODE}` | `${COVER_LABEL}` |

**In PHP** — two `TableRows` instances, different anchors and different data:

```php
use Nowo\WordTemplateBundle\Model\TableRows;

$processor->process($templatePath, [
    'garantias' => new TableRows('GARANTIAARRAY', [
        ['GARANTIAARRAY' => 'Acopios', 'LIMITE_ARRAY' => 1000],
        ['GARANTIAARRAY' => 'Aduanas', 'LIMITE_ARRAY' => 20000],
    ]),
    'coberturas' => new TableRows('COVER_CODE', [
        ['COVER_CODE' => 'C1', 'COVER_LABEL' => 'Fire'],
        ['COVER_CODE' => 'C2', 'COVER_LABEL' => 'Theft'],
        ['COVER_CODE' => 'C3', 'COVER_LABEL' => 'Flood'],
    ]),
]);
```

| Rule | Detail |
|------|--------|
| Anchors | `GARANTIAARRAY` ≠ `COVER_CODE` (must be unique across tables) |
| Context keys | `'garantias'` / `'coberturas'` are only PHP keys; Word never sees them |
| Row counts | Can differ (2 vs 3 above) |
| Bundle YAML | No per-table profile — only the `TableRows` objects above |

---

## One conditional block

**In Word** — each marker in its **own paragraph** (Enter between lines). Do not put `${#if …}` mid-sentence in the same paragraph as body text.

Prefer pasting the whole marker in one go (plain text) so Word does not split it across font runs. If Word does split `${#if name}` across several runs, the bundle **heals** that fragmentation before applying conditionals (`BrokenMacroNormalizer`, same idea as PHPWord’s `fixBrokenMacros`).

```text
Preamble always visible.

${#if annex}
This annex paragraph is optional.
More annex text.
${#endif annex}

Closing always visible.
```

**In PHP** — block name in `ConditionalBlock` must match the name after `#if` / `#endif` (`annex`):

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;

$processor->process($templatePath, [
    'show_annex' => new ConditionalBlock('annex', true),  // false → removes the region
]);
```

With defaults, opening marker = `${#if` + ` ` + `annex` + `}` → `${#if annex}`. Closing → `${#endif annex}`.

The second argument must be a `bool`; how to derive it from ints, strings, dates, enums, collections or request data is covered in [Conditionals by data type](#conditionals-by-data-type), and what happens with a missing block or a misplaced marker in [What happens when…](#what-happens-when) / [Where markers can go in Word](#where-markers-can-go-in-word).

---

## Several sibling conditionals in the same document

**In Word:**

```text
${#if annex}
Annex content.
${#endif annex}

${#if vip_section}
VIP-only paragraph.
${#endif vip_section}

Footer always here.
```

**In PHP:**

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;

$processor->process($templatePath, [
    'show_annex' => new ConditionalBlock('annex', $includeAnnex),      // true/false
    'show_vip'   => new ConditionalBlock('vip_section', $isVip),       // true/false
]);
```

Each block name (`annex`, `vip_section`) must be unique. Visibility is independent.

---

## Nested conditionals

**In Word** (inner pair fully inside the outer pair; each marker alone in its paragraph):

```text
${#if optional_funding}
Funding applies.
${#if funding_detail}
Detail: ${funding_note}
${#endif funding_detail}
${#endif optional_funding}
```

**In PHP:**

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;

$processor->process($templatePath, [
    'optional_funding' => new ConditionalBlock('optional_funding', true),
    'funding_detail'   => new ConditionalBlock('funding_detail', false), // inner hidden
    'funding_note'     => 'Horizon Europe grant #12345',
]);
```

Nested blocks resolve **inside-out** (deepest first). There is no `else` / `elseif` — use two blocks with opposite conditions (["else"](#else-two-blocks-with-opposite-conditions)) or one block per option ([One of N](#one-of-n-switch)).

---

## Conditionals by data type

With named blocks the template never evaluates an expression: everything between `${#if ` and `}` is a **block name**, not a condition (`${#if total > 0}` … `${#endif total > 0}` does not compare anything). Visibility is decided in PHP and handed over as a **`bool`**:

```php
Nowo\WordTemplateBundle\Model\ConditionalBlock::__construct(string $blockName, bool $visible)
```

> Since 1.8 the template can also carry the condition: `${#if total > 0}` … `${#endif}` (nameless end marker) with [expression conditionals](#expression-conditionals) (opt-in). The PHP rules below still apply — they are the same decisions — and [When to compute in PHP, when in the template](#when-to-compute-in-php-when-in-the-template) helps choose.

So every named conditional boils down to one question: *how do I turn my value into a reliable `true` / `false`?* This section answers it for every common PHP type. Each expression in the tables is copied verbatim into `tests/Integration/ConditionalsUsageDocTest.php`, which runs it against sample values (and renders a real `.docx` for the patterns further down) — if an expression here and the test drift apart, the suite fails.

Quick reference (details and pitfalls in the subsections):

| Value | Recommended expression | Avoid |
|-------|------------------------|-------|
| `bool` | `$isVip` | — |
| `?bool` | `$accepted === true` | `(bool) $accepted` hides the "unknown" case silently |
| `int` | `$qty > 0` / `$qty !== 0` | `(bool) $qty`, `!empty($qty)` |
| `?int` | `$discount !== null` (0 counts) or `($discount ?? 0) > 0` | `!empty($discount)` when 0 must show |
| `float` | `round($total, 2) > 0.0` | `$total > 0.0` on computed values, `===` between floats |
| `string` | `trim($notes ?? '') !== ''` | `!empty($code)` / `(bool) $code` (`'0'` is falsy) |
| `"yes"`/`"no"` strings | `filter_var($flag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true` | `(bool) 'false'` (is `true`) |
| `array` | `$lines !== []` | `count($lines)` without a comparison |
| `DateTimeInterface` | `$dueDate < $today` (clock-based `$today`) | `new DateTime()` inside the service |
| backed enum | `$invoice->status === InvoiceStatus::Paid` | `$status === 'paid'` (always `false`) |
| Doctrine relation | `$contract->getGuarantor() !== null` | passing the entity itself |
| Doctrine collection | `!$order->getLines()->isEmpty()` | `(bool) $order->getLines()` (always `true`) |
| request parameter | `$request->query->getBoolean('annex')` | `(bool) $request->query->get('annex')` |

### Rule zero: pass a real `bool`

```php
<?php

declare(strict_types=1);

use Nowo\WordTemplateBundle\Model\ConditionalBlock;

new ConditionalBlock('vip_section', $customer->isVip());   // bool → OK
new ConditionalBlock('discount', $order->discountPercent); // int → TypeError
```

The second line throws:

```text
TypeError: Nowo\WordTemplateBundle\Model\ConditionalBlock::__construct(): Argument #2 ($visible) must be of type bool, int given
```

What PHP does with a non-`bool` depends on the **calling** file:

| Calling file | Value passed as `$visible` | Result |
|--------------|----------------------------|--------|
| any | `true` / `false` | used as is |
| `declare(strict_types=1)` | `1`, `0`, `0.0`, `'yes'`, `'0'`, … | `TypeError` |
| any (strict or not) | `null`, array, object | `TypeError` (`null` is never coerced to a non-nullable `bool`) |
| no `strict_types` (weak mode) | `1` / `0` / `0.0` / `''` / `'0'` | coerced: `1` → `true`, the others → `false` |
| no `strict_types` (weak mode) | `'false'`, `'no'`, `'off'` | **`true`** — any non-empty string except `'0'` |

Use `declare(strict_types=1)` and write an explicit comparison (`> 0`, `!== ''`, `=== InvoiceStatus::Paid`) instead of casting: almost every pitfall below is a `(bool)` cast or a truthiness check in disguise.

### `bool` and `?bool`

| Show the block when… | Expression |
|----------------------|------------|
| the flag is set | `$isVip` |
| the flag is **not** set (the "else" block) | `!$isVip` |
| a nullable flag is explicitly `true` (`null` = unknown → hidden) | `$accepted === true` |
| a nullable flag is explicitly `false` (`null` → hidden too) | `$accepted === false` |
| a nullable flag is still unknown | `$accepted === null` |

### `int` and `?int`

| Show the block when… | Expression |
|----------------------|------------|
| non-zero (negative counts too) | `$qty !== 0` |
| strictly positive | `$qty > 0` |
| one specific value | `$seats === 2` |
| inside a range | `$age >= 18 && $age < 65` |
| a nullable int is set — **0 included** | `$discount !== null` |
| a nullable int is set **and** positive (null and 0 hidden) | `($discount ?? 0) > 0` |
| a numeric **string** (DBAL `bigint`/`decimal`, CSV, form text) is a positive integer | `filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false` |

Pitfalls:

- **0 vs null** — decide whether "0" is information. `($discount ?? 0) > 0` hides a 0 % discount; `$discount !== null` shows "Discount: 0 %". `!empty($discount)` silently behaves like the first one.
- **Numeric strings** — `'0' !== 0` is `true` (different types), so `$row['qty'] !== 0` shows the block for a DB string `'0'`. Normalise first (`(int) $row['qty'] !== 0`) or use the `filter_var` row; `(int) 'abc'` is `0` and `(int) '12abc'` is `12`, so validate untrusted strings with `filter_var`.

### `float`

| Show the block when… | Expression |
|----------------------|------------|
| a computed amount is positive at cent precision | `round($total, 2) > 0.0` |
| a computed amount is non-zero at cent precision | `round($total, 2) !== 0.0` |
| two computed amounts are equal | `abs($paid - $due) < 0.005` |

Pitfalls:

- `0.1 + 0.2 === 0.3` is `false`, and `0.1 + 0.2 - 0.3 > 0.0` is `true` (the result is `5.6e-17`). Round to the precision you print (or compare with a tolerance) before deciding.
- `PHP_FLOAT_EPSILON` is the spacing of floats around `1.0`, not a business tolerance — pick one (`0.005` = half a cent).
- Doctrine `decimal` columns are hydrated as **strings** (`'0.00'` is truthy!). Use `(float) $amount > 0.0` or keep money as integer cents and use the `int` rules.

### `string` and `?string`

| Show the block when… | Expression |
|----------------------|------------|
| not empty | `$notes !== ''` |
| not blank (whitespace does not count) | `trim($notes) !== ''` |
| a nullable string is not blank | `trim($notes ?? '') !== ''` |
| a code is present — **`'0'` included** | `$code !== ''` |
| a "boolean-like" string means yes (`1`, `true`, `on`, `yes`, any case, surrounding spaces ignored) | `filter_var($flag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true` |
| equals a value, case-insensitive (ASCII) | `strcasecmp($country, 'es') === 0` |
| equals a value, case-insensitive (UTF-8) | `mb_strtolower($city) === 'málaga'` |
| contains | `str_contains($subject, 'URGENT')` |
| contains, case-insensitive | `stripos($subject, 'urgent') !== false` |
| starts with / ends with | `str_starts_with($iban, 'ES')` / `str_ends_with($email, '@example.com')` |
| matches a pattern | `preg_match('/^\d{5}$/', $zip) === 1` |

Pitfalls:

- `'0'` is falsy in PHP: `!empty('0')`, `(bool) '0'` and `if ($code)` are all `false`. Compare with `''` instead.
- `(bool) 'false'`, `(bool) 'no'` and `(bool) 'off'` are `true`. Strings that carry a yes/no answer go through `filter_var(…, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)`, which returns `null` for anything it does not recognise (`'maybe'`) — `=== true` turns that into "hidden".
- `stripos()` / `strpos()` return `0` when the needle is at the start: `if (stripos($subject, 'urgent'))` hides `"Urgent: …"`. Always compare with `!== false` (or use `str_contains`).
- String values that pick **one of several** blocks (plans, document types): see [One of N](#one-of-n-switch).

### `null` and optional values

| Show the block when… | Expression |
|----------------------|------------|
| an optional object is present | `$guarantor !== null` |
| a property of an optional object is true (null-safe) | `$guarantor?->isCompany() === true` |
| an options array has a true flag (missing key = hidden) | `($options['annex'] ?? false) === true` |
| an array key exists, **even with a null value** | `array_key_exists('iban', $payment)` |
| an array key exists **and** is not null | `isset($payment['iban'])` |

`??` only replaces `null` / missing keys — `($options['copies'] ?? 1) > 1` still sees an explicit `0`. `isset()` is `false` for a key whose value is `null`; use `array_key_exists()` when "present but empty" matters.

### Arrays and collections

| Show the block when… | Expression |
|----------------------|------------|
| the array has elements | `$lines !== []` |
| more than N elements | `count($lines) > 3` |
| it contains a value (strict) | `in_array('fire', $covers, true)` |
| at least one element matches | `array_filter($lines, static fn (array $line): bool => $line['qty'] > 10) !== []` |

Pitfall: without the third argument `in_array()` compares loosely — `in_array('10', ['1e1'])` is `true` (both numeric strings equal 10). Always pass `true`.

#### Show a table (or a "no items" message) depending on its rows

`TableRows` needs **at least one** row (`InvalidContextValueException` otherwise), so a table that may be empty is wrapped in a block and the `TableRows` is only added when it has rows.

**In Word:**

```text
${#if has_lines}
| Code         | Description  |      ← table header row
| ${line_code} | ${line_desc} |      ← row cloned by TableRows
${#endif has_lines}
${#if no_lines}
No items in this order.
${#endif no_lines}
```

**In PHP:**

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\TableRows;

$hasLines = $lines !== [];

$context = [
    'has_lines' => new ConditionalBlock('has_lines', $hasLines),
    'no_lines'  => new ConditionalBlock('no_lines', !$hasLines),
];

if ($hasLines) {
    $context['lines'] = new TableRows('line_code', array_map(
        static fn (array $line): array => ['line_code' => $line['code'], 'line_desc' => $line['description']],
        $lines,
    ));
}

$result = $processor->process($templatePath, $context);
```

Order of work inside `process()`: **conditionals first**, then `TableRows`, then scalars / `HtmlContent` / `ImageSource`. Consequences:

- Values for placeholders that sit inside a **hidden** block are simply ignored (scalars, `HtmlContent`, `ImageSource`: no error).
- A `TableRows` whose template row was inside a hidden block throws PHPWord's `Can not clone row, template variable not found or variable contains markup.` — only add the `TableRows` when the block is visible (as above).

### `DateTimeInterface`

Take "now" from a clock (Symfony `ClockInterface`, or a `DateTimeImmutable` passed in) instead of calling `new DateTime()` inside the service — that keeps the decision testable.

```php
$now   = $clock->now();             // DateTimeImmutable
$today = $now->setTime(0, 0);
```

| Show the block when… | Expression |
|----------------------|------------|
| a due date is in the past (before today) | `$dueDate < $today` |
| something starts in the future | `$startsAt > $now` |
| a date is today | `$date->format('Y-m-d') === $now->format('Y-m-d')` |
| something expires within 30 days | `$expiresAt <= $now->modify('+30 days')` |
| an optional date is set | `$signedAt !== null` |

Pitfalls: `<`, `>`, `==` compare **instants** (time zones are taken into account), but `format('Y-m-d')` compares **calendar days in each object's own time zone** — convert first (`$date->setTimezone($now->getTimezone())`). With mutable `DateTime`, `$now->modify()` changes `$now` itself; use `DateTimeImmutable`.

### Backed enums

```php
enum InvoiceStatus: string
{
    case Draft   = 'draft';
    case Paid    = 'paid';
    case Overdue = 'overdue';
}
```

| Show the block when… | Expression |
|----------------------|------------|
| one case | `$invoice->status === InvoiceStatus::Paid` |
| any of several cases | `in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Overdue], true)` |
| a raw string (request, DB, CSV) is one case — unknown values hidden | `InvoiceStatus::tryFrom($raw) === InvoiceStatus::Paid` |

Pitfalls: an enum case never equals its backing value (`$status === 'paid'` is always `false`; compare `$status->value === 'paid'` or the case). `InvoiceStatus::from('unknown')` throws a `ValueError`; `tryFrom()` returns `null`.

One block per case, built from the enum so a case can never be forgotten — see [One of N](#one-of-n-switch).

### Doctrine entities and relations

The context accepts scalars, `Stringable` and the bundle models only — passing an entity throws `InvalidContextValueException` (or is cast to string when the entity is `Stringable`). Read the entity in PHP and pass the decision:

| Show the block when… | Expression |
|----------------------|------------|
| an optional `ManyToOne` / `OneToOne` is set | `$contract->getGuarantor() !== null` |
| a field of an optional relation is true | `$contract->getGuarantor()?->isCompany() === true` |
| a `OneToMany` / `ManyToMany` collection has elements | `!$order->getLines()->isEmpty()` |
| a collection reaches a size | `$order->getLines()->count() >= 3` |
| any element of a collection matches | `$order->getLines()->exists(static fn (int $key, OrderLine $line): bool => $line->isHazardous())` |

Pitfalls: a `Collection` object is always truthy (`(bool) $order->getLines()` is `true` even when empty). Each relation access may lazy-load (one query per relation, `count()` on an `EXTRA_LAZY` collection runs a `COUNT`), so compute all decisions once, before `process()`. `decimal` columns are strings — see [`float`](#float).

### Symfony forms and request parameters

Raw HTTP input is always a string (or missing). Let Symfony convert it:

| Source | Expression | Behaviour |
|--------|------------|-----------|
| query / body flag (`?annex=1`) | `$request->query->getBoolean('annex')` | `1`, `true`, `on`, `yes` → `true`; missing, `''`, `0`, `false`, `off`, `no` → `false`; anything else (`maybe`) → `BadRequestException` (HTTP 400) |
| query / body integer | `$request->query->getInt('copies') > 1` | missing → `0`; a non-integer (`abc`, `''`, `3.5`) → `BadRequestException` (HTTP 400) |
| value you cannot let fail | `filter_var($request->request->get('annex'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true` | unknown → hidden, never an exception |
| `CheckboxType` | `$data['annex'] === true` | `true` when checked, `false` when unchecked |
| `IntegerType` / `NumberType` (empty field → `null`) | `($data['copies'] ?? 0) > 1` | `null` behaves as 0 |
| `TextType` / `TextareaType` (empty field → `null`) | `trim($data['notes'] ?? '') !== ''` | blank → hidden |
| `EnumType` | `$data['status'] === InvoiceStatus::Paid` | unselected → `null` → hidden |
| `ChoiceType` with string choices (`'yes'` / `'no'`) | `$data['newsletter'] === 'yes'` | anything else → hidden |

Pitfall: `(bool) $request->query->get('annex')` is `true` for `?annex=false` and `?annex=no`.

### "else": two blocks with opposite conditions

There is no `${#else}`. Use a second block whose condition is the negation, and compute the condition **once** so both blocks can never be visible (or hidden) together:

**In Word:**

```text
${#if paid}
Thank you, invoice ${number} is paid.
${#endif paid}
${#if not_paid}
Please pay invoice ${number} before ${due_date}.
${#endif not_paid}
```

**In PHP:**

```php
$isPaid = $invoice->status === InvoiceStatus::Paid;

$context = [
    'number'   => $invoice->number,
    'due_date' => $invoice->dueDate->format('d/m/Y'),
    'paid'     => new ConditionalBlock('paid', $isPaid),
    'not_paid' => new ConditionalBlock('not_paid', !$isPaid),
];
```

For "word A or word B" **inside a sentence**, do not use blocks (markers must sit in their own paragraph); compute a scalar instead: `'tier_label' => $isVip ? 'Gold' : 'Standard'` with `${tier_label}` in the text.

### One of N (switch)

Give each option its own block, decide the selected option **once**, then build every block with a strict comparison. A list of `ConditionalBlock` under one context key is fine: nested arrays are flattened and the objects are kept (the context keys never reach Word — only `blockName` matters).

**From a backed enum** (one block per case — adding a case adds a block):

```text
${#if status_draft}
Draft — not valid as an invoice.
${#endif status_draft}
${#if status_paid}
Paid — thank you.
${#endif status_paid}
${#if status_overdue}
Overdue — please pay now.
${#endif status_overdue}
```

```php
$context['status_blocks'] = array_map(
    static fn (InvoiceStatus $case): ConditionalBlock => new ConditionalBlock('status_' . $case->value, $invoice->status === $case),
    InvoiceStatus::cases(),
);
```

**Several cases share one text** — group them with `match` (exhaustive: a new enum case without an arm throws `UnhandledMatchError` instead of silently hiding everything):

```php
$tone = match ($invoice->status) {
    InvoiceStatus::Paid                          => 'thanks',
    InvoiceStatus::Draft, InvoiceStatus::Overdue => 'reminder',
};

$context['tone_blocks'] = [
    new ConditionalBlock('tone_thanks', $tone === 'thanks'),
    new ConditionalBlock('tone_reminder', $tone === 'reminder'),
];
```

**From a free string** (normalise first, and keep a fallback block for unexpected values):

```php
$plans = ['basic', 'pro', 'enterprise'];
$plan  = strtolower(trim($customer->plan));

$context['plan_blocks'] = array_map(
    static fn (string $option): ConditionalBlock => new ConditionalBlock('plan_' . $option, $plan === $option),
    $plans,
);
$context['plan_other'] = new ConditionalBlock('plan_other', !in_array($plan, $plans, true));
```

### Combining conditions: `&&`, `||`, nesting or flattening

- **Flatten in PHP** (one block) for any combination: `new ConditionalBlock('eu_vat_note', $isCompany && $country !== 'ES')`, `new ConditionalBlock('priority', $isVip || $total >= 10_000)`.
- **Nest in Word** only for **AND** when the outer block has content of its own: the inner block is shown only when both are visible.

  ```text
  ${#if company}
  Company details: ${company_name}
  ${#if foreign_company}
  Intra-community VAT number: ${vat_number}
  ${#endif foreign_company}
  ${#endif company}
  ```

  `OR` and `NOT` cannot be expressed with nesting — compute them in PHP.
- Prefer flattening when nesting would only add markers: one block named after the business rule (`eu_vat_note`) is easier for template authors than two nested technical flags.

### Same block name used several times

- Every region with the same name is shown or hidden **together** by one `ConditionalBlock` (e.g. a "DRAFT" notice in the header **and** at the end of the body: `${#if draft}` in both places, one `new ConditionalBlock('draft', $isDraft)`).
- Do **not** nest a block inside a block with the **same** name (`${#if a}` … `${#if a}` … `${#endif a}` … `${#endif a}`): the pairs get mixed up and markers can stay in the output. Use distinct names.
- If the context holds two `ConditionalBlock`s with the same `blockName`, the **last** one wins. Build each name once.

### What happens when…

| Situation | Result |
|-----------|--------|
| The template has `${#if annex}` … `${#endif annex}` but the context has **no** `ConditionalBlock('annex', …)` | Nothing is removed: both marker paragraphs and the content stay in the output **as literal text** (`${#if annex}` is visible in the document). No exception. |
| The context has a `ConditionalBlock` for a name that is not in the template | Ignored. |
| `${#if annex}` has no matching `${#endif annex}` (typo, different name, missing marker) | The region is not recognised; the markers stay as literal text. |
| Placeholders inside a hidden block | Removed with the region; their values are ignored (no error) — except `TableRows`, see [above](#show-a-table-or-a-no-items-message-depending-on-its-rows). |
| `${#if Annex}` vs `ConditionalBlock('annex', …)` | Names are currently matched case-insensitively, but `listConditionalBlocks()` returns them as typed (`Annex`). Do not rely on it: use the exact same spelling (lowercase `snake_case` recommended). |
| Dotted names (`${#if invoice.paid}`) | Supported; the `ConditionalBlock` name is the full string (`'invoice.paid'`), independent of the context key. |
| `${#if total > 0}` … `${#endif total > 0}` | Not an expression: it is a block literally named `total > 0` (and Word stores `>` as `&gt;`). Use a plain name and decide in PHP — or close it with a nameless `${#endif}` and enable [expression conditionals](#expression-conditionals). |

To fail fast instead of shipping literal markers, compare the names in the template with the blocks you build:

```php
$blocks = [
    new ConditionalBlock('annex', $hasAnnex),
    new ConditionalBlock('vip_section', $isVip),
];

$supplied = array_map(static fn (ConditionalBlock $block): string => $block->blockName, $blocks);
$missing  = array_diff($processor->listConditionalBlocks($templatePath), $supplied);

if ($missing !== []) {
    throw new LogicException('No visibility decided for block(s): ' . implode(', ', $missing));
}

$result = $processor->process($templatePath, ['blocks' => $blocks] + $values);
```

### Where markers can go in Word

| Placement | Supported | Detail |
|-----------|-----------|--------|
| Each marker alone in its own paragraph | ✅ | The canonical form. Each **marker paragraph is removed** from the output, whether the block is shown or hidden. |
| Text **before** a marker in the same paragraph (`Notes: ${#if notes}`) | ⚠️ | The region works, but that text is removed together with the marker paragraph — even when the block is visible. |
| Text **after** a marker in the same paragraph (`${#endif notes} End.`), or a full pair inside one sentence (`Hello ${#if vip}dear ${#endif vip}client`) | ❌ | The region is not recognised; markers stay as literal text. For inline alternatives use a computed scalar placeholder. |
| Around whole paragraphs, lists, images, tables, page breaks | ✅ | Everything between the two marker paragraphs is kept or removed; the content may span several pages. |
| Both markers inside the **same table cell** | ✅ | Only that part of the cell is toggled; the table stays. |
| Around a **whole table** (markers in paragraphs before and after it) | ✅ | The table is kept or removed; combine with `TableRows` as [shown above](#show-a-table-or-a-no-items-message-depending-on-its-rows). |
| Opening marker in one cell / row and closing marker in another | ❌ | Unsupported — the table XML is cut in the middle and the document is damaged. To drop rows, leave them out of `TableRows`; to drop the table, wrap the whole table. |
| Headers and footers | ✅ | Processed like the body (all headers and footers of every section). A pair must open and close in the **same** part — not open in a header and close in the body. A name used in the header and in the body is toggled in both. |
| Footnotes, endnotes, comments | ❌ | Not processed — markers there stay as text. |
| Properly nested pairs | ✅ | Resolved inside-out (see [Nested conditionals](#nested-conditionals)). |
| Overlapping pairs (`#if a`, `#if b`, `#endif a`, `#endif b`) | ❌ | Unsupported — damages the document. |
| Marker split across runs by Word (spell-check, partial formatting) | ✅ | Healed before applying (`BrokenMacroNormalizer`). |

## Expression conditionals

*Since 1.8 — opt-in.* Instead of deciding every block in PHP, the template can carry the condition itself:

```text
${#if total > 1000}
Orders above 1,000 EUR ship for free.
${#endif}
```

The block opens with `${#if <expression>}` and closes with a **nameless** `${#endif}`. The expression is evaluated against the context you already pass to `process()`, with [Symfony ExpressionLanguage](https://symfony.com/doc/current/components/expression_language.html) syntax and a small set of [functions](#functions-reference). Named blocks (`${#if annex}` … `${#endif annex}` + `ConditionalBlock`) keep working exactly as before, in the same document.

Every example in this section is executed by `tests/Integration/ExpressionConditionalsUsageDocTest.php`: each row of the operator, data-type, function and error tables is rendered through a real `.docx`, and each "In Word / In PHP / Result" example is run as written. If the docs and the code drift apart, the suite fails.

### Enable expression conditionals

```yaml
# config/packages/nowo_word_template.yaml
nowo_word_template:
    conditional_expressions:
        enabled: true          # default false: expression markers stay as literal text (1.7 behaviour)
        on_error: exception    # exception | hide | keep — see "Errors"
        max_length: 500        # characters per expression
        timezone: null         # time zone of now() / date(); null = PHP default
        functions:
            allow: ~           # null = every function; or a list, e.g. [upper, lower, length]
            deny: []           # e.g. [now, date]
```

All options are described in [CONFIGURATION.md](CONFIGURATION.md#conditional-expressions). Without `enabled: true` nothing changes: a `${#if total > 1000}` marker is left in the document as literal text, as in 1.7.

### First example

<!-- expr-example -->
**In Word:**

```text
Invoice ${number}
${#if total > 1000}
Orders above 1,000 EUR ship for free.
${#endif}
Thank you.
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'number' => 'F-2026-001',
    'total'  => 1500,
]);
```

**Result:**

```text
Invoice F-2026-001
Orders above 1,000 EUR ship for free.
Thank you.
```

With `'total' => 900` the middle paragraph is removed. As with named blocks, each marker must sit **alone in its own paragraph**, and the two marker paragraphs are always removed from the output.

### Named block or expression? The classification rule

The rule is **syntactic** and does not depend on the context:

- Markers are paired like brackets, in document order (main part, each header and each footer separately).
- A **nameless** `${#endif}` closes the nearest open `${#if …}` → that block is an **expression block**, evaluated by the bundle.
- A **named** `${#endif name}` closes the nearest open `${#if name}` (same name, case-insensitive) → that block is a **named block**, shown or hidden by a `ConditionalBlock('name', …)` exactly as before. Without a matching `ConditionalBlock`, a named block stays as literal text (unchanged behaviour).

| In Word | Kind | Decided by |
|---------|------|------------|
| `${#if annex}` … `${#endif annex}` | named block | `new ConditionalBlock('annex', $bool)` |
| `${#if annex}` … `${#endif}` | expression (`annex` is a variable) | the `annex` variable — or `ConditionalBlock('annex', …)`, see below |
| `${#if total > 1000}` … `${#endif}` | expression | the `total` variable |
| `${#if total > 1000}` … `${#endif total > 1000}` | named block literally called `total > 1000` (1.7 behaviour) | a `ConditionalBlock` with that name |

An identifier-only expression such as `${#if is_vip}` … `${#endif}` reads the bool variable `is_vip`. If the context has no `is_vip` key but has a `ConditionalBlock` named `is_vip`, its visibility is used — so existing `ConditionalBlock` decisions can be combined in expressions (`${#if annex and total > 0}`). A context key with the same name wins over a block.

### What the expression can read

The **top-level keys** of the context are the variables. A key must be an identifier (`[A-Za-z_][A-Za-z0-9_]*`) to be usable (`'client-name'` is not; use `client_name`).

| Context value | Seen by the expression as | Example |
|---------------|---------------------------|---------|
| `int`, `float`, `string`, `bool`, `null` | the same value | `qty > 0` |
| associative array | a read-only map: `client.country` or `client['country']` | `client.address.city == 'Madrid'` |
| list (`array_is_list()`, **including `[]`**) | a PHP list: `lines[0]`, `'fire' in covers` | `length(lines) > 3` |
| `Stringable` | its string | `company == 'ACME'` |
| backed enum | its **value** (also in `${placeholders}` since 1.8) | `status == 'paid'` |
| pure enum | its **case name** | `priority == 'High'` |
| `DateTimeInterface` | a `'Y-m-d'` string, the calendar day in the object's own time zone (also in `${placeholders}` since 1.8) | `due_date < today` |
| `ConditionalBlock` | not a value; its **visibility** is exposed as a bool under its block name (when that name is an identifier and no context key uses it) | `annex and is_vip` |
| `TableRows`, `HtmlContent`, `ImageSource`, other objects | not exposed (pass a plain value next to them, e.g. `'lines_count' => count($lines)`) | — |

- An empty array is a list, so `options.annex` fails on `'options' => []`; the bracket form `options['annex'] ?? false` works for both.
- Reading a variable or a map key that does not exist is an error (`unknown variable "client.zip"`) — use `client.zip ?? ''` or `is_defined('client.zip')` when a key is optional.
- Method calls (`client.getName()`) and property access on real objects are impossible: only the values above reach the template.
- `DateTimeInterface` values are compared as `'Y-m-d'` strings, which order correctly (`'2026-10-01' < '2026-10-30'`). Pass "today" from a clock (`'today' => $clock->now()`) or use [`now()`](#functions-reference). For time-of-day decisions, compute the bool in PHP.

### Operators

| Operator | In Word | Context (PHP) | Result |
|----------|---------|---------------|--------|
| `==` equal (loose) | `${#if qty == 3}` | `['qty' => 3]` | ✅ kept |
| `==` int vs numeric string | `${#if qty == '3'}` | `['qty' => 3]` | ✅ kept |
| `===` identical (type too) | `${#if qty === '3'}` | `['qty' => 3]` | ❌ removed |
| `!=` not equal | `${#if country != 'PT'}` | `['country' => 'ES']` | ✅ kept |
| `!==` not identical | `${#if qty !== 3}` | `['qty' => 3]` | ❌ removed |
| `<` | `${#if qty < 3}` | `['qty' => 3]` | ❌ removed |
| `>` | `${#if total > 1000}` | `['total' => 1500]` | ✅ kept |
| `<=` | `${#if qty <= 3}` | `['qty' => 3]` | ✅ kept |
| `>=` | `${#if total >= 1000}` | `['total' => 999.99]` | ❌ removed |
| `and` / `&&` | `${#if is_vip and total > 100}` | `['is_vip' => true, 'total' => 150]` | ✅ kept |
| `or` / `\|\|` | `${#if is_vip or total > 10000}` | `['is_vip' => false, 'total' => 150]` | ❌ removed |
| `not` / `!` | `${#if not is_vip}` | `['is_vip' => false]` | ✅ kept |
| `in` (strict) | `${#if country in ['ES', 'PT']}` | `['country' => 'PT']` | ✅ kept |
| `in` a context list | `${#if 'fire' in covers}` | `['covers' => ['theft', 'fire']]` | ✅ kept |
| `not in` | `${#if 'flood' not in covers}` | `['covers' => ['theft', 'fire']]` | ✅ kept |
| `in` a range | `${#if age in 18..64}` | `['age' => 65]` | ❌ removed |
| `contains` | `${#if subject contains 'URGENT'}` | `['subject' => 'Re: URGENT renewal']` | ✅ kept |
| `starts with` | `${#if iban starts with 'ES'}` | `['iban' => 'ES00 TEST']` | ✅ kept |
| `ends with` | `${#if email ends with '@example.com'}` | `['email' => 'jane@example.com']` | ✅ kept |
| `matches` (PCRE) | `${#if zip matches '/^28[0-9]+$/'}` | `['zip' => '28001']` | ✅ kept |
| arithmetic `+ - * / %` | `${#if paid - due < 0}` | `['paid' => 80, 'due' => 100]` | ✅ kept |
| `~` string concatenation | `${#if country ~ '-' ~ zip == 'ES-28001'}` | `['country' => 'ES', 'zip' => '28001']` | ✅ kept |
| `? :` ternary | `${#if (is_vip ? 0 : 5) == 0}` | `['is_vip' => true]` | ✅ kept |
| `??` null coalescing | `${#if (discount ?? 0) > 0}` | `['discount' => null]` | ❌ removed |
| `?.` null-safe access | `${#if client?.vat == null}` | `['client' => null]` | ✅ kept |
| parentheses | `${#if (a or b) and c}` | `['a' => false, 'b' => true, 'c' => true]` | ✅ kept |
| `"double"` or `'single'` quotes | `${#if plan == "pro"}` | `['plan' => 'pro']` | ✅ kept |

Notes:

- String literals use straight quotes. **Word's AutoCorrect turns them into typographic quotes** (`‘pro’`, `“pro”`) while you type; the bundle converts `‘ ’ ‚ ‛ ′` to `'` and `“ ” „ ‟ ″ « »` to `"` before parsing, as well as en/em dashes and the minus sign (`–`, `—`, `−`) to `-`, non-breaking and other special spaces to plain spaces, and drops zero-width characters. A side effect: a literal cannot contain a typographic apostrophe (write `"O'Brien"` with double quotes).
- `>` and `<` are stored by Word as `&gt;` / `&lt;`; the bundle decodes them. Markers split by Word into several runs (spell-check, partial formatting) are healed first, as for named blocks.
- `matches` takes a PCRE pattern between delimiters. A backslash must be written twice (`'/^\\w+$/'`) because the string literal unescapes it once, and the pattern cannot contain `}` (the closing delimiter), so `{5}` quantifiers are not available — repeat the class (`[0-9][0-9][0-9]`) or decide in PHP. Patterns are bounded by PHP's `pcre.backtrack_limit` / `pcre.recursion_limit` (a pattern that hits them is an evaluation error, not a hang).
- The expression cannot contain the closing delimiter (`}` by default), so hash literals (`{a: 1}`) are not available inside a marker.
- A range (`18..64`) is only accepted between two **integer literals** and may hold at most 10,000 numbers (`1..100000000` or `1..n` are errors): the whole list is built in memory.
- `|` is the bitwise OR operator of ExpressionLanguage, **not** a Twig filter: write `upper(name)`, not `name|upper` (see the [Twig mapping](#twig-filters-and-tests-as-functions)).
- The result must be a **bool**: `${#if notes}` with a string is an error (`must evaluate to true or false, got string`). Compare explicitly: `notes != ''`, `qty > 0`, `not empty(lines)`.

### Expressions by data type

The [rules of the PHP section](#conditionals-by-data-type) apply here too (`0` vs `null`, float rounding, `'0'` strings). The same decisions written in the template:

| Type | Show the block when… | In Word | Context (PHP) | Result |
|------|---------------------|---------|---------------|--------|
| `bool` | the flag is set | `${#if is_vip}` | `['is_vip' => true]` | ✅ kept |
| `bool` | the flag is not set ("else") | `${#if not is_vip}` | `['is_vip' => true]` | ❌ removed |
| `?bool` | explicitly `true` (`null` = unknown → hidden) | `${#if accepted === true}` | `['accepted' => null]` | ❌ removed |
| `?bool` | still unknown | `${#if accepted === null}` | `['accepted' => null]` | ✅ kept |
| `int` | strictly positive | `${#if qty > 0}` | `['qty' => -2]` | ❌ removed |
| `int` | inside a range | `${#if age >= 18 and age < 65}` | `['age' => 18]` | ✅ kept |
| `?int` | set, **0 included** | `${#if discount !== null}` | `['discount' => 0]` | ✅ kept |
| `?int` | set and positive | `${#if (discount ?? 0) > 0}` | `['discount' => 0]` | ❌ removed |
| `float` | positive at cent precision | `${#if round(total, 2) > 0}` | `['total' => 0.004]` | ❌ removed |
| `float` | two amounts equal (tolerance) | `${#if abs(paid - due) < 0.005}` | `['paid' => 0.1 + 0.2, 'due' => 0.3]` | ✅ kept |
| `string` | not blank | `${#if trim(notes) != ''}` | `['notes' => '   ']` | ❌ removed |
| `string` | a code is present, **`'0'` included** | `${#if code !== ''}` | `['code' => '0']` | ✅ kept |
| `string` | equals, case-insensitive (UTF-8) | `${#if lower(city) == 'málaga'}` | `['city' => 'MÁLAGA']` | ✅ kept |
| `?string` | nullable and not blank | `${#if trim(default(notes, '')) != ''}` | `['notes' => null]` | ❌ removed |
| `null` | an optional value is present | `${#if guarantor != null}` | `['guarantor' => null]` | ❌ removed |
| map | a nested value | `${#if client.country == 'ES'}` | `['client' => ['country' => 'ES']]` | ✅ kept |
| map | an optional key (bracket form also works when the array is empty) | `${#if (options['annex'] ?? false) === true}` | `['options' => []]` | ❌ removed |
| map | a key exists, even null | `${#if is_defined('payment.iban')}` | `['payment' => ['iban' => null]]` | ✅ kept |
| list | has elements | `${#if lines != []}` | `['lines' => []]` | ❌ removed |
| list | more than N elements | `${#if length(lines) > 3}` | `['lines' => [1, 2, 3]]` | ❌ removed |
| list | contains a value (strict) | `${#if 'fire' in covers}` | `['covers' => ['theft', 'fire']]` | ✅ kept |
| `DateTimeInterface` | a due date is in the past | `${#if due_date < today}` | `['due_date' => new DateTimeImmutable('2026-10-29 18:00'), 'today' => new DateTimeImmutable('2026-10-30')]` | ✅ kept |
| `DateTimeInterface` | a date is today | `${#if signed_at == now()}` | `['signed_at' => new DateTimeImmutable('2026-10-30 08:00')]` | ✅ kept |
| `DateTimeInterface` | expires within 30 days | `${#if expires_at <= date('+30 days')}` | `['expires_at' => new DateTimeImmutable('2026-12-01')]` | ❌ removed |
| `?DateTimeInterface` | an optional date is set | `${#if signed_at != null}` | `['signed_at' => null]` | ❌ removed |
| backed enum | one case | `${#if status == 'paid'}` | `['status' => InvoiceStatus::Paid]` | ✅ kept |
| backed enum | any of several cases | `${#if status in ['draft', 'overdue']}` | `['status' => InvoiceStatus::Paid]` | ❌ removed |
| `ConditionalBlock` | combine a PHP decision with a value | `${#if annex and total > 0}` | `['blocks' => [new ConditionalBlock('annex', true)], 'total' => 10]` | ✅ kept |

The date rows assume today is **2026-10-30** (`now()` and `date()` read the Symfony clock). Enums use `enum InvoiceStatus: string { case Draft = 'draft'; case Paid = 'paid'; case Overdue = 'overdue'; }`.

### Functions reference

Functions are called like in PHP or Twig: `upper(name)`. They are the **only** callable surface of a template: pure, no I/O, arguments validated (a wrong type is an [error](#errors)), strings limited to 64 KiB and lists to 10,000 elements — sizes are checked **before** a result is built (`replace()`, `join()`, `number_format()` with at most 20 decimals, date formats up to 100 bytes). `constant()`, `enum()` and every PHP function are **not** available.

| Function | Twig equivalent | In Word | Context (PHP) | Result |
|----------|-----------------|---------|---------------|--------|
| `lower(s)` | `s\|lower` | `${#if lower(country) == 'es'}` | `['country' => 'ES']` | ✅ kept |
| `upper(s)` | `s\|upper` | `${#if upper(name) == 'ACME'}` | `['name' => 'Acme']` | ✅ kept |
| `capitalize(s)` | `s\|capitalize` | `${#if capitalize(city) == 'Málaga'}` | `['city' => 'MÁLAGA']` | ✅ kept |
| `title(s)` | `s\|title` | `${#if title(name) == 'Acme Industries'}` | `['name' => 'acme industries']` | ✅ kept |
| `trim(s[, chars])` | `s\|trim` | `${#if trim(code) == 'ES-01'}` | `['code' => '  ES-01 ']` | ✅ kept |
| `length(x)` (string: characters, list/map: elements) | `x\|length` | `${#if length(city) == 6}` | `['city' => 'Málaga']` | ✅ kept |
| `replace(s, search, replacement)` / `replace(s, {map})` | `s\|replace({…})` | `${#if replace(iban, ' ', '') == 'ES0012'}` | `['iban' => 'ES00 12']` | ✅ kept |
| `slice(x, start[, length])` (string or list) | `x\|slice(start, length)` | `${#if slice(iban, 0, 2) == 'ES'}` | `['iban' => 'ES00 12']` | ✅ kept |
| `starts_with(s, prefix)` | `s starts with prefix` | `${#if starts_with(iban, 'ES')}` | `['iban' => 'PT50 01']` | ❌ removed |
| `ends_with(s, suffix)` | `s ends with suffix` | `${#if ends_with(email, '.org')}` | `['email' => 'jane@example.org']` | ✅ kept |
| `abs(n)` | `n\|abs` | `${#if abs(balance) < 0.01}` | `['balance' => -0.004]` | ✅ kept |
| `round(n[, precision])` | `n\|round(precision)` | `${#if round(total, 2) == 10.01}` | `['total' => 10.006]` | ✅ kept |
| `floor(n)` / `ceil(n)` | `n\|round(0, 'floor')` / `'ceil'` | `${#if ceil(weight) == 3}` | `['weight' => 2.1]` | ✅ kept |
| `min(a, b, …)` / `min(list)` | `min(…)` | `${#if min(prices) < 5}` | `['prices' => [10, 2.5, 7]]` | ✅ kept |
| `max(a, b, …)` / `max(list)` | `max(…)` | `${#if max(qty, 1) == 1}` | `['qty' => 0]` | ✅ kept |
| `number_format(n[, decimals, point, thousands])` | `n\|number_format` | `${#if number_format(total, 2, ',', '.') == '1.234,50'}` | `['total' => 1234.5]` | ✅ kept |
| `count(x)` (list or map) | `x\|length` | `${#if count(lines) == 2}` | `['lines' => ['A', 'B']]` | ✅ kept |
| `keys(x)` | `x\|keys` | `${#if 'iban' in keys(payment)}` | `['payment' => ['iban' => null]]` | ✅ kept |
| `first(x)` / `last(x)` | `x\|first` / `x\|last` | `${#if first(lines) == 'A'}` | `['lines' => ['A', 'B']]` | ✅ kept |
| `join(list[, glue])` | `list\|join(glue)` | `${#if join(covers, ', ') == 'fire, theft'}` | `['covers' => ['fire', 'theft']]` | ✅ kept |
| `empty(x)` (`null`, `''`, `false`, `[]`, empty map; **not** `0`) | `x is empty` | `${#if empty(notes)}` | `['notes' => '']` | ✅ kept |
| `default(x[, fallback])` (fallback when `x` is empty) | `x\|default(fallback)` | `${#if default(plan, 'basic') == 'basic'}` | `['plan' => null]` | ✅ kept |
| `is_null(x)` | `x is null` | `${#if is_null(signed_at)}` | `['signed_at' => null]` | ✅ kept |
| `is_defined('path')` | `x is defined` | `${#if is_defined('client.vat')}` | `['client' => ['name' => 'ACME']]` | ❌ removed |
| `now([format])` (default `'Y-m-d'`) | `'now'\|date(format)` | `${#if now('m') == '10'}` | `[]` | ✅ kept |
| `date(value[, format])` (`'Y-m-d'` string, relative text such as `'+30 days'`, or a timestamp) | `value\|date(format)` | `${#if date(due_date, 'N') >= 6}` | `['due_date' => new DateTimeImmutable('2026-10-31')]` | ✅ kept |
| `date_diff_days(from, to)` (whole days, negative when `to` is earlier) | — | `${#if date_diff_days(now(), due_date) <= 7}` | `['due_date' => new DateTimeImmutable('2026-11-03')]` | ✅ kept |

`now()` / `date()` use the Symfony `clock` service (so tests can freeze time with `MockClock`) and the `conditional_expressions.timezone` time zone. The examples assume now is 2026-10-30 12:00 in `Europe/Madrid`.

#### Twig filters and tests as functions

ExpressionLanguage has no `|filter` syntax (`|` is bitwise OR), so every Twig filter or test becomes a function call:

| Twig | Expression conditional |
|------|-----------------------|
| `name\|upper == 'ACME'` | `upper(name) == 'ACME'` |
| `name\|lower\|trim` | `trim(lower(name))` |
| `items\|length > 0` | `length(items) > 0` |
| `items is empty` / `items is not empty` | `empty(items)` / `not empty(items)` |
| `value is null` | `is_null(value)` or `value == null` |
| `client.vat is defined` | `is_defined('client.vat')` |
| `notes\|default('n/a')` | `default(notes, 'n/a')` |
| `'fire' in covers` | `'fire' in covers` (same operator) |
| `name starts with 'A'` | `name starts with 'A'` (same operator) or `starts_with(name, 'A')` |
| `date\|date('Y')` | `date(date, 'Y')` |
| `total\|number_format(2, ',', '.')` | `number_format(total, 2, ',', '.')` |
| `items\|join(', ')` | `join(items, ', ')` |

`contains` and `in` are operators (`subject contains 'x'`, `x in list`), so they are not functions.

#### Your own functions

Implement `Nowo\WordTemplateBundle\Expression\ExpressionFunctionProviderInterface` in a service (autoconfigured with the tag `nowo_word_template.expression_function_provider`). Each evaluator receives the exposed variables first, then the arguments; throw `ExpressionFunctionError` on bad input so the [error mode](#errors) applies. Expressions are never compiled, so the compiler can be `BuiltinExpressionFunctions::notCompilable(...)`.

```php
namespace App\WordTemplate;

use Nowo\WordTemplateBundle\Expression\BuiltinExpressionFunctions;
use Nowo\WordTemplateBundle\Expression\ExpressionFunctionError;
use Nowo\WordTemplateBundle\Expression\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

final class VatExpressionFunctions implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('vat_rate', BuiltinExpressionFunctions::notCompilable(...), static function (array $variables, mixed $country): float {
                return match ($country) {
                    'ES'    => 0.21,
                    'PT'    => 0.23,
                    default => throw new ExpressionFunctionError('vat_rate() only knows ES and PT.'),
                };
            }),
        ];
    }
}
```

```text
${#if vat_rate(client.country) > 0.2}
VAT above 20 % applies.
${#endif}
```

Keep host functions pure (no database, HTTP or filesystem access): templates may be uploaded by users in some applications. A host function with the same name as a built-in replaces it. `conditional_expressions.functions.allow` / `deny` apply to built-in and host functions alike (an unknown name there is a configuration error).

### Nesting, "else" and one of N

Expression blocks nest like named ones, and both kinds can be mixed. Blocks are evaluated **outside-in**: the expressions inside a removed block are never evaluated (so `client.vip` below is safe even when `client` is `null`).

<!-- expr-example -->
**In Word:**

```text
${#if client != null}
Customer: ${client.name}
${#if client.vip}
Priority support included.
${#endif}
${#endif}
End of offer.
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'client' => ['name' => 'ACME', 'vip' => false],
]);
```

**Result:**

```text
Customer: ACME
End of offer.
```

**"else"** — there is no `${#else}`; write the opposite expression. One expression per branch keeps both branches readable in Word:

<!-- expr-example -->
**In Word:**

```text
${#if status == 'paid'}
Thank you, invoice ${number} is paid.
${#endif}
${#if status != 'paid'}
Please pay invoice ${number} before ${due_date}.
${#endif}
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'number'   => 'F-7',
    'status'   => InvoiceStatus::Overdue,
    'due_date' => new DateTimeImmutable('2026-11-15'),
]);
```

**Result:**

```text
Please pay invoice F-7 before 2026-11-15.
```

**One of N** — one block per option, plus a fallback:

<!-- expr-example -->
**In Word:**

```text
${#if plan == 'basic'}
Plan: Basic.
${#endif}
${#if plan == 'pro'}
Plan: Pro.
${#endif}
${#if plan not in ['basic', 'pro']}
Plan not recognised — please contact sales.
${#endif}
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'plan' => 'pro',
]);
```

**Result:**

```text
Plan: Pro.
```

**Mixed with named blocks** — the named block is still decided in PHP; the expression inside it reads a plain value:

<!-- expr-example -->
**In Word:**

```text
${#if annex}
Annex
${#if lines_count == 0}
No order lines.
${#endif}
${#endif annex}
```

**In PHP:**

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;

$result = $processor->process($templatePath, [
    'annex'       => new ConditionalBlock('annex', true),
    'lines_count' => 0,
]);
```

**Result:**

```text
Annex
No order lines.
```

**A table only when there are rows** — `TableRows` needs at least one row, so keep it out of the context when the list is empty, and pass the list itself for the expression:

```text
${#if lines != []}
| ${line_code} | ${line_desc} |      ← table row cloned by TableRows
${#endif}
${#if lines == []}
No items in this order.
${#endif}
```

```php
use Nowo\WordTemplateBundle\Model\TableRows;

$context = ['lines' => $lines];
if ($lines !== []) {
    $context['rows'] = new TableRows('line_code', $lines); // rows: [['line_code' => 'A-1', 'line_desc' => 'Bolts'], …]
}
$result = $processor->process($templatePath, $context);
```

With two lines the table shows both rows and "No items" is removed; with `[]` only "No items in this order." remains (tested in `ExpressionConditionalsIntegrationTest::testTableShownOnlyWhenThereAreRows`).

### When to compute in PHP, when in the template

| Prefer the **template** expression when… | Prefer **PHP** (`ConditionalBlock` or a computed bool) when… |
|------------------------------------------|---------------------------------------------------------------|
| the rule is about the document's wording and template authors own it (thresholds, country lists) | the rule is business logic that must be tested and reused (eligibility, pricing, permissions) |
| it reads values already in the context | it needs entities, relations, services, the database or the request |
| it is short and readable in Word (`total > 1000`, `status == 'paid'`) | it needs loops, time of day, time-zone conversions or many conditions |
| the same template serves several apps with different rules | the template is uploaded by end users and you do not want them to change the logic (keep `enabled: false` or restrict `functions.allow`) |

### Errors

| Problem | In Word | Context (PHP) | Result (`on_error: exception`) |
|---------|---------|---------------|--------------------------------|
| unknown variable (typo) | `${#if totl > 0}` | `['total' => 5]` | throws `uses the unknown variable "totl"` |
| unknown map key | `${#if client.vat == ''}` | `['client' => ['name' => 'ACME']]` | throws `uses the unknown variable "client.vat"` |
| syntax error | `${#if total >}` | `['total' => 5]` | throws `is invalid` |
| unknown or disabled function | `${#if constant('PHP_VERSION') != ''}` | `[]` | throws `The function "constant" does not exist` |
| method call | `${#if client.getName() == 'x'}` | `['client' => ['name' => 'x']]` | throws `method calls are not available in templates` |
| not a bool | `${#if notes}` | `['notes' => 'text']` | throws `must evaluate to true or false, got string` |
| bad function argument | `${#if upper(lines) == ''}` | `['lines' => ['A']]` | throws `upper() expects a string, got array` |
| missing list index | `${#if lines[5] == 'x'}` | `['lines' => ['A']]` | throws `could not be evaluated` |

Every error is a `Nowo\WordTemplateBundle\Exception\ConditionalExpressionException` (implements `WordTemplateExceptionInterface`; `$e->expression` holds the expression). Messages contain template text (the expression, variable and function names, argument types) but never context values, so they can be shown to template authors. Layout problems are errors too: a `${#endif}` with no open block, an expression block never closed, opening and closing marker in the same paragraph (inline blocks are not supported), a marker sharing a paragraph with another block's marker, or a block that cuts table cells / rows / content controls in half (for example opening in one cell and closing in another cell of the same row).

**Whole table rows** — when the opening and the closing marker sit in rows of their own of the same table, an expression block keeps or removes the rows between them (the two marker rows are always removed), exactly like [row loops](#repeat-table-rows):

```text
| Item | Amount |
| ${#if show_discount} |
| Discount | -${discount} |
| ${#endif} |
| Total | ${total} |
```

`conditional_expressions.on_error` decides what happens:

| Mode | Invalid expression | Layout problem | Logged |
|------|--------------------|----------------|--------|
| `exception` (default) | `ConditionalExpressionException` — nothing is generated | `ConditionalExpressionException` | warning `Word template merge rejected.` |
| `hide` | the block is removed (as if `false`) | markers left as literal text | warning with the number of errors |
| `keep` | markers left as literal text, content kept (inner blocks still evaluated) | markers left as literal text | warning with the number of errors |

Logs never contain context values or expressions — only the template file name, the mode and the error count.

**Validate templates up front** (for example when a user uploads one) with `ConditionalExpressionInspectorInterface`, implemented by the processor service. It works whether or not `enabled` is set:

```php
use Nowo\WordTemplateBundle\Processor\ConditionalExpressionInspectorInterface;

/** @var ConditionalExpressionInspectorInterface $inspector */
$expressions = $inspector->listConditionalExpressions($templatePath);
// ['total > 1000', "status == 'paid'"]

$problems = $inspector->lintConditionalExpressions($templatePath, ['total', 'status', 'client']);
foreach ($problems as $problem) {
    echo $problem->getMessage(), "\n"; // e.g. Conditional expression "totl > 0" uses the unknown variable "totl" …
}
```

`lintConditionalExpressions()` checks layout, syntax, functions, length and **top-level** variable names (it does not know the keys of your maps). With `enabled: true`, `listConditionalBlocks()` returns only named blocks (the ones you must build a `ConditionalBlock` for).

### Security

Expressions are written by template authors. Treat an uploaded template like code with a tiny API: it can only read the values you put in the context and call the [functions](#functions-reference) (built-in or yours). There is no `constant()`, `enum()`, method call, property access on real objects, filesystem or network access. Expressions are limited to `max_length` characters, parsed expressions are cached in a bounded in-memory pool (256 entries per worker), function inputs and outputs are size-capped, and `matches` is bounded by PCRE's backtrack / recursion limits. See [SECURITY.md](SECURITY.md#expression-conditionals).

## Loops

*Since 1.8 — opt-in.* Repeat part of the document once per element of a list, directly from the template:

```text
${#for line in lines}
${loop.index}. ${line.name} — ${line.amount} EUR
${#endfor}
```

A loop opens with `${#for <item> in <expression>}` and closes with `${#endfor}` (the nearest open loop). The iterable is an [expression](#expression-conditionals) — a context variable (`lines`), a nested value (`order.lines`) or a function result (`slice(lines, 0, 5)`) — evaluated with the same language, functions, error mode and security rules. Every example of this section is executed by `tests/Integration/ExpressionConditionalsUsageDocTest.php`.

### Enable loops

```yaml
# config/packages/nowo_word_template.yaml
nowo_word_template:
    loops:
        enabled: true               # default false: loop markers stay as literal text
        max_iterations: 1000        # elements of one loop
        max_total_iterations: 10000 # all loops of one merge together (nested loops multiply)
        max_output_bytes: 33554432  # XML produced by all loops of one merge, checked before rendering
    conditional_expressions:
        enabled: true               # needed for ${#if …} inside loop bodies (and everywhere else)
        on_error: exception         # also applies to loops
```

Loops have their own switch: with `loops.enabled: true` and `conditional_expressions.enabled: false`, loops are expanded and `${#if …}` expression markers stay as text. The loop markers have their own delimiters (`loop_for_opening: '${#for'`, `loop_for_closing: '}'`, `loop_endfor_opening: '${#endfor'`, `loop_endfor_closing: '}'`) — see [CONFIGURATION.md](CONFIGURATION.md#loops).

### Repeat paragraphs

Each marker sits in its own paragraph; the paragraphs between them are repeated. Inside the body, `${line.name}` reads the current element (dot access for maps, `${row[0]}` for lists) and `${loop.index}` the position.

<!-- expr-example -->
**In Word:**

```text
Order ${number}
${#for line in lines}
${loop.index}. ${line.name} — ${line.amount} EUR
${#endfor}
Total: ${total} EUR
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'number' => 'F-2026-001',
    'lines'  => [
        ['name' => 'Anchor bolts', 'amount' => 40],
        ['name' => 'Brackets', 'amount' => 25.5],
    ],
    'total'  => 65.5,
]);
```

**Result:**

```text
Order F-2026-001
1. Anchor bolts — 40 EUR
2. Brackets — 25.5 EUR
Total: 65.5 EUR
```

Values are printed like other placeholders (`true` → `1`, `false` → `0`, `null` → empty, enums → value, dates → `Y-m-d`) and XML-escaped. A value is never read as a template again: `${…}` inside a value (an order line typed by a customer, say) is printed as typed, not replaced by another context value — neither alone nor next to another value (every `$` of a value is written as `&#36;`, which Word shows as `$`) — and characters that are invalid in XML are dropped — this also applies to ordinary placeholders since 1.8. A list or a map cannot be printed: print one of its values or loop over it.

### Repeat table rows

When the opening and the closing marker sit **in rows of their own** of the same table, the rows between them are repeated and the two marker rows are removed. Any number of rows can be repeated together.

<!-- expr-example -->
**In Word:**

```text
| Item | Amount |
| ${#for line in lines} |
| ${line.name} | ${line.amount} |
| ${#endfor} |
| Total | ${total} |
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'lines' => [
        ['name' => 'Anchor bolts', 'amount' => 40],
        ['name' => 'Brackets', 'amount' => 25.5],
    ],
    'total' => 65.5,
]);
```

**Result:**

```text
| Item | Amount |
| Anchor bolts | 40 |
| Brackets | 25.5 |
| Total | 65.5 |
```

(In the Word example each `|` line is one table row.) Row loops and [`TableRows`](#one-table-with-several-columns-tablerows) are independent: keep using `TableRows` for existing templates, use a loop when the template should decide (conditions per row, nested lists, several rows per element). Do not put a `TableRows` anchor inside a loop body.

Markers in the **same** cell repeat paragraphs inside that cell; an opening marker in one row and the closing marker in another **cell of the same row** is an error (the block would cut the row).

### Loop variables

| Placeholder / variable | Value | Twig |
|------------------------|-------|------|
| `${item}` / `item` | the current element (the name is yours: `line`, `order`, …) | same |
| `${key}` / `key` | with `${#for key, item in items}`: the list index (0-based) or the map key | `for key, item in items` |
| `${loop.index}` | position, starting at 1 | same |
| `${loop.index0}` | position, starting at 0 | same |
| `${loop.revindex}` / `${loop.revindex0}` | positions from the end (1-based / 0-based) | same |
| `${loop.first}` / `${loop.last}` | `1` for the first / last element, else `0` (bools in expressions) | same |
| `${loop.length}` | number of elements | same |
| `${loop.parent.index}` | in a nested loop, the `loop` of the enclosing loop (`loop.parent.parent…` further out) | Twig's `loop.parent` is the parent *context* (`loop.parent.loop.index`) |

The same names are variables of every `${#if …}` and nested `${#for …}` inside the body. **Shadowing:** a loop variable hides a context key with the same name inside the body only (an inner `loop` hides the outer one — use `loop.parent`). Outside the body, `${item}` is an ordinary placeholder again.

### Maps, keys and functions

<!-- expr-example -->
**In Word:**

```text
${#for code, label in countries}
${code}: ${label}
${#endfor}
${#for tag in slice(tags, 0, 2)}
#${tag}
${#endfor}
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'countries' => ['ES' => 'Spain', 'PT' => 'Portugal'],
    'tags'      => ['urgent', 'export', 'fragile'],
]);
```

**Result:**

```text
ES: Spain
PT: Portugal
#urgent
#export
```

### Conditions inside a loop and nested loops

Blocks are processed outside-in with the loop variables in scope, so a condition can test the current element, and an inner loop can iterate over a list of the current element.

<!-- expr-example -->
**In Word:**

```text
${#for order in orders}
Order ${order.id}
${#if order.urgent}
Ship today.
${#endif}
${#for line in order.lines}
${loop.parent.index}.${loop.index} ${line}
${#endfor}
${#endfor}
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'orders' => [
        ['id' => 'A-7', 'urgent' => true, 'lines' => ['Bolts', 'Nuts']],
        ['id' => 'B-8', 'urgent' => false, 'lines' => ['Crane']],
    ],
]);
```

**Result:**

```text
Order A-7
Ship today.
1.1 Bolts
1.2 Nuts
Order B-8
2.1 Crane
```

A loop inside a removed `${#if …}` is never evaluated, and a `${#if …}` around the whole loop is the way to skip a heading when the list is empty.

### Empty lists

An empty list removes the whole block (including the marker paragraphs). There is no `${#else}`; add the opposite condition:

<!-- expr-example -->
**In Word:**

```text
Order lines
${#for line in lines}
${line}
${#endfor}
${#if length(lines) == 0}
No items in this order.
${#endif}
```

**In PHP:**

```php
$result = $processor->process($templatePath, [
    'lines' => [],
]);
```

**Result:**

```text
Order lines
No items in this order.
```

### Loop errors and limits

| Problem | Result (`on_error: exception`) |
|---------|-------------------------------|
| the iterable is not a list or a map (`${#for x in total}` with an int) | `ConditionalExpressionException`: `must iterate over a list or a map, got int` |
| unknown iterable / typo (`${#for x in itms}`) | `uses the unknown variable "itms"` |
| more than `loops.max_iterations` elements | `has 1500 items; the limit is 1000` |
| all loops together above `loops.max_total_iterations` (e.g. 200 orders × 100 lines) | `exceed the allowed 10000 iterations (nested loops multiply)` |
| all loops of the merge above `loops.max_output_bytes` (32 MiB) — checked before a loop renders | `would produce more than 33554432 bytes of document XML` |
| more than `max_markers` (2000) markers in one part, nesting deeper than `max_depth` (32), more than `max_evaluations` (100,000) evaluations | `TemplateLimitExceededException` — always thrown, whatever `on_error` says |
| `${item.missing}` | `uses the unknown variable "item.missing"` |
| `${item}` holding a list / map | `not a printable value` |
| `${#endfor}` without loop, loop never closed, `${#for items}` (no `in`), `${#endif}` closing a loop | layout error (`there is no open loop to close`, `the loop is never closed`, `a loop needs "item in <expression>"`, `it cannot close the loop`) |

With `on_error: hide` a failing loop is removed (a failing placeholder prints empty); with `keep` the loop is left as text (a failing placeholder stays as typed). `null` is not iterable — write `${#for x in default(items, [])}` when a list may be missing.

`listLoops()` returns the loop heads of a template (`['line in lines', 'key, line in order.lines']`), `listVariables()` omits the loop markers and the placeholders of loop variables when loops are enabled, and `lintConditionalExpressions()` checks loop heads too, knowing the loop variables inside each body.

---

## Combined example (scalars + 2 tables + 2 conditionals)

**Bundle config** (defaults shown explicitly):

```yaml
nowo_word_template:
    macro_opening: '${'
    macro_closing: '}'
    conditional_if_opening: '${#if'
    conditional_if_closing: '}'
    conditional_endif_opening: '${#endif'
    conditional_endif_closing: '}'
```

**In Word** (structure):

```text
Offer: ${offer_title}
Client: ${client.name}

${#if annex}
Annex for ${client.name}.
${#endif annex}

${#if vip_section}
VIP clause.
${#endif vip_section}
```

Table Garantías:

| TIPO | LIMITE |
|------|--------|
| `${GARANTIAARRAY}` | `${LIMITE_ARRAY}` |

Table Coberturas:

| CODE | LABEL |
|------|-------|
| `${COVER_CODE}` | `${COVER_LABEL}` |

**In PHP:**

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\TableRows;

$result = $processor->process($templatePath, [
    'offer_title' => 'Caución 2026',
    'client'      => ['name' => 'ACME'],

    'show_annex' => new ConditionalBlock('annex', true),
    'show_vip'   => new ConditionalBlock('vip_section', false),

    'garantias' => new TableRows('GARANTIAARRAY', [
        ['GARANTIAARRAY' => 'Acopios', 'LIMITE_ARRAY' => 1000],
        ['GARANTIAARRAY' => 'Aduanas', 'LIMITE_ARRAY' => 20000],
    ]),
    'coberturas' => new TableRows('COVER_CODE', [
        ['COVER_CODE' => 'C1', 'COVER_LABEL' => 'Fire'],
        ['COVER_CODE' => 'C2', 'COVER_LABEL' => 'Theft'],
    ]),
]);

file_put_contents('/tmp/out.docx', $result->readContents());
$result->dispose();
```

---

## Context types (summary)

| PHP type | Behaviour |
|----------|-----------|
| Scalars / null | `setValue`; booleans become `1` / `0`; null becomes empty string. |
| Backed / pure enum | Its value / case name (since 1.8). |
| `DateTimeInterface` | `'Y-m-d'` (since 1.8; pass a formatted string for another format). |
| Nested arrays | Flattened to dot keys (`client.city`). |
| `Stringable` | Cast to string. |
| `TableRows` | One per independent table row; distinct `rowVariable` anchors. |
| `ConditionalBlock` | One per named region (or one list of them under a key); siblings and nesting supported; `$visible` must be a `bool` — see [Conditionals by data type](#conditionals-by-data-type). |
| `HtmlContent` | `setComplexBlock` via PHPWord `Html::addHtml`. |
| `ImageSource` | `setImageValue`; optional width/height. |

---

## Listing placeholders and conditional blocks

**Paint variables** (including those inside conditional regions). Markers `${#if …}` / `${#endif …}` are omitted:

```php
/** @var list<string> $variables */
$variables = $processor->listVariables('/srv/templates/offer.docx');
// e.g. ['client.name', 'annex_title', 'GARANTIAARRAY', 'offer_title']
```

**Conditional block names** (from opening markers such as `${#if annex}` → `annex`), using `conditional_*` delimiters:

```php
/** @var list<string> $blocks */
$blocks = $processor->listConditionalBlocks('/srv/templates/offer.docx');
// e.g. ['annex', 'vip_section', 'optional_funding']
```

**Expression conditionals** (`${#if total > 0}` … `${#endif}`) are listed and checked by `listConditionalExpressions()` / `lintConditionalExpressions()` — see [Errors](#errors). With `conditional_expressions.enabled: true`, `listConditionalBlocks()` returns named blocks only.

Both scan the main document plus headers/footers. Table anchors appear in `listVariables()` as base names (not `#1` / `#2`). Use `listConditionalBlocks()` to know which `ConditionalBlock('name', $visible)` entries you must supply.

**In Word:**

```text
${#if annex}
Annex title: ${annex_title}
${#endif annex}
```

**Listed as:**
- `listVariables()` → `annex_title` (and any other paint fields)
- `listConditionalBlocks()` → `annex`

---

## Combining with HtmlToWordBundle

Use **WordTemplateBundle** when you already have a `.docx` skeleton with macros. Use **[HtmlToWordBundle](https://github.com/nowo-tech/HtmlToWordBundle)** to build a complete `.docx` from HTML alone when you do not maintain a Word template.

## Forms in host apps and demos (REQ-TWIG-005)

The bundle ships no page templates of its own; host apps call its services from their own controllers and forms. Every form in host apps and in the demo is a PHP Form Type created in the controller and rendered only with the Twig form helpers and the rendered-aware child loop (REQ-TWIG-003):

```twig
{{ form_start(form) }}
    {% for child in form %}
        {% if not child.rendered %}
            {{ form_row(child) }}
        {% endif %}
    {% endfor %}
    <button type="submit">Submit</button>
{{ form_end(form) }}
```

Hosts and demos must not reintroduce raw `<form>` / `<input>` / `<textarea>` / `<select>` markup; `make check-no-raw-html-form` (part of `make release-check`) fails when bundle or demo Twig contains them.
