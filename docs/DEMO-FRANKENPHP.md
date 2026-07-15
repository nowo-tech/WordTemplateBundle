# FrankenPHP demo

The repository includes an **optional Symfony demo app** under `demo/symfony8` (FrankenPHP + Docker Compose). It is excluded from the Packagist package via `archive.exclude`.

| Demo | PHP | Default HTTP port |
|------|-----|-------------------|
| `demo/symfony8` | 8.2 | 8021 |

See [`demo/README.md`](../demo/README.md) for quick start and aggregate `make` targets.

## Development vs production (FrankenPHP worker mode)

The demo ships two Caddy configurations under `docker/frankenphp/`:

| File | FrankenPHP worker | Typical use |
|------|-------------------|-------------|
| `Caddyfile.dev` | **Off** (one PHP process per request) | Local demo (`APP_ENV=dev`, default in `docker-compose.yml`) |
| `Caddyfile` | **On** (`worker /app/public/index.php`) | Production-style (`APP_ENV=prod`, `APP_DEBUG=0`) |

The container entrypoint copies `Caddyfile.dev` over the default Caddyfile when `APP_ENV=dev`, so **`make up` runs without worker mode**. For production-style behaviour, set `APP_ENV=prod` in `.env` and rebuild/restart the demo container so FrankenPHP keeps workers in memory.

### Demo page

The demo exposes a single form at `/` that:

1. Lists every `${variable}` read from `public/demo/doc-final-tpl.docx`.
2. Offers **Download blank template** (`/template`), **Download .docx** (filled), and **Download PDF** (PhpWord → DomPDF).
3. Shows sample **HtmlContent** with inline table styles, **TableRows** editors, and **ConditionalBlock** toggles.

The `Dockerfile` and `docker-compose.yml` at the **repository root** are for **developer QA** (`make up`, Composer, PHPUnit, PHPStan) against PHP 8.2 with required extensions — not for serving the demo.

Conventions (Nowo bundles): Web Profiler + Twig Inspector in dev, path repository to the mounted bundle, `make up` prints `Demo started at: http://localhost:<PORT>`, Composer DNS fallbacks in compose when needed.
