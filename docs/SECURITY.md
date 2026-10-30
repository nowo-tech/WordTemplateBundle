# Security — WordTemplateBundle

This document describes the **attack surface**, **threats**, and **controls** for `nowo-tech/word-template-bundle`. It is written in English per project standards.

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threats and mitigations](#threats-and-mitigations)
  - [Unsafe template or output paths](#unsafe-template-or-output-paths)
  - [Untrusted context / XXE and XML](#untrusted-context--xxe-and-xml)
  - [Resource exhaustion / long merges](#resource-exhaustion--long-merges)
  - [HTML images (SSRF / local file read)](#html-images-ssrf--local-file-read)
  - [Image path abuse](#image-path-abuse)
  - [Dependency vulnerabilities](#dependency-vulnerabilities)
  - [Expression conditionals](#expression-conditionals)
- [Logging and secrets](#logging-and-secrets)
- [Cryptography](#cryptography)
- [Reporting](#reporting)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

The bundle merges **application-provided context** into **WordprocessingML (.docx)** templates using PHPWord `TemplateProcessor`. It may:

- Read `.docx` template files from paths chosen by the application.
- Write merged output to a temp file or a caller-supplied path.
- Embed **HTML fragments** (via PHPWord `Html::addHtml`) and **images** from filesystem paths.

It does **not** expose HTTP routes by itself; the host application controls authorization and input validation.

## Attack surface

| Input | Source | Notes |
|-------|--------|-------|
| Template path | Application | Must point to intended `.docx`; avoid user-controlled absolute paths without validation. |
| Context values | Application / stored user data | Escaped as OOXML text during `WordTemplateProcessor::process()` via PHPWord `Settings::setOutputEscapingEnabled(true)` (restored after merge). |
| HTML in `HtmlContent` | Application | Parsed into OOXML; treat as untrusted if sourced from end users. `<img src>` is filtered (see below). |
| Image paths in `ImageSource` | Application | Path traversal / sensitive file read if paths are user-controlled. |
| Expressions in `${#if …}` markers (opt-in) | Template author — possibly an end user when the host lets users upload templates | Evaluated by a sandboxed Symfony ExpressionLanguage; see [Expression conditionals](#expression-conditionals). |

## Threats and mitigations

### Unsafe template or output paths

- **Risk**: Path traversal or reading/writing outside intended directories.
- **Mitigation**: Configure `nowo_word_template.allowed_roots` and set `path_allowlist_required: true` in production (Flex `when@prod`). `WordTemplateProcessor` refuses template, image, and output paths outside those roots.
- **Risk**: Writing merged documents to predictable or world-readable locations.
- **Mitigation**: Use random temp names (`ProcessedDocument`) or app-controlled storage; restrict filesystem permissions.

### Untrusted context / XXE and XML

- **Risk**: Extremely large strings or crafted payloads stressing PHPWord; unescaped `&` / `<` in scalar merges corrupting `document.xml`.
- **Mitigation**: Enforce size limits at the application layer; keep dependencies updated (`composer audit`). Configure `nowo_word_template.timeout` (default **180s**) so a pathological merge cannot pin a FrankenPHP worker indefinitely (**REQ-RUNTIME-001**). Scalar and `TableRows` cell values are XML-escaped for the duration of `process()` (PHPWord output escaping).

### Resource exhaustion / long merges

- **Risk**: Large templates, heavy HTML, or many `TableRows` occupy a PHP thread until completion.
- **Mitigation**: Bundle `timeout` applies a cooperative deadline between merge phases and `set_time_limit`. Keep PHP `max_execution_time` and reverse-proxy write deadlines **above** this value (see [CONFIGURATION.md](CONFIGURATION.md) and [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md)).

### HTML images (SSRF / local file read)

- **Risk**: PHPWord `Html::addHtml()` calls `file_get_contents()` on every `<img src>`: remote URLs (SSRF to internal services / cloud metadata), stream wrappers (`php://filter`, `phar://`, `file://`) and local files outside `allowed_roots`; `data:` images are written to temp files PHPWord never deletes.
- **Mitigation**: `HtmlImageSanitizer` rewrites the fragment before PHPWord sees it. Only `data:image/(png|jpeg|gif|bmp);base64` payloads that decode to a real image ≤ `html_image_max_bytes`, and existing local files under a non-empty `allowed_roots`, are kept; every other `<img>` is removed. Decoded `data:` images are written to bundle-owned temp files deleted after each merge.

### Image path abuse

- **Risk**: `ImageSource` pointing at sensitive local files.
- **Mitigation**: Same `allowed_roots` gate as templates; validate paths before merging when allowlist is empty (BC).

### Dependency vulnerabilities

- **Mitigation**: Run `composer audit` before releases; track PHPWord and Symfony advisories.

### Expression conditionals

Opt-in (`conditional_expressions.enabled`, default `false`). A template can carry `${#if <expression>}` … `${#endif}`; the expression is parsed and evaluated by the bundle.

| Threat | Mitigation |
|--------|------------|
| Code execution / reading PHP state (`constant('…')`, `enum(…)`, calling PHP functions) | ExpressionLanguage's default functions are not registered (`SandboxedExpressionLanguage`); expressions are only evaluated, never compiled to PHP or `eval()`'d. The **only callables** are the whitelisted pure built-ins (`BuiltinExpressionFunctions`) and host functions explicitly tagged `nowo_word_template.expression_function_provider`; `functions.allow` / `deny` restrict both. |
| Object traversal (`client.getPassword()`, `user.roles`, service objects) | Only scalars, lists and read-only maps built from the context reach the template (`ExpressionVariables`); entities and other objects are never exposed. Method calls are rejected at parse time; maps are read-only. |
| Information disclosure through the context | The template can read every identifier-named top-level context key. Do not put secrets in the merge context of user-uploaded templates; validate uploads with `lintConditionalExpressions()` and a list of allowed variable names. |
| Denial of service — huge expressions | `max_length` (default 500 characters). Parsed expressions are cached in a bounded pool (256 entries per evaluator). |
| Denial of service — functions | Function inputs and outputs are capped (64 KiB strings, 10,000 elements); arguments are type-checked (`ExpressionFunctionError`). |
| ReDoS through `matches` | Patterns run through `preg_match` and are bounded by PHP's `pcre.backtrack_limit` / `pcre.recursion_limit` (and the PCRE JIT stack): a pathological pattern fails with an evaluation error instead of hanging. The merge `timeout` still applies around the whole merge. No amplification: one `preg_match` per evaluated operator, no template-controlled loop. |
| Silent wrong output | Default `on_error: exception`: unknown variables, typos, non-bool results and invalid marker layouts throw `ConditionalExpressionException`; `hide` / `keep` are explicit choices and are logged (count only). |
| Log leakage | Logs carry the template file name, the error mode and the error count — never expressions or values. |
| Loops: output / CPU amplification (`${#for a in xs}${#for b in xs}…`) | Opt-in (`loops.enabled`). `loops.max_iterations` per loop (1000), `loops.max_total_iterations` for all loops of a merge (10000 — nested loops multiply, so the product is capped), 32 MiB per expanded document part, plus the merge `timeout`. Loops only iterate over context values (or function results, themselves capped); no recursion: nesting depth is the nesting of markers in the template. |
| Loops: injection through values | Loop placeholders are XML-escaped before they are written into the document. |

Residual risk: a template author can still choose what to show from the values the host passes (that is the feature). Keep `enabled: false`, or restrict `functions.allow`, when templates come from untrusted users and the decision must stay in PHP.

## Logging and secrets

The bundle logs through an optional PSR-3 logger (Monolog channel `word_template`, see [CONFIGURATION.md](CONFIGURATION.md#logging)):

- **Logged:** finished merges (`info`), path rejections by `allowed_roots`, dropped HTML images, timeouts and rejected input (`warning`), unexpected failures (`error`). Context is structured (`bundle`, `action`, `template`, counters, exception class).
- **Never logged:** full template / image / output paths (only the template file name), merge context values (often personal data), HTML fragments, image `src` values, document bytes, exception messages (they contain full paths).

Host apps: do not log full merged documents or template paths that reveal internal layout yourself, and avoid logging API keys or tokens when handling remote storage.

## Cryptography

Not applicable; no custom cryptography in this bundle.

## Reporting

See the repository `.github/SECURITY.md` for coordinated disclosure contacts.

## Release security checklist (12.4.1)

Before each tagged release, maintainers confirm (tick in the release PR or tag notes):

| Item | Confirm |
|------|--------|
| `docs/SECURITY.md` and `.github/SECURITY.md` reviewed | ☐ |
| `.env` / secrets not committed (`.gitignore` baseline) | ☐ |
| No secrets in recipes or sample configs | ☐ |
| Inputs validated at application boundary where untrusted | ☐ |
| `composer audit` clean or exceptions documented | ☐ |
| No sensitive data in logs | ☐ |
| Resource limits (`timeout`) for large templates/HTML considered | ☐ |
| Permissions / exposure of generated files acceptable | ☐ |
| **AI security audit (REQ-SEC-004)** | Pass (conditional) recorded in monorepo `BUNDLES_SECURITY_ANALYSIS.md` |
