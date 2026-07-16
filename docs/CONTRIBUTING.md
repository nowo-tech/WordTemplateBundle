# Contributing

For how this repository uses requirement IDs (`REQ-*`) and a spec-first habit around Makefiles and demos, see [Spec-driven development](SPEC-DRIVEN-DEVELOPMENT.md).

1. Open an issue or draft PR to describe the change.
2. Follow PSR-12 + Symfony rules (`composer cs-fix` / `composer cs-check`).
3. Run static analysis and tests: `composer qa` (or `make qa` in Docker).
4. Keep line coverage at or above the project minimum (`composer coverage-check`).

See repository workflows under `.github/workflows/` for CI expectations.

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.

If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.
