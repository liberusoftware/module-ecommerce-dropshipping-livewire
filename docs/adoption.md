# Adopting `ecommerce-dropshipping-livewire`

## 1. Install

The domain package is not on Packagist, so a host declares it as a VCS
repository. This package declares the same entry for its own resolution; a host
needs it too.

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-dropshipping" },
        { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-dropshipping-livewire" }
    ]
}
```

```bash
composer require liberusoftware/ecommerce-dropshipping-livewire:^0.1
```

Installing boots nothing. `extra.laravel.providers` is absent on purpose, and the
module manager registers
`Liberu\Ecommerce\Dropshipping\Livewire\DropshippingLivewireServiceProvider` only
when the module is named in `MODULES_ENABLED`:

```dotenv
MODULES_ENABLED=ecommerce-dropshipping,ecommerce-dropshipping-livewire
```

The domain package must be enabled as well. This package renders its queries and
nothing else.

## 2. Render the component

```blade
<livewire:dynamic-component :component="'dropshipping::order-despatch'"
    :tenant-id="$tenantId"
    :destination-ref="$destinationRef"
    :order-ref="$orderRef" />
```

| Argument | What the host passes |
| --- | --- |
| `tenantId` | The merchant this request resolved to |
| `destinationRef` | The opaque destination reference the host handed `Actions\RoutePurchases` |
| `orderRef` | The order reference the host handed `Actions\RoutePurchases` |

All three are `#[Locked]`. A browser cannot change them, which is the point: they
are the whole of the component's state and there is nothing else to send.

## 3. What the host owes this component

**Authorisation.** The host authorises the shopper against the order *before* it
mounts the component. This package cannot do it: it has no session, no user and no
`Auth` facade, and it deliberately imports none of them. Mounting it with a
`destinationRef` the current shopper does not own will render that shopper's data,
exactly as passing the wrong id to any other view would.

**The destination reference.** The host is the only party that knows which
destination reference belongs to which order and which person — this module stores
an opaque string and never resolves it. Keep the mapping the host already needed
in order to call `RoutePurchases` in the first place.

**Nothing else.** No seam is required. `TransmitsPurchases` and `ReportsShipments`
are unbound by default and this surface needs neither; it renders facts already
recorded. A host with no provider bound at all still gets a correct page — every
purchase reads "Not despatched yet", which is true.

## 4. Idempotency

**None is shipped, and none is needed.** An idempotency key protects a caller
against repeating an act. This package performs no act: the writable set is empty,
the submittable set is empty, there is no public method but `render()`, and no
domain action is named anywhere in `src/`. Reloading the page a hundred times
writes nothing and changes nobody's standing.

That is the same reasoning the presentation brief asks for on the `-api` side,
reaching the opposite conclusion for the opposite reason: there, a retry could
authorise a second irreversible act; here there is no first one.

## 5. What the host deletes

Nothing in this package replaces host code directly — it is new. The host code it
makes obsolete is listed in the **domain** package's `docs/adoption.md`, and one
item there matters to anybody wiring this component in:

- `orders.supplier_tracking_number` was added and never wired: written by nobody
  and read by nobody. A host that had planned to render it from the order should
  render this component instead and drop the column, along with `supplier_id`,
  `supplier_order_reference`, `supplier_response` and `is_dropshipped`.
- Removing `Order::STATUS_SUPPLIER_QUEUED` and `STATUS_SUPPLIER_FAILED` changes
  VAT reporting — both appear in `OssReportService::REPORTABLE_STATUSES` and in
  `EcSalesListService`. Map them before dropping them. The domain package's
  adoption doc carries the detail.

## 6. Styling

The component ships semantic markup and class names, no CSS and no JavaScript.
Every element carries a `dropshipping-` prefixed class. Publish the view to change
the wording or the structure:

```bash
php artisan vendor:publish --tag=views --provider="Liberu\Ecommerce\Dropshipping\Livewire\DropshippingLivewireServiceProvider"
```

If you publish it, keep the four refusals intact: no cost, no supplier, one
sentence for every refusal, and `null` never rendered as `false` or as zero.
`docs/domain.md` §4 has the table.

## 7. Upgrading

`0.1.0` is the first release. The component alias `dropshipping::order-despatch`
and its three mount arguments are the public contract; the shape of the array the
view is given is not.
