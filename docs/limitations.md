# Supported Types and Limits

This page is the authoritative list of what the Core MVP supports, what it deliberately does not, why each limit exists, and what to do instead. See [PRD.md](https://github.com/Maxiviper117/DTOkit/blob/main/PRD.md) for the product roadmap and [Phases.md](https://github.com/Maxiviper117/DTOkit/blob/main/Phases.md) for delivery status.

## Input support

| Feature | Status | Notes |
| --- | --- | --- |
| Associative arrays | Supported | String keys only. |
| `stdClass` | Supported | Cast via `get_object_vars()`. |
| Plain objects with public properties | Supported | Private/protected state is not read. |
| Scalars and nullability | Supported | `string`, `int`, `float`, `bool`, with `?T` for nullable. |
| Constructor defaults | Supported | Used only when a key is missing. |
| Nested DTOKit objects | Supported | Mapped recursively; paths preserved. |
| Typed array lists via `#[ListOf]` | Supported | Input must satisfy `array_is_list()`. |
| Backed enum input | Supported | From the backing value via `from()`. |
| Date string input | Supported | Parsed into `DateTimeImmutable` for `DateTimeInterface` properties. |
| Custom casts | Supported | Zero-argument classes implementing `Cast`. |
| `mixed` parameters | Supported | Accept any value, including `null` when nullable. |
| Non-null union inference | Not supported | Use a `#[WithCast]` that picks a member. |
| Intersection types | Not supported | No single class-string to map against. |
| Private/protected object input | Not supported | Only public properties are read. |
| JSON strings passed directly to `from()` | Not supported | Decode with `json_decode()` first. |
| Unit (pure) enum input | Not supported | No backing value to map from; use a cast. |
| Resources, closures | Not supported | Convert to a serializable form before mapping. |
| Iterables (non-array) | Not supported | Materialize to an array first. |

## Output support

| Feature | Status | Notes |
| --- | --- | --- |
| Scalars and `null` | Supported | Emitted directly. |
| Arrays containing supported values | Supported | Walked element by element. |
| Nested DTOKit objects | Supported | Serialized recursively. |
| Backed enums | Supported | Emit their backing value. |
| Unit enums | Supported | Emit their case name. |
| `DateTimeInterface` | Supported | ISO 8601 (`DateTimeInterface::ATOM`). |
| Custom transformers | Supported | Zero-argument classes implementing `Transformer`. |
| `#[Hidden]`, `#[Sensitive]`, `#[Redact]` | Supported | See [Serialization](/serialization). |
| `#[MapOutputName]` | Supported | Decouples PHP names from external keys. |
| Arbitrary object serialization | Not supported | Use a transformer or a nested `Data` class. |
| Circular object graphs | Not supported | Restructure as a tree. |
| Resources, closures | Not supported | Convert before constructing the DTO. |
| Custom date formats | Not via attribute | Use a `#[WithTransformer]` that calls `format()`. |
| Serialization profiles | Not supported | One output shape per DTO; define separate DTOs per profile. |

## Declaration requirements

- DTO classes need a public constructor.
- Constructor fields must be typed promoted properties.
- Final readonly classes are recommended.
- List item types require `#[ListOf]` at runtime (PHPDoc alone is not enough).
- Cast and transformer classes must be constructible without arguments.
- Non-null union parameters require a `#[WithCast]` to disambiguate.
- Each input name must be unique within a class.
- Each attribute may appear at most once per parameter (or property).

## Why each limit exists

### Non-null union inference is not supported

A non-null union like `int|string` has two valid members. Picking one based on the value (`'42'` → int, `'hello'` → string) would be implicit, inconsistent, and impossible to explain. The engine refuses to guess and raises `AmbiguousMappingException` so the developer writes an explicit rule in a cast. The rule is then part of the codebase, not part of the engine's magic.

### Intersection types are not supported

An intersection type (`A&B`) does not yield a single class-string the engine can map against. The runtime would have to find a class that satisfies both, which is not generally possible. Use a single concrete class or a value object built by a cast.

### Private/protected object input is not supported

Reading private or protected state through reflection would bypass the class's encapsulation. A malicious or buggy object could smuggle state through visibility barriers. DTOKit only reads public properties, which are explicitly part of the class's public contract.

### JSON strings are not supported as input

`from()` accepts arrays and objects. A JSON string is a string. Converting it implicitly would couple DTOKit to a specific encoding and would hide the decode step — and its possible failure — from the caller. Decode explicitly with `json_decode()` and handle decode errors before mapping.

### Unit (pure) enums are not supported as input

A pure enum has no backing value, so there is no scalar to map from. The engine would have to accept the case name as a string, which is ambiguous (a case could be named `1`, which collides with integer keys) and inconsistent with backed enums. Use a cast that maps names to cases explicitly, or switch to a backed enum.

### Arbitrary object serialization is not supported

Serializing an arbitrary object's public properties would make output contracts unstable — a third-party class could add a public property in a minor release and your output contract would silently change. It would also risk leaking private information. Use a transformer that emits exactly the keys the contract requires, or wrap the object in a `Data` subclass.

### Circular object graphs are not supported

`readonly` makes it impossible to construct a circular graph through DTOKit, but a circular graph can be assembled manually and then handed to `toArray()`. The 64-depth guard catches this and raises `SerializationException('Maximum serialization depth exceeded.')` instead of recursing forever. Restructure the data as a tree.

### Serialization profiles are not supported

A "profile" (e.g. `admin`, `public`, `internal`) would let one DTO emit different shapes depending on context. The Core MVP keeps output contracts explicit: one DTO per shape. Define separate output DTOs for separate contexts. Profile support is on the roadmap as part of advanced safety.

### Persistent metadata caches are not supported

Metadata is cached in memory for the current process. A persistent cache (APCu, Redis, files) would introduce a global-state dependency, which contradicts the framework-neutral principle. Long-running processes already benefit from the in-memory cache; short-lived processes pay the reflection cost once per request, which is cheap relative to I/O. A persistent cache is on the roadmap as developer tooling.

## Current product limits

The Core MVP does not include:

- **Framework request adapters.** Laravel request, validation, response, cache, and Artisan integration; followed by other frameworks. Map payloads yourself until adapters ship.
- **Validation rule engines.** DTOKit enforces shape; business rules belong in your application. Pair with a validator like Laravel's, Symfony's, or a standalone library.
- **Explicit partial-update field state.** Missing/null/present wrappers are deferred. Use a dedicated DTO per patch shape today.
- **Contract snapshots and schema generation.** TypeScript, Zod, JSON Schema, OpenAPI, and Markdown generation are deferred. Hand-write contracts for now.
- **CLI tooling.** No `dtokit doctor` or `dtokit inspect` command yet.
- **Compiled persistent metadata caches.** See above.
- **Output profiles.** See above.

These omissions are deliberate release boundaries, not oversights. See [PRD.md](https://github.com/Maxiviper117/DTOkit/blob/main/PRD.md) for the full roadmap and [Phases.md](https://github.com/Maxiviper117/DTOkit/blob/main/Phases.md) for delivery status.

## Workarounds at a glance

| Need | Workaround |
| --- | --- |
| Map a JSON string. | `json_decode()` first, then `from()`. |
| Map a unit enum. | Write a cast that maps names to cases. |
| Serialize a value object. | Write a `#[WithTransformer]` that emits an array. |
| Disambiguate a union. | Write a `#[WithCast]` that picks a member. |
| Version an output contract. | Define a new `OutputData` subclass per version. |
| Profile an output. | Define a separate `OutputData` per profile. |
| Patch-update a resource. | Define a DTO per supported patch shape. |
| Generate TypeScript types. | Hand-write them; schema generation is deferred. |
| Validate a business rule. | Validate after mapping, in your application layer. |
| Map a private-property object. | Expose the data through a method, then write a cast. |

## Where to go next

- [Mapping Input](/mapping) and [Serialization](/serialization) cover what is supported and how.
- [Custom Casts and Transformers](/extensions) covers the workaround for most "not supported" entries.
- [API Reference](/api) covers the exact public surface.
