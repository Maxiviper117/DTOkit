# DTOKit

[![Tests](https://github.com/Maxiviper117/DTOkit/actions/workflows/tests.yml/badge.svg)](https://github.com/Maxiviper117/DTOkit/actions/workflows/tests.yml)
[![PHPStan](https://github.com/Maxiviper117/DTOkit/actions/workflows/phpstan.yml/badge.svg)](https://github.com/Maxiviper117/DTOkit/actions/workflows/phpstan.yml)
[![Latest Version](https://img.shields.io/packagist/v/maxiviper117/dtokit-core.svg)](https://packagist.org/packages/maxiviper117/dtokit-core)
[![PHP Version](https://img.shields.io/packagist/php-v/maxiviper117/dtokit-core.svg)](https://packagist.org/packages/maxiviper117/dtokit-core)
[![License](https://img.shields.io/github/license/Maxiviper117/DTOkit.svg)](LICENSE.md)

DTOKit provides framework-agnostic typed data boundaries for PHP 8.4+. It maps arrays and objects into immutable data objects, serializes them safely, and explains mapping decisions and failures.

## Installation

```bash
composer require maxiviper117/dtokit-core
```

## Basic usage

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
    'title' => 'Hello',
    'body' => 'World',
]);

$payload = $post->toArray();
```

`InputData` rejects unknown fields by default. `Data` and `OutputData` ignore them. Use `#[Strict]` or `#[IgnoreUnknown]` to override this per class.

## Mapping and serialization

- Supported sources: associative arrays, `stdClass`, and public properties of plain objects.
- Supported values: typed scalars, nullable/defaulted fields, backed enums, `DateTimeInterface`, nested DTOKit objects, and lists declared with `#[ListOf]`.
- `#[MapInputName]` and `#[MapOutputName]` define external names.
- `#[WithCast]` and `#[WithTransformer]` invoke container-free extension classes.
- `#[Hidden]`, `#[Sensitive]`, and `#[Redact]` control safe output.
- Nullable accepts explicit `null`; only a constructor default makes a missing field optional.

```php
use DTOKit\Attribute\ListOf;
use DTOKit\Attribute\MapInputName;

final readonly class OrderData extends InputData
{
    /** @param list<LineData> $lines */
    public function __construct(
        #[MapInputName('order_id')] public int $orderId,
        #[ListOf(LineData::class)] public array $lines,
    ) {}
}
```

## Non-throwing and explain APIs

`Data::tryFrom()` returns a `MappingResult`. `Data::explain()` returns an `ExplainResult` with `toArray()`, `toJson()`, and `toText()` renderers. Diagnostics contain paths and types but never raw sensitive values.

## Development

```bash
composer install
composer check
```

See [PRD.md](PRD.md) for product requirements, [Phases.md](Phases.md) for delivery status, and [AGENTS.md](AGENTS.md) for repository rules.
