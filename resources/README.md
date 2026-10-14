# Bundled address templates

`templates/` is generated from the `conf/` directory of the
[OpenCageData/address-formatting](https://github.com/OpenCageData/address-formatting)
Git submodule at `address-formatter-templates`. `templates/UPSTREAM.json` records
the source repository and exact commit. The upstream MIT license is preserved in
`templates/LICENSE.txt`. YAML files are copied byte for byte without modification.

The formatter always uses this snapshot. Committing it ensures GitHub source
archives and Composer installations contain the templates, even though Git
submodule contents are not included in those archives.

Run `composer templates:sync` to regenerate from the currently checked-out submodule
commit, or `composer templates:update` to fetch the latest upstream commit,
regenerate, and run the quality checks. Commit the snapshot and submodule pointer
together. `composer templates:check` verifies an exact match, including provenance,
and runs in CI. See [CONTRIBUTING.md](../CONTRIBUTING.md) for the update workflow.
