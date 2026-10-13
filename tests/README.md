# Template fixture coverage

`TestCasesTest` runs the country and other YAML fixtures from the templates
submodule against the bundled runtime snapshot. `composer templates:check` verifies
that the snapshot matches the pinned submodule. The test suite asserts
that the fixture directory and parsed fixture collection are nonempty.

Two expectations in the templates repository currently disagree with its
configuration at commit `cbf23487c13ad437cadab0083ef79fe5f1d1823e`:

- `countries/cn.yaml`, Nyingchi: `district` aliases to `neighbourhood`, which the
  China template renders as `Bayi District`. The fixture omits that line.
- `countries/ir.yaml`, “district should be treated as alias of state_district”:
  `district` actually aliases to `neighbourhood`, and the template renders
  `province` before `state_district`. The result includes the district in the
  fallback attention line and `Tehran Province` after `Tehran`.

`TestCasesTest::EXPECTATION_OVERRIDES` records the current output for these exact
file/expectation pairs. The tests still assert both outputs; no fixtures are
skipped. Remove the overrides when the templates or fixtures are reconciled in
[the templates repository](https://github.com/OpenCageData/address-formatting).
All other fixtures use their upstream expectations without modification.
