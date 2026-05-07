# FrankenPHP demos

The repository includes **optional Symfony demo apps** under `demo/symfony7` and `demo/symfony8` (FrankenPHP + Docker Compose). They are excluded from the Packagist package via `archive.exclude`.

| Demo | PHP | Default HTTP port |
|------|-----|-------------------|
| `demo/symfony7` | 8.2 | 8020 |
| `demo/symfony8` | 8.4 | 8021 |

See [`demo/README.md`](../demo/README.md) for quick start and aggregate `make` targets.

The `Dockerfile` and `docker-compose.yml` at the **repository root** are for **developer QA** (`make up`, Composer, PHPUnit, PHPStan) against PHP 8.2 with required extensions — not for serving the demos.

Conventions (Nowo bundles): Web Profiler + Twig Inspector in dev, path repository to the mounted bundle, `make up` prints `Demo started at: http://localhost:<PORT>`, Composer DNS fallbacks in compose when needed.
