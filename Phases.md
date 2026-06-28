# DTOKit Delivery Phases

This is the living implementation tracker. Update it in the same change that completes or materially revises a phase.

## Current release: Core MVP

- [x] Phase 0 — Package identity and repository guidance
- [x] Phase 1 — Reflection metadata and process-local cache
- [x] Phase 2 — Array/object normalization and constructor mapping
- [x] Phase 3 — Nested data, typed lists, enums, dates, and casts
- [x] Phase 4 — Serialization, transformers, visibility, and redaction
- [x] Phase 5 — Structured errors, `tryFrom()`, and explain traces
- [x] Phase 6 — Public documentation and full quality-suite verification

Core MVP completion requires mapping and serialization from supported sources, deterministic metadata, strict `InputData`, safe diagnostics, documented public APIs, and a passing `composer check` on PHP 8.4.

## Deferred releases

- Contract toolkit: snapshots, compatibility checks, versions, and deprecation.
- Framework adapters: Laravel first, then other integrations.
- Advanced field state: explicit missing/null/present wrappers and partial-update APIs.
- Schema tooling: TypeScript, Zod, JSON Schema, OpenAPI, and Markdown contracts.
- Tooling: CLI doctor, compiled caches, benchmarks, static-analysis extensions, and IDE support.

## Change log

- 2026-06-28: Established the PHP 8.4 Core MVP phases from the product plan.
- 2026-06-28: Implemented and tested Core MVP phases 1–5; phase 6 verification is in progress.
- 2026-06-28: Completed Core MVP documentation and verification (`composer check` and VitePress build).
- 2026-06-28: Added `PRD.md` as the durable product requirements source.
- 2026-06-28: Hardened Core MVP edge cases and added the PHP 8.4 coverage gate, API reference, changelog, and `0.1.0` release checklist.
- 2026-06-28: Rebuilt the VitePress site with complete Core MVP guides and a single sidebar-only navigation structure.
- 2026-06-28: Changed the Composer package identity to `maxiviper117/dtokit-core` while retaining the `DTOKit` PHP namespace.
- 2026-06-28: Expanded the documentation with detailed mapping, serialization, extension, testing, security, recipes, and limitations guidance.
- 2026-06-28: Removed all template-repository CI guards and enabled package workflows unconditionally, retaining the Dependabot actor safety check.
- 2026-06-28: Expanded CI to PHP 8.4/8.5, upgraded checkout actions, added problem matchers and scoped triggers, and completed release and Composer metadata.
- 2026-06-28: Configured Release Please for a one-time `0.1.0` bootstrap and predictable pre-v1 minor/patch versioning.
- 2026-06-28: Completed local `0.1.0` release preparation: Release Please-owned changelog, stable Composer dependencies, badges, security policy, issue forms, and repository-settings checklist.
