# API Reference

The public API consists of three base classes, ten attributes, two extension interfaces, two result objects, and an exception hierarchy. Everything in `src/Internal/` is private and may change without notice between minor versions.

## Stability

The Core MVP follows semantic versioning. Before `1.0.0`, minor versions may include breaking changes (per the pre-v1 convention). After `1.0.0`, the public API in this page is stable: method signatures, attribute names, attribute constructor parameters, result object shapes, and exception class names will not change without a major version bump.

The exact wording of exception messages and explain event `message` strings is **not** stable. Assert on exception classes, `context` keys, and event `stage` values — not on prose.

## Base classes

Extend `Data`, `InputData`, or `OutputData` with a public constructor containing typed promoted properties. `InputData` rejects unknown input fields by default; `Data` and `OutputData` ignore them.

### `abstract readonly class Data`

The neutral base. Subclass when you want default-lenient behavior in both directions, or when building a value object that is not specifically inbound or outbound.

#### `Data::from(array|object $source): static`

Maps an associative array, `stdClass`, or public-property object into an instance of the called data class.

- **Throws** `MissingRequiredFieldException` when a required key is absent.
- **Throws** `TypeMismatchException` when a value does not satisfy the declared type.
- **Throws** `UnknownFieldException` when a strict class receives undeclared keys.
- **Throws** `AmbiguousMappingException` when a non-null union is encountered without a `#[WithCast]`.
- **Throws** `CastException` when a `#[WithCast]` implementation throws.
- **Throws** `MetadataException` when the class shape is unsupported.
- **Throws** `MappingException` for other mapping failures (depth exceeded, non-string keys, constructor failure).

```php
$post = CreatePostData::from(['title' => 'Hello', 'body' => 'World']);
```

#### `Data::tryFrom(array|object $source): MappingResult`

Non-throwing variant of `from()`. Returns a `MappingResult` containing either `data` (on success) or `error` (on failure). Only `MappingException` subclasses are caught; `MetadataException` and `SerializationException` are still thrown, because they indicate programmer errors.

```php
$result = CreatePostData::tryFrom($payload);

if ($result->succeeded()) {
    $post = $result->data;
} else {
    $error = $result->error; // MappingException subclass
}
```

#### `Data::explain(array|object $source): ExplainResult`

Runs the same mapping pipeline as `from()` while recording safe structured events. Mapping failures are returned in `error` rather than thrown. The result exposes `toArray()`, `toJson()`, and `toText()` renderers.

```php
$explanation = CreatePostData::explain($payload);

$explanation->toArray();          // structured events + error context
$explanation->toJson(JSON_PRETTY_PRINT);
$explanation->toText();           // one line per event
```

`explain()` is slightly slower than `tryFrom()` because it records events. Use it for debugging, testing, and one-off diagnostics, not on hot paths.

#### `Data::toArray(): array`

Serializes the object recursively using output names, transformers, visibility rules, redaction, enum backing values, and ISO-8601 date strings. Returns an associative array.

- **Throws** `SerializationException` for unsupported values or depth exceeded.
- **Throws** `TransformerException` when a `#[WithTransformer]` implementation throws.

```php
$post->toArray(); // ['title' => 'Hello', 'body' => 'World']
```

#### `Data::toJson(int $flags = 0): string`

JSON-encodes `toArray()` with `JSON_THROW_ON_ERROR` plus any additional flags passed by the caller. Encoding failures are wrapped in `SerializationException`.

```php
$post->toJson();                       // {"title":"Hello","body":"World"}
$post->toJson(JSON_PRETTY_PRINT);      // pretty-printed
```

### `abstract readonly class InputData extends Data`

Strict-by-default base for inbound payloads. Unknown fields are rejected. Override with `#[IgnoreUnknown]` on the class.

### `abstract readonly class OutputData extends Data`

Lenient-by-default base for outbound shapes. Unknown fields supplied during mapping are ignored. Override with `#[Strict]` on the class.

## Attributes

All attributes are final readonly classes. Attribute targets are listed per attribute. Attributes may be placed on promoted constructor parameters or (equivalently for promoted parameters) on the corresponding property.

| Attribute | Target | Constructor | Behavior |
| --- | --- | --- | --- |
| `Strict` | Class | none | Rejects unknown input keys. |
| `IgnoreUnknown` | Class | none | Ignores unknown input keys. |
| `MapInputName` | Parameter/property | `string $name` | Selects the external input key. |
| `MapOutputName` | Parameter/property | `string $name` | Selects the serialized output key. |
| `ListOf` | Parameter/property | `string $type` | Declares the runtime item type of an array list. |
| `WithCast` | Parameter/property | `class-string<Cast> $cast` | Converts input before type mapping. |
| `WithTransformer` | Parameter/property | `class-string<Transformer> $transformer` | Converts a value before recursive serialization. |
| `Hidden` | Parameter/property | none | Omits the field from serialization. |
| `Sensitive` | Parameter/property | none | Redacts the field from serialization and diagnostics. |
| `Redact` | Parameter/property | `string $replacement = '[redacted]'` | Emits the configured replacement value. |

Each attribute may be declared at most once per parameter (or property). A duplicate raises `MetadataException` at metadata time. Input names must be unique within a class; a collision raises `MetadataException`.

