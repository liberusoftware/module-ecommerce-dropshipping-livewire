<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Dropshipping\Livewire\Support;

use Liberu\Ecommerce\Dropshipping\Exceptions\DropshippingException;
use Livewire\Attributes\Locked;

/** The one property a refusal writes, and the two methods that write it. */
trait RendersFailures
{
    /**
     * What the shopper is told about a refusal, or null when there wasn't one.
     *
     * `#[Locked]`: it is a server decision about what happened, and a browser
     * able to write it could clear a refusal or manufacture one.
     */
    #[Locked]
    public ?string $failure = null;

    protected function clearFailure(): void
    {
        $this->failure = null;
    }

    protected function fail(DropshippingException $failure): void
    {
        $this->failure = Failures::classify($failure);
    }
}
