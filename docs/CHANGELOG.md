# Changelog

All notable changes are documented here using [Keep a Changelog](https://keepachangelog.com/).

## Unreleased

## 1.2.0 — 2026-07-22

### Added

- **`timeout`** config (`nowo_word_template.timeout`, default **180**) — wall-clock limit for `WordTemplateProcessor::process()` via cooperative deadline between merge phases and PHP `set_time_limit` (**REQ-RUNTIME-001**). Prefer `%env(int:PROCESS_TIMEOUT)%`.
- **`ProcessingTimedOutException`** when the merge deadline expires.
- **`ProcessDeadline`** — internal cooperative wall-clock checks during merge.
- **Demo** — FrankenPHP timeout hierarchy (merge **180** &lt; PHP **240** &lt; Caddy write **250**, `max_wait_time` **30s**); `PROCESS_TIMEOUT` in `.env` / `.env.example`; extracted `docker/entrypoint.sh` for `FRANKENPHP_MODE`.

### Changed

- **PHP-CS-Fixer** — `fully_qualified_strict_types.import_symbols` enabled (import style for FQCN in docs/`@see`).

### Documentation

- **[`CONFIGURATION.md`](CONFIGURATION.md)**, **[`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)**, **[`SECURITY.md`](SECURITY.md)**, **[`UPGRADING.md`](UPGRADING.md)**, **[`README.md`](../README.md)**, Spec Kit inventory — timeout hierarchy and FrankenPHP worker guidance.

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.2
```

No breaking API changes. Optional: set `timeout` / `PROCESS_TIMEOUT` and align PHP / Caddy deadlines. See [UPGRADING.md](UPGRADING.md).

## 1.1.0 — 2026-07-15

### Added

- **`ConditionalBlock`** — Twig-style `${#if blockName}` … `${#endif blockName}` regions in `.docx` templates; show or remove content from PHP. **Nested blocks** with distinct names are resolved inside-out (deepest first).
- **Configuration** — `conditional_if_opening`, `conditional_if_closing`, `conditional_endif_opening`, `conditional_endif_closing` under `nowo_word_template` (defaults `${#if` / `${#endif` with `}` closers).
- **`ConditionalBlockApplicator`** and **`TemplateProcessorBridge`** — apply conditional transforms on WordprocessingML (main document, headers, footers) before scalar/table/HTML/image replacement.
- **`listVariables()`** — omits conditional marker names (`#if …`, `#endif …`).
- **Tests** — unit and integration coverage for conditionals, nested blocks, custom delimiters, and DI wiring; line coverage **≥ 99%**.
- **Demo (`demo/symfony8`)** — dynamic form exercises scalars, inline computed scalar, `HtmlContent`, `TableRows`, `ConditionalBlock` (including nested `funding_detail`), `ImageSource` (`demo_logo`), and commented `config/packages/nowo_word_template.yaml`.
- **Spec Kit** — baseline spec and code inventory updated (US-08, US-09; 18/18 production files mapped).

### Changed

- **Compatibility** — PHP **8.2+** and Symfony **7.x / 8.x** only (`composer.json` no longer allows Symfony **6.4**). Applications on Symfony 6.4 should stay on **`1.0.x`** (see [UPGRADING.md](UPGRADING.md)).
- **Demo** — removed `demo/symfony7`; FrankenPHP demo image targets PHP **8.2** (was 8.4 in the demo container).

### Documentation

- **[`USAGE.md`](USAGE.md)**, **[`CONFIGURATION.md`](CONFIGURATION.md)**, **[`INSTALLATION.md`](INSTALLATION.md)**, **[`SPEC-DRIVEN-DEVELOPMENT.md`](SPEC-DRIVEN-DEVELOPMENT.md)**, **[`README.md`](../README.md)** — conditional blocks, `conditional_*` keys, compatibility, and demo scope.

### Upgrade

Symfony **7.x / 8.x** on `^1.0`:

```bash
composer require nowo-tech/word-template-bundle:^1.1
```

No code changes required unless you adopt `ConditionalBlock` or customize conditional delimiters. See [UPGRADING.md](UPGRADING.md).

## 1.0.5 — 2026-07-15

Repository tooling and documentation only. **No changes** to PHP APIs, services, or `nowo_word_template` configuration.

### Added

- **REQ-GIT-001** — block `Co-authored-by: Cursor` trailers: `.scripts/check-no-cursor-coauthor.sh`, `.scripts/strip-cursor-coauthor-from-history.sh`, `.githooks/commit-msg`, `make check-no-cursor-coauthor`, `make strip-cursor-coauthor-from-history`.
- **CI job `git-hygiene`** — runs the co-author check on every push/PR (GitHub Actions).
- **[`docs/GITLAB_CI.md`](GITLAB_CI.md)** — CI requirements for GitLab mirrors (REQ-GIT-001 and related gates).
- **[`CODE_OF_CONDUCT.md`](../CODE_OF_CONDUCT.md)** — Contributor Covenant.

### Changed

- **`make release-check`** — includes `check-no-cursor-coauthor` before QA.
- **`make setup-hooks`** — installs `commit-msg` hook when present.
- **`.githooks/pre-commit`** — prefer Docker when the container is running; fall back to local `php-cs-fixer` when PHP is on `PATH` (WSL bind-mount setups).
- **[`CONTRIBUTING.md`](CONTRIBUTING.md)**, **[`RELEASE.md`](RELEASE.md)**, **[`README.md`](../README.md)** — Code of Conduct, git hooks, and GitLab CI links.
- **`ContextFlattener`** — Rector PHPDoc cleanup (no behavior change).

### Demos

- Refresh demo `composer.lock` path references for `word-template-bundle`.

## 1.0.4 — 2026-07-13

Repository tooling only. **No changes** to PHP APIs, services, or `nowo_word_template` configuration.

### Changed

- **`make test-coverage`** — pipe PHPUnit output to `coverage-php.txt` and run `.scripts/php-coverage-percent.sh` (aligned with **REQ-TEST-008** contract documented in the script).

### Development

- **`.gitignore`** — ignore `.cursor/sandbox.json` (machine-specific Cursor sandbox config).

## 1.0.3 — 2026-07-08

Documentation and repository tooling only. **No changes** to PHP APIs, services, or `nowo_word_template` configuration.

### Added

- **GitHub Spec Kit** — baseline under `specs/001-baseline/` (`spec.md`, `code-inventory.md` mapping 100% of `src/`), `.specify/` scaffolding, and Cursor Agent skills (`.cursor/skills/speckit-*`).
- **[`docs/SPEC-KIT.md`](SPEC-KIT.md)** — operator manual (install, init, usage, maintainer checklist).

### Documentation

- **[`SPEC-DRIVEN-DEVELOPMENT.md`](SPEC-DRIVEN-DEVELOPMENT.md)** — three-layer model (Spec Kit baseline, product behavior, `REQ-*` traceability); link to SPEC-KIT.
- **[`README.md`](../README.md)** — link to SPEC-KIT in documentation index.

### Demos

- Refresh demo `composer.lock` path references and dev dependency pins.

## 1.0.2 — 2026-07-07

### Changed

- **`WordTemplateProcessor`** — extract `persistTemplate()` as a `protected` hook around `TemplateProcessor::saveAs()`; class is no longer `final` (enables focused unit tests and optional subclassing). Public API unchanged.

### Added

- Tests for boolean/null scalar replacement, `persistTemplate()` failure cleanup, and `WordTemplateExtension::getAlias()`.

### Documentation

- **FrankenPHP demos** — document dev (`Caddyfile.dev`, no worker) vs production (`Caddyfile`, worker mode) in [`README.md`](../README.md) and [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md).

### Demos

- Aggregate `demo/Makefile`: `DEMO=symfony7|symfony8` for `up`, `down`, and `update-bundle`; demo `composer.lock` files reference `nowo-tech/word-template-bundle` **^1.0**.

### CI / tooling

- Raise PHPUnit line-coverage floor from **93%** to **99%** (`composer.json`, CI workflow, README badge).
- Coverage Clover output: `coverage/clover.xml` → `coverage.xml`.
- Bump `actions/checkout` to **v7**.

## 1.0.1 — 2026-06-29

### Fixed

- **`WordTemplateProcessorInterface` service alias** — mark the interface alias as `public: true` so `$container->get(WordTemplateProcessorInterface::class)` works after the container is compiled. Without this, Symfony inlines the private alias and throws *"service or alias has been removed or inlined"*. Constructor injection was unaffected.

## 1.0.0 — 2026-06-29

First **stable** release. The public API (`WordTemplateProcessorInterface`, value objects, `nowo_word_template` configuration, and service id) is now covered by [Semantic Versioning](https://semver.org/) for `1.x`.

### Summary

- Fill `.docx` templates from a PHP context: scalars, nested keys, `TableRows`, `HtmlContent`, `ImageSource`.
- Discover placeholders with `listVariables()`.
- Configurable macro delimiters (`macro_opening` / `macro_closing`).
- Symfony **6.4 / 7.x / 8.x**, PHP **8.2+**.

### Changed

- **CI** — install Symfony matrix dependencies without a root `composer.lock` (`composer require` for prod + `--dev` packages separately; QA job uses `composer update`).

### Upgrade

Drop-in replacement for `0.1.x`. Set `composer require nowo-tech/word-template-bundle:^1.0`. See [UPGRADING.md](UPGRADING.md).

## 0.1.3 — 2026-06-29

### Added

- **`WordTemplateProcessorInterface::listVariables()`** — returns unique placeholder names from a `.docx` template (main document, headers, footers), using the configured `macro_opening` / `macro_closing` delimiters. Throws `TemplateNotFoundException` when the file is missing or not readable, same as `process()`.

### Demos

- Symfony 7 / 8 demo controllers use `listVariables()` instead of calling PHPWord `TemplateProcessor::getVariables()` directly.

### Development

- **Docker** — mark `/app` as a Git `safe.directory` to avoid “dubious ownership” warnings when the repository is bind-mounted from the host.
- **Makefile** — retry `composer install` after clearing a partial `vendor/` directory when the first attempt fails (common on bind mounts / flaky GitHub downloads).

## 0.1.2 — 2026-06-16

### Demos

- **Download blank template** — `GET /template` (`demo_template`) serves `public/demo/doc-final-tpl.docx` with placeholders intact (no `symfony/mime` dependency; explicit `Content-Type`).
- **HtmlContent examples** — default values for `${methodology.body}` and `${results.body}` use inline-styled HTML tables (borders, header background, alternating row colours) compatible with PHPWord’s border shorthand (`width color style`).
- **Demo UI** — styled fieldsets, dark monospace textareas for HTML fields, and a bordered table layout for `TableRows` editing.
- **Make / update-deps** — define `DEMOS := symfony7 symfony8` in `demo/Makefile` (fixes infinite recursion in `make update-deps`); add `COMPOSE` / `SERVICE_PHP` in per-demo Makefiles; wire root `Makefile` to shared `update-deps` scripts (REQ-MAKE-008).

### Documentation

- Add user stories (US-01 … US-07) to [`SPEC-DRIVEN-DEVELOPMENT.md`](SPEC-DRIVEN-DEVELOPMENT.md).
- Align Symfony version badge in [`README.md`](../README.md); reorder documentation links.
- Update [`demo/README.md`](../demo/README.md) and [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md) with correct template filename and download options.

### CI / tooling

- Add CodeRabbit configuration (`.coderabbit.yaml`) and GitHub workflow.
- Refresh demo `composer.lock` / Flex config (`csrf`, `property_info`, `reference.php`) for Symfony 7 and 8 demos.

## 0.1.1 — 2026-05-12

### Documentation

- Add [`SPEC-DRIVEN-DEVELOPMENT.md`](SPEC-DRIVEN-DEVELOPMENT.md): functional scope of the bundle, how behavior is validated (tests / static analysis), and `REQ-*` traceability for Makefiles and demos.
- Link the guide from [`README.md`](../README.md), [`CONTRIBUTING.md`](CONTRIBUTING.md), and [`ENGRAM.md`](ENGRAM.md).

## 0.1.0 — 2026-05-12

- Initial tagged release: `WordTemplateProcessor`, context flattening, `HtmlContent`, `TableRows`, `ImageSource`, Symfony extension `nowo_word_template`.
- FrankenPHP demos for Symfony 7 and 8 (`demo/symfony7`, `demo/symfony8`); optional download of the filled `.docx` as **PDF** (PhpWord PDF writer + DomPDF, internally `docx → html → pdf`; fidelity limits are noted in the demo UI).
