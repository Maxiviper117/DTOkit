# Errors and Explain Mode

## Exception hierarchy

All package exceptions derive from `DTOKitException`.

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

Mapping exceptions expose safe `context` with the DTO class, nested path, pipeline stage, and expected or received types where applicable.

## Throwing mapping

Use `from()` when invalid input should stop the current operation.

```php
try {
    $data = CustomerData::from($payload);
} catch (TypeMismatchException $error) {
    echo $error->context['path'];
}
```

## Result-based mapping

Use `tryFrom()` when failure is expected control flow.

```php
$result = CustomerData::tryFrom($payload);

if ($result->succeeded()) {
    $customer = $result->data;
} else {
    $error = $result->error;
}
```

## Explain mode

`explain()` records the mapping pipeline without leaking values marked sensitive.

```php
$explanation = CustomerData::explain($payload);

$explanation->toArray();
$explanation->toJson(JSON_PRETTY_PRINT);
$explanation->toText();
```

Events cover source normalization, matched keys, defaults, custom casts, unknown fields, and final success or failure. Failed mapping is returned in `error`; it is not thrown by `explain()`.

Explain output is designed for debugging and assertions. It is not a validation-message localization system.

## Reading error context

```php
try {
    CustomerData::from($payload);
} catch (MappingException $error) {
    $error->context;
}
```

A context payload may contain:

| Key | Meaning |
| --- | --- |
| `class` | DTO being mapped when the failure occurred. |
| `path` | Dot-separated location in the original payload. |
| `stage` | Mapping stage such as `type`, `cast`, or `constructor`. |
| `expected` | Expected runtime type when applicable. |
| `received` | Safe received type, never the raw value. |
| `fields` | Unknown field paths for strict-input failures. |

## Common failures

### Missing is not nullable

If a nullable parameter has no default, callers must still provide the key. Add a constructor default only when omission is part of the contract.

### Unknown field rejected

Check for a typo first. Use `#[IgnoreUnknown]` only when the boundary intentionally accepts forward-compatible extra fields.

### Ambiguous union

DTOKit does not guess between non-null union members. Add a custom cast that implements a documented selection rule, or replace the union with a dedicated value type.

### Constructor failure

Constructor exceptions are wrapped as mapping failures. DTO constructors should remain side-effect free and should not contain business validation or service calls.
