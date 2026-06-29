# Configuration

Root key: `nowo_word_template`.

| Option | Default | Description |
|--------|---------|-------------|
| `macro_opening` | `${` | Opening characters for placeholders (must match what authors type in Word). |
| `macro_closing` | `}` | Closing characters for placeholders. |

PHPWord uses **static** delimiter settings internally for `TemplateProcessor`; avoid mixing delimiter styles across concurrent long-lived workers if you change defaults.

`listVariables()` and `process()` both apply these delimiters when opening a template.

Example:

```yaml
nowo_word_template:
    macro_opening: '${'
    macro_closing: '}'
```
