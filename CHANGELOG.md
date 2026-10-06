# 0.1.0

Initial release.

- Reference-data import, from the plugin configuration ("Run reference-data import") or with `bin/console africa:reference:import`: activates 26 African countries, adds 443 ISO 3166-2 regions/states, and sets ISO 4217 decimal places for 18 African currencies (XOF, XAF, RWF, UGX and others use 0 decimals, TND uses 3).
- Country address policies applied as data: Ghana no longer requires a postal code, Nigeria shows the state field at registration.
- The import is safe to re-run: a second run changes nothing. Currencies that are not installed are listed as "Missing" and never created, because a currency needs your real exchange rate; create them under Settings > Currencies, then run the import again.
- "Merchant override" field on countries, currencies, states and divisions: tick it and the import never changes that record.
- Optional local divisions below the state (for example Nigerian LGAs and wards), with a cascading "Local division" dropdown in the storefront address forms. Ships a Lagos sample.
- Optional extra address fields in registration and account address forms: nearest landmark, area/neighbourhood, directions, digital address code and local division. Saved values are kept when an address is edited by a client that does not send them.
- Optional live phone check in the address forms: the number is shown in international format, or a warning appears if it does not look valid. It never blocks the order.
- Store API endpoints for headless storefronts: `GET /store-api/kmh-af/divisions/{countryIso}` and `POST /store-api/kmh-af/phone/normalize`.
- Each capability has its own on/off switch in the plugin configuration, per sales channel.
- "Country rules" setting for the expected division depth, which the address validator uses to suggest picking a division.
- Optional debug logging, per sales channel.
- Uninstalling without keeping data removes the plugin's own tables and fields; countries, states and currencies stay, so existing orders and addresses are unaffected.
- Storefront in English, German and French; administration in English and German. Compatible with Shopware 6.6 and 6.7.

<!--
Format notes (this file is rendered by the Shopware Store):

- One `# <version>` heading per release, newest first, separated by `---`.
- Bullets describe user-visible behaviour, not commits. "XOF and XAF now use 0
  decimal places", not "refactor ReferenceImporter".
- `make changelog` renders this exactly as the Store will display it.
- Localised variants live in CHANGELOG_de-DE.md / CHANGELOG_fr-FR.md and must
  carry the same version headings.
-->
