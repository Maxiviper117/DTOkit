# Mapping Input

Mapping is the process of turning a source payload (array or object) into an immutable, typed data object. This page covers every rule the engine applies, in the order it applies them, with edge cases and pitfalls.

## Supported sources

`from()`, `tryFrom()`, and `explain()` accept three source shapes:

- **Associative arrays.** The canonical source. Keys must be strings; non-string keys raise `MappingException` during normalization.
- **`stdClass` instances.** Cast to an array via `get_object_vars()` internally.
- **Plain objects with public properties.** Public properties are read via `get_object_vars()`. Private and protected properties are not read, and `__get`/`__set` magic is not invoked.

```php
$fromArray  = UserData::from(['id' => 1]);
$fromStd    = UserData::from((object) ['id' => 1]);
$fromObject = UserData::from(new SomePublicObject(id: 1));
```

JSON strings are not supported — decode them with `json_decode()` first. Resources, closures, and internal PHP classes are not supported sources.

## Source normalization

Before mapping, the engine normalizes the source into a string-keyed array:

1. Arrays are taken as-is.
2. Objects are converted via `get_object_vars()`.
3. Every key is checked. Non-string keys raise `MappingException('Input field names must be strings.')`.

Normalization is shallow. Nested structures are not normalized until they reach their own nested data class. This keeps the cost of mapping proportional to the declared shape, not to the size of the payload.

## Scalar conversion

DTOKit performs narrow, predictable conversions:

| Declared type | Accepted input | Coercion rule |
| --- | --- | --- |
| `string` | Strings only. | No coercion. |
| `int` | Integers or integer-form strings. | String must match `^-?\d+$`. Floats and `'3.14'` are rejected. |
| `float` | Integers, floats, or numeric strings. | `is_numeric()` is the gate. `3` becomes `3.0`. |
| `bool` | Booleans and recognized boolean strings. | `filter_var(FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)`. `'1'`, `'true'`, `'on'`, `'yes'` → `true`; `'0'`, `'false'`, `'off'`, `''`, `'no'` → `false`; anything else → type mismatch. |
| `array` | Arrays only. | No coercion. The declared element type is not enforced unless `#[ListOf]` is used. |
| `object` | Objects only. | No coercion. Arrays are not auto-cast to objects. |
| `mixed` | Any value, including `null` when nullable. | No coercion; the value passes through. |

Invalid values produce `TypeMismatchException` with the full dotted path.

### Coercion examples

```php
// int
UserData::from(['id' => 42]);     // ok, int
UserData::from(['id' => '42']);   // ok, integer-form string
UserData::from(['id' => 3.14]);   // fail, float not coerced to int
UserData::from(['id' => '3.14']); // fail, not an integer-form string
UserData::from(['id' => true]);   // fail, bool not coerced to int

// float
Price::from(['amount' => 3]);     // ok, int → 3.0
Price::from(['amount' => 3.14]);  // ok
Price::from(['amount' => '3.14']);// ok, numeric string

// bool
Flag::from(['on' => true]);       // ok
Flag::from(['on' => 'true']);     // ok
Flag::from(['on' => 1]);          // fail, int not coerced to bool
Flag::from(['on' => 'maybe']);    // fail, not a recognized boolean
```

The engine deliberately avoids broad coercion. It does not stringify objects, truncate decimal strings into integers, convert arbitrary arrays into objects, or treat `1`/`0` as booleans. When in doubt, fail loudly.

## Nested data

Typed `Data` properties map recursively. The engine recognizes the declared class-string and maps the nested payload through the same pipeline.

```php
final readonly class AddressData extends InputData
{
    public function __construct(public string $city) {}
}

final readonly class CustomerData extends InputData
{
    public function __construct(public AddressData $address) {}
}

$customer = CustomerData::from([
    'address' => ['city' => 'Johannesburg'],
]);
```

If the nested value is already an instance of the declared `Data` subclass (for example, you pre-mapped it), it is accepted as-is. If it is neither an array, object, nor the target `Data` class, mapping fails with a `TypeMismatchException`.

