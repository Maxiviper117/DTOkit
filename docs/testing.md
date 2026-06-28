# Testing Data Boundaries

Boundary tests should prove both accepted input and rejected input. A happy-path snapshot alone does not protect the contract — most production incidents come from inputs the contract was supposed to reject but did not. This page covers test organization, Pest patterns, fixtures, the recommended per-DTO matrix, and the coverage gate.

## Test organization

Organize tests by behavior, not by class. A single DTO may have a mapping test file, a serialization test file, and a diagnostics test file; or a single file per DTO covering all three. The DTOKit test suite uses the latter for fixtures shared across behaviors and the former for cross-cutting behaviors.

Suggested layout:

```text
tests/
├── Support/
│   └── Fixtures.php         # shared DTO classes, enums, value objects
└── Feature/
    ├── MappingTest.php      # scalar, nested, list, enum, date, cast failures
    ├── SerializationTest.php # output names, hidden, sensitive, redact, transformer
    ├── DiagnosticsTest.php   # explain events, tryFrom, error context, depth guard
    └── MetadataTest.php      # constructor validation, duplicate attributes
```

Keep fixtures in a single `Support/Fixtures.php` file so every test references the same DTO classes. Avoid defining throwaway DTO classes inside test functions — they cannot be cached by the engine and they make failures harder to reproduce.

## Mapping test

```php
it('maps a create-user payload', function (): void {
    $data = CreateUserData::from([
        'name'  => 'Ada',
        'email' => 'ada@example.com',
    ]);

    expect($data->name)->toBe('Ada')
        ->and($data->email)->toBe('ada@example.com')
        ->and($data->company)->toBeNull();
});
```

### Pest datasets for coercion edge cases

Use Pest's `with()` to cover coercion edge cases without duplicating test bodies:

```php
it('accepts integer-form strings as int', function (mixed $input, int $expected): void {
    $data = NumberData::from(['value' => $input]);

    expect($data->value)->toBe($expected);
})->with([
    'int'              => [42, 42],
    'integer string'   => ['42', 42],
    'negative string'  => ['-12', -12],
]);

it('rejects non-integer-form values as int', function (mixed $input): void {
    expect(fn () => NumberData::from(['value' => $input]))
        ->toThrow(TypeMismatchException::class);
})->with([
    'float'        => [3.14],
    'float string' => ['3.14'],
    'bool'         => [true],
    'array'        => [[]],
]);
```

Datasets keep the matrix explicit and produce a named case per row in the test output, which makes failures easier to localize.

## Failure-path test

```php
it('reports the nested address path', function (): void {
    expect(fn () => CustomerData::from([
        'address' => ['city' => []],
    ]))->toThrow(TypeMismatchException::class, 'address.city');
});

it('reports missing required fields with their path', function (): void {
    expect(fn () => CustomerData::from([]))
        ->toThrow(MissingRequiredFieldException::class, 'name');
});

it('lists unknown fields in strict mode', function (): void {
    expect(fn () => CreateUserData::from([
        'name'  => 'Ada',
        'email' => 'ada@example.com',
        'slug'  => 'ada',
    ]))->toThrow(UnknownFieldException::class);
});
```

Always assert on the **path**, not just the exception class. A `TypeMismatchException` without a path assertion could match a failure at the wrong field and still pass.

## Serialization safety test

```php
it('does not expose credentials in toArray', function (): void {
    $output = new UserOutput(1, 'secret-value');

    expect($output->toArray())
        ->not->toContain('secret-value')
        ->and($output->toArray())->toHaveKey('password')
        ->and($output->toArray()['password'])->toBe('[redacted]');
});

it('omits hidden fields entirely', function (): void {
    $output = new HiddenOutput('visible', 'hidden-value');

    expect($output->toArray())
        ->not->toHaveKey('internalId')
        ->and($output->toArray())->toHaveKey('reference');
});
```

The first test asserts the value is replaced; the second asserts the key is gone. Both matter — a `#[Sensitive]` field that loses its key breaks callers, and a `#[Hidden]` field that keeps its key leaks the value.

### Round-trip test

```php
it('round-trips through serialization', function (): void {
    $payload = ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com'];
    $data = UserData::from($payload);

    expect($data->toArray())
        ->toMatchArray($payload);
});
```

Round-trip tests catch regressions in `#[MapInputName]` / `#[MapOutputName]` symmetry and in transformer pairs. Use `toMatchArray` rather than `toBe` to avoid coupling the test to key order.

## Explain test

Machine-readable explain output is stable enough for focused assertions. Prefer checking meaningful stages and paths over snapshotting incidental message wording.

```php
it('records the source event first', function (): void {
    $explanation = CustomerData::explain(['name' => 'Ada', 'address' => ['city' => 'Cape Town']]);

    expect($explanation->succeeded())->toBeTrue()
        ->and($explanation->events[0]['stage'])->toBe('source');
});

it('carries the failure path in error context', function (): void {
    $explanation = CustomerData::explain([
        'name' => 'Ada',
        'address' => ['city' => []],
    ]);

    expect($explanation->succeeded())->toBeFalse()
        ->and($explanation->error?->context['path'])->toBe('address.city')
        ->and($explanation->error?->context['stage'])->toBe('type');
});
```

