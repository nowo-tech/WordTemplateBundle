# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/word-template-bundle` (`symfony-bundle`) |
| Audited revision | `v1.3.8` (remediation on top of `v1.3.7` / `d45b5f3`) |
| Audit date | 2026-09-25 |
| Method | Manual review of every file under `src/` (processor, PHPWord bridge, deadline, utils, models, result, DI extension, `Resources/config/*.yaml`); static state of `phpoffice/phpword` 1.4.0 (installed dev dependency) checked in `Shared/Html.php` and `TemplateProcessor.php` |
| Remediation (2026-09-25) | W-01 fixed (PHPWord `Html` statics isolated per `process()` call and restored in `finally`, `Processor/PhpWordHtmlState.php`); W-02 fixed (PHPWord working copy deleted on every path); regression tests reuse one processor for consecutive merges |
| **Verdict** | ✅ **Viable under scenario B** — the bundle service is stateless, and every PHPWord global it touches (`Settings` output escaping, `Html::$css` / `$xpath` / `$options`) is saved and restored around each merge. W-03 (cooperative timeout) is accepted |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `WordTemplateProcessor` is a `readonly class` holding only config strings and the timeout |
| Static properties / `static` locals | ✅ | None in the bundle; `ContextFlattener` / `BrokenMacroNormalizer::forConditionalDelimiters()` are pure static helpers |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset in the bundle |
| Request / user / locale captured in services | ✅ | Template path and context are method arguments |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ⚠️ Low | `set_time_limit()` is changed and restored inside `process()` |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | None; result is a file path (`ProcessedDocument`) |
| Resources (files, sockets, cURL) held open | ✅ | PHPWord's temporary template copy is removed on every path (W-02, resolved) |
| Memory growth across requests | ✅ | No caches; PHPWord's static `Html::$xpath` is cleared after each merge |
| Blocking I/O and timeouts | ⚠️ Low | Cooperative deadline between merge phases; a single PHPWord call cannot be interrupted (W-03, accepted) |
| Third-party static state | ✅ | `PhpWord\Settings` output escaping and `PhpWord\Shared\Html::$css` / `$xpath` / `$options` are saved, cleared and restored around each `process()` (W-01, resolved); `Html::$listIndex` only grows as a counter |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist:11-12` |

A worker demo exists: `demo/symfony8/docker/frankenphp/Caddyfile:31` declares a `worker` block, with `max_execution_time 240`, Caddy `write 250s` and `max_wait_time 30s` configured above it.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\WordTemplateBundle\Processor\WordTemplateProcessor` (public, alias `WordTemplateProcessorInterface`) | yes | none (`readonly class`); PHPWord statics scoped per call | ✅ | ✅ |

Per-call objects: `TemplateProcessorBridge` (one per call, local variable), `ConditionalBlockApplicator` / `BrokenMacroNormalizer` (readonly config), `ProcessDeadline` (`final readonly`, created per `process()` call with `hrtime()`), `ProcessedDocument` (`final readonly`). Models (`ConditionalBlock`, `HtmlContent`, `ImageSource`, `TableRows`) are readonly value objects passed in by the caller and never stored in a service.

## Findings

### W-01 — PHPWord `Html` keeps the last parsed `<style>` sheet in a static property (Medium)

