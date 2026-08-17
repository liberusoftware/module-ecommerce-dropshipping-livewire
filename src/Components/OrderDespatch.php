<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Dropshipping\Livewire\Components;

use Illuminate\Contracts\View\View;
use Liberu\Ecommerce\Dropshipping\Data\PurchaseView;
use Liberu\Ecommerce\Dropshipping\Enums\PurchaseState;
use Liberu\Ecommerce\Dropshipping\Exceptions\DropshippingException;
use Liberu\Ecommerce\Dropshipping\Livewire\Support\Despatch;
use Liberu\Ecommerce\Dropshipping\Livewire\Support\RendersFailures;
use Liberu\Ecommerce\Dropshipping\Queries\ExportDestinationRecord;
use Liberu\Ecommerce\Dropshipping\Queries\FindPurchase;
use Liberu\Ecommerce\Dropshipping\Queries\PurchaseStanding;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Has any of my order gone yet, when was it promised, and what is the tracking
 * number?"
 *
 * ## What a shopper is not told, and why the omissions are the design
 *
 * **Who supplies it.** `PurchaseView::$supplierReference` and
 * `$providerReference` are read on every request that renders this page and
 * neither reaches the array the view is given. A shopper who can see which
 * supplier fulfils a line can see the merchant's supply chain, and the merchant
 * did not agree to publish it.
 *
 * **What the merchant paid.** `PurchaseView` carries `expectedCost`,
 * `actualCost` and `costVariance()`. None of them is rendered, none is passed to
 * the view, and a boundary case asserts the words do not appear in this package.
 * Publishing the cost of goods on the order-status page is a commercial leak that
 * cannot be taken back once a scraper has it.
 *
 * **Which sources were considered.** No sourcing plan, no supply offer, no
 * alternative supplier, and no control of any kind that touches routing. The
 * host read `$request->input('supplier_id', 'dropxl')` at checkout, so a hidden
 * form field named the party who got paid. This package is the one that has to
 * make that impossible, and it does it by having nothing to write and nothing to
 * submit.
 *
 * ## Absence is rendered as absence
 *
 * A promise nobody made is not a date this page invents. `onTime` of `null` is
 * "there was no promise to measure against", which is a different fact from
 * `false`, and neither is shown as the other. Part of a purchase leaving is not
 * the purchase leaving, and the per-line quantities are shown so the difference
 * is checkable rather than asserted.
 *
 * ## Two queries, because no single one answers this
 *
 * `Queries\PurchaseStanding` folds the state, the promise and the despatched
 * quantities but publishes no carrier or tracking reference;
 * `Queries\ExportDestinationRecord` publishes those but not the fold. The export
 * is also the only published query keyed on anything a shopper owns, so it is
 * how a destination reference becomes a list of purchase references. Both facts
 * are gaps reported against the domain package rather than closed here.
 */
final class OrderDespatch extends Component
{
    use RendersFailures;

    #[Locked]
    public string $tenantId;

    /** The shopper's own destination reference, as the host handed it to routing. */
    #[Locked]
    public string $destinationRef;

    #[Locked]
    public string $orderRef;

    public function render(): View
    {
        // Annotated because the view namespace is registered in boot(), so
        // static analysis cannot resolve the name to a file.
        /** @var view-string $view */
        $view = 'dropshipping-livewire::order-despatch';

        return view($view, ['despatches' => $this->despatches()]);
    }

    /** @return list<array<string, mixed>> */
    private function despatches(): array
    {
        $this->clearFailure();
        $despatches = [];

        foreach ($this->purchasesInThisOrder() as $row) {
            try {
                $standing = (new PurchaseStanding())(
                    (new FindPurchase())($this->tenantId, self::text($row, 'reference')),
                );
            } catch (DropshippingException $failure) {
                $this->fail($failure);

                return [];
            }

            $despatches[] = [
                'key' => $standing->purchaseReference,
                'status' => Despatch::wording($standing->state),
                'promisedBy' => $standing->promisedBy,
                'despatchedOn' => $standing->despatchedOn,
                'onTime' => $standing->onTime,
                'lines' => self::lines($standing),
                'tracking' => self::tracking($row),
            ];
        }

        return $despatches;
    }

    /**
     * The purchases this merchant holds against this shopper's destination, kept
     * to the one order the host asked about.
     *
     * A destination that is not this shopper's, one that has been erased, an
     * order reference that does not exist and an order with nothing to despatch
     * all produce an empty list. Telling them apart is an enumeration oracle, and
     * an order page is the last place to publish one.
     *
     * @return list<array<string, mixed>>
     */
    private function purchasesInThisOrder(): array
    {
        $record = (new ExportDestinationRecord())($this->tenantId, $this->destinationRef);

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values(array_filter((array) ($record['purchases'] ?? []), is_array(...)));

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => self::text($row, 'order_ref') === $this->orderRef,
        ));
    }

    /**
     * Ordered against despatched, per line of the shopper's own order.
     *
     * Only where they can differ. A fully despatched purchase needs no breakdown,
     * and one nothing has left yet has nothing to break down — but a cancelled
     * purchase a supplier shipped anyway does, and that is the case this exists
     * for.
     *
     * @return list<array{lineRef: string, ordered: int, despatched: int}>
     */
    private static function lines(PurchaseView $standing): array
    {
        if ($standing->shippedQuantities === [] || $standing->state === PurchaseState::Shipped) {
            return [];
        }

        $lines = [];

        foreach ($standing->orderedQuantities as $lineRef => $ordered) {
            $lines[] = [
                'lineRef' => $lineRef,
                'ordered' => $ordered,
                'despatched' => $standing->shippedQuantities[$lineRef] ?? 0,
            ];
        }

        return $lines;
    }

    /**
     * The carrier references the supplier reported, deduplicated.
     *
     * A supplier reporting two despatches under one tracking reference is
     * ordinary; showing it twice reads as two parcels. A reference with no
     * carrier named is shown without one rather than with a guess, and an event
     * with no reference at all contributes nothing.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{carrier: string|null, reference: string}>
     */
    private static function tracking(array $row): array
    {
        $tracking = [];

        /** @var list<array<string, mixed>> $events */
        $events = array_values(array_filter((array) ($row['events'] ?? []), is_array(...)));

        foreach ($events as $event) {
            $reference = $event['tracking_ref'] ?? null;

            if (! is_string($reference) || $reference === '') {
                continue;
            }

            $carrier = $event['carrier_code'] ?? null;
            $carrier = is_string($carrier) && $carrier !== '' ? $carrier : null;

            $tracking[$carrier.'|'.$reference] = ['carrier' => $carrier, 'reference' => $reference];
        }

        return array_values($tracking);
    }

    /** @param  array<string, mixed>  $row */
    private static function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