A nested failure is reported with the full path: `address.city`, not `city`. This holds at every depth, so a failure inside `customer.address.country.code` is reported as `customer.address.country.code`.

## Typed lists

PHP cannot express an array item type at runtime, so an `array` parameter is treated as an opaque array unless `#[ListOf]` declares the element type.

```php
use DTOKit\Attribute\ListOf;

final readonly class TeamData extends InputData
{
    /** @param list<MemberData> $members */
    public function __construct(
        #[ListOf(MemberData::class)]
        public array $members,
    ) {}
}
```

Rules:

- The input must be a list (`array_is_list($value)`). Associative arrays and arrays with non-sequential integer keys are rejected with a `TypeMismatchException` expecting `list<MemberData>`.
- Every item is mapped using the declared element type. The element type may itself be a `Data` subclass, a backed enum, a `DateTimeInterface`, or any supported scalar.
- An empty list `[]` is a valid list and maps to an empty array.
- PHPDoc (`@param list<MemberData>`) is for static analysis only; `#[ListOf]` is what the engine reads at runtime. Use both.

## Enums and dates

### Backed enums

Backed enums map from their backing value. The engine calls `YourEnum::from($value)` and wraps a mismatch as a `TypeMismatchException`.

```php
enum OrderState: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}

final readonly class OrderData extends InputData
{
    public function __construct(
        public OrderState $state,
    ) {}
}

OrderData::from(['state' => 'pending']); // ok
OrderData::from(['state' => 'paid']);    // ok
OrderData::from(['state' => 'shipped']); // fail, unknown enum value
```

If the value is already an instance of the enum, it is accepted as-is. Integers and strings are accepted as the backing value; anything else fails.

### Unit enums

Pure (non-backed) enums cannot be mapped from a value — they have no backing value to map from. Use a custom cast that selects a case by name or by an explicit mapping table.

### Dates

A parameter typed as `DateTimeInterface` (or any concrete `DateTimeImmutable`/`DateTime`) accepts:

- An existing `DateTimeInterface` instance (passed through).
- A string parsed via `new DateTimeImmutable($value)`.

```php
final readonly class OrderData extends InputData
{
    public function __construct(
        public DateTimeImmutable $createdAt,
    ) {}
}

OrderData::from(['createdAt' => '2026-06-28T12:00:00Z']); // ok
OrderData::from(['createdAt' => '2026-06-28 12:00:00']);  // ok
OrderData::from(['createdAt' => 'next tuesday']);         // ok, parsed by DateTimeImmutable
OrderData::from(['createdAt' => 1751107200]);             // fail, int not accepted
```

A parse failure raises a `TypeMismatchException`. Timezone-aware input is preserved. If you need a specific timezone, use a `#[WithCast]` that converts to the desired zone.

## Custom casts

Implement `DTOKit\Contract\Cast` and attach it with `#[WithCast]`. Casts must have a zero-argument constructor and must not depend on a service container, database, or network.

```php
use DTOKit\Contract\Cast;

final class TrimCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
```

```php
use DTOKit\Attribute\WithCast;

public function __construct(
    #[WithCast(TrimCast::class)]
    public string $name,
) {}
```

The cast runs **before** normal type conversion. If a cast returns a value that still does not satisfy the declared type, mapping fails with a `TypeMismatchException` after the cast runs. Cast failures are wrapped in `CastException` with the field path.

See [Custom Casts and Transformers](/extensions) for patterns, composition, and testing.

## Unknown fields

The unknown-field policy is per class:

- `InputData` is strict by default: unknown fields raise `UnknownFieldException` with a `fields` array of dotted paths.
- `Data` and `OutputData` ignore unknown fields by default. Ignored fields still appear in explain traces as `[unknown] Ignored unknown fields` events.
- `#[Strict]` on a `Data` or `OutputData` class opts into strict behavior.
- `#[IgnoreUnknown]` on an `InputData` class opts out of strict behavior.

Strict mode is enforced **after** all declared parameters have been matched and converted, so a type mismatch in a declared field is reported before an unknown-field error. This makes errors actionable: fix the known field first, then the unknown keys.

## Non-null union types

