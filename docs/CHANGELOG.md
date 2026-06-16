# Changelog

All notable changes are documented here using [Keep a Changelog](https://keepachangelog.com/).

## Unreleased

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
