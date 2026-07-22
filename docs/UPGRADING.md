# Upgrading

## 1.2.0

**New feature:** configurable merge **`timeout`** (**REQ-RUNTIME-001**, FrankenPHP-safe). **No breaking API changes.**

```bash
composer require nowo-tech/word-template-bundle:^1.2
```

### Optional — configure merge timeout

New config key **`timeout`** (default **180** seconds). Existing apps need no code changes; the default applies automatically.

```yaml
nowo_word_template:
    timeout: '%env(int:PROCESS_TIMEOUT)%'   # PROCESS_TIMEOUT=180 in .env
```

On expiry, `process()` throws `Nowo\WordTemplateBundle\Exception\ProcessingTimedOutException`. Keep PHP `max_execution_time` and reverse-proxy write deadlines **above** this value under FrankenPHP — see [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md) and [CONFIGURATION.md](CONFIGURATION.md).

## 1.1.0

**New feature:** `ConditionalBlock` for `${#if blockName}` … `${#endif blockName}` regions (nested blocks supported). **Platform change:** Symfony **6.4** is no longer supported — require PHP **8.2+** and Symfony **7.x** or **8.x**.

### Symfony 7.x / 8.x (recommended)

```bash
composer require nowo-tech/word-template-bundle:^1.1
```

**No breaking API changes** for existing integrations. Scalars, `TableRows`, `HtmlContent`, and `ImageSource` behave as in `1.0.x`.

**Optional — conditional blocks**

1. Add markers in Word (one marker per paragraph), e.g. `${#if optional_funding}` … `${#endif optional_funding}`.
2. Pass `ConditionalBlock` in the context:

```php
use Nowo\WordTemplateBundle\Model\ConditionalBlock;

'optional_funding' => new ConditionalBlock('optional_funding', $order->hasPublicFunding()),
```

3. If your templates use non-default delimiters, copy the new `conditional_*` keys from `src/Resources/config/nowo_word_template.yaml` (defaults match `${#if` / `${#endif`).

`listVariables()` now omits `#if` / `#endif` marker names; only data placeholders are returned.

**Inline “word A or B”** in a single paragraph is not a block conditional — compute a scalar in PHP (see demo `${client_tier_label}`).

### Symfony 6.4

Stay on **`1.0.x`** until you upgrade the framework:

```bash
composer require nowo-tech/word-template-bundle:^1.0.5
```

Then move to `^1.1` after Symfony 7 or 8.

## 1.0.5

Repository tooling and documentation only. **No application or configuration changes** when upgrading from `1.0.4`.

```bash
composer require nowo-tech/word-template-bundle:^1.0.5
```

Maintainers: run `make setup-hooks` once per clone (REQ-GIT-001).

## 1.0.4

Repository tooling only. **No application or configuration changes** when upgrading from `1.0.3`.

```bash
composer require nowo-tech/word-template-bundle:^1.0.4
```

## 1.0.3

Documentation and Spec Kit tooling only. **No application or configuration changes** when upgrading from `1.0.2`.

```bash
composer require nowo-tech/word-template-bundle:^1.0.3
```

Maintainers: see [`SPEC-KIT.md`](SPEC-KIT.md) for baseline specs and Cursor Agent workflow.

## 1.0.2

Patch release. **No configuration or interface changes.**

```bash
composer require nowo-tech/word-template-bundle:^1.0.2
```

`WordTemplateProcessor` is no longer `final` and exposes a `protected persistTemplate()` hook. You do not need to change application code unless you intentionally subclass the processor.

## 1.0.1

Bugfix release. **No configuration or API changes.**

If you saw *"WordTemplateProcessorInterface service or alias has been removed or inlined"* when calling `$container->get(WordTemplateProcessorInterface::class)`, upgrade:

```bash
composer require nowo-tech/word-template-bundle:^1.0.1
```

Prefer constructor injection when possible; both autowiring and explicit `$container->get(WordTemplateProcessorInterface::class)` work after this release.

## 1.0.0

First stable `1.x` release. **No breaking changes** compared to `0.1.3`.

```bash
composer require nowo-tech/word-template-bundle:^1.0
```

Public API now follows semver for `1.x`:

- `WordTemplateProcessorInterface` — `process()`, `listVariables()`
- Models — `HtmlContent`, `TableRows`, `ImageSource`
- Configuration — `nowo_word_template.macro_opening`, `nowo_word_template.macro_closing`
- Service — `Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface` (public alias of `WordTemplateProcessor`; since **1.0.1** the interface id is also reachable via `$container->get()`)

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
