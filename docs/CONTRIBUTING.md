# Contributing

For how this repository uses requirement IDs (`REQ-*`) and a spec-first habit around Makefiles and demos, see [Spec-driven development](SPEC-DRIVEN-DEVELOPMENT.md).

1. Open an issue or draft PR to describe the change.
2. Follow PSR-12 + Symfony rules (`composer cs-fix` / `composer cs-check`).
3. Run static analysis and tests: `composer qa` (or `make qa` in Docker).
4. Keep line coverage at or above the project minimum (`composer coverage-check`).

See repository workflows under `.github/workflows/` for CI expectations.
