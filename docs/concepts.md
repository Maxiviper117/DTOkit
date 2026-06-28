# Core Concepts

## Data boundaries

A boundary is a point where data changes trust level or representation. DTOKit makes the accepted or exposed shape explicit at that point.

DTOs describe transport data. They should not persist models, call services, make business decisions, or become domain entities.

## Base classes

### `Data`

The neutral base for typed boundary objects. Unknown input fields are ignored unless `#[Strict]` is applied.

### `InputData`

For incoming and generally untrusted payloads. Unknown fields are rejected by default to prevent accidental acceptance of undeclared values.

### `OutputData`

For deliberate response and export shapes. Unknown fields supplied during construction mapping are ignored by default.

## Immutable declarations

DTOKit expects public constructors with typed promoted properties. Final readonly classes are the recommended declaration style.

```php
final readonly class CoordinatesData extends Data
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}
}
```

## Required, nullable, and optional

```php
public string $name                 // required, cannot be null
public ?string $middleName          // required, may be null
public string $country = 'ZA'       // optional, default used only when missing
public ?string $company = null      // optional and nullable
```

Defaults never mask invalid provided values. If `country` is present with the wrong type, mapping fails rather than using `'ZA'`.

## Deterministic metadata

DTOKit reflects constructor and attribute metadata once per process, then reuses the compiled result. Mapping does not use a framework container, global configuration, or environment-dependent service lookup.
