# Changelog

All notable changes are documented here using [Keep a Changelog](https://keepachangelog.com/).

## Unreleased

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