Assert on `stage` and `context` keys (stable) rather than `message` prose (may evolve). The event set and context shape are part of the public contract; the wording is not.

### tryFrom test

```php
it('returns a failure result for invalid input', function (): void {
    $result = CustomerData::tryFrom(['name' => 'Ada']);

    expect($result->failed())->toBeTrue()
        ->and($result->error)->toBeInstanceOf(MissingRequiredFieldException::class)
        ->and($result->data)->toBeNull();
});

it('returns a success result for valid input', function (): void {
    $result = CustomerData::tryFrom(['name' => 'Ada', 'address' => ['city' => 'Cape Town']]);

    expect($result->succeeded())->toBeTrue()
        ->and($result->data)->toBeInstanceOf(CustomerData::class)
        ->and($result->error)->toBeNull();
});
```

## Recommended matrix

For each public DTO, cover:

- **Complete valid input.** Every required field present with a valid value.
- **Every required field missing, one at a time.** Each should raise `MissingRequiredFieldException` with that field's path.
- **Nullable fields as both value and explicit `null`.** Both should map.
- **Defaults when missing and invalid values when present.** Missing → default; present-but-invalid → `TypeMismatchException` (the default must not mask the invalid value).
- **Unknown-field policy.** For strict DTOs, assert `UnknownFieldException` and the `fields` array. For lenient DTOs, assert the unknown keys are ignored and the DTO maps successfully.
- **Nested and list item failure paths.** A bad nested field reports the dotted path; a bad list item reports the indexed path.
- **Enum and date failures.** Unknown enum value, malformed date string.
- **Input and output name mappings.** `#[MapInputName]` accepts the right key and rejects the PHP name; `#[MapOutputName]` emits the right key.
- **Hidden and redacted output.** `#[Hidden]` removes the key; `#[Sensitive]` and `#[Redact]` replace the value.
- **Custom cast and transformer failures.** Cast throws → `CastException`; transformer throws → `TransformerException`.

A compact way to encode the matrix is a Pest dataset of `[payload, expectedExceptionClass | null, expectedPath]` rows:

```php
it('satisfies the customer-data matrix', function (array $payload, ?string $exception, ?string $path): void {
    if ($exception === null) {
        expect(CustomerData::from($payload))->toBeInstanceOf(CustomerData::class);
    } else {
        expect(fn () => CustomerData::from($payload))
            ->toThrow($exception)
            ->and(fn () => CustomerData::from($payload))->toThrow($exception, $path);
    }
})->with([
    'valid'              => [['name' => 'Ada', 'address' => ['city' => 'Cape Town']], null, null],
    'missing name'       => [['address' => ['city' => 'Cape Town']], MissingRequiredFieldException::class, 'name'],
    'bad city'           => [['name' => 'Ada', 'address' => ['city' => []]], TypeMismatchException::class, 'address.city'],
    'unknown field'      => [['name' => 'Ada', 'address' => ['city' => 'Cape Town'], 'extra' => 1], UnknownFieldException::class, ''],
]);
```

## Fixtures

Centralize DTO classes in `tests/Support/Fixtures.php`. Use small, named classes whose purpose is obvious from the class name:

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

enum OrderState: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
```

Avoid reusing a fixture for multiple unrelated behaviors. If a test needs a class with `#[Hidden]`, define a `HiddenOutput` fixture rather than overloading an existing `UserOutput` with extra attributes.

## Coverage gate

DTOKit requires 100% line coverage on `composer test-coverage`:

```bash
composer test-coverage
```

The gate is intentional: the package is small, its behavior is fully deterministic, and uncovered lines in a boundary library are exactly where security and correctness bugs hide. New behavior must come with coverage for:

- **Success paths.** The happy case.
- **Failure paths.** Every exception class the new behavior can raise.
- **Nested paths.** Failures inside nested data and list items.
- **Sensitive-data safety.** Tests that assert raw values do not appear in serialized output, error context, or explain events.

When a line is genuinely unreachable, refactor so it is reachable, or mark it with a `@codeCoverageIgnore` comment after reviewing why. Do not leave uncovered lines "for later" — the gate will not pass.

## Mutation testing considerations

Coverage measures which lines executed, not which assertions would catch a bug. To strengthen a boundary suite, imagine mutations and verify your tests catch them:

- **Flip a default.** Does any test fail?
- **Change a `#[MapInputName]` value.** Does the input-name test fail?
- **Remove a `#[Hidden]`.** Does the serialization test fail?
- **Change `expected` to `received` in a context array.** Does the explain test fail?

If a mutation passes your tests, add an assertion that catches it. Pest's expectation API makes this concise.

## What to assert on

| Source | Assert on |
| --- | --- |
| `from()` | The mapped object's properties, or the thrown exception class and path. |
| `tryFrom()` | `succeeded()` / `failed()`, `data`, `error`. |
| `explain()` | `succeeded()`, `events[*].stage`, `error?->context`. |
| `toArray()` / `toJson()` | Keys present, keys absent, replacement values, structure. |
| Error context | `path`, `stage`, `expected`, `received`, `fields`. Never assert on raw values. |

## Where to go next

- [Errors and Explain Mode](/diagnostics) covers the event catalog and context shape.
- [Custom Casts and Transformers](/extensions) covers testing extensions in isolation.
- [Security](/security) covers sensitive-data safety tests in depth.
