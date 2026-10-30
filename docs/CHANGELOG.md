# Changelog

All notable changes are documented here using [Keep a Changelog](https://keepachangelog.com/).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.7.1] - 2026-10-29](#171---2026-10-29)
- [[1.7.0] - 2026-10-29](#170---2026-10-29)
- [[1.6.1] - 2026-10-28](#161---2026-10-28)
- [[1.6.0] - 2026-10-13](#160---2026-10-13)
- [[1.5.0] - 2026-10-13](#150---2026-10-13)
- [[1.4.3] - 2026-10-13](#143---2026-10-13)
- [[1.4.2] - 2026-10-09](#142---2026-10-09)
- [[1.4.1] - 2026-09-28](#141---2026-09-28)
- [[1.4.0] - 2026-09-28](#140---2026-09-28)
- [[1.3.9] - 2026-09-27](#139---2026-09-27)
- [[1.3.8] - 2026-09-25](#138---2026-09-25)
- [[1.3.7] - 2026-09-09](#137---2026-09-09)
- [[1.3.6] - 2026-08-24](#136---2026-08-24)
- [[1.3.5] - 2026-08-19](#135---2026-08-19)
- [[1.3.4] - 2026-08-19](#134---2026-08-19)
- [[1.3.3] - 2026-08-19](#133---2026-08-19)
- [[1.3.2] - 2026-08-18](#132---2026-08-18)
- [[1.3.1] - 2026-07-31](#131---2026-07-31)
- [[1.3.0] - 2026-07-30](#130---2026-07-30)
- [[1.2.4] - 2026-07-30](#124---2026-07-30)
- [[1.2.3] - 2026-07-29](#123---2026-07-29)
- [[1.2.2] - 2026-07-24](#122---2026-07-24)
- [[1.2.1] - 2026-07-24](#121---2026-07-24)
- [[1.2.0] - 2026-07-22](#120---2026-07-22)
- [[1.1.0] - 2026-07-15](#110---2026-07-15)
- [[1.0.5] - 2026-07-15](#105---2026-07-15)
- [[1.0.4] - 2026-07-13](#104---2026-07-13)
- [[1.0.3] - 2026-07-08](#103---2026-07-08)
- [[1.0.2] - 2026-07-07](#102---2026-07-07)
- [[1.0.1] - 2026-06-29](#101---2026-06-29)
- [[1.0.0] - 2026-06-29](#100---2026-06-29)
- [[0.1.3] - 2026-06-29](#013---2026-06-29)
- [[0.1.2] - 2026-06-16](#012---2026-06-16)
- [[0.1.1] - 2026-05-12](#011---2026-05-12)
- [[0.1.0] - 2026-05-12](#010---2026-05-12)

## [Unreleased]

### Added

- **Expression conditionals (opt-in).** `${#if <expression>}` … `${#endif}` — a **nameless** end marker makes the block an
  expression evaluated against the context with a sandboxed Symfony ExpressionLanguage (`==`, `!=`, `===`, `!==`, `<`,
  `>`, `<=`, `>=`, `and`/`or`/`not`, `in`/`not in`, `matches`, `starts with`/`ends with`, `contains`, arithmetic,
  `??`, `?.`, ranges). Named blocks (`${#if name}` … `${#endif name}` + `ConditionalBlock`) are unchanged; the
  classification is syntactic (nameless vs named end marker). Enable with
  `nowo_word_template.conditional_expressions.enabled: true` (default `false`: no behaviour change).
- Built-in, Twig-named **expression functions** (`lower`, `upper`, `capitalize`, `title`, `trim`, `length`, `replace`,
  `slice`, `starts_with`, `ends_with`, `abs`, `round`, `floor`, `ceil`, `min`, `max`, `number_format`, `count`, `keys`,
  `first`, `last`, `join`, `empty`, `default`, `is_null`, `is_defined`, `now`, `date`, `date_diff_days`); `now()` /
  `date()` use the Symfony clock and `conditional_expressions.timezone`. Host functions via
  `ExpressionFunctionProviderInterface` (autoconfigured tag `nowo_word_template.expression_function_provider`);
  `conditional_expressions.functions.allow` / `deny`.
- `conditional_expressions.on_error` (`exception` default, `hide`, `keep`) and `conditional_expressions.max_length`;
  new `ConditionalExpressionException` (implements `WordTemplateExceptionInterface`).
- `ConditionalExpressionInspectorInterface` (implemented by `WordTemplateProcessor`):
  `listConditionalExpressions()` and `lintConditionalExpressions()` to validate templates before merging.
- Word typographic quotes (`‘ ’ “ ”`), dashes, non-breaking spaces and split runs inside expression markers are normalised.
- Backed enums (value), pure enums (case name) and `DateTimeInterface` (`'Y-m-d'`) are accepted in the context (they were
  rejected with `InvalidContextValueException` before) and render as such in placeholders.
- New runtime dependencies: `symfony/expression-language`, `symfony/cache`, `symfony/clock` (`^7.4 || ^8.0`).
- **Loops (opt-in).** `${#for item in <expression>}` … `${#endfor}` (and `${#for key, item in …}`) repeat the paragraphs
  between the markers — or whole table rows when the markers sit in rows of their own — once per element of a list or
  map; `${item.name}`, `${key}` and Twig-like `${loop.index}`, `index0`, `revindex`, `revindex0`, `first`, `last`,
  `length`, `parent` are filled per iteration and are variables of the `${#if …}` / `${#for …}` blocks inside the body
  (processed outside-in with scoping). Enable with `nowo_word_template.loops.enabled: true`; limits
  `loops.max_iterations` (1000) and `loops.max_total_iterations` (10000, nested loops multiply), `loops.max_output_bytes`
  (32 MiB for the whole merge); delimiters `loop_for_opening` / `loop_for_closing` / `loop_endfor_opening` / `loop_endfor_closing`.
- Expression blocks whose markers sit in different rows of the same table keep or remove whole rows.
- `ConditionalExpressionInspectorInterface::listLoops()`; `lintConditionalExpressions()` checks loop heads and knows the
  loop variables inside loop bodies.

### Security

- Hardening of the unreleased expression conditionals after the REQ-SEC-004 review (Appendix BQ): the `..` range
  operator only accepts integer literals with a span below 10,000 (`count(1..100000000)` exhausted memory);
  `number_format()` accepts at most 20 decimals and `replace()`, `join()`, `number_format()` check the result size before
  building it; date formats are limited to 100 bytes; error messages no longer echo context values (function arguments,
  dynamic map keys / list indexes, regexes read from values); a PCRE failure while scanning markers fails closed.
  `replace()` accepts maps with numeric keys again.
- Loops and expression blocks (REQ-SEC-004 Appendix BQ M2, M3, L1, L2): one output byte budget for all loops and parts of
  a merge (`loops.max_output_bytes`, 32 MiB), checked before a loop renders; linear marker layout analysis with
  `conditional_expressions.max_markers` (2000 per part), `max_depth` (32) and `max_evaluations` (100,000 per merge), which
  always throw the new `TemplateLimitExceededException`; the merge `timeout` is checked while rendering and the
  inspection APIs run under it; values printed by loops **and by ordinary placeholders** can no longer be read as
  placeholders again (`${other}` in a value stays literal, `$` is written as `&#36;`), and XML-invalid control
  characters are dropped from values.

### Documentation

- `docs/USAGE.md` — new **Expression conditionals** section (enable, classification rule, exposed values, operators,
  data types, function reference and Twig mapping, host functions, nesting / else / one of N, PHP vs template, errors
  and error modes, linting, security) and **Loops** section (paragraph and row loops, loop variables, maps and keys,
  nesting, empty lists, errors and limits); README conditionals and loops sections with screenshots; CONFIGURATION, UPGRADING,
  SECURITY (threat table) and FRANKENPHP-WORKER-AUDIT updated.

- `docs/USAGE.md` — new **Conditionals by data type** section: how to turn `bool`, `?bool`, `int`/`?int`, `float`,
  `string`/`?string`, `null`/optional values, arrays, `DateTimeInterface`, backed enums, Doctrine relations/collections and
  Symfony request/form data into the `bool` that `ConditionalBlock` requires, with recommended expressions and pitfalls
  (`'0'` is falsy, `(bool) 'false'` is `true`, float noise, `stripos()` at offset 0, loose `in_array()`, time zones,
  `getBoolean()`/`getInt()` throwing `BadRequestException`), the `TypeError` in strict mode vs weak-mode coercion, the
  "else" and "one of N" patterns, combining conditions (flatten vs nest), same block name used twice, the table / "no
  items" pattern with `TableRows`, what happens with a missing block (markers stay as literal text), and where markers may
  go in Word (own paragraph, table cells, headers/footers, page breaks; not across cells, footnotes or overlapping).
  Linked from the README.

### Tests

- Unit tests for the classifier/applicator (mixed nesting, layout errors per mode, split runs, smart quotes, custom
  delimiters), the evaluator (every operator, failures), every built-in function (happy path, bad types, size caps,
  allow/deny, host providers) and the exposure rules; `.docx` integration tests (body, table cell, header, footer,
  functions, error modes, listing/linting, kernel wiring with an autoconfigured host provider).
- `tests/Integration/ExpressionConditionalsUsageDocTest.php` renders every operator / data-type / function / error row of
  the new USAGE section through a real `.docx` and runs every "In Word / In PHP / Result" example as written.

- `tests/Integration/ConditionalsUsageDocTest.php` executes every documented expression (asserting the snippet is present
  in `docs/USAGE.md`) and renders the documented patterns and edge cases through real `.docx` templates, so the docs
  cannot drift from the behaviour. `doctrine/collections` added to `require-dev` for the collection examples.

### Demo

- New `/conditionals` page in `demo/symfony8`: one form field per PHP type, a preview of the `ConditionalBlock`
  decisions and a download of `public/demo/conditionals-tpl.docx` (built by `bin/build-conditionals-template.php`).
- `/conditionals` also shows the expression conditionals of its template (evaluated from the raw `order` values);
  new `/conditionals/examples` page (Word text, PHP context, result, template / generated `.docx` downloads);
  `bin/console app:conditionals:export-examples`; Playwright e2e (`make test-e2e`), page screenshots
  (`make demo-screenshots`) and LibreOffice-rendered "template → generated document" previews (`make docx-previews`)
  under `docs/images/demo/`.
- New `/loops` page: an invoice whose lines are repeated table rows with a conditional discount row, loop variables,
  nested loops, a map and an empty list, with downloads, e2e and previews.

### Changed

- CHANGELOG headings use the `## [x.y.z] - YYYY-MM-DD` form.
- `listVariables()` also omits the nameless `${#endif}` marker.
- With `conditional_expressions.enabled: true`, `listConditionalBlocks()` returns named blocks only (expression blocks
  are listed by `listConditionalExpressions()`).
- With `loops.enabled: true`, `listVariables()` omits loop markers and the placeholders of loop variables
  (`${line.name}`, `${loop.index}`).
## [1.7.1] - 2026-10-29
### Security

- **HTML image filter bypass via HTML entities (SSRF / local file read).** `HtmlImageSanitizer` looked for `<img` before
  decoding entities, while PHPWord decodes them before parsing, so `&#60;img src="http://…"/&#62;` reached
  `Html::parseImage()` (`file_get_contents()` on any URL or stream wrapper, files outside `allowed_roots` embedded in the
  output). The filter now runs on the same decoded XML PHPWord parses, and an unparsable fragment that contains an image
  is dropped (fail closed). Affects `HtmlContent` values built from untrusted HTML, 1.5.0–1.7.0 (REQ-SEC-004 re-audit M1,
  CWE-918 / CWE-180).

### Changed

- `phpoffice/phpword` floor raised to `^1.4` (keeps `phpoffice/math` 0.2 with GHSA-42hm-pq2f-3r7m out of the tree).

## [1.7.0] - 2026-10-29
### Added

- **PSR-3 logging** (REQ-OBS-001): `WordTemplateProcessor` takes an optional `?LoggerInterface $logger` (last
  constructor argument, wired to `@?logger`, Monolog channel `word_template`). It logs finished merges (`info`),
  path rejections by `allowed_roots`, dropped HTML images, timeouts and rejected input (`warning`) and unexpected
  failures (`error`) with structured context; only the template file name is logged, never full paths, context
  values or document contents. `psr/log` (`^1.1 || ^2.0 || ^3.0`) is now in `require`.
- `HtmlImageSanitizer::droppedImages()` and an optional temp directory argument (internal class).

### Changed

- Coverage gate raised from 99% to **100%** lines (`composer coverage-check`, CI); README publishes the measured value
  and TS/JS / Python N/A rows (REQ-TEST-003 / REQ-TEST-007).
- Coverage reports move to `coverage/` (`coverage/coverage.xml`, `coverage/coverage-php.txt`,
  `coverage/coverage-output.txt`); the root `coverage-data` volume is mounted at `/app/coverage` (REQ-DOC-001).
- PHPDoc on every public class and method of `src/` (REQ-CS-001); `docs/PSR.md` re-evaluated (PSR-3 adopted, REQ-CS-007).
- Makefile: `assets` no-op target and `help` in the REQ-MAKE-001 block order (incl. `igor`, `update`, `update-deps`, `validate`).
- `.github/SECURITY.md`: supported versions `1.x` and the canonical report checklist (REQ-SEC-003).
- `.gitignore`: `# Archives / compressed` block with `*.tar` (REQ-GITIGNORE-001); demo compose explains its `dns:` (REQ-DEMO-009).

## [1.6.1] - 2026-10-28
### Security

- **Temporary files are owner-only.** The temporary output `.docx` and the images extracted from HTML values were
  written to the shared temp dir with the default umask (usually world-readable); merged documents (contracts,
  invoices) could be read by other local users until disposed. Both are `0600` now.

### Changed

- Docs: USAGE states that host and demo forms are Symfony Form Types rendered with `form_start` / the `not child.rendered` child loop / `form_end`, and that raw `<form>` / `<input>` must not be reintroduced; `check-no-raw-html-form` enforces it in `release-check` (REQ-TWIG-005 rule 9).
- Developer tooling: `make release-check` runs the full REQ-MAKE-002 chain in order (`ensure-up` first, then `check-no-cursor-coauthor`, `check-open-prs`, new `check-no-raw-html-form` (REQ-TWIG-005), `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, `igor`, `coverage-check`, demo release-check).
- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Demo: `demo/index.html.twig` keeps its grouped placeholder fieldsets (rule 3) and ends the form with the child loop
  (`{% for child in form %}` + `not child.rendered` + `form_row(child)`) so any remaining child is rendered, per
  REQ-TWIG-003.
- Internal: applied the pending Rector suggestion (private static helper in the internal `HtmlImageSanitizer` made non-static); no behaviour change. `make rector-dry` is clean again.

### Documentation

- Documentation: wave-1 REQ compliance — README badges (canonical Downloads, Symfony 7.4 | 8.0 | 8.1, Coverage), canonical FrankenPHP claim, documentation list order (extras under Additional documentation), canonical issue and pull request templates, CONTRIBUTING Code of Conduct link and Cursor Agent queue settings (REQ-IDE-006), DEMO-FRANKENPHP troubleshooting, commented demo `.env.example`, tables of contents on long docs, Spec Kit inventory refresh (25/25: path allowlist, image sanitizer, split-macro normalizer, PHPWord HTML state).

## [1.6.0] - 2026-10-13
### Changed

- Minimum Symfony version raised from 7.0 to **7.4** (the lowest version tested in CI).
- `symfony/yaml` moved from `require-dev` to `require` (the extension loads `services.yaml` with `YamlFileLoader`).

## [1.5.0] - 2026-10-13
### Security

- `HtmlContent`: `<img src>` values are filtered before PHPWord parses the HTML. Remote URLs, stream wrappers (`php://`, `phar://`, `file://`, …) and local paths outside `allowed_roots` are dropped, closing SSRF and arbitrary local file reads. Only `data:image/(png|jpeg|gif|bmp);base64` images up to `html_image_max_bytes` (default 5 MiB) and, when `allowed_roots` is configured, existing local files under those roots are kept.
- `HtmlContent`: temp files created for `data:` images are deleted after each merge (previously leaked one file per image, unbounded in FrankenPHP worker mode).

### Added

- Config `html_image_max_bytes` (default `5242880`): maximum decoded size of one `data:` image inside `HtmlContent`.

### Fixed

- `listVariables()` derives the conditional-marker filter from the configured `conditional_if_opening` / `conditional_endif_opening` (and `macro_opening`) instead of hard-coding `#if ` / `#endif `, so custom conditional delimiters no longer show up as variables.

### Documentation

- `docs/CONFIGURATION.md` and the reference/recipe YAML now document `allowed_roots`, `path_allowlist_required` and `html_image_max_bytes`.

## [1.4.3] - 2026-10-13
### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so local `composer update` resolves dependencies installable on the minimum PHP (the lock is not committed); CI overrides the platform per matrix cell.

## [1.4.2] - 2026-10-09
### Dependencies

- `igor-php/igor-php` require-dev constraint bumped to `^0.10.1` (Dependabot). Dev tooling only.
- Demo `symfony8`: Symfony 8.1.8, Twig 3.30.0, `sabberworm/php-css-parser` 9.5.0.

## [1.4.1] - 2026-09-28
### Fixed

- PHPStan: drop redundant `is_string()` on `list<string>` allowlist roots.
- Tests: expand `PathAllowlist` coverage for empty/invalid roots and `mustExist=false` output paths (CI 99% gate).
- Style: PHP CS Fixer on path-allowlist related files.

## [1.4.0] - 2026-09-28
### Security

- Template/output paths are gated by `allowed_roots`; `path_allowlist_required` (Flex `when@prod`) rejects empty allowlists in production.

## [1.3.9] - 2026-09-27
### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

## [1.3.8] - 2026-09-25
### Fixed

- **Worker mode — PHPWord `Html` static state:** `process()` now clears PHPWord's static `Html::$css` / `$xpath` / `$options` before the merge and restores the previous values in `finally` (`Processor\PhpWordHtmlState`). A `<style>` block in one `HtmlContent` no longer styles documents generated by later requests in the same FrankenPHP worker, and the last HTML DOM is not kept in memory after the merge.
- **Temp files:** PHPWord's working copy of the template (`PhpWord*` in the PHPWord temp dir) is now deleted after `listVariables()`, `listConditionalBlocks()` and a failed `process()` (`TemplateProcessorBridge::removeTemporaryDocument()`).

### Documentation

- **[`FRANKENPHP-WORKER-AUDIT.md`](FRANKENPHP-WORKER-AUDIT.md)** — audit under kernel-not-reset (scenario B); W-01 / W-02 remediated, W-03 accepted.
- **[`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)** / **[`CONFIGURATION.md`](CONFIGURATION.md)** / **[`README.md`](../README.md)** — worker isolation notes and audit link.

### Tests

- Integration coverage for consecutive merges on one processor (`WorkerModeIsolationTest`): stylesheet isolation, HTML state restore, no leftover `PhpWord*` temp files.

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.3.8
```

No API or config changes. See [UPGRADING.md](UPGRADING.md).

## [1.3.7] - 2026-09-09
### Fixed

- **XML escape scalar / table merge values** — `WordTemplateProcessor::process()` now enables PHPWord `Settings::setOutputEscapingEnabled(true)` for the duration of the merge (restored afterwards). Values containing `&`, `<`, etc. (e.g. partner names like `Ores & Bryan…`) no longer corrupt `word/document.xml`, which previously broke downstream LibreOffice Word→PDF conversion.

### Security

- **[`SECURITY.md`](SECURITY.md)** — context merge fields are escaped as OOXML text by default during `process()` (PHPWord output escaping).

### Tests

- Integration coverage for ampersand / angle-bracket scalars and `TableRows` cell values.

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.3.7
```

No API or config changes. See [UPGRADING.md](UPGRADING.md).

## [1.3.6] - 2026-08-24
### Changed

- **Docs:** Flex recipe installation notes (REQ-RECIPE-001).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

## [1.3.5] - 2026-08-19
### Added

- **Symfony Flex recipe:** ship `nowo_word_template.yaml`, manifest, and post-install text. Prefer **`^1.3.5`** over `v1.3.4`.

## [1.3.4] - 2026-08-19
### Added

- **Symfony Flex recipe:** ships `nowo_word_template.yaml` with prod `timeout` defaults.

## [1.3.3] - 2026-08-19
### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.3.2] - 2026-08-18
### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

## [1.3.1] - 2026-07-31
### Fixed

- **Word-split conditional markers** — `ConditionalBlockApplicator` now heals macros fragmented across `<w:r>/<w:t>` runs (common when Word spell-check or partial formatting splits `${#if name}` / `${#endif name}`) via `BrokenMacroNormalizer` before discover/apply. Discovery and show/hide no longer depend solely on PHPWord’s internal `fixBrokenMacros`.

### Documentation

- **[`USAGE.md`](USAGE.md)** / **[`UPGRADING.md`](UPGRADING.md)** — authoring tip (paste whole marker) + note that split `#if` / `#endif` markers are healed at runtime.

### Tests

- Unit coverage for `BrokenMacroNormalizer` and split-run `#if` / `#endif` regions (pattern from real Word templates).

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.3.1
```

No API or config changes. See [UPGRADING.md](UPGRADING.md).

## [1.3.0] - 2026-07-30
### Added

- **`WordTemplateProcessorInterface::listConditionalBlocks()`** — unique conditional block names from opening markers (`${#if annex}` → `annex`), using configured `conditional_*` delimiters (main document, headers, footers).
- **`ConditionalBlockApplicator::discoverBlockNames()`** — scans WordprocessingML for opening markers.
- **`TemplateProcessorBridge::documentPartXmls()`** — exposes main/header/footer XML for discovery.

### Documentation

- **[`USAGE.md`](USAGE.md)** / **[`README.md`](../README.md)** / **[`UPGRADING.md`](UPGRADING.md)** — `listVariables()` vs `listConditionalBlocks()` with Word examples.

### Tests

- Integration and unit coverage for `listConditionalBlocks()`, custom delimiters, and `discoverBlockNames()`.

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.3
```

Backward compatible for DI consumers of `WordTemplateProcessor`. Custom implementations of `WordTemplateProcessorInterface` must add `listConditionalBlocks()`. See [UPGRADING.md](UPGRADING.md).

## [1.2.4] - 2026-07-30
### Documentation

- **[`USAGE.md`](USAGE.md)** / **[`README.md`](../README.md)** — end-to-end Word + YAML + PHP examples for multiple independent `TableRows` and sibling / nested `ConditionalBlock`s; anti-pattern of two `cloneRow`s on the same physical row.

### Tests

- Integration: two independent tables + sibling conditionals in one document; unit: sibling `ConditionalBlockApplicator` regions.

### Fixed

- Demo / root Makefiles resolve `docker` via absolute path so an empty `PATH` entry (`::` → `.`) cannot pick the local `docker/` directory (`Permission denied` during `release-check-demos`).

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.2.4
```

No runtime or config API changes. See [UPGRADING.md](UPGRADING.md).

## [1.2.3] - 2026-07-29
### Added

- `make check-open-prs`, `demo-smoke`, `down-dev`, `release-check-demos` (REQ-MAKE-002 / 007).
- `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- FrankenPHP Friendly banner (`docs/images/frankenphp-friendly.png`) — REQ-DOCS-017.
- `docs/SECURITY.md` 12.4.1 row for REQ-SEC-004 Pass (conditional).

### Changed

- Demo Symfony 8 image `dunglas/frankenphp:1-php8.5-alpine` (REQ-DEMO-010).
- Packagist homepage + GitHub About topics (REQ-DOCS-018).
- `release-check` no longer runs `cs-fix`; includes `check-open-prs` + demo release-check.

### Documentation

- **[`CHANGELOG.md`](CHANGELOG.md)**, **[`UPGRADING.md`](UPGRADING.md)** — patch release notes.

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.2.3
```

No runtime or config API changes.

## [1.2.2] - 2026-07-24
### Added

- **GitHub automation (REQ-GH)** — `.github/copilot-instructions.md` (REQ-GH-001), `workflows/pr-lint.yml` semantic PR titles (REQ-GH-004), `workflows/stale.yml` inactive issues/PRs (REQ-GH-005).

### Documentation

- **[`CHANGELOG.md`](CHANGELOG.md)**, **[`UPGRADING.md`](UPGRADING.md)** — patch release notes.

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.2.2
```

No runtime or config API changes.

## [1.2.1] - 2026-07-24
### Changed

- **Dev / QA** — require-dev [`nowo-tech/phpstan-frankenphp`](https://github.com/nowo-tech/PhpStanFrankenPhp) `^1.0` and include classic + worker rulesets in `phpstan.neon.dist` (FrankenPHP-safe static analysis for contributors).

### Documentation

- **[`CHANGELOG.md`](CHANGELOG.md)**, **[`UPGRADING.md`](UPGRADING.md)** — patch release notes.

### Upgrade

```bash
composer require nowo-tech/word-template-bundle:^1.2.1
```

No runtime or config API changes. Contributors: `composer update` (dev) so PHPStan loads the FrankenPHP rulesets.

## [1.2.0] - 2026-07-22
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

## [1.1.0] - 2026-07-15
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

## [1.0.5] - 2026-07-15
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

## [1.0.4] - 2026-07-13
Repository tooling only. **No changes** to PHP APIs, services, or `nowo_word_template` configuration.

### Changed

- **`make test-coverage`** — pipe PHPUnit output to `coverage-php.txt` and run `.scripts/php-coverage-percent.sh` (aligned with **REQ-TEST-008** contract documented in the script).

### Development

- **`.gitignore`** — ignore `.cursor/sandbox.json` (machine-specific Cursor sandbox config).

## [1.0.3] - 2026-07-08
Documentation and repository tooling only. **No changes** to PHP APIs, services, or `nowo_word_template` configuration.

### Added

- **GitHub Spec Kit** — baseline under `specs/001-baseline/` (`spec.md`, `code-inventory.md` mapping 100% of `src/`), `.specify/` scaffolding, and Cursor Agent skills (`.cursor/skills/speckit-*`).
- **[`docs/SPEC-KIT.md`](SPEC-KIT.md)** — operator manual (install, init, usage, maintainer checklist).

### Documentation

- **[`SPEC-DRIVEN-DEVELOPMENT.md`](SPEC-DRIVEN-DEVELOPMENT.md)** — three-layer model (Spec Kit baseline, product behavior, `REQ-*` traceability); link to SPEC-KIT.
- **[`README.md`](../README.md)** — link to SPEC-KIT in documentation index.

### Demos

- Refresh demo `composer.lock` path references and dev dependency pins.

## [1.0.2] - 2026-07-07
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

## [1.0.1] - 2026-06-29
### Fixed

- **`WordTemplateProcessorInterface` service alias** — mark the interface alias as `public: true` so `$container->get(WordTemplateProcessorInterface::class)` works after the container is compiled. Without this, Symfony inlines the private alias and throws *"service or alias has been removed or inlined"*. Constructor injection was unaffected.

## [1.0.0] - 2026-06-29
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

## [0.1.3] - 2026-06-29
### Added

- **`WordTemplateProcessorInterface::listVariables()`** — returns unique placeholder names from a `.docx` template (main document, headers, footers), using the configured `macro_opening` / `macro_closing` delimiters. Throws `TemplateNotFoundException` when the file is missing or not readable, same as `process()`.

### Demos

- Symfony 7 / 8 demo controllers use `listVariables()` instead of calling PHPWord `TemplateProcessor::getVariables()` directly.

### Development

- **Docker** — mark `/app` as a Git `safe.directory` to avoid “dubious ownership” warnings when the repository is bind-mounted from the host.
- **Makefile** — retry `composer install` after clearing a partial `vendor/` directory when the first attempt fails (common on bind mounts / flaky GitHub downloads).

## [0.1.2] - 2026-06-16
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

## [0.1.1] - 2026-05-12
### Documentation

- Add [`SPEC-DRIVEN-DEVELOPMENT.md`](SPEC-DRIVEN-DEVELOPMENT.md): functional scope of the bundle, how behavior is validated (tests / static analysis), and `REQ-*` traceability for Makefiles and demos.
- Link the guide from [`README.md`](../README.md), [`CONTRIBUTING.md`](CONTRIBUTING.md), and [`ENGRAM.md`](ENGRAM.md).

## [0.1.0] - 2026-05-12
- Initial tagged release: `WordTemplateProcessor`, context flattening, `HtmlContent`, `TableRows`, `ImageSource`, Symfony extension `nowo_word_template`.
- FrankenPHP demos for Symfony 7 and 8 (`demo/symfony7`, `demo/symfony8`); optional download of the filled `.docx` as **PDF** (PhpWord PDF writer + DomPDF, internally `docx → html → pdf`; fidelity limits are noted in the demo UI).

