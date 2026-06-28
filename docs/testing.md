# Testing Data Boundaries

Boundary tests should prove both accepted input and rejected input. A happy-path snapshot alone does not protect the contract.

## Mapping test

```php
it('maps a create-user payload', function (): void {
    $data = CreateUserData::from([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    expect($data->name)->toBe('Ada')
        ->and($data->email)->toBe('ada@example.com');
});
```

## Failure-path test

```php
it('reports the nested address path', function (): void {
    expect(fn () => CustomerData::from([
        'address' => ['city' => []],
    ]))->toThrow(TypeMismatchException::class, 'address.city');
});
```

## Serialization safety test

```php
it('does not expose credentials', function (): void {
    $output = new UserOutput(1, 'secret');

    expect($output->toArray())
        ->not->toContain('secret');
});
```

## Explain test

Machine-readable explain output is stable enough for focused assertions. Prefer checking meaningful stages and paths over snapshotting incidental message wording.

```php
$result = CustomerData::explain($payload);

expect($result->succeeded())->toBeFalse()
    ->and($result->error?->context['path'])->toBe('address.city');
```

## Recommended matrix

For each public DTO, cover:

- Complete valid input.
- Every required field missing.
- Nullable fields as both value and explicit `null`.
- Defaults when missing and invalid values when present.
- Unknown-field policy.
- Nested and list item failure paths.
- Enum and date failures.
- Input and output name mappings.
- Hidden and redacted output.
- Custom cast and transformer failures.
