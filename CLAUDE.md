# KmhAfricaCommerceCoreSW

Foundational localization, geography, and address infrastructure for Shopware 6 across African markets: ISO-standard reference-data seeding, a Sales-Channel-aware country-configuration resolver, and additive, standards-anchored address capabilities. A framework-agnostic domain core plus a thin Shopware adapter, in one plugin, with each capability toggleable.

PHP namespace root: `Kommandhub\AfricaCommerceCore\` → `src/`.

## Commands

All commands run inside the Docker dev stack (see `Makefile`). The plugin lives
at `custom/static-plugins/KmhAfricaCommerceCoreSW` inside a Shopware install.

- `make up` / `make down` — start / tear down the stack
- `make test` — PHPUnit (`phpunit.dist.xml`). Filter: `make test FILTER=SomeTest`
- `make test-coverage` — coverage text report
- `make analyse` — PHPStan (`phpstan.dist.neon`, level in that file), `src` only
- `make cs` / `make cs-fix` — php-cs-fixer dry-run / apply
- `make validate-plugin` — shopware-cli store-compliance check
- `make shell` — bash into the app container

Run `make cs-fix && make analyse && make test` before committing.

## Architecture

**Two layers in one plugin:**

- `src/Domain/` — the framework-agnostic core (former `kommandhub/africa-commerce-core`
  library, now internal). Pure value objects, enums, interfaces, the precedence
  resolver, the reconciler, and the versioned dataset. **Imports no Shopware**
  (grep gate: `grep -rE '^use +Shopware\\' src/Domain` must be empty) and is
  unit-testable without a kernel. It is **excluded from the DI autowire glob**
  (see `services.yml` `exclude:`) so its scalar-constructor value objects are
  never treated as services; the few Domain services adapters need (`Reconciler`,
  `PrecedenceConfigResolver`, `AfricanReferenceData`) are registered explicitly.
- `src/<Module>/` (e.g. `CountryConfiguration/`, `Geo/`, `Feature/`) — the thin
  Shopware adapter: reads SystemConfig, drives the DAL, exposes the CLI command.
  Adapters depend on Domain, never the reverse.

**Feature-first modules** otherwise, following Shopware's own plugin layout. One
obvious home per class; reach across modules through a service.

**Everything data, no country branches.** Country-varying rules are data +
config, resolved `global → per-country → per-Sales-Channel` through the one
`ConfigResolverInterface`. No `match($iso)` / `switch($country)` anywhere.

**Feature toggles.** Four capabilities (reference data, administrative hierarchy,
address extensions, phone normalization) are bools in the config.xml *Features*
card, read Sales-Channel-aware through `Feature/FeatureGate` (keyed by the
`Domain/Feature/Feature` enum). A subsystem checks its gate before acting — see
`Geo/ReferenceImportCommand` and the divisions section of `Geo/ReferenceImporter`.
The **administrative hierarchy** is implemented (self-referencing
`kmh_af_administrative_division` entity, pure `DivisionTreeBuilder`,
`DalDivisionProvider`, gated seed). Phone normalization (`LibPhoneNumberNormalizer`,
giggsey/libphonenumber) and additive address fields (a `kmh_af_customer_address_data`
1:1 aggregate + `CustomerAddressExtension`) are implemented too, and the storefront
address form renders + persists them.

**Currency formatting is NOT a toggle.** Shopware core already ICU-formats
currencies with the decimals the reference import corrects, so there is no feature
gate. `Domain/CurrencyFormatting/IntlCurrencyFormatter` (ext-intl) stays as a
published pure utility service for programmatic formatting — do not re-add a
`currencyFormattingEnabled` flag.

**Decoration seams.** The design targets ≤2 (address validation, formatting). A
**third, deliberate** seam exists by explicit decision: core's address routes map
a fixed field whitelist and ignore extra databag fields, so the additive fields
(which live in the `kmh_af_customer_address_data` aggregate) are written by two
decorators — `KmhUpsertAddressRoute` (account address add/edit) and
`KmhRegisterRoute` (registration billing/shipping). Both run the core save
untouched, then write the aggregate via the shared `AddressDataWriter`, gated by
the address feature. Edit-form pre-fill loads the aggregate through
`AddressPageSubscriber` on `AddressDetailPageLoadedEvent`.

**Gotcha:** `plugin:activate`/reinstall re-applies config.xml defaults, which turns
the feature flags back off. Re-enable with `system:config:set --json
KmhAfricaCommerceCoreSW.config.<feature>Enabled true` after any reactivation, or
the gated decorators/commands silently no-op.

**Admin** (`Resources/app/administration/`): the plugin was scaffolded without the
admin feature, so this app tree was added by hand. `config.xml` renders a custom
`<component name="kmh-af-reference-import">` (a "Run reference-data import" button +
result table) that calls the admin action `POST /api/_action/kmh-af/reference-import`
(`Administration/Controller/ReferenceImportController`, runs the same
`ReferenceImporter`). Non-technical merchants seed data from the config page, no CLI.
Rebuild admin JS with `bin/build-administration.sh` after changes.

**Storefront JS** (`Resources/app/storefront/src/kmh-address-form/`): the
`KmhAddressForm` plugin (registered on `[data-kmh-af-address]`) drives a cascading
division dropdown (fetches `/kmh-af/divisions/state/{id}` on state change) and live
phone feedback (posts to `/kmh-af/phone/normalize` on blur; the controller resolves
the ISO from the form's countryId). Both are storefront controllers next to the
store-API routes. Rebuild JS with `bin/build-storefront.sh` after changes.

Edit-form pre-fill is complete: `AddressPageSubscriber` loads the aggregate with
its `division` association, the Twig emits `data-selected-division`, and the JS
pre-selects it once the cascade loads. Because core fills the saved state
*asynchronously* (programmatically, no change event), the JS also runs a short
bounded poll (`_watchStateValue`) so the cascade fires on edit.

The address-validation seam ships a default: `Domain/Address/DefaultAddressValidator`
(advisory, config-driven via `RuleKey::DivisionDepth`; warn-not-block), aliased to
`AddressValidatorInterface` — override the alias to add warnings. Surfacing those
warnings in the storefront UI is left to consumers.

Cross-cutting, always present:

- `Setting/Service/Config.php` — typed reader over `SystemConfigService`, always
  sales-channel aware.
- `Logging/ConfigurableLogger.php` — PSR-3 wrapper gating output on the
  `enableDebugging` / `logLevels` settings, per sales channel. `error` and above
  are always written.
- `Exception/` — one plugin-scoped exception base.
- `Resources/config/` — `services.yml`, `routes.yml`, `config.xml`, `packages/`.

## Conventions & gotchas

- **DI is autowired** via the `../../*` glob in `services.yml`. Symfony does NOT
  auto-alias an interface to its single implementation — when you add a new
  `*Interface` that is constructor-injected, add an explicit `alias:` entry.
- **Never alias `Psr\Log\LoggerInterface` container-wide.** `services.yml` uses a
  scoped `bind:` so only this plugin's services get the ConfigurableLogger;
  a global alias would hijack Shopware core and every sibling plugin.
- **Config keys are read through `Config`**, never `SystemConfigService`
  directly, and always with the sales-channel id in hand.
- **Reference data is versioned + provenance-tracked + upsert-by-ISO.** Re-running
  `africa:reference:import` converges (0 created / 0 updated the second time) and
  never clobbers a record a merchant marked with the `kmh_af_merchant_override`
  custom field. New reference records go in `Domain/Geo/AfricanReferenceData`, not
  inline SQL.
- Keep a change inside its layer/module; reach across through a service, not by
  deep-linking another module's internals. Adapter code may depend on Domain;
  Domain must never depend on an adapter or on Shopware.
- Built assets in `Resources/public/` and `Resources/app/*/dist/` are generated —
  never hand-edit.
- Tests mirror `src/` under `tests/Unit/` (+ `tests/Integration/`). Add a test
  with each behaviour change. Tests needing a booted kernel carry
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.

## Shopware traps that fail silently

Each of these cost real debugging time. They share a trait: the failure gives no
error at the point of the mistake.

- **A DAL repository is autowired by argument name only.** For entity
  `<vendor>_thing`, Shopware injects `$<vendor>ThingRepository` — any other name
  fails to autowire with an opaque "no such service" pointing at
  `EntityRepository`. Either name the argument that way, or map a readable name
  once in the `_defaults` `bind:` (e.g.
  `EntityRepository $thingRepository: '@<vendor>_thing.repository'`).
- **DAL tables and entity names must carry a vendor prefix.** The DAL table
  namespace is global and shared with every other plugin, so `sms_template` is a
  collision waiting to happen — use `<vendor>_sms_template`. The translation's
  foreign-key *property* is derived from the parent entity name
  (`<vendor>SmsTemplateId`), so it is part of the name, not decoration to trim.
- **`when@test` is ignored in a plugin's `services.yml`.** Shopware loads plugin
  service files with a null-environment loader, so env-conditional blocks never
  fire. To expose a private service to an integration test, mark it
  `public: true` outright, or boot a plugin-aware kernel inside the test
  (`KernelFactory` + `DbalKernelPluginLoader`) rather than `KernelTestBehaviour`,
  whose shared test kernel loads no plugins at all.
- **A `flow.action` tag needs its `key` attribute.** `FlowExecutor` indexes
  tagged actions by `key` (`tagged_iterator index-by="key"`); a sequence whose
  action name is missing from that index is skipped with no log line — the flow
  "runs" and nothing happens. The `key` must equal the action's `getName()`.
- **vue-i18n treats `{ … }` as interpolation.** A literal `{{ order.number }}`
  in an admin snippet — the obvious way to document a Twig placeholder — makes
  the i18n compiler throw, which removes the *entire surrounding element* from
  the DOM (a whole card can vanish). Escape as `{'{{'} order.number {'}}'}`.
- **`sw-textarea-field` is a deprecated wrapper whose two-way binding does not
  round-trip.** `v-model:value` on it silently never writes back to the entity.
  Bind core's `mt-textarea` with a plain `v-model` instead — that is what
  Shopware's own modules do.
- **`beStrictAboutCoverageMetadata` voids a test's whole coverage** when it
  executes a class outside its `#[CoversClass]`. A test that constructs a
  collaborator value object it does not cover must declare it with `#[UsesClass]`,
  or its real coverage silently reads as zero.

## Store compliance (`make validate-plugin`)

Run it before any release; it runs ESLint, Stylelint and PHPStan with
Shopware's own rules. Recurring findings a first release trips on:

- `composer.json` descriptions must be **150–185 characters** (en and de).
- CSS: `overflow-wrap: break-word`, never the deprecated `word-break: break-word`.
- Storefront JS: `const Plugin = window.PluginBaseClass`, never
  `import … from 'src/plugin-system/plugin.class'`.
- Admin JS: do not pass `snippets` to `Module.register` — snippets auto-load
  from a `snippet/` folder next to the module.
- DAL: `new Criteria([$id])`, never `EqualsFilter('id', $id)`.
- `StringTemplateRenderer` is `@internal` but is the only sandboxed Twig-string
  renderer Shopware exposes (core's own `MailService` depends on it the same
  way). If you use it, add a scoped PHPStan ignore with that reason rather than
  fighting it.
