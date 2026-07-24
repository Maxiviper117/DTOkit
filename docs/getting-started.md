# Getting Started

## Requirements

- PHP 8.4 or newer (tested on 8.4 and 8.5).
- Composer 2.
- No framework, container, or extension beyond the standard library is required.

## Installation

```bash
composer require maxiviper117/dtokit-core
```

If you are contributing to DTOKit itself instead, clone the repository and run:

```bash
composer install
composer check
```

`composer check` runs Pint (formatting), PHPStan (static analysis), Rector (refactor dry-run), and Pest (tests with coverage gate). Use it as the single pre-push verification command.

## Your first input boundary

Input data represents untrusted values entering the application. It rejects unknown fields by default to prevent accidental acceptance of undeclared payload keys.

```php
use DTOKit\InputData;

final readonly class CreatePostData extends InputData
{
    public function __construct(
        public string $title,
        public string $body,
        public ?int $categoryId = null,
    ) {}
}

$post = CreatePostData::from([
    'title' => 'Typed boundaries',
    'body'  => 'Arrays in, typed object out.',
]);
```

After mapping, `$post->title`, `$post->body`, and `$post->categoryId` are typed and immutable. Because `categoryId` is nullable and has a default, both `['title' => …, 'body' => …]` and `['title' => …, 'body' => …, 'categoryId' => null]` are accepted. An unknown key such as `slug` is rejected with `UnknownFieldException`.

### Nullable and optional are different

This is the single most important rule to internalize:

```php
public string $name            // required, cannot be null, key must be present
public ?string $middleName     // required, may be null, key must still be present
public string $country = 'ZA'  // optional, default used only when key is missing
public ?string $company = null // optional AND nullable: missing → null, present null → null
```

A nullable type without a default still requires the key to be present. Only a constructor default makes a missing key optional. Defaults never mask invalid provided values: `['country' => []]` fails with a type mismatch rather than falling back to `'ZA'`.

## Your first output boundary

Output data describes values leaving the application. Unknown fields supplied during mapping are ignored by default because output shapes are deliberate export contracts rather than untrusted payloads.

```php
use DTOKit\OutputData;

final readonly class PostOutput extends OutputData
{
    public function __construct(
        public int $id,
        public string $title,
    ) {}
}

$output = new PostOutput(10, 'Typed boundaries');

$output->toArray(); // ['id' => 10, 'title' => 'Typed boundaries']
$output->toJson();  // {"id":10,"title":"Typed boundaries"}
```

You can either construct an output object directly (as above) or map a payload into it with `PostOutput::from($payload)`. Direct construction is common when an output DTO is assembled from domain objects rather than from a transport array.

## Map external field names

External payloads rarely follow PHP naming conventions. `#[MapInputName]` and `#[MapOutputName]` decouple inbound and outbound names.

```php
use DTOKit\Attribute\MapInputName;
use DTOKit\Attribute\MapOutputName;

final readonly class UserData extends InputData
{
    public function __construct(
        #[MapInputName('user_id')]
        #[MapOutputName('id')]
        public int $userId,
        public string $name,
    ) {}
}

$user = UserData::from([
    'user_id' => '42',
    'name'    => 'Ada',
]);

$user->userId;     // 42 (integer-string coerced to int)
$user->toArray();  // ['id' => 42, 'name' => 'Ada']
```

Input and output names are independent decisions. This makes it possible to accept a legacy payload while emitting a cleaner response contract — useful when an API field is renamed but backwards compatibility with existing clients must be preserved.

## Map nested data

```php
final readonly class AddressData extends InputData
{
    public function __construct(public string $city) {}
}

final readonly class CustomerData extends InputData
{
    public function __construct(
        public string $name,
        public AddressData $address,
    ) {}
}

$customer = CustomerData::from([
    'name'    => 'Ada',
    'address' => ['city' => 'Cape Town'],
]);
```

Nested failures retain their path. An invalid city produces an error for `address.city`, not just `city`. This holds at every depth: a failure inside `address.country.code` is reported as `address.country.code`.

## Handle mapping failure

`from()` throws a specific mapping exception on any failure. Use `tryFrom()` when failure is expected control flow.

```php
$result = CreatePostData::tryFrom(['title' => 'Missing body']);

if ($result->failed()) {
    $error = $result->error; // MappingException subclass
    echo $error->getMessage();
    echo $error->context['path'] ?? '';
}
```

`tryFrom()` is preferred when callers can recover, when building validation messages, or when a single failure should not abort a batch. `from()` is preferred inside request handlers where invalid input is genuinely exceptional.

## A complete round-trip

A typical request handler maps input, hands the typed object to the application, then serializes an output object back to JSON.

```php
final readonly class CreateOrderInput extends InputData
{
    public function __construct(
        public string $reference,
        public int $amountCents,
        public ?string $coupon = null,
    ) {}
}

final readonly class OrderOutput extends OutputData
{
    public function __construct(
        public string $id,
        public string $reference,
        public int $amountCents,
    ) {}
}

$input = CreateOrderInput::from($request->payload);
$order = $this->orders->place($input);

echo (new OrderOutput($order->id, $order->reference, $order->amountCents))->toJson();
```

The input DTO rejects unknown fields, enforces types, and gives the application a typed object to work with. The output DTO deliberately exposes only the fields the response contract promises — never the internal model.

## Inspect a mapping with explain

When a mapping behaves unexpectedly, `explain()` produces a safe trace of every decision the engine made.

```php
$explanation = CreateOrderInput::explain($request->payload);

$explanation->toText();
// [source] Normalized input source
// [match] Matched input field
// [match] Matched input field
// [default] Used constructor default
// [result] Mapping succeeded

$explanation->toArray(); // structured events, error context
$explanation->toJson(JSON_PRETTY_PRINT);
```

Trace events contain paths, types, and stage names — never raw sensitive values. See [Errors and Explain Mode](/diagnostics) for the full event catalog.

## Choose the right entry point

| Method | Use when |
| --- | --- |
| `from()` | Invalid input should throw and stop the current operation. |
| `tryFrom()` | Mapping failure is expected control flow. |
| `explain()` | You need a safe trace showing how mapping reached its result. |
| `toArray()` | You need a transport-ready PHP array. |
| `toJson()` | You need JSON generated from the same array contract. |

## Common first-attempt mistakes

- **Forgetting `final readonly`.** DTOKit does not require it, but non-readonly classes can mutate after mapping and break the boundary guarantee. The base classes are themselves `readonly`, so subclasses must be readonly-compatible.
- **Treating `?int` as optional.** A nullable type without a default still requires the key. Add `= null` only when the key may be missing.
- **Putting business validation in the constructor.** Constructors should be declarative side-effect-free assignments. Validate business rules after mapping.
- **Serializing domain models directly.** Build a dedicated `OutputData` for each response shape instead of leaking persistence fields.
- **Passing JSON strings to `from()`.** Decode JSON first; DTOKit maps arrays and objects, not strings.
- **Declaring lists without `#[ListOf]`.** PHP cannot express array item types at runtime, so DTOKit treats an `array` parameter as an opaque array. Use `#[ListOf(MemberData::class)]` to map items.

## Next steps

Continue with [Core Concepts](/concepts) for the mental model, then [Mapping Input](/mapping) for the full mapping rules and [Serialization](/serialization) for output contracts.