- **Where:** `src/Processor/WordTemplateProcessor.php:200-207` (`applyHtml()` calls `Html::addHtml($cell, $content->html, false, false)` at line 205). In PHPWord 1.4.0: `vendor/phpoffice/phpword/src/PhpWord/Shared/Html.php:45-54` (`static $listIndex`, `$xpath`, `$options`, `$css`), `:197-199` (`self::$css = new Css(...)` when a `<style>` element is parsed), `:74` and `:95` (`$options` / `$xpath` overwritten on every call), `:166-173` (class/id rules applied from `self::$css`).
- **Worker impact:** `Html::$css` is only assigned when an HTML fragment contains a `<style>` element, and never cleared. In classic mode the process ends after the request; in a worker the stylesheet from one `HtmlContent` stays active for every later `addHtml()` call on that worker thread, under A and B (static properties are not touched by `services_resetter`). A later document — possibly for another user — whose HTML uses matching `class` / `id` attributes is rendered with the previous document's CSS. Only formatting is affected, not text content. `Html::$xpath` also keeps the last fragment's DOM (which may contain another user's data) in memory until the next call; it is not exposed. `$listIndex` only grows as an integer used for unique list style names.
- **Recommendation:** after each `addHtml()` call (and before the first one), reset PHPWord's statics — for example with a small internal subclass of `PhpOffice\PhpWord\Shared\Html` exposing `public static function resetState(): void { self::$css = null; self::$xpath = null; self::$options = null; }`, called from `applyHtml()` in a `finally`. Until then, avoid `<style>` blocks in `HtmlContent` and use inline `style="..."` attributes.
- **Status:** Resolved — new internal `src/Processor/PhpWordHtmlState.php` (reflection on `Html::$css`, `$xpath`, `$options`; missing properties are skipped). `WordTemplateProcessor::process()` calls `PhpWordHtmlState::isolate()` before the merge and `restore()` in its `finally`, so each merge starts without CSS and the previous (application) values come back afterwards. The scope is one `process()` call: a `<style>` block still applies to later `HtmlContent` values of the same merge, as before. Test: `tests/Integration/WorkerModeIsolationTest.php` (request 1 with `<style>.hl{color:#FF0000}`, request 2 with `class="hl"` and no stylesheet → not red).

### W-02 — PHPWord temporary template copies are not always removed (Low)

- **Where:** `src/Processor/WordTemplateProcessor.php:241-251` (`openTemplate()` creates a `TemplateProcessorBridge`), used by `listVariables()` (`:44-52`), `listConditionalBlocks()` (`:54-70`) and `process()` (`:88`). PHPWord copies the template to `tempnam(Settings::getTempDir(), 'PhpWord')` in its constructor (`vendor/phpoffice/phpword/src/PhpWord/TemplateProcessor.php:110-118`) and only deletes that copy inside `saveAs()` (`:1037-1053`); `__destruct()` only closes the `ZipArchive` (`:139-149`).
- **Worker impact:** every `listVariables()` / `listConditionalBlocks()` call, and every `process()` call that fails before `persistTemplate()` (timeout, `InvalidContextValueException`, PHPWord error), leaves one `PhpWord*` file in the temp directory. The bundle does clean its own output file on a failed save (`:131-134`). This is not specific to worker mode, but long-lived workers and containers make the disk usage grow until the temp dir is purged.
- **Recommendation:** in the bridge, expose the temporary filename and `unlink()` it in a `finally` when the processor is not saved (or add a `__destruct()` in `TemplateProcessorBridge` that removes it if it still exists). Callers must also call `ProcessedDocument::dispose()` for temporary outputs.
- **Status:** Resolved — `TemplateProcessorBridge::removeTemporaryDocument()` closes the zip and deletes the working copy; it runs in `finally` blocks of `listVariables()`, `listConditionalBlocks()` and `process()` (no-op after a successful `saveAs()`). `ProcessedDocument::dispose()` remains the caller's responsibility for temporary outputs.

### W-03 — Timeout is cooperative, and restoring `set_time_limit()` restarts the budget (Low)

- **Where:** `src/Processor/WordTemplateProcessor.php:74-76` (reads `max_execution_time`, calls `set_time_limit($this->timeout)`), `:140-143` (`finally` restores escaping and calls `set_time_limit($previousMaxExecution)`); `src/Runtime/ProcessDeadline.php:29-36` (checked between phases).
- **Worker impact:** `ProcessDeadline` can only stop work between merge steps; one slow PHPWord call (large `cloneRow()`, big `Html::addHtml()`, `setImageValue()` with a slow or remote image path) runs until PHP's own limit. `set_time_limit()` restarts PHP's execution timer each time it is called, so after a 170 s merge the `finally` block grants the rest of the request a fresh full `max_execution_time`. In a worker this can keep a thread busy longer than intended. State does not leak: the value is restored.
- **Recommendation:** keep the documented hierarchy (`timeout` < `max_execution_time` < Caddy `write` timeout) and FrankenPHP `max_wait_time`, as the demo does. Only pass local image paths in `ImageSource`. Optionally restore the limit as `max(1, $previous - elapsed)` instead of the full previous value.
- **Status:** Accepted — no state leaks (the limit is restored); a single PHPWord call cannot be interrupted in-process. Deployment limits above apply.

No other findings. `PhpWord\Settings::setOutputEscapingEnabled(true)` is always paired with a restore in `finally` (`:75`, `:80`, `:141`), so the global PHPWord setting does not leak between requests.

## Usage recommendations in worker mode

- `<style>` elements inside `HtmlContent` only apply within the same `process()` call.
- Always call `ProcessedDocument::dispose()` (or pass an explicit `$outputPath` and delete it yourself) after streaming the file.
- Output files (`nowo_word_tpl_*`) are the caller's: dispose them after streaming.
- Configure `nowo_word_template.timeout` below `max_execution_time`, and set FrankenPHP `max_wait_time` so busy merges do not queue requests forever. For heavy traffic, move merges to Messenger workers.
- Do not change other `PhpOffice\PhpWord\Settings` values per request in application code without restoring them; they are process-wide in a worker.

## Re-audit triggers

Re-run this audit when a change adds: properties to `WordTemplateProcessor`, a template/result cache, new PHPWord static calls (`Settings::set*`, `Html`, `Shared\*`), a PHPWord major upgrade, external processes (LibreOffice / PDF conversion), or remote image fetching.
