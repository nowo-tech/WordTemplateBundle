# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/word-template-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Table of contents

- [Baseline (always)](#baseline-always)
- [Interface / contract PSRs](#interface--contract-psrs)
- [Summary](#summary)

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for package sources and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | `WordTemplateProcessor::__construct(?LoggerInterface $logger = null)` (`psr/log` in `require`); wired to `@?logger` with Monolog channel `word_template` in `services.yaml`; tests use a recording `AbstractLogger`. Logs path rejections, dropped HTML images, timeouts, failures and finished merges ([CONFIGURATION.md](CONFIGURATION.md#logging)). |
| PSR-6 / PSR-16 Cache | **N/A** | No package-owned cache layer. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | No HTTP messages API: the bundle works on files and returns a `ProcessedDocument`. |
| PSR-18 HTTP client | **N/A** | No outbound HTTP client. |
| PSR-11 Container | **N/A** | No container locator in the public API; constructor injection only. |
| PSR-14 Event dispatcher | **N/A** | No domain event extension points that need a dispatcher SPI. |
| PSR-15 HTTP middleware | **N/A** | No HTTP middleware pipeline (the bundle has no HTTP layer). |
| PSR-20 Clock | **N/A** | Only the merge timeout uses time, via a monotonic `hrtime()` deadline; a wall-clock SPI would add nothing. |

## Summary

- **Adopted beyond baseline:** PSR-3 (`Psr\Log\LoggerInterface`, optional constructor argument of `WordTemplateProcessor`).
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains logging, HTTP, cache, clock, or event SPIs.

---

_REQ-CS-007 evaluation date: 2026-08-21; re-evaluated 2026-10-29 (PSR-3 adopted with the logger)._
