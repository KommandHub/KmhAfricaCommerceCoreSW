# African Commerce Core for Shopware 6

Foundational localization, geography, and address infrastructure for Shopware 6 across African markets: ISO-standard reference-data seeding, a Sales-Channel-aware country-configuration resolver, and additive, standards-anchored address capabilities. A framework-agnostic domain core with a thin Shopware adapter in one plugin; each capability can be toggled on or off.

Infrastructure, not a storefront feature: it configures and extends what Shopware
already does, it does not add payments, shipping, or checkout logic.

[![Shopware](https://img.shields.io/badge/Shopware-~6.6.1%20%7C%7C%20~6.7.0-189eff)](https://www.shopware.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache-2.0-blue)](LICENSE)

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Features](#features)
- [Local development](#local-development)
- [Makefile commands](#makefile-commands)
- [Testing](#testing)
- [Code quality](#code-quality)
- [CI/CD](#cicd)
- [Release process](#release-process)
- [Logging and debugging](#logging-and-debugging)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Requirements

| | |
| --- | --- |
| Shopware | `~6.6.1 || ~6.7.0` |
| PHP | 8.2 or newer |
| Database | MySQL 8.0+ / MariaDB 10.11+ |

## Installation

### Via Composer (recommended)

```bash
composer require kommandhub/africa-commerce-core-sw
bin/console plugin:refresh
bin/console plugin:install --activate KmhAfricaCommerceCoreSW
bin/console cache:clear
```

### Manual upload

Download the release zip and install it under **Extensions > My extensions >
Upload extension**, then activate it.

## Configuration

**Settings > Extensions > African Commerce Core for Shopware 6**

Every setting is sales-channel scoped: use the sales-channel selector at the top
of the configuration page to override a value for one channel only.

The **Features** card turns each capability on or off, per sales channel:

| Feature | Default | What it gates |
| --- | --- | --- |
| Reference data | on | the `africa:reference:import` command (countries, subdivisions, currency precision) |
| Administrative hierarchy | off | division tree below `country_state`, seeded and exposed via a provider |
| Address extensions | off | additive nullable address fields on `customer_address` |
| Phone normalization | off | libphonenumber E.164 normalization (warn, don't block) |

Every toggle gates real runtime behaviour. **Currency formatting is not a toggle**:
Shopware core already ICU-formats currencies with the decimals the reference import
corrects (XOF/XAF → 0), so the value ships under *Reference data*.
`IntlCurrencyFormatter` remains as a published pure utility service for programmatic
formatting.

## Architecture

Two layers in one plugin:

- **`src/Domain/`** — the framework-agnostic core. Pure value objects, enums,
  interfaces, the precedence resolver, the reconciler, and the versioned dataset.
  It imports **no Shopware** and is unit-testable without a kernel. It is
  excluded from the DI autowire glob (see `services.yml`), so its
  scalar-constructor value objects are never treated as services; the few Domain
  services adapters need are registered explicitly.
- **`src/<Module>/`** — the thin Shopware adapter (`CountryConfiguration/`,
  `Geo/`, `Feature/`). Reads SystemConfig, drives the DAL, exposes the CLI
  command. Adapters depend on Domain, never the reverse.

Every country-varying rule is **data + configuration**, resolved
`global → per-country → per-Sales-Channel` through one `ConfigResolverInterface`.
There is no `match($iso)` / `switch($country)` anywhere — Africa is not a country.

| Path | Responsibility |
| --- | --- |
| `src/KmhAfricaCommerceCoreSW.php` | plugin bootstrap and lifecycle hooks |
| `src/Domain/CountryConfiguration/` | rule contract + pure precedence resolver |
| `src/Domain/Geo/` | reconciler + versioned ISO reference dataset |
| `src/Domain/AdministrativeHierarchy/` | division records + pure tree builder + provider contract |
| `src/Domain/CurrencyFormatting/` | `CurrencyFormatterInterface` + `IntlCurrencyFormatter` (ext-intl) |
| `src/Domain/Phone/` | `PhoneNormalizerInterface` + `LibPhoneNumberNormalizer` |
| `src/Domain/Address/` | address value object + validation seam (interface) |
| `src/AdministrativeHierarchy/` | division DAL entity + `DalDivisionProvider` |
| `src/Address/` | `CustomerAddressExtension` (additive DAL fields) |
| `src/CountryConfiguration/`, `src/Geo/`, `src/Feature/` | Shopware adapters |
| `src/Setting/Service/Config.php` | typed, sales-channel-aware settings reader |
| `src/Logging/ConfigurableLogger.php` | PSR-3 wrapper gated on the debug settings |
| `tests/Unit`, `tests/Integration` | mirror `src/` |

## Features

### Reference-data import

```bash
bin/console africa:reference:import
```

Reconciles a versioned, provenance-tracked dataset (`Domain/Geo/AfricanReferenceData`)
against the DAL: activates African countries, seeds ISO 3166-2 first-tier
subdivisions into `country_state`, and corrects ISO 4217 currency precision
(notably XOF/XAF to 0 decimals). Upsert is keyed by ISO code, so it is
**idempotent** — a second run creates and updates nothing.

Currencies are **never created**: a currency needs a real exchange factor, which
only the merchant knows. A missing one is reported in the *Missing* column —
create it under *Settings > Currencies*, then re-run the import to fix its
precision.

No-ops with a notice when the *Reference data* feature is turned off (the CLI and
the admin button alike). The admin button requires the `system.plugin_maintain`
privilege.

### Merchant override

`kommandhub_africacommercecore_fieldset` installs a boolean
`kmh_af_merchant_override` on the `country`, `currency`, and `country_state`
entities. Set it on a record the import must not touch — the reconciler treats
it as merchant-owned and skips it, so hand-tuned data is never clobbered.

Keys are global across the installation and live in
`Util/AfricaCommerceCoreConstants` and nowhere else. The installer is idempotent;
uninstall removes the set — and drops the plugin's `kmh_af_*` tables — only when
the merchant did not choose to keep data.

### Administrative-division hierarchy

An optional, self-referencing tree **below** core's `country_state` — arbitrary
depth per country (Nigeria LGAs, Kenya sub-counties, Rwanda cells). Additive
tables (`kmh_af_administrative_division` + translation), never a change to core.

Enable the *Administrative hierarchy* feature, then `africa:reference:import`
seeds the shipped divisions (a Lagos sample: LGAs with wards beneath, proving the
tree nests). Read it back through `DivisionProviderInterface` — the published
extension seam — which returns a nested `DivisionNode` tree assembled by the pure
`DivisionTreeBuilder`. Ship your own dataset or provider without patching the
foundation.

### Store-API (published extension points)

JSON endpoints a storefront (or any client) can call. Both are sales-channel
aware and gated by their feature toggle.

| Method & path | Feature | Returns |
| --- | --- | --- |
| `GET /store-api/kmh-af/divisions/{countryIso}` | Administrative hierarchy | the nested division tree (`code`, `name`, `parentCode`, `children`) + `maxDepth`; empty when the feature is off |
| `POST /store-api/kmh-af/phone/normalize` | Phone normalization | `{ e164, national, international, valid, warning }` for a `number` + `countryIso`; 200 even when invalid (warn, don't block); 404 when the feature is off |

### PHP extension points

Services other plugins can inject (all registered under their interface):

| Service | Purpose |
| --- | --- |
| `ConfigResolverInterface` | resolve a `RuleKey` `global → per-country → per-Sales-Channel` |
| `AddressValidatorInterface` | advisory address warnings; the default warns when a country expects divisions (`divisionDepth > 0`) and none is picked. Re-alias it to add your own. Surfacing the warnings is up to the consumer. |
| `DivisionProviderInterface` | the division tree for a country or state, with names in an optional language (system language as fallback) |
| `PhoneNormalizerInterface` | E.164 normalization |
| `CurrencyFormatterInterface` | ICU currency formatting |

Rule values: the global default is the *Country rules* card (Sales-Channel
scoped like any setting); per-country values are plain system-config keys:

```bash
bin/console system:config:set KmhAfricaCommerceCoreSW.country.NG.divisionDepth 2
```

### Address flags (no custom validator)

Representative per-country address policies are applied to core's own `country`
flags (`postalCodeRequired`, `displayStateInRegistration`, …) as data — e.g.
Ghana relaxes the postal code, Nigeria shows states. The storefront honours these
core flags natively; the plugin adds no postal-code validator and no per-country
branch.

### Storefront

- `src/Resources/views/storefront/` — Twig overrides. **Namespace every block**
  you add (`{% block kommandhub_africacommercecore_foo %}`) so it cannot collide
  with another plugin extending the same template.
- `src/Resources/snippet/<locale>/` — storefront translations; every locale file
  must carry the same key tree.

Built output under `src/Resources/app/storefront/dist/` is generated.


## Local development

The plugin is developed inside a Docker stack that runs a full Shopware install
with this directory mounted at `custom/static-plugins/KmhAfricaCommerceCoreSW`.

```bash
git clone https://github.com/Kommandhub/KmhAfricaCommerceCoreSW.git
cd KmhAfricaCommerceCoreSW

make up     # build the image, start Shopware, install dependencies
make shell  # bash into the container

# inside the container
bin/console plugin:refresh
bin/console plugin:install --activate KmhAfricaCommerceCoreSW
```

Storefront: <http://localhost> · Administration: <http://localhost/admin>
(`admin` / `shopware`).

## Makefile commands

| Command | What it does |
| --- | --- |
| `make up` / `make down` | start / tear down the stack |
| `make restart` | `down` then `up` |
| `make shell` | shell into the container |
| `make test` | PHPUnit; filter with `make test FILTER=SomeTest` |
| `make test-coverage` | coverage text report |
| `make analyse` | PHPStan on `src/` |
| `make cs` / `make cs-fix` | php-cs-fixer dry-run / apply |
| `make validate-plugin` | shopware-cli store-compliance check |
| `make changelog` | render `CHANGELOG.md` as the Store will |
| `make zip` | build a distributable zip into `build/` |
| `make cli ARGS="..."` | any other shopware-cli command |

## Testing

```bash
make test
make test FILTER=ConfigTest
make test-coverage
```

- `tests/Unit/` mirrors `src/`. No kernel, no database — fast, and what CI runs.
- `tests/Integration/` needs a booted Shopware kernel. Mark those tests
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.
- Add or update a test with every behaviour change.

## Code quality

```bash
make cs-fix && make analyse && make test
```

All three must pass before a commit. PHPStan runs at the level pinned in
`phpstan.dist.neon`; php-cs-fixer enforces PSR-12 plus the rules in
`.php-cs-fixer.dist.php`.

## CI/CD

`.github/workflows/php.yml` runs on every push to `main`/`develop` and on every
pull request: composer validate, PHP lint, PHPStan, php-cs-fixer (dry-run),
PHPUnit with coverage, and a coverage threshold gate.

CI runs **without a Shopware kernel**, so kernel-dependent tests are excluded
there and the plugin bootstrap is excluded from coverage in `phpunit.dist.xml`.

## Release process

1. Land everything on `develop`; make sure the local gate passes.
2. Bump `version` in `composer.json`.
3. Add a `# <version>` section at the top of `CHANGELOG.md` (and the localised
   variants). Check the rendering with `make changelog`.
4. `make validate-plugin` — must be clean for a Store submission.
5. `make zip` — the artefact lands in `build/`.
6. Merge `develop` into `main` and tag the release.

## Logging and debugging

Enable **debug logging** in the plugin configuration; entries land in
`var/log/kommandhub_africacommercecore_<env>.log` (rotating, 7 files).

`error` and above are **always** written regardless of the toggle, so production
keeps a trail of failures. Both the toggle and the level filter are
sales-channel scoped — pass the sales channel id in the log context so it
resolves against the right scope:

```php
$this->logger->info('something happened', [
    ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
]);
```

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Never open a
public issue for one, and never paste real credentials into an issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Pull requests target `develop`, commits
are signed off (`git commit -s`), and the local gate must pass.

## License

Apache-2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).
