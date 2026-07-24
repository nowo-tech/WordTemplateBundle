# WordTemplateBundle — Copilot / AI hints

## AI contribution guidelines (Nowo Symfony bundle)

Use this when suggesting code, tests, documentation, or CI changes for this repository.

### Scope

- This is a **Symfony bundle** published as `nowo-tech/word-template-bundle` on Packagist.
- Respect the **PHP** (`>=8.2 <8.6`) and **Symfony** (`^7.0 || ^8.0`) ranges in `composer.json`.
- Prefer **PHP 8 attributes**. Do not introduce `doctrine/annotations`.
- Template merge (`WordTemplateProcessor::process()`) is blocking in-process work; always keep configurable **`timeout`** (REQ-RUNTIME-001) and FrankenPHP hierarchy docs in sync.

### Code

- Follow **PSR-12** and `.php-cs-fixer.dist.php` (including `declare_strict_types` and `fully_qualified_strict_types.import_symbols`).
- Keep changes **minimal** and consistent with `src/` and `tests/`.
- Root config key is `nowo_word_template` (see `Configuration::ALIAS`).
- Align with `composer cs-check`, `composer phpstan` (includes `nowo-tech/phpstan-frankenphp` classic + worker rulesets), and `composer coverage-check` (≥ 99% lines).

### Documentation

- User-facing docs are **English** under `docs/`.
- Only `README.md` (+ `CODE_OF_CONDUCT.md`) at repository root.

### Git

- **Never** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages (REQ-GIT-001).

### Tests

- Add or update tests for new behaviour; keep PHP line coverage at or above the project floor.
- Match existing PHPUnit patterns under `tests/Unit` and `tests/Integration`.