## Extension interfaces

### `interface Cast`

```php
interface Cast
{
    public function cast(mixed $value): mixed;
}
```

Implementations are instantiated with `new` (no constructor arguments) and invoked before normal type conversion during mapping. Implementations must be deterministic and side-effect free. Throw a meaningful exception on failure; the engine wraps it in `CastException` and chains the original.

### `interface Transformer`

```php
interface Transformer
{
    public function transform(mixed $value): mixed;
}
```

Implementations are instantiated with `new` (no constructor arguments) and invoked after redaction and before recursive serialization. Return a scalar, `null`, an array, a `Data` object, an enum, or a `DateTimeInterface`; anything else raises `SerializationException`. Throw a meaningful exception on failure; the engine wraps it in `TransformerException` and chains the original.

See [Custom Casts and Transformers](/extensions) for patterns and rules.

## Result objects

### `final readonly class MappingResult`

Returned by `Data::tryFrom()`. Exactly one of `data` and `error` is populated by the public factories.

| Member | Type | Meaning |
| --- | --- | --- |
| `data` | `?Data` | Populated for a successful mapping. |
| `error` | `?MappingException` | Populated for a failed mapping. |
| `succeeded()` | `bool` | Whether `data` is available. |
| `failed()` | `bool` | Whether `error` is available. |

`MappingResult` cannot be constructed directly. Use `MappingResult::success($data)` or `MappingResult::failure($error)` internally; callers receive instances only from `tryFrom()`.

### `final readonly class ExplainResult`

Returned by `Data::explain()`.

| Member | Type | Meaning |
| --- | --- | --- |
| `events` | `list<array<string, mixed>>` | Ordered mapping events. |
| `data` | `?Data` | Successfully mapped object, or `null`. |
| `error` | `?MappingException` | Mapping failure, or `null`. |
| `succeeded()` | `bool` | Whether `data` is available. |
| `toArray()` | `array{success: bool, events: list<array<string, mixed>>, error: array<string, mixed>\|null}` | Machine-readable result. |
| `toJson(int $flags = 0)` | `string` | JSON representation, encoded with `JSON_THROW_ON_ERROR`. |
| `toText()` | `string` | Compact human-readable trace, one line per event. |

See [Errors and Explain Mode](/diagnostics) for the event catalog and context shape.

## Exceptions

`DTOKitException` (extends `\RuntimeException`) is the package root. All package exceptions derive from it, so `catch (DTOKitException)` handles any package failure as a single category.

```text
DTOKitException
├── MappingException
│   ├── UnknownFieldException
│   ├── MissingRequiredFieldException
│   ├── TypeMismatchException
│   ├── AmbiguousMappingException
│   └── CastException
├── MetadataException
└── SerializationException
    └── TransformerException
```

### `MappingException`

Base class for mapping failures. Adds a readonly `context` array with safe, non-sensitive diagnostic data.

```php
class MappingException extends DTOKitException
{
    public readonly array $context;

    public function __construct(string $message, array $context = [], ?\Throwable $previous = null);
}
```

`context` may contain:

| Key | Type | Meaning |
| --- | --- | --- |
| `class` | `string` | DTO being mapped when the failure occurred. |
| `path` | `string` | Dot-separated location in the original payload. |
| `stage` | `string` | Mapping stage: `type`, `cast`, `constructor`, `unknown_fields`, `mapping`. |
| `expected` | `string` | Expected type name, when applicable. |
| `received` | `string` | Received value's debug type (never the raw value). |
| `fields` | `list<string>` | Unknown field paths, for `UnknownFieldException`. |

### Subclasses

| Class | When thrown | Context keys |
| --- | --- | --- |
| `UnknownFieldException` | Strict class received undeclared keys. | `class`, `path`, `stage`, `fields`. |
| `MissingRequiredFieldException` | A required key is absent and has no default. | `class`, `path`, `stage`, `expected`. |
| `TypeMismatchException` | A value does not satisfy the declared type. | `class`, `path`, `stage`, `expected`, `received`. |
| `AmbiguousMappingException` | Non-null union encountered without a `#[WithCast]`. | `class`, `path`, `stage`, `expected`. |
| `CastException` | A `#[WithCast]` implementation threw. | `class`, `path`, `stage`, `expected`; previous exception chained. |
| `MetadataException` | The class shape is unsupported. | (varies) |
| `SerializationException` | Serialization failure (unsupported value, depth, JSON encoding). | (varies) |
| `TransformerException` | A `#[WithTransformer]` implementation threw. | (varies); previous exception chained. |

`MetadataException` and `SerializationException` are direct subclasses of `DTOKitException` (not of `MappingException`), because they indicate programmer errors rather than bad input.

## Internal classes

The following are internal and not part of the public API. They may change without notice between minor versions:

- `DTOKit\Internal\Engine` — the process-local singleton that performs mapping, serialization, and explain.
- Anything in a namespace ending with `\Internal\`.

Program against the public base classes, attributes, contracts, results, and exceptions only.

## Where to go next

- [Getting Started](/getting-started) for a walk-through.
- [Mapping Input](/mapping) and [Serialization](/serialization) for behavior.
- [Errors and Explain Mode](/diagnostics) for the failure surfaces.
