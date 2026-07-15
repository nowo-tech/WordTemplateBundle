# Configuration

Root key: `nowo_word_template`.

| Option | Default | Description |
|--------|---------|-------------|
| `macro_opening` | `${` | Opening characters for placeholders (must match what authors type in Word). |
| `macro_closing` | `}` | Closing characters for placeholders. |
| `conditional_if_opening` | `${#if` | Opening delimiter for conditional blocks (before the block name). |
| `conditional_if_closing` | `}` | Closing delimiter for conditional opening markers. |
| `conditional_endif_opening` | `${#endif` | Opening delimiter for conditional end markers (before the block name). |
| `conditional_endif_closing` | `}` | Closing delimiter for conditional end markers. |

PHPWord uses **static** delimiter settings internally for `TemplateProcessor`; avoid mixing delimiter styles across concurrent long-lived workers if you change defaults.

`listVariables()` and `process()` both apply placeholder delimiters when opening a template. Conditional markers use the `conditional_*` keys and are independent from scalar placeholder delimiters.

Example:

```yaml
# Delimiter settings — must match placeholders typed in Word (see docs/USAGE.md).
nowo_word_template:
    macro_opening: '${'
    macro_closing: '}'
    conditional_if_opening: '${#if'
    conditional_if_closing: '}'
    conditional_endif_opening: '${#endif'
    conditional_endif_closing: '}'
```

A commented reference copy ships in `src/Resources/config/nowo_word_template.yaml` and in `demo/symfony8/config/packages/nowo_word_template.yaml`.

Authors then type `${#if optional_funding}` … `${#endif optional_funding}` in the `.docx` (one marker per paragraph).
