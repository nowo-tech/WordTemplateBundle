# Configuration

Root key: `nowo_word_template`.

| Option | Default | Description |
|--------|---------|-------------|
| `timeout` | `180` | Wall-clock timeout in seconds for `WordTemplateProcessor::process()`. Applied as a **cooperative deadline** between merge phases and as PHP `set_time_limit`. Prefer `%env(int:PROCESS_TIMEOUT)%` (shared Nowo default **180**). Keep PHP `max_execution_time` and the HTTP server write timeout **above** this value (FrankenPHP / **REQ-RUNTIME-001**). |
| `allowed_roots` | `[]` | Absolute directories that template, `ImageSource`, output paths and local `<img src>` paths inside `HtmlContent` must stay under (resolved with `realpath`, so `..` and symlinks cannot escape). Empty = unrestricted for templates / `ImageSource` / output (BC), but local image paths in `HtmlContent` are then **dropped**. Set it in production. |
| `path_allowlist_required` | `false` | When `true`, an empty `allowed_roots` is a configuration error (fail-closed). The Flex recipe enables it under `when@prod`. |
| `html_image_max_bytes` | `5242880` | Maximum decoded size (bytes) of one `data:` image inside `HtmlContent`; larger images are dropped. |
| `macro_opening` | `${` | Opening characters for placeholders (must match what authors type in Word). |
| `macro_closing` | `}` | Closing characters for placeholders. |
| `conditional_if_opening` | `${#if` | Opening delimiter for conditional blocks (before the block name). |
| `conditional_if_closing` | `}` | Closing delimiter for conditional opening markers. |
| `conditional_endif_opening` | `${#endif` | Opening delimiter for conditional end markers (before the block name). |
| `conditional_endif_closing` | `}` | Closing delimiter for conditional end markers. |
| `loop_for_opening` / `loop_for_closing` | `${#for` / `}` | Delimiters of loop markers (`${#for item in items}`). |
| `loop_endfor_opening` / `loop_endfor_closing` | `${#endfor` / `}` | Delimiters of loop end markers (`${#endfor}`). |
| `loops.enabled` | `false` | Opt-in (1.8): expand `${#for item in items}` … `${#endfor}`. See [Loops](#loops). |
| `loops.max_iterations` | `1000` | Maximum elements of one loop. |
| `loops.max_total_iterations` | `10000` | Maximum iterations of all loops of one merge together (nested loops multiply). |
| `loops.max_output_bytes` | `33554432` | Maximum bytes of document XML produced by all loops of one merge (all parts together); checked before rendering. |
| `conditional_expressions.max_markers` | `2000` | Maximum expression / loop markers per document part (body, each header, each footer). |
| `conditional_expressions.max_depth` | `32` | Maximum nesting depth of expression blocks and loops. |
| `conditional_expressions.max_evaluations` | `100000` | Maximum expression evaluations of one merge (loops re-evaluate their bodies). |
| `conditional_expressions.enabled` | `false` | Opt-in (1.8): evaluate `${#if <expression>}` … `${#endif}` blocks (nameless end marker). When `false`, expression markers stay as literal text. See [Conditional expressions](#conditional-expressions). |
| `conditional_expressions.on_error` | `exception` | `exception`, `hide` or `keep`: what happens with an invalid expression, an unknown variable, a bad function argument or a marker layout the bundle cannot apply. `hide` removes a block whose expression fails; a block with a bad marker layout cannot be delimited and is left as text in `hide` and `keep` (use `exception`, the default, to fail closed). |
| `conditional_expressions.max_length` | `500` | Maximum length of one expression, in characters (1–10000). |
| `conditional_expressions.timezone` | `null` | Time zone of `now()` / `date()`; `null` = PHP's default time zone. Validated when the configuration is compiled (an unknown identifier is a configuration error). |
| `conditional_expressions.functions.allow` | `null` | When a list, only these functions are available (built-in and host). |
| `conditional_expressions.functions.deny` | `[]` | Functions that are never available (e.g. `[now, date]`). |

### Timeout hierarchy (FrankenPHP-safe)

Innermost → outermost:

1. **`timeout`** / `PROCESS_TIMEOUT` (bundle operation) — fires first; throws `ProcessingTimedOutException`.
2. PHP `max_execution_time` / `max_input_time` — must be **greater** than `timeout` (demo: **240**).
3. Reverse proxy / Caddy `servers.timeouts.write` — must be **greater** than PHP (demo: **250s**).
4. FrankenPHP `max_wait_time` (when workers exist) — caps wait for a free thread (demo: **30s**).

When raising `timeout`, raise PHP and Caddy write deadlines in the same change. See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

PHPWord uses **static** delimiter settings internally for `TemplateProcessor`; avoid mixing delimiter styles across concurrent long-lived workers if you change defaults. `process()` isolates PHPWord `Html` statics (`$css` / `$xpath` / `$options`) and restores `Settings::setOutputEscapingEnabled` so merges stay safe when FrankenPHP does **not** reset the kernel between requests — see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

### Images inside `HtmlContent`

PHPWord's HTML parser would otherwise `file_get_contents()` any `<img src>`. Before the fragment reaches PHPWord, the bundle keeps only:

- `data:image/png|jpeg|gif|bmp;base64,…` payloads that decode to a real image of at most `html_image_max_bytes`; they are written to temp files that are deleted after the merge;
- local filesystem paths that exist under `allowed_roots` (only when `allowed_roots` is non-empty).

Remote URLs (`http(s)://`, `//host`), stream wrappers (`file://`, `php://`, `phar://`, …) and any other `src` are removed from the HTML (one `warning` log record per merge with the count, see [Logging](#logging)); the rest of the fragment is merged unchanged. Use `ImageSource` for images that must always appear.

`listVariables()` and `process()` both apply placeholder delimiters when opening a template. Conditional markers use the `conditional_*` keys and are independent from scalar placeholder delimiters. When a conditional opening delimiter starts with `macro_opening` (e.g. `${#if` / `${`), `listVariables()` omits the markers (`#if name`, `#endif name`) from the returned variables.

Example:

```yaml
# Delimiter settings — must match placeholders typed in Word (see docs/USAGE.md).
# Timeout: prefer shared PROCESS_TIMEOUT under FrankenPHP (see DEMO-FRANKENPHP.md).
nowo_word_template:
    timeout: '%env(int:PROCESS_TIMEOUT)%'
    allowed_roots: []
    path_allowlist_required: false
    html_image_max_bytes: 5242880
    macro_opening: '${'
    macro_closing: '}'
    conditional_if_opening: '${#if'
    conditional_if_closing: '}'
    conditional_endif_opening: '${#endif'
    conditional_endif_closing: '}'
    conditional_expressions:
        enabled: false
```

### Conditional expressions

```yaml
nowo_word_template:
    conditional_expressions:
        enabled: true
        on_error: exception      # exception | hide | keep
        max_length: 500
        timezone: 'Europe/Madrid'
        functions:
            allow: ~             # null = all functions
            deny: [now, date]    # e.g. keep templates independent of the current date
```

- The nameless end marker is the `conditional_endif_opening` + `conditional_endif_closing` delimiters without a name (`${#endif}` by default) — there is no separate setting.
- `now()` / `date()` read the Symfony `clock` service when it exists (FrameworkBundle registers it), so `MockClock` freezes them in tests; otherwise the system clock.
- Host functions: services implementing `Nowo\WordTemplateBundle\Expression\ExpressionFunctionProviderInterface` are autoconfigured with the `nowo_word_template.expression_function_provider` tag; built-ins are registered with priority 1000, so a host function with the same name (default priority 0) replaces the built-in. An unknown name in `allow` / `deny` is a configuration error raised when the evaluator is built.
- `max_markers`, `max_depth` and `max_evaluations` always throw `TemplateLimitExceededException` (whatever `on_error` says). The merge `timeout` is also checked while blocks and loops are rendered, and `listConditionalExpressions()`, `listLoops()`, `listConditionalBlocks()`, `listVariables()` and `lintConditionalExpressions()` run under the same `timeout` (`ProcessingTimedOutException`).
- Parsed expressions are cached per evaluator service in a bounded in-memory pool (256 entries), so a FrankenPHP worker never grows without limit.
- `on_error: hide` / `keep` log one `warning` per merge with the number of errors (see [Logging](#logging)); `exception` throws `ConditionalExpressionException` (logged as `Word template merge rejected.`).

Full syntax, operators, data types and the function reference: [USAGE.md — Expression conditionals](USAGE.md#expression-conditionals).

### Loops

```yaml
nowo_word_template:
    loops:
        enabled: true
        max_iterations: 1000
        max_total_iterations: 10000
    # optional, defaults shown
    loop_for_opening: '${#for'
    loop_for_closing: '}'
    loop_endfor_opening: '${#endfor'
    loop_endfor_closing: '}'
```

- The iterable of `${#for item in <expression>}` is evaluated by the same evaluator as conditional expressions (functions, `max_length`, `functions.allow` / `deny`), and loop errors follow `conditional_expressions.on_error`.
- `loops.enabled` is independent from `conditional_expressions.enabled`: `${#if …}` inside loop bodies is only evaluated when expressions are enabled too.
- Placeholders of loop variables (`${line.name}`, `${loop.index}`) are written with the `macro_opening` / `macro_closing` delimiters.
- `loops.max_output_bytes` (default 32 MiB) is **one budget for the whole merge** (all loops, all parts), checked before a loop renders (body size × iterations) and while it renders.

Full reference: [USAGE.md — Loops](USAGE.md#loops).

### Logging

`WordTemplateProcessor` takes an optional PSR-3 `Psr\Log\LoggerInterface` (`$logger`, wired to `@?logger`; no logger service = no logging). With MonologBundle the records go to the **`word_template`** channel, so they can be routed or silenced per channel:

```yaml
# config/packages/monolog.yaml (host app) — example: keep only warnings and errors of the bundle
monolog:
    handlers:
        word_template:
            type: stream
            path: '%kernel.logs_dir%/word_template.log'
            level: warning
            channels: ['word_template']
```

| Level | Message | Context |
|-------|---------|---------|
| `info` | `Word template merged.` | `template` (file name), `temporary`, `duration_ms` |
| `warning` | `Word template path rejected by nowo_word_template.allowed_roots.` | `path_kind` (`template` / `image` / `output`), `template` |
| `warning` | `Word template merge dropped HTML images that are not allowed.` | `template`, `dropped_images` |
| `warning` | `Word template merge timed out.` | `template`, `exception`, `timeout_s` |
| `warning` | `Word template merge rejected.` | `template`, `exception` (missing template, invalid context value, invalid conditional expression) |
| `warning` | `Word template conditional expressions failed and were applied with on_error mode.` | `template`, `on_error` (`hide` / `keep`), `errors` (count) |
| `error` | `Word template merge failed.` | `template`, `exception` (unexpected failure, e.g. the output cannot be written) |

Every record also has `bundle: word_template` and `action`. Only the template **file name** is logged — never full paths, context values, HTML, image sources or document contents ([SECURITY.md](SECURITY.md#logging-and-secrets)).

A commented reference copy ships in `src/Resources/config/nowo_word_template.yaml` and in `demo/symfony8/config/packages/nowo_word_template.yaml`.

Production (as shipped in the Flex recipe):

```yaml
when@prod:
    nowo_word_template:
        path_allowlist_required: true
        allowed_roots:
            - '%kernel.project_dir%/templates'
            - '%kernel.project_dir%/var'
            - '%kernel.project_dir%/public'
```

Authors then type `${#if optional_funding}` … `${#endif optional_funding}` in the `.docx` (one marker per paragraph).
