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

The template never evaluates an expression: everything between `${#if ` and `}` is a **block name**, not a condition (`${#if total > 0}` does not compare anything). Visibility is always decided in PHP and handed over as a **`bool`**:

```php
Nowo\WordTemplateBundle\Model\ConditionalBlock::__construct(string $blockName, bool $visible)
```

So every conditional boils down to one question: *how do I turn my value into a reliable `true` / `false`?* This section answers it for every common PHP type. Each expression in the tables is copied verbatim into `tests/Integration/ConditionalsUsageDocTest.php`, which runs it against sample values (and renders a real `.docx` for the patterns further down) — if an expression here and the test drift apart, the suite fails.

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
| `${#if total > 0}` | Not an expression: it is a block literally named `total > 0` (and Word stores `>` as `&gt;`). Use a plain name and decide in PHP. |

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
