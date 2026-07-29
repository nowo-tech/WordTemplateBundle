# Release

Releases are tagged on GitHub. Packagist serves versions from those tags (see [`composer.json`](../composer.json) `name` / metadata).

Maintainers:

1. Ensure `make release-check` passes (Docker PHP container: QA, coverage floor, `composer validate`).
2. Update `docs/CHANGELOG.md`: move items from **Unreleased** into a dated `X.Y.Z` section.
3. If users must change integration code or config, add a short entry to `docs/UPGRADING.md` for that version.
4. Commit the documentation updates on `main` (or the release branch).
5. Create an annotated tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`.
6. Push commits and the tag: `git push origin main` and `git push origin vX.Y.Z`.
7. On GitHub, open **Releases → Draft** from the tag and paste the changelog section as release notes (helps consumers and Packagist users).

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.

### Example for v1.2.3

```bash
git add -A
git -c core.hooksPath=.githooks commit -m "release: v1.2.3 (FrankenPHP banner, release-check gates, demo php8.5)"
make check-no-cursor-coauthor
git tag -a v1.2.3 -m "Release v1.2.3"
git push origin main
git push origin v1.2.3
```
