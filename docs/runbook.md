# Runbook

## "A shopper says the page shows no despatch information"

The component renders one sentence — *There is no despatch information for this
order.* — for four different situations, on purpose, so that a shopper cannot use
it to discover which references exist. An operator has to tell them apart from the
inside.

Ask, in this order:

1. **Was anything drop-shipped in that order?** `RoutePurchases` writes nothing
   when the sourcing plan sourced nothing. Check `dropship_purchases` for the
   order reference. No row is the ordinary answer, not a fault.
2. **Is the host passing the right `destinationRef`?** It must be the exact string
   the host handed `RoutePurchases`. This module never resolves it and cannot
   correct a mismatch.
3. **Has the destination been erased?** `Actions\RedactDestination` replaces the
   reference with `redacted_…`, per redaction rather than per person. The
   purchases and every figure on them survive; the page correctly shows nothing.
   This is not recoverable and is not meant to be.
4. **Is the right tenant resolved?** Another merchant holding the identical order
   and destination references reads as empty, which is the isolation working.

## "The page says nothing has been despatched, but the supplier has shipped it"

The page renders the fold, so it is showing what was recorded. Look for the
recording, not for the page.

- No `shipped` event on the purchase means no despatch was ever reported. Either
  the provider callback was never delivered, or it was delivered and rejected by
  the verification the host performs before calling `Actions\RecordSupplierReport`.
- A `shipped` event whose named line references do not match the purchase's lines
  is skipped by the domain deliberately — a provider may not add lines to a
  purchase after the fact. The event exists and no quantity moved. Compare
  `dropship_shipment_lines` against `dropship_purchase_lines`.
- With `ReportsShipments` unbound, nothing polls the supplier at all. Reconciling
  is a merchant action and this page never triggers one.

## "It says part of the order has been despatched and the shopper received all of it"

Believe the shopper and check the quantities on the page against
`dropship_shipment_lines`. The most common cause is a supplier reporting a second
despatch under a `causeRef` it had already used: the journal is idempotent on
`(tenant, purchase, kind, cause_ref)`, so the second report was correctly recorded
once and its quantities were never added.

That is the index doing its job, and the remedy is a corrected report from the
supplier under a fresh cause reference — never an edit. Purchase events are
append-only and enforced by the model.

## "It says the despatch was late and the merchant disagrees"

The promise is the `promised_by` on the most recent event that carried one, and
the despatch date is the day the **last outstanding line** left, in the supplier's
own timezone. Two things follow that surprise people:

- A purchase that is only partly despatched has no despatch date at all, so it is
  never late. It becomes late only once everything has gone.
- A supplier whose timezone is set wrongly on `dropship_suppliers.timezone` will
  produce off-by-one-day comparisons. That is a supplier record to fix, not a page.

*"There was no promised date to measure that against"* means `onTime` folded to
`null` — nobody promised anything. It does not mean late, and the page will never
say late in that case.

## "A shopper is asking who the supplier is"

They cannot see it here, and no configuration makes them able to. The supplier
reference, code and name are never passed to the view, and a test asserts they are
absent from the rendered HTML of a purchase that has all of them. If a merchant
wants to publish supplier attribution, that is a decision for the merchant to make
somewhere else, with its own consequences.

The same holds for cost. Nothing in this package reads `expectedCost`,
`actualCost` or `costVariance()`, and the boundary suite fails the build if a name
appears.

## "The component does not resolve"

`Livewire::isDiscoverable('dropshipping::order-despatch')` is false when the
provider has not booted. Check `MODULES_ENABLED` names
`ecommerce-dropshipping-livewire` **and** `ecommerce-dropshipping`. The resolver
is registered in `boot()` via `Livewire::resolveMissingComponent()` and answers
only for the one alias this package publishes.

## "A domain exception reached the page"

Every concrete exception the domain publishes is mapped in `Support\Failures` and
seven of the eight render *That could not be shown.* — they are operator failures
a read-only page cannot reach, and reaching one is worth a look at the logs. An
unmapped exception renders the same sentence rather than a 500, and the unit suite
fails on the next release if the domain adds a type nobody mapped.

No domain exception message ever reaches a shopper. They name references,
currencies and supplier codes, which is why the sentences are written in this
package rather than taken from the exception.
