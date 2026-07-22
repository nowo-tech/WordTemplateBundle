# FrankenPHP demo

The repository includes an **optional Symfony demo app** under `demo/symfony8` (FrankenPHP + Docker Compose). It is excluded from the Packagist package via `archive.exclude`.

| Demo | PHP | Default HTTP port |
|------|-----|-------------------|
| `demo/symfony8` | 8.2 | 8021 |

See [`demo/README.md`](../demo/README.md) for quick start and aggregate `make` targets.

## Development vs production (FrankenPHP worker mode)

The demo ships two Caddy configurations under `docker/frankenphp/`:

| File | `FRANKENPHP_MODE` | Typical use |
|------|-------------------|-------------|
| `Caddyfile.dev` | **Off** (one PHP process per request) when `APP_ENV=dev` | Local demo (`APP_ENV=dev`, default in `docker-compose.yml`) |
| `Caddyfile` | **On** (`worker /app/public/index.php`) | Production-style (`APP_ENV=prod`, `APP_DEBUG=0`) |

The container entrypoint copies `Caddyfile.dev` over the default Caddyfile when `APP_ENV=dev`, so **`make up` runs without worker mode**. For production-style behaviour, set `APP_ENV=prod` in `.env` and rebuild/restart the demo container so FrankenPHP keeps workers in memory.

## Timeouts (avoid stuck FrankenPHP workers)

Template merge (`WordTemplateProcessor::process()`) is **blocking in-process work** (PHPWord). Under FrankenPHP a hung or very large merge can occupy a worker thread. Keep this hierarchy (**REQ-RUNTIME-001**):

| Layer | Demo default | Role |
|-------|--------------|------|
| **`PROCESS_TIMEOUT`** → `nowo_word_template.timeout` | **180s** | Shared Nowo env for the merge wall-clock timeout (cooperative deadline between phases **and** PHP `set_time_limit`). On expiry the processor throws `ProcessingTimedOutException`. Bundle default when unset in YAML is also **180**. |
| PHP `max_execution_time` / `max_input_time` | **240s** | Set via `frankenphp { php_ini … }` and `docker/php-dev.ini` — must be **greater** than the operation timeout so the cooperative deadline can fire first |
| Caddy `servers.timeouts.write` | **250s** | HTTP write deadline above PHP |
| FrankenPHP `max_wait_time` | **30s** | Max time a request waits for a free PHP thread before **504** (limits backlog when workers are busy merging) |

### Shared `PROCESS_TIMEOUT`

```bash
# .env / .env.example
PROCESS_TIMEOUT=180
```

```yaml
# config/packages/nowo_word_template.yaml
nowo_word_template:
    timeout: '%env(int:PROCESS_TIMEOUT)%'
```

- **Name:** `PROCESS_TIMEOUT` (same env as other Nowo bundles that perform blocking work).
- **Default:** `180` seconds.
- **Hierarchy:** `PROCESS_TIMEOUT` &lt; PHP `max_execution_time` (240) &lt; Caddy write (250).
- When raising `PROCESS_TIMEOUT`, raise PHP + Caddy write timeouts in the same step.

### Demo page

The demo exposes a single form at `/` that:

1. Lists every `${variable}` read from `public/demo/doc-final-tpl.docx`.
2. Offers **Download blank template** (`/template`), **Download .docx** (filled), and **Download PDF** (PhpWord → DomPDF).
3. Shows sample **HtmlContent** with inline table styles, **TableRows** editors, and **ConditionalBlock** toggles.

The `Dockerfile` and `docker-compose.yml` at the **repository root** are for **developer QA** (`make up`, Composer, PHPUnit, PHPStan) against PHP 8.2 with required extensions — not for serving the demo.

Conventions (Nowo bundles): Web Profiler + Twig Inspector in dev, path repository to the mounted bundle, `make up` prints `Demo started at: http://localhost:<PORT>`, Composer DNS fallbacks in compose when needed.
