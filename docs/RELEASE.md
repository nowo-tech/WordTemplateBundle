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
