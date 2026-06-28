# API reference

## Base classes

Extend `Data`, `InputData`, or `OutputData` with a public constructor containing typed promoted properties. `InputData` rejects unknown input fields by default; the other bases ignore them.

### `Data::from(array|object $source): static`

Maps an associative array or public object properties into the called data class. It throws a `MappingException` subtype for mapping failures and `MetadataException` for invalid class declarations.

### `Data::tryFrom(array|object $source): MappingResult`

Returns a successful result containing `data`, or a failed result containing `error`. Use `succeeded()` and `failed()` to inspect the outcome.

### `Data::explain(array|object $source): ExplainResult`

Runs the same mapping pipeline while recording safe structured events. The result exposes `toArray()`, `toJson()`, and `toText()`. Mapping failures are returned in `error` rather than thrown.

### `Data::toArray(): array`

Serializes the object recursively using output names, transformers, visibility rules, redaction, enum backing values, and ISO-8601 date strings.

### `Data::toJson(int $flags = 0): string`

JSON-encodes `toArray()` with `JSON_THROW_ON_ERROR`. Encoding failures are wrapped in `SerializationException`.

## Attributes

| Attribute | Target | Behavior |
| --- | --- | --- |
| `MapInputName(string $name)` | Parameter/property | Selects the external input key. |
| `MapOutputName(string $name)` | Parameter/property | Selects the serialized output key. |
| `ListOf(string $type)` | Parameter/property | Defines the runtime item type of an array list. |
| `WithCast(class-string<Cast> $cast)` | Parameter/property | Converts input before type mapping. |
| `WithTransformer(class-string<Transformer> $transformer)` | Parameter/property | Converts a value before recursive serialization. |
| `Strict` | Class | Rejects unknown input keys. |
| `IgnoreUnknown` | Class | Ignores unknown input keys. |
| `Hidden` | Parameter/property | Omits the field from serialization. |
| `Sensitive` | Parameter/property | Redacts the field from serialization and diagnostics. |
| `Redact(string $replacement = '[redacted]')` | Parameter/property | Emits the configured replacement value. |

Cast and transformer classes must implement their corresponding DTOKit interface and be constructible without arguments.

## Exceptions

`DTOKitException` is the package root. Mapping failures derive from `MappingException`: `UnknownFieldException`, `MissingRequiredFieldException`, `TypeMismatchException`, `AmbiguousMappingException`, and `CastException`. Other roots are `MetadataException` and `SerializationException`; transformer failures use `TransformerException`.

Mapping exceptions expose safe structured `context`, including class, path, stage, expected type, and received type where relevant.

## Result objects

### `MappingResult`

| Member | Type | Meaning |
| --- | --- | --- |
| `data` | `?Data` | Populated for a successful mapping. |
| `error` | `?MappingException` | Populated for a failed mapping. |
| `succeeded()` | `bool` | Whether data is available. |
| `failed()` | `bool` | Whether an error is available. |

Exactly one of `data` and `error` is populated by the public factories.

### `ExplainResult`

| Member | Type | Meaning |
| --- | --- | --- |
| `events` | `list<array<string, mixed>>` | Ordered mapping events. |
| `data` | `?Data` | Successfully mapped object. |
| `error` | `?MappingException` | Mapping failure, when present. |
| `succeeded()` | `bool` | Whether mapping succeeded. |
| `toArray()` | `array` | Machine-readable result. |
| `toJson(int $flags = 0)` | `string` | JSON representation. |
| `toText()` | `string` | Compact human-readable trace. |

## Extension interfaces

### `Cast`

```php
interface Cast
{
    public function cast(mixed $value): mixed;
}
```

### `Transformer`

```php
interface Transformer
{
    public function transform(mixed $value): mixed;
}
```

Extensions are instantiated with no constructor arguments for deterministic, framework-neutral behavior.
