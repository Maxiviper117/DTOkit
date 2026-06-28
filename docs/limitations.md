# Supported Types and Limits

## Input support

| Feature | Status |
| --- | --- |
| Associative arrays | Supported |
| `stdClass` and public object properties | Supported |
| Scalars and nullability | Supported |
| Constructor defaults | Supported |
| Nested DTOKit objects | Supported |
| Typed array lists via `ListOf` | Supported |
| Backed enum input | Supported |
| Date string input | Supported |
| Custom casts | Supported |
| Non-null union inference | Not supported; use a cast |
| Intersection types | Not supported |
| Private/protected object input | Not supported |
| JSON strings passed directly to `from()` | Not supported; decode first |

## Output support

| Feature | Status |
| --- | --- |
| Scalars, null, and arrays | Supported |
| Nested DTOKit objects | Supported |
| Backed and unit enums | Supported |
| `DateTimeInterface` | Supported as ISO 8601 |
| Custom transformers | Supported |
| Arbitrary object serialization | Not supported; use a transformer |
| Circular object graphs | Not supported |

## Declaration requirements

- DTO classes need a public constructor.
- Constructor fields must be typed promoted properties.
- Final readonly classes are recommended.
- List item types require `#[ListOf]` at runtime.
- Cast and transformer classes require zero-argument construction.

## Current product limits

The Core MVP does not include framework request adapters, validation rule engines, explicit partial-update field state, serialization profiles, contract snapshots, schema generation, compiled persistent metadata caches, or CLI tooling.

These omissions are deliberate release boundaries. See the project PRD and phases tracker for planned work.
