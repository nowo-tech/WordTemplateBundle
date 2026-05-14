# Changelog

All notable changes are documented here using [Keep a Changelog](https://keepachangelog.com/).

## Unreleased

## 0.1.1 — 2026-05-12

### Documentation

- Add [`SPEC-DRIVEN-DEVELOPMENT.md`](SPEC-DRIVEN-DEVELOPMENT.md): functional scope of the bundle, how behavior is validated (tests / static analysis), and `REQ-*` traceability for Makefiles and demos.
- Link the guide from [`README.md`](../README.md), [`CONTRIBUTING.md`](CONTRIBUTING.md), and [`ENGRAM.md`](ENGRAM.md).

## 0.1.0 — 2026-05-12

- Initial tagged release: `WordTemplateProcessor`, context flattening, `HtmlContent`, `TableRows`, `ImageSource`, Symfony extension `nowo_word_template`.
- FrankenPHP demos for Symfony 7 and 8 (`demo/symfony7`, `demo/symfony8`); optional download of the filled `.docx` as **PDF** (PhpWord PDF writer + DomPDF, internally `docx → html → pdf`; fidelity limits are noted in the demo UI).
