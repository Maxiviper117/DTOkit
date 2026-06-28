# Attributes

Attributes may be placed on promoted constructor parameters. PHP also exposes promoted attributes on their corresponding properties, and DTOKit resolves them once during metadata compilation.

## Naming

### `MapInputName`

Selects the external key used during mapping.

```php
#[MapInputName('user_id')]
public int $userId
```

### `MapOutputName`

Selects the external key used during serialization.

```php
#[MapOutputName('user_id')]
public int $userId
```

Input and output names are independent.

## Type behavior

### `ListOf`

Declares the item type for a runtime array list. Items may be supported scalar, enum, date, or data-object types.

### `WithCast`

References a zero-argument class implementing `Cast`. It runs before normal type conversion.

### `WithTransformer`

References a zero-argument class implementing `Transformer`. It runs before recursive output serialization.

## Input policy

### `Strict`

Rejects keys that do not map to constructor parameters.

### `IgnoreUnknown`

Ignores undeclared keys. Explain mode records them as ignored.

## Output safety

### `Hidden`

Omits the property and its key.

### `Sensitive`

Emits `[redacted]` and prevents raw values from entering explain events.

### `Redact`

Emits a configured replacement, defaulting to `[redacted]`.

## Resolution order

DTOKit resolves class policy, names, list metadata, casts, type conversion, output visibility, and transformers in a deterministic order. Duplicate or conflicting metadata fails during compilation instead of silently choosing a winner.
