# Release

Releases are tagged on GitHub; Packagist mirrors tags from `composer.json` metadata.

Maintainers:

1. Ensure `make release-check` passes (Docker PHP container).
2. Update `docs/CHANGELOG.md` with the version section.
3. Tag `vX.Y.Z` and push tags.
