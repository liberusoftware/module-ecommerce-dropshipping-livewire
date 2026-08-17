<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Dropshipping\Livewire\Support;

use Liberu\Ecommerce\Dropshipping\Exceptions\CurrencyMismatch;
use Liberu\Ecommerce\Dropshipping\Exceptions\DropshippingException;
use Liberu\Ecommerce\Dropshipping\Exceptions\EventsAreAppendOnly;
use Liberu\Ecommerce\Dropshipping\Exceptions\InvalidSupplyOffer;
use Liberu\Ecommerce\Dropshipping\Exceptions\NotFound;
use Liberu\Ecommerce\Dropshipping\Exceptions\PurchaseNotCancellable;
use Liberu\Ecommerce\Dropshipping\Exceptions\PurchaseNotTransmittable;
use Liberu\Ecommerce\Dropshipping\Exceptions\TransmissionFailed;
use Liberu\Ecommerce\Dropshipping\Exceptions\TransmissionUnavailable;

/**
 * Every domain failure mapped once to a sentence a shopper may read.
 *
 * No `resubmittable` flag, unlike the loyalty surface this package follows: that
 * flag says whether repeating a submission could succeed, and nothing here is
 * submitted. A locked property that is false in every reachable state is a lying
 * constraint, so it is not shipped.
 *
 * Seven of the eight are operator failures a read-only shopper page cannot
 * reach. They are mapped anyway, because the table is asserted complete against
 * the installed domain package: a new exception type must not arrive as a 500,
 * and must not arrive carrying a domain message onto a page either.
 */
final class Failures
{
    /** Anything the domain adds later that nobody mapped. */
    public const UNCLASSIFIED = 'That could not be shown.';

    /**
     * `NotFound` covers a purchase that is not there and one that belongs to
     * somebody else, and it is the same sentence for both — a refusal a shopper
     * could tell apart publishes which references exist.
     *
     * @return array<class-string<DropshippingException>, string>
     */
    public static function table(): array
    {
        return [
            NotFound::class => 'There is no despatch information for this order.',

            CurrencyMismatch::class => self::UNCLASSIFIED,
            EventsAreAppendOnly::class => self::UNCLASSIFIED,
            InvalidSupplyOffer::class => self::UNCLASSIFIED,
            PurchaseNotCancellable::class => self::UNCLASSIFIED,
            PurchaseNotTransmittable::class => self::UNCLASSIFIED,
            TransmissionFailed::class => self::UNCLASSIFIED,
            TransmissionUnavailable::class => self::UNCLASSIFIED,
        ];
    }

    public static function classify(DropshippingException $failure): string
    {
        return self::table()[$failure::class] ?? self::UNCLASSIFIED;
    }
}
