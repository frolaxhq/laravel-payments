# Upgrade Guide

## From 0.x to 1.0

This is the initial stable release. There are no breaking changes from prior versions.

## Future Upgrades

This section will be updated as new major versions are released. The upgrade guide will document:

- **Breaking changes** — Any API changes that require code modifications
- **Migration steps** — Database schema changes and data migrations
- **Configuration changes** — New or modified config keys
- **Deprecations** — Features being retired with recommended replacements

## Versioning Policy

This package follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html):

- **Major** (X.0.0) — Breaking API changes
- **Minor** (0.X.0) — New features, backward-compatible
- **Patch** (0.0.X) — Bug fixes, backward-compatible

### Stability Promise

Within a major version:

- All public interfaces (`GatewayDriverContract`, capability interfaces, etc.) remain backward-compatible
- The canonical payload structure remains stable
- Database schemas are only extended, never modified destructively
- New capabilities are added as opt-in interfaces (Open/Closed Principle)
