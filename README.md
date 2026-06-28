# Your Package

[![Tests](https://github.com/your-vendor/your-package/actions/workflows/tests.yml/badge.svg)](https://github.com/your-vendor/your-package/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/your-vendor/your-package.svg)](https://packagist.org/packages/your-vendor/your-package)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

A short, outcome-focused description of the package.

## Creating a package from this template

1. Click **Use this template** on GitHub and clone the new repository.
2. Replace every placeholder listed below (matching case matters).
3. Run `composer update`, then `composer check`.
4. Replace the sample `Package` class and test with your implementation.
5. Enable **Allow auto-merge** if you add Dependabot, and enable GitHub Actions.

GitHub Actions jobs are intentionally skipped while this repository is named
`template-php-package`. They activate automatically in repositories created
from the template with a different name.

| Placeholder | Example |
| --- | --- |
| `your-vendor` | `maxiviper117` |
| `your-package` | `result-flow` |
| `YourVendor` | `Maxiviper117` |
| `YourPackage` | `ResultFlow` |
| `Your Name` | `David Example` |

Search before publishing: `git grep -n -E 'your-vendor|your-package|YourVendor|YourPackage|Your Name'`.

## Installation

```bash
composer require your-vendor/your-package
```

## Usage

```php
use YourVendor\YourPackage\Package;

echo Package::name();
```

## Development

```bash
composer install
composer check
```

### Documentation

The documentation site uses VitePress and pnpm:

```bash
pnpm install
pnpm docs:dev
```

Run `pnpm docs:build` before publishing documentation changes. The included
GitHub Pages workflow deploys `docs/` after pushes to `main`. In the GitHub
repository settings, set **Pages → Build and deployment → Source** to
**GitHub Actions**.

## License

MIT. See [LICENSE.md](LICENSE.md).
