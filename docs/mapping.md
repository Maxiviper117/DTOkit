# Mapping Input

## Supported sources

`from()`, `tryFrom()`, and `explain()` accept associative arrays, `stdClass`, and plain objects with public properties.

```php
$fromArray = UserData::from(['id' => 1]);
$fromObject = UserData::from((object) ['id' => 1]);
```

Private and protected object state is not read.

## Scalar conversion

DTOKit performs narrow, predictable conversions:

- Integer strings such as `'42'` can map to `int`.
- Numeric strings can map to `float`.
- Recognized boolean representations can map to `bool`.
- Strings remain strict strings; arrays and objects are not stringified.

Invalid values produce `TypeMismatchException` with the full input path.

| Declared type | Accepted input |
| --- | --- |
| `string` | Strings only. |
| `int` | Integers or integer-form strings such as `'-12'`. |
| `float` | Integers, floats, or numeric strings. |
| `bool` | Booleans and values recognized by PHP's boolean validation filter. |
| `array` | Arrays only. |
| `object` | Objects only. |
| `mixed` | Any value, including `null` when the declaration permits it. |

DTOKit deliberately avoids broad coercion. It does not stringify objects, truncate decimal strings into integers, or convert arbitrary arrays into objects.

## Nested data

Typed `Data` properties map recursively.

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

A city failure is reported at `address.city`.

## Typed lists

PHP cannot express an array item type at runtime. Use `#[ListOf]` and document the static type.

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

The input must be a list. Every item is mapped using the declared item type.

## Enums and dates

Backed enums map from their backing value. Date properties accepting `DateTimeInterface` map string input to `DateTimeImmutable`.

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
        public DateTimeImmutable $createdAt,
    ) {}
}
```

An unknown enum value or invalid date produces a type mismatch at the affected field path.

## Custom casts

Implement `DTOKit\Contract\Cast` and attach it with `#[WithCast]`. Casts must have a zero-argument constructor and must not depend on a service container.

```php
final class TrimCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
```

## Unknown fields

`InputData` is strict by default. `#[IgnoreUnknown]` makes a class permissive; `#[Strict]` makes neutral or output data strict. Ignored fields still appear in explain traces.

Non-null union types require a custom cast because implicit union selection would be ambiguous.

## Constructor defaults

Defaults apply only when a key is absent.

```php
public function __construct(public string $locale = 'en') {}
```

- `[]` uses `'en'`.
- `['locale' => 'fr']` uses `'fr'`.
- `['locale' => null]` fails because the property is not nullable.
- `['locale' => []]` fails rather than falling back to `'en'`.

## Mapping lifecycle

For each DTO, DTOKit performs these stages in order:

1. Normalize the source into string-keyed input.
2. Load or compile constructor metadata.
3. Resolve input names and required fields.
4. Apply a custom cast when configured.
5. Convert nested, list, enum, date, or scalar values.
6. Enforce the unknown-field policy.
7. Invoke the constructor with named arguments.

The maximum recursive depth is 64. Payloads exceeding it fail instead of exhausting the process stack.
