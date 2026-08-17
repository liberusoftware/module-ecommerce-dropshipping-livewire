# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this package follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-17

### Added

- `dropshipping::order-despatch` — what has been despatched of the shopper's own
  order, the date it was promised, whether a complete despatch met that promise,
  the per-line quantities where part of a purchase has gone, and the carrier
  references the supplier reported. Three `#[Locked]` mount arguments and no
  writable state.
- `Support\Despatch` — the domain's seven purchase states in shopper words.
  `PartiallyShipped` and `Shipped` are never the same sentence; `Draft` and
  `Transmitting` always are.
- `Support\Failures` — every concrete domain exception mapped to a sentence a
  shopper may read, asserted complete against the installed domain package.

### Deliberately not shipped

- **Supplier attribution.** No component names the supplier that fulfils a line.
  Publishing it publishes the merchant's supply chain.
- **Anything derived from cost.** `PurchaseView` carries both costs and
  `costVariance()`; nothing here reads them and the names do not appear in the
  package. A boundary case and a feature case both enforce it.
- **A despatch estimate on a product page.** `SupplyOffersFor` makes it one query
  away. It is not a fact about the shopper's own order, and it is the surface that
  invites a supplier-choosing control beside it.
- **A shopper-facing subject-access surface.** The only person-scoped query the
  domain publishes is the GDPR export, which carries both costs, the supplier
  reference and the supplier SKU. That needs a redacted view the domain does not
  have; the gap is reported rather than papered over by filtering fields on a
  page.
- **A `resubmittable` failure flag.** The reference Livewire package ships one.
  Nothing here is submitted, so it would be false in every reachable state, and a
  locked property that is false in every reachable state is a lying constraint.

### Notes

- The provider binds nothing, registers no route and schedules nothing. Both of
  the domain's seams stay unbound; this surface renders facts already recorded and
  needs neither.
- Every refusal is one refusal. An order this merchant holds nothing for, a
  destination belonging to somebody else, an erased destination and an order with
  nothing drop-shipped all produce the same sentence and the same markup.
- `null` is never rendered as `false` or as zero. A promise nobody made, a
  timeliness nothing can be measured against and a carrier nobody named each get
  their own words.
- Two domain queries are needed for one page: `PurchaseStanding` publishes no
  carrier or tracking reference, and `ExportDestinationRecord` publishes no fold.
  Both are reported as gaps rather than closed here.
