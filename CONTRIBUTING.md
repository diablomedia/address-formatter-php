# Contributing

Initialize the templates submodule with `git submodule update --init` and install
dependencies with `composer install`, then run `composer check` before
opening a pull request. This verifies the bundled templates, then runs PHPUnit, PHPStan at level 8,
PHP CS Fixer, and Rector. Describe the problem and the resulting behavior in your pull request.

Follow [PER Coding Style 3.0](https://www.php-fig.org/per/coding-style/).
Run `composer cs:fix` to format code and `composer rector:fix` to apply refactorings.
Add or update tests when changing address formatting behavior.

Changes must work on PHP 8.2–8.5, including the lowest compatible dependency
versions. To check those locally, run:

```bash
composer update --prefer-lowest --prefer-stable
composer check
```

Restore the latest dependencies with `composer update --prefer-stable`.

Address templates and YAML fixtures come directly from
[OpenCageData/address-formatting](https://github.com/OpenCageData/address-formatting),
pinned by the `address-formatter-templates` Git submodule. Runtime templates are
generated into `resources/templates`, including upstream license and commit metadata.
All installations use that bundled snapshot.

To adopt the latest upstream templates, run:

```bash
composer templates:update
```

This advances the submodule to the latest upstream commit, regenerates the snapshot
(including deletion of obsolete files), and runs `composer check`. Review changes
to the submodule pointer and snapshot, then commit them together. Check failures
leave the generated changes available for review; resolve them before committing.

To adopt a specific upstream commit, check it out in the submodule, then run
`composer templates:sync` and `composer check`. `templates:sync` copies the currently
checked-out commit without fetching; `templates:check` checks for missing, modified,
and obsolete files and verifies the recorded source commit. CI uses this read-only
check, keeping builds reproducible.

Submit changes to template data and fixtures to OpenCage upstream. Preserve its
license and attribution. See [tests/README.md](tests/README.md) for the two known
upstream fixture exceptions.
