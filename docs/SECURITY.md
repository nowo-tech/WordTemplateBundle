# Security — WordTemplateBundle

This document describes the **attack surface**, **threats**, and **controls** for `nowo-tech/word-template-bundle`. It is written in English per project standards.

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
| Context values | Application / stored user data | Treated as merge fields; XML escaping follows PHPWord defaults when enabled. |
| HTML in `HtmlContent` | Application | Parsed into OOXML; treat as untrusted if sourced from end users. |
| Image paths in `ImageSource` | Application | Path traversal / sensitive file read if paths are user-controlled. |

## Threats and mitigations

### Unsafe template or output paths

- **Risk**: Writing merged documents to predictable or world-readable locations.
- **Mitigation**: Use random temp names (`ProcessedDocument`) or app-controlled storage; restrict filesystem permissions.

### Untrusted context / XXE and XML

- **Risk**: Extremely large strings or crafted payloads stressing PHPWord.
- **Mitigation**: Enforce size limits at the application layer; keep dependencies updated (`composer audit`). Configure `nowo_word_template.timeout` (default **180s**) so a pathological merge cannot pin a FrankenPHP worker indefinitely (**REQ-RUNTIME-001**).

### Resource exhaustion / long merges

- **Risk**: Large templates, heavy HTML, or many `TableRows` occupy a PHP thread until completion.
- **Mitigation**: Bundle `timeout` applies a cooperative deadline between merge phases and `set_time_limit`. Keep PHP `max_execution_time` and reverse-proxy write deadlines **above** this value (see [CONFIGURATION.md](CONFIGURATION.md) and [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md)).

### Image path abuse

- **Risk**: `ImageSource` pointing at sensitive local files.
- **Mitigation**: Validate paths against an allowlist or storage root before merging.

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
