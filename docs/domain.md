# What this package renders, and what it refuses to

## 1. The question this package had to answer first

Dropshipping is a merchant-facing capability. Before writing a component, the
question was *what does a shopper legitimately see?* — and the honest answer is
short.

The domain publishes eight queries. Seven of them answer merchant questions:

| Query | Whose question |
| --- | --- |
| `SourcingPlanFor` | The merchant's — where an order *would* be sourced |
| `SupplyOffersFor` | The merchant's, and Multi-Source Inventory's |
| `OutstandingPurchases` | The merchant's — what is owed to suppliers |
| `SupplierPerformanceOf` | The merchant's — is this supplier any good |
| `FindSupplier` | The merchant's |
| `FindPurchase` / `PurchaseStanding` | The merchant's, except for a few of the fields |
| `ExportDestinationRecord` | The **shopper's**, and it publishes cost |

What is left that belongs to a shopper is a small set of facts about their **own
order**, which a supplier reported and which the merchant has every reason to pass
on: whether anything has been despatched, when it was promised, when it went, and
the carrier reference. That is one component.

## 2. The four shopper surfaces deliberately not shipped

Each of these is a component another wave would plausibly have shipped. None of
them is here.

**"Sold by" / supplier attribution.** Naming the supplier that fulfils a line
publishes the merchant's supply chain to anybody who can place an order. It is
also the single most requested dropshipping feature, which is why it is written
down here rather than left to be rediscovered. Not shipped, and the reason is
commercial rather than technical: the merchant did not agree to publish it.

**Anything derived from cost.** `PurchaseView` carries `expectedCost`,
`actualCost` and `costVariance()`, and the export publishes per-line `unit_cost`.
A "you saved" figure, a supplier price, a margin, a currency badge — every one of
them is the merchant's cost of goods, and once a scraper has it the merchant
cannot take it back. Not shipped. The names do not appear in this package's code
at all, which a boundary case asserts.

**A despatch estimate on a product page.** `SupplyOffersFor` publishes a lead time
per offer, so "usually despatched in three days" is one query away. It is not a
fact about the shopper's own order, it is a merchant input rather than a supplier
report, it leaks that the merchant holds no stock, and — the deciding reason — it
is the surface that invites a "choose a faster supplier" control next to it. Not
shipped.

**A shopper-facing copy of what we hold about them.** `ExportDestinationRecord`
is the natural fit and it publishes both costs, the supplier reference and the
supplier SKU on every purchase. A shopper-facing subject-access surface needs a
redacted view the domain does not have; that gap is reported rather than closed
here by filtering fields on a page.

If that leaves fewer components than other waves shipped, it leaves fewer
components. A surface invented to fill a quota is how wave 11 put reviewer PII on
a public listing.

## 3. Routing is never an input, and the design makes it unreachable

The host's fault 1 was `'is_dropshipped' => $request->has('dropship')` and
`$supplierId = $request->input('supplier_id', 'dropxl')`: a hidden form field
decided an order was drop-shipped and another named the party who got paid.

This package is the one that has to make that impossible, and it does it by
having nothing to say:

- The writable set is empty. Every public property is `#[Locked]`.
- The **submittable** set is empty too. There is no public method but `render()`,
  so there is no Livewire action a browser can call.
- The rendered HTML contains no `<form>`, `<input>`, `<select>`, `<button>`,
  `wire:model`, `wire:click` or `wire:submit`. A feature case asserts it on a
  fully populated purchase.
