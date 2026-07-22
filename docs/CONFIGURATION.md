# Configuration

Root key: `nowo_word_template`.

| Option | Default | Description |
|--------|---------|-------------|
| `timeout` | `180` | Wall-clock timeout in seconds for `WordTemplateProcessor::process()`. Applied as a **cooperative deadline** between merge phases and as PHP `set_time_limit`. Prefer `%env(int:PROCESS_TIMEOUT)%` (shared Nowo default **180**). Keep PHP `max_execution_time` and the HTTP server write timeout **above** this value (FrankenPHP / **REQ-RUNTIME-001**). |
| `macro_opening` | `${` | Opening characters for placeholders (must match what authors type in Word). |
| `macro_closing` | `}` | Closing characters for placeholders. |
| `conditional_if_opening` | `${#if` | Opening delimiter for conditional blocks (before the block name). |
| `conditional_if_closing` | `}` | Closing delimiter for conditional opening markers. |
| `conditional_endif_opening` | `${#endif` | Opening delimiter for conditional end markers (before the block name). |
| `conditional_endif_closing` | `}` | Closing delimiter for conditional end markers. |

### Timeout hierarchy (FrankenPHP-safe)

Innermost → outermost:

1. **`timeout`** / `PROCESS_TIMEOUT` (bundle operation) — fires first; throws `ProcessingTimedOutException`.
2. PHP `max_execution_time` / `max_input_time` — must be **greater** than `timeout` (demo: **240**).
3. Reverse proxy / Caddy `servers.timeouts.write` — must be **greater** than PHP (demo: **250s**).
4. FrankenPHP `max_wait_time` (when workers exist) — caps wait for a free thread (demo: **30s**).

When raising `timeout`, raise PHP and Caddy write deadlines in the same change. See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

PHPWord uses **static** delimiter settings internally for `TemplateProcessor`; avoid mixing delimiter styles across concurrent long-lived workers if you change defaults.

`listVariables()` and `process()` both apply placeholder delimiters when opening a template. Conditional markers use the `conditional_*` keys and are independent from scalar placeholder delimiters.

Example:

```yaml
# Delimiter settings — must match placeholders typed in Word (see docs/USAGE.md).
# Timeout: prefer shared PROCESS_TIMEOUT under FrankenPHP (see DEMO-FRANKENPHP.md).
nowo_word_template:
    timeout: '%env(int:PROCESS_TIMEOUT)%'
    macro_opening: '${'
    macro_closing: '}'
    conditional_if_opening: '${#if'
    conditional_if_closing: '}'
    conditional_endif_opening: '${#endif'
    conditional_endif_closing: '}'
```

A commented reference copy ships in `src/Resources/config/nowo_word_template.yaml` and in `demo/symfony8/config/packages/nowo_word_template.yaml`.

Authors then type `${#if optional_funding}` … `${#endif optional_funding}` in the `.docx` (one marker per paragraph).
