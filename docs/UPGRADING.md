# Upgrading

## 1.0.0

First stable `1.x` release. **No breaking changes** compared to `0.1.3`.

```bash
composer require nowo-tech/word-template-bundle:^1.0
```

Public API now follows semver for `1.x`:

- `WordTemplateProcessorInterface` — `process()`, `listVariables()`
- Models — `HtmlContent`, `TableRows`, `ImageSource`
- Configuration — `nowo_word_template.macro_opening`, `nowo_word_template.macro_closing`
- Service — `Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface` (alias of `WordTemplateProcessor`)

If you pinned `^0.1`, you can move to `^1.0` without code changes.

## 0.1.3

New optional API: `WordTemplateProcessorInterface::listVariables(string $templatePath): array`. No configuration changes. Fully backward compatible with `0.1.2`.

If you previously called PHPWord `TemplateProcessor::getVariables()` directly to discover placeholders, you can switch to the bundle method so delimiter settings from `nowo_word_template` apply automatically:

```php
$variables = $this->wordTemplateProcessor->listVariables('/path/to/template.docx');
```

## 0.1.2

Demos, documentation, and repository tooling only. **No changes** to `nowo-tech/word-template-bundle` PHP APIs, services, or `nowo_word_template` configuration when upgrading from `0.1.1`.

If you run the FrankenPHP demos from a git clone, pull this tag to get the blank-template download, styled `HtmlContent` samples, and the `make update-deps` fix in `demo/Makefile`.

## 0.1.1

Documentation only. No changes are required to application code or `nowo_word_template` configuration when upgrading from `0.1.0`.

## 0.1.0

First tagged release in this repository. Install with `composer require nowo-tech/word-template-bundle:^0.1` (or an exact `0.1.0` pin). For later versions, follow `docs/CHANGELOG.md` and the matching GitHub Release notes.

## From pre-release snapshots

Follow `docs/CHANGELOG.md` and tagged GitHub releases. Breaking changes will be listed under major version headings.