A non-null union (`int|string`) cannot be inferred — the engine cannot pick a member without a rule. Mapping fails with `AmbiguousMappingException`:

> Non-null union type at `path` requires an explicit cast.

Resolve the ambiguity with a `#[WithCast]` that implements a documented selection rule:

```php
final class IntOrStringCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_int($value) ? $value : (string) $value;
    }
}
```

Or replace the union with a dedicated value object whose constructor documents the rule. Nullable unions (`int|null`) are fine — `null` is handled explicitly and the non-null member is the only candidate.

## Constructor defaults

Defaults apply only when a key is **absent**. They never mask an invalid provided value.

```php
public function __construct(public string $locale = 'en') {}
```

| Payload | Result |
| --- | --- |
| `[]` | uses `'en'` |
| `['locale' => 'fr']` | uses `'fr'` |
| `['locale' => null]` | fails: not nullable |
| `['locale' => []]` | fails: type mismatch, does not fall back to `'en'` |

This is deliberate. A missing key is a contract gap; a malformed value is a contract violation. Treating them the same would hide attacker-crafted input behind a default.

## Mapping lifecycle

For each DTO, DTOKit performs these stages in order:

1. **Normalize** the source into a string-keyed array (see [Source normalization](#source-normalization)).
2. **Load or compile** constructor metadata (see [Deterministic metadata](/concepts#deterministic-metadata)).
3. **Resolve input names** and required fields. For each declared parameter, look up the input key (defaults to the PHP name, overridable with `#[MapInputName]`).
4. **Default** — if the key is missing and a constructor default is available, skip the parameter and let the default apply.
5. **Match** — if the key is missing and no default is available, raise `MissingRequiredFieldException`. Otherwise record a `match` event.
6. **Apply a custom cast** when `#[WithCast]` is present.
7. **Convert** the value:
   - If `null` and the parameter is nullable, accept.
   - If `#[ListOf]` is present, validate the list and map each item.
   - If the declared type is a `Data` subclass, map recursively.
   - If the declared type is a backed enum, call `from()`.
   - If the declared type is `DateTimeInterface`, parse.
   - Otherwise apply scalar coercion.
8. **Enforce the unknown-field policy** — reject or ignore unknown keys.
9. **Invoke the constructor** with the converted arguments. Constructor exceptions are wrapped in `MappingException`.

### Depth guard

The maximum recursive depth is 64. Payloads exceeding it fail with `MappingException('Maximum mapping depth exceeded.')` instead of exhausting the process stack. The depth counts nested `Data` mapping calls and `#[ListOf]` item mapping calls, not scalar conversions.

## Path reporting

Every mapping error carries a dotted `path` in its context:

- A failure on the root field `email` is reported as `email`.
- A failure inside `address.city` is reported as `address.city`.
- A failure inside `members.3.email` (the fourth member's email) is reported as `members.3.email`.

Paths are constructed from the input names actually used (after `#[MapInputName]`), not the PHP property names. This keeps error messages aligned with what the caller sent, not with how the DTO stores it internally.

## Common pitfalls

- **Passing a JSON string.** Decode with `json_decode()` first.
- **Declaring an `array` without `#[ListOf]`.** The engine accepts any array; items are not mapped. Add `#[ListOf]` for typed lists.
- **Treating `?T` as optional.** A nullable type still requires the key. Add `= null` only when the key may be missing.
- **Expecting `1`/`0` to coerce to `bool`.** They do not. Use `'1'`/`'0'`, `true`/`false`, or a cast.
- **Expecting `'3.14'` to coerce to `int`.** It does not; only integer-form strings coerce to `int`. Use a cast or change the type to `float`.
- **Mapping a class without a public constructor.** Raises `MetadataException`. DTOs must have a public constructor.
- **Mixing `Data` instances and arrays in a list.** `#[ListOf]` accepts both — a `Data` instance is passed through, an array is mapped — but a scalar in the same list fails.

## Where to go next

- [Serialization](/serialization) covers the reverse direction.
- [Errors and Explain Mode](/diagnostics) covers reading and asserting on mapping traces.
- [Custom Casts and Transformers](/extensions) covers writing and testing extensions.
