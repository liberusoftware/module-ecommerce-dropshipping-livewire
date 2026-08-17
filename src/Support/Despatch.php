<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Dropshipping\Livewire\Support;

use Liberu\Ecommerce\Dropshipping\Enums\PurchaseState;

/** The seven domain states in shopper words. */
final class Despatch
{
    /**
     * `Draft` and `Transmitting` are one sentence here, and `Acknowledged` is its
     * own. The first two differ only in whether a supplier has been asked, which
     * is the merchant's business and not a fact about the parcel; the third is
     * the parcel being confirmed, which is.
     *
     * `PartiallyShipped` and `Shipped` are never the same sentence: a supplier
     * shipping part of a purchase is real, and "despatched" said of it is a claim
     * about goods still sitting in a warehouse.
     */
    public static function wording(PurchaseState $state): string
    {
        return match ($state) {
            PurchaseState::Draft, PurchaseState::Transmitting => 'Not despatched yet.',
            PurchaseState::Acknowledged => 'Confirmed for despatch. It has not left yet.',
            PurchaseState::PartiallyShipped => 'Part of this has been despatched.',
            PurchaseState::Shipped => 'This has been despatched.',
            PurchaseState::Rejected => 'This could not be despatched. The store has been told.',
            PurchaseState::Cancelled => 'This was cancelled.',
        };
    }
}
