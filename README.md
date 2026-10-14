# Address Formatter for PHP

[![GitHub Actions](https://github.com/diablomedia/address-formatter-php/actions/workflows/ci.yaml/badge.svg)](https://github.com/diablomedia/address-formatter-php/actions/workflows/ci.yaml)
[![Latest Stable Version](https://poser.pugx.org/diablomedia/address-formatter-php/v/stable.png)](https://packagist.org/packages/diablomedia/address-formatter-php)

This is the Diablo Media fork of [Calendify/address-formatter-php](https://github.com/Calendify/address-formatter-php),
originally developed by [PredictHQ](https://github.com/predicthq/address-formatter-php).
It is maintained as `diablomedia/address-formatter-php` and retains the
`PredictHQ\AddressFormatter` PHP namespace for compatibility.

Requires PHP 8.2–8.5 and supports Symfony YAML 6.4, 7.x, and 8.x.

This library uses the address templates from <https://github.com/OpenCageData/address-formatting> to format addresses
differently depending on the country.

### Installation

Install the latest version with

```bash
composer require diablomedia/address-formatter-php
```

This package includes a generated snapshot of the templates from
[OpenCageData/address-formatting](https://github.com/OpenCageData/address-formatting).
The formatter uses that snapshot in both development and Composer installations;
applications do not need a separate templates package or a Git submodule.
See [template provenance and update instructions](resources/README.md).

When migrating, remove the old direct dependency on `predicthq/address-formatter`
and require this package instead. Existing `use PredictHQ\AddressFormatter\...`
imports continue to work. This package declares that it replaces the original
Composer package. PHP versions below 8.2 and Symfony YAML versions below 6.4 are
no longer supported.

### Examples

You can use either the `Address` object or provide an array of address parts.

```php
use PredictHQ\AddressFormatter\Address;

$a = new Address();
$a->setCity('Wellington')
  ->setCountry('New Zealand')
  ->setCountryCode('NZ')
  ->setCounty('Wellington City')
  ->setHouseNumber(53)
  ->setPostcode(6011)
  ->setRoad('Pirie Street')
  ->setState('Wellington')
  ->setSuburb('Mount Victoria');

$text = $a->format();

/**
 * Will display as:
 *
 * 53 Pirie Street
 * Mount Victoria
 * Wellington 6011
 * New Zealand
 */
echo $text;
```

Or, pass an array of address parts to the `Formatter`.

```php
use PredictHQ\AddressFormatter\Formatter;

$address = [
  'city' => 'Wellington',
  'country' => 'New Zealand',
  'country_code' => 'NZ',
  'county' => 'Wellington City',
  'house_number' => 53,
  'postcode' => 6011,
  'road' => 'Pirie Street',
  'state' => 'Wellington',
  'suburb' => 'Mount Victoria',
];

/**
 * Will display as:
 *
 * 53 Pirie Street
 * Mount Victoria
 * Wellington 6011
 * New Zealand
 */
$f = new Formatter();
$actual = $f->formatArray($address);
```

### Development

```bash
git submodule update --init
composer install
composer check
```

`composer check` verifies that the bundled templates match the pinned upstream
submodule, then runs PHPUnit, PHPStan at level 8, PHP CS Fixer (PER Coding Style
3.0), and a Rector dry run. Use `composer cs:fix` and `composer rector:fix` to apply
formatting and refactorings.

Use `composer templates:update` to fetch the latest upstream templates, regenerate
the bundled snapshot, and run all checks. Review and commit the submodule pointer
and generated files together.

The country fixture suite uses the upstream templates repository, with two documented
[fixture expectation exceptions](tests/README.md).

GitHub Actions tests PHP 8.2–8.5 with the highest and lowest compatible dependency
versions and generates coverage. Dependabot checks Composer dependencies and
GitHub Actions weekly. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.
