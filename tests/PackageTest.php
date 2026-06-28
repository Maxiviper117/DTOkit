<?php

declare(strict_types=1);

use YourVendor\YourPackage\Package;

it('exposes the package name', function (): void {
    expect(Package::name())->toBe('your-package');
});