- No domain `Actions\` class is named anywhere in `src/`, so no page load can
  route, transmit, cancel, reconcile or erase anything.
- `SourcingPlanFor`, `SupplyOffersFor` and `FindSupplier` are not called and their
  names do not appear.

## 4. Absence is rendered as absence

The rule the domain states about `null` holds harder on a shopper surface than
anywhere else, because the shopper is the one party who cannot check.

| Fact | `null` means | Rendered as |
| --- | --- | --- |
| `promisedBy` | No promise was made | "No despatch date has been promised for this." |
| `despatchedOn` | Not everything has left | Nothing — the state sentence carries it |
| `onTime` | No promise, or no complete despatch | "There was no promised date to measure that against." |
| `carrier_code` | The supplier did not name one | The reference alone, without a carrier |

`onTime` of `null` is never rendered as `false`. A shopper told "this was late"
when the truth is "nobody promised a date" has been given a fact about the
merchant that is not true.

`despatched: 0` on a line is not in that table, and deliberately: it is a **known**
zero. The ordered quantities come from this merchant's own purchase lines and the
despatched quantities from the supplier's despatch events, so a line with no
despatch event genuinely has none despatched.

## 5. Partial despatch is a different claim, and gets a different sentence

`Support\Despatch::wording()` maps the domain's seven states to seven sentences,
and two decisions in it are worth stating:

**`PartiallyShipped` and `Shipped` are never the same sentence.** "Despatched"
said of a purchase with items still in a warehouse is a claim about goods that
have not moved. The partial case also renders the per-line ordered-against-
despatched quantities, so "part" is checkable rather than asserted.

**`Draft` and `Transmitting` are the same sentence.** They differ only in whether
a supplier has been asked yet, which is the merchant's business and not a fact
about the parcel. `Acknowledged` is its own sentence, because the parcel being
confirmed *is* a fact about the parcel.

A cancelled purchase the supplier despatched anyway renders both halves. The
domain's fold keeps the terminal state and still accumulates the quantities,
because the goods really did leave, and rendering only one of the two would hide
the thing everybody needs to know.

## 6. One refusal, so nothing can be enumerated

These four produce the identical sentence and the identical markup:

- an order reference this merchant holds no purchases for;
- a destination reference belonging to a different shopper;
- a destination reference that has been erased by `Actions\RedactDestination`;
- an order with nothing drop-shipped in it at all.

The domain already raises one `NotFound` for "no such purchase" and "not your
purchase". `Support\Failures` maps it to the same sentence the empty state uses,
and a case asserts the two strings are the same string. A 404 that reads
differently from a 403 publishes which references exist.

## 7. `Support\Failures` has no `resubmittable` flag, unlike the reference package

`module-ecommerce-loyalty-livewire` ships a `Support\Failure` value object with
`resubmittable` and `transient` flags, and a locked `$failureResubmittable`
property on every component. That surface has a shopper action — redeeming a
reward — so both flags describe something real.

Here nothing is submitted, so `resubmittable` would be false in every reachable
state, and a locked property that is false in every reachable state is a lying
constraint. `Failures::classify()` returns the sentence and nothing else. This is
a deliberate divergence from the reference package.

Seven of the eight concrete domain exceptions are operator failures a read-only
page cannot reach. They are mapped anyway, to `Failures::UNCLASSIFIED`, because
the table is asserted complete against the installed domain package: a new
exception type must not arrive as a 500, and must not arrive carrying a domain
message — which names references, currencies and supplier codes — onto a page.

## 8. Two queries, because no single one answers "where is my order?"

`Queries\PurchaseStanding` folds the state, the promise, the despatch date, the
timeliness and the despatched quantities. It publishes **no carrier and no
tracking reference**.

`Queries\ExportDestinationRecord` publishes the carrier and the tracking
reference, and it is the only published query keyed on anything a shopper owns —
so it is also how a destination reference becomes a list of purchase references.
It publishes both costs and the supplier alongside them.

So the component reads the export for the references and the tracking, and
`FindPurchase` plus `PurchaseStanding` for the fold. Of the export it uses four
keys — `reference`, `order_ref`, and each event's `carrier_code` and
`tracking_ref` — and touches nothing else.

Both halves of that are gaps reported against the domain package. Neither is
closed here: a presentation package that computed `onTime` from two dates, or
that grew its own purchases-for-an-order query, would be a business rule in a
surface.

## 9. What is not modelled here at all

- **No poll, no refresh, no waiting.** `wire:poll` is banned by a boundary case.
  A despatch arrives when a supplier says so, which is minutes or days, and a page
  that polls for it is a page that hammers a database for nothing.
- **No problem detail.** A rejection renders as one neutral sentence.
  `ProblemReport::$code` is the supplier's own vocabulary and can carry the
  supplier's name in it; `$field` names the field the supplier rejected, which is
  the merchant's to act on. Both stay off the page.
- **No delivery.** The shipment to the shopper is Fulfillment's (wave 5) and the
  carrier relationship is Carrier Operations' (#828). This renders the despatch
  the *supplier* reported, which is the fact that lets those modules record
  theirs.
- **No seam binding.** `TransmitsPurchases` and `ReportsShipments` stay unbound.
  This surface renders facts already recorded and needs neither; binding one here
  would be a shopper page deciding how a merchant talks to a supplier.
