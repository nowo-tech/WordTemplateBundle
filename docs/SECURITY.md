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

## Logging and secrets

Do not log full merged documents or template paths that reveal internal layout. Avoid logging API keys or tokens when handling remote storage.

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
