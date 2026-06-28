# Release checklist

## Pre-v1 versioning

DTOKit uses normal semantic versions below `1.0.0`; these are not prerelease identifiers.

- `fix:` commits bump the patch version, for example `0.1.0` to `0.1.1`.
- `feat:` commits bump the minor version, for example `0.1.0` to `0.2.0`.
- Breaking changes also bump the minor version before v1, for example `0.2.0` to `0.3.0`.
- Release tags use the `v0.x.y` format without a component prefix.

The current `release-as: 0.1.0` setting is a one-time bootstrap override. Remove it from `release-please-config.json` immediately after the first release PR is merged so later versions are calculated from Conventional Commits.

## Version 0.1.0

- Confirm Composer package name, repository URL, and maintainer metadata.
- Confirm GitHub Actions may create pull requests and repository auto-merge is enabled.
- Protect `main` with tests, coverage, PHPStan, Pint, and Rector as required checks.
- Configure GitHub Pages to deploy through GitHub Actions.
- Enable private vulnerability reporting.
- Run `composer validate --strict` and `composer check` on PHP 8.4.
- Require the CI coverage job to pass at 100%.
- Run `pnpm docs:build`.
- Review `CHANGELOG.md`, public API documentation, and deferred scope in `PRD.md`.
- Tag `v0.1.0` only after all required checks pass.
- Publish `maxiviper117/dtokit-core` to Packagist and verify installation in an empty PHP 8.4 project.
- Connect the Packagist GitHub hook so tags trigger package updates.
- Remove the one-time `release-as` override after Release Please records `0.1.0` in `.release-please-manifest.json`.

## Conventional Commit examples

```text
fix: preserve nested mapping paths
feat: add a field-state wrapper
feat!: change the default unknown-field policy
docs: explain custom casts
test: cover invalid enum values
```

Only `fix`, `feat`, and breaking changes normally change the package version. Documentation, tests, refactors, and CI maintenance are included in release notes according to Release Please defaults but do not independently require a version bump.
