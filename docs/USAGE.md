# Usage

Inject `Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface`.

## Placeholders in Word

Authors insert plain-text placeholders in the `.docx`, for example `${company}` or `${project.code}` (nested context keys use dots after flattening).

For repeating **table rows**, the row that must be duplicated must include the anchor placeholder first (e.g. `${line_id}`), then `cloneRow` appends `#1`, `#2`, … to each column placeholder name you fill from PHP.

For **conditional blocks** (Twig-like `{% if %}` / `{% endif %}`), wrap content between opening and closing markers. Delimiters are configured separately from scalar placeholders:

| Marker | Config key | Default |
|--------|------------|---------|
| Opening | `conditional_if_opening` + block name + `conditional_if_closing` | `${#if blockName}` |
| Closing | `conditional_endif_opening` + block name + `conditional_endif_closing` | `${#endif blockName}` |

Each marker must sit in its **own paragraph** in Word (same constraint as PHPWord table rows). Pass a `ConditionalBlock` in the context to show or remove the region. **Nested blocks** with different names are resolved inside-out (deepest first).

**Scope (v1):** only simple `if` / `endif` pairs. There is no `elseif` or `else` in the template syntax yet — model mutually exclusive sections with separate block names in PHP when needed.

## Context types

| PHP type | Behaviour |
|----------|-----------|
| Scalars / null | `setValue`; booleans become `1` / `0`; null becomes empty string. |
| Nested arrays | Flattened: `['a' => ['b' => 1]]` → key `a.b`. |
| `Stringable` | Cast to string. |
| `TableRows` | `cloneRow` + `setValue` for `name#N` per row. |
| `ConditionalBlock` | `${#if block}` … `${#endif block}` regions: keep or remove content from PHP (nested blocks supported). |
| `HtmlContent` | `setComplexBlock` with HTML parsed by PHPWord (`Html::addHtml`). |
| `ImageSource` | `setImageValue`; optional width/height passed through to PHPWord. |

## Example

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;

$result = $processor->process(
    '/srv/templates/offer.docx',
    [
        'offer_title' => 'Maintenance 2026',
        'client' => ['name' => 'ACME'],
        'optional_funding' => new ConditionalBlock('optional_funding', $order->hasPublicFunding()),
        'funding_note' => 'Funded by Horizon Europe (grant #12345).',
        'lines' => new TableRows('line_code', [
            ['line_code' => 'A1', 'description' => 'Item one'],
            ['line_code' => 'B2', 'description' => 'Item two'],
        ]),
        'terms_block' => new HtmlContent('<p>Payment within <strong>30 days</strong>.</p>'),
    ],
);

file_put_contents('/tmp/out.docx', $result->readContents());
$result->dispose();
```

## Listing placeholders

To discover which placeholders a template defines (for dynamic forms, validation, or documentation), call `listVariables()` on the same service:

```php
/** @var list<string> $variables */
$variables = $processor->listVariables('/srv/templates/offer.docx');
// e.g. ['client.name', 'line_code', 'offer_title', 'terms_block']
```

The method scans the main document part plus headers and footers. It uses the delimiters from `nowo_word_template` (`macro_opening` / `macro_closing`). For repeating table rows, only the base names appear (e.g. `line_code`), not the `#1`, `#2` suffixes added by `cloneRow` during `process()`. Conditional opening/closing markers (`#if …`, `#endif …`) are omitted.

## Combining with HtmlToWordBundle

Use **WordTemplateBundle** when you already have a `.docx` skeleton with macros. Use **[HtmlToWordBundle](https://github.com/nowo-tech/HtmlToWordBundle)** to build a complete `.docx` from HTML alone when you do not maintain a Word template.
