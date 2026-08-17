# Ecommerce Dropshipping — Livewire

[![Tests](https://github.com/liberusoftware/module-ecommerce-dropshipping-livewire/actions/workflows/tests.yml/badge.svg)](https://github.com/liberusoftware/module-ecommerce-dropshipping-livewire/actions/workflows/tests.yml)

The shopper's side of [`liberusoftware/ecommerce-dropshipping`](https://github.com/liberusoftware/module-ecommerce-dropshipping):
**one** Livewire 4 component, because there is exactly one thing about dropshipping
a shopper is entitled to see.

## The component

| Alias | What it renders |
| --- | --- |
| `dropshipping::order-despatch` | What has been despatched of the shopper's own order, when it was promised, and the carrier references the supplier reported. |

```blade
<livewire:dynamic-component :component="'dropshipping::order-despatch'"
    :tenant-id="$tenantId"
    :destination-ref="$destinationRef"
    :order-ref="$orderRef" />
```

Three references the host already holds, all `#[Locked]`, and nothing else. The
host authorises them before it mounts anything; this package cannot do that for
it.

## Why one component

Dropshipping is a merchant-facing capability. The domain publishes eight queries
and seven of them answer merchant questions — where an order *would* be sourced,
which supplier is cheapest or quickest, what is owed to suppliers per currency,
whether a supplier keeps its promises. None of those is a shopper's business, and
several of them would be actively harmful on a public page.

What is left is the honest set: what has happened to the shopper's own order,
which the supplier told us and which the merchant has every reason to pass on.

## What it refuses to publish

**Who supplies the goods.** The supplier reference, code and name are read on
every request that renders this page and none of them reaches the view. A shopper
who can see which supplier fulfils a line can see the merchant's supply chain.

**What the merchant paid.** `PurchaseView` carries `expectedCost`, `actualCost`
and `costVariance()`. Nothing here reads them, a boundary case asserts the names
do not appear in the package at all, and a feature case asserts neither figure is
in the rendered HTML of a purchase that has both. An order-status page that
publishes the cost of goods is a commercial leak that cannot be taken back.

**Which sources were considered.** No sourcing plan, no supply offer, no
alternative supplier — and no control of any kind. The host read
`$request->input('supplier_id', 'dropxl')` at checkout, so a hidden form field
named the party who got paid. This package renders no form, no input, no select
and no button; there is nothing to submit and nothing to tamper with.

**A promise nobody made.** A purchase with no promised date says so. No estimate
is invented from a lead time, an average or anything else.

**A `false` where the answer is `null`.** `onTime` is `null` when there was no
promise or no complete despatch, which is *"there was nothing to measure against"*
and not *"it was late"*. The two get different sentences and neither stands in for
the other.

**Part of an order as the whole of it.** A supplier despatching two of three is
"part of this has been despatched", with the per-line quantities underneath so the
claim is checkable. A purchase a merchant cancelled and a supplier shipped anyway
shows both facts, because the goods really did leave.

**Which references exist.** An order this merchant holds nothing for, a
destination belonging to somebody else, an erased destination, and an order with
nothing to despatch are one sentence. A refusal a shopper can tell apart is an
enumeration oracle.

## Installing

Everything a host has to do is in [`docs/adoption.md`](docs/adoption.md) — the VCS
`repositories` entry, `MODULES_ENABLED`, and what the host must authorise before
it mounts the component. The decisions behind the one component, and the four
shopper surfaces deliberately not shipped, are in
[`docs/domain.md`](docs/domain.md). [`docs/runbook.md`](docs/runbook.md) covers
what to do when a shopper reports that their order has no despatch information.

## Licence

MIT.
