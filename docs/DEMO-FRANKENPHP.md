# FrankenPHP demos

The repository includes **optional Symfony demo apps** under `demo/symfony7` and `demo/symfony8` (FrankenPHP + Docker Compose). They are excluded from the Packagist package via `archive.exclude`.

| Demo | PHP | Default HTTP port |
|------|-----|-------------------|
| `demo/symfony7` | 8.2 | 8020 |
| `demo/symfony8` | 8.4 | 8021 |

See [`demo/README.md`](../demo/README.md) for quick start and aggregate `make` targets.

### Demo page

Each demo exposes a single form at `/` that:

1. Lists every `${variable}` read from `public/demo/doc-final-tpl.docx`.
2. Offers **Download blank template** (`/template`), **Download .docx** (filled), and **Download PDF** (PhpWord → DomPDF).
3. Shows sample **HtmlContent** with inline table styles and **TableRows** editors in a bordered table layout.

The `Dockerfile` and `docker-compose.yml` at the **repository root** are for **developer QA** (`make up`, Composer, PHPUnit, PHPStan) against PHP 8.2 with required extensions — not for serving the demos.

Conventions (Nowo bundles): Web Profiler + Twig Inspector in dev, path repository to the mounted bundle, `make up` prints `Demo started at: http://localhost:<PORT>`, Composer DNS fallbacks in compose when needed.
