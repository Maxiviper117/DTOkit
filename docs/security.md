# Security

DTOKit operates at trust boundaries, so safe defaults matter more than permissive convenience.

## Reject undeclared input

`InputData` rejects unknown fields by default. This reduces accidental mass assignment and makes newly supplied attacker-controlled keys visible immediately.

Use `#[IgnoreUnknown]` only for boundaries that explicitly require forward compatibility. Ignored values are never passed to the DTO constructor.

## Deliberate output contracts

Do not serialize persistence models directly. Create an output DTO containing only approved fields.

`#[Hidden]`, `#[Sensitive]`, and `#[Redact]` provide additional controls, but the DTO shape remains the primary exposure boundary.

## Sensitive diagnostics

Mapping errors record received types rather than raw values. Explain events redact fields marked `#[Sensitive]`. Avoid putting secrets in field names, exception messages from custom extensions, or class names.

Custom casts and transformers are responsible for not including raw secrets in exceptions they throw.

## Object input

Only public object properties are normalized. Private and protected state is not bypassed through reflection.

## Resource limits

Recursive mapping and serialization stop after 64 levels. This prevents malformed deeply nested payloads from causing unbounded recursion. Applications should still enforce request-size and collection-size limits before mapping.

## What DTOKit does not provide

- Authentication or authorization.
- Business-rule validation.
- HTML or SQL escaping.
- Request body size limits.
- Rate limiting.
- Encryption or secret storage.

Treat DTOKit as one boundary-control layer, not a complete application security system.
