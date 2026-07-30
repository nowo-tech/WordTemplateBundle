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

| CÓDIGO | DESCRIPCIÓN |
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

Nested blocks resolve **inside-out** (deepest first). There is no `else` / `elseif` in v1 — use separate named blocks for mutually exclusive sections.

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
| `ConditionalBlock` | One per named region; siblings and nesting supported. |
| `HtmlContent` | `setComplexBlock` via PHPWord `Html::addHtml`. |
| `ImageSource` | `setImageValue`; optional width/height. |

---

## Listing placeholders

```php
/** @var list<string> $variables */
$variables = $processor->listVariables('/srv/templates/offer.docx');
// e.g. ['client.name', 'GARANTIAARRAY', 'LIMITE_ARRAY', 'COVER_CODE', 'offer_title']
```

Scans the main document plus headers/footers. Uses `macro_opening` / `macro_closing`. Table anchors appear as base names (not `#1` / `#2`). Conditional markers (`#if …`, `#endif …`) are omitted.

---

## Combining with HtmlToWordBundle

Use **WordTemplateBundle** when you already have a `.docx` skeleton with macros. Use **[HtmlToWordBundle](https://github.com/nowo-tech/HtmlToWordBundle)** to build a complete `.docx` from HTML alone when you do not maintain a Word template.
