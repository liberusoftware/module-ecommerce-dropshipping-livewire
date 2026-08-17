<?php

declare(strict_types=1);

use Liberu\Ecommerce\Dropshipping\Actions\CancelPurchase;
use Liberu\Ecommerce\Dropshipping\Actions\RedactDestination;
use Liberu\Ecommerce\Dropshipping\Livewire\Components\OrderDespatch;
use Livewire\Livewire;

it('says the same thing about an order it holds nothing for', function () {
    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('There is no despatch information for this order.')
        ->assertDontSee('Not despatched yet.');
});

it('gives that same answer for a destination belonging to somebody else', function () {
    routedPurchase();

    // "No such order", "not your order" and "nothing to despatch" are one
    // answer. A refusal a shopper could tell apart publishes which references
    // exist.
    Livewire::test(OrderDespatch::class, mountFor(destinationRef: 'ship-to-someone-else'))
        ->assertSee('There is no despatch information for this order.');

    Livewire::test(OrderDespatch::class, mountFor(orderRef: 'order-99'))
        ->assertSee('There is no despatch information for this order.');
});

it('gives that same answer for another merchant holding the identical references', function () {
    routedPurchase(tenantId: 'tenant-b');

    Livewire::test(OrderDespatch::class, mountFor(tenantId: 'tenant-a'))
        ->assertSee('There is no despatch information for this order.');
});

it('shows each merchant its own despatch when both hold the identical references', function () {
    [$a] = routedPurchase(tenantId: 'tenant-a');
    [$b] = routedPurchase(tenantId: 'tenant-b');

    despatch($a, ['line-1' => 3, 'line-2' => 2], trackingRef: 'TRACK-A');
    despatch($b, ['line-1' => 1], trackingRef: 'TRACK-B');

    // Not "scoped" — the right non-zero answer on each side. A restatement bug
    // that reported nothing for everybody would pass a scoping assertion.
    Livewire::test(OrderDespatch::class, mountFor(tenantId: 'tenant-a'))
        ->assertSee('This has been despatched.')
        ->assertSee('TRACK-A')
        ->assertDontSee('TRACK-B');

    Livewire::test(OrderDespatch::class, mountFor(tenantId: 'tenant-b'))
        ->assertSee('Part of this has been despatched.')
        ->assertSee('TRACK-B')
        ->assertDontSee('TRACK-A');
});

it('keeps two orders to the same destination apart', function () {
    [$first] = routedPurchase(orderRef: 'order-1');
    despatch($first, ['line-1' => 3, 'line-2' => 2], trackingRef: 'TRACK-ONE');

    Livewire::test(OrderDespatch::class, mountFor(orderRef: 'order-2'))
        ->assertSee('There is no despatch information for this order.')
        ->assertDontSee('TRACK-ONE');
});

it('renders a routed purchase as not despatched, with no promise invented', function () {
    routedPurchase();

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('Not despatched yet.')
        ->assertSee('No despatch date has been promised for this.')
        ->assertDontSee('It all left on');
});

it('renders the promise the supplier made, and does not pretend anything has left', function () {
    [$purchase] = routedPurchase();
    acknowledge($purchase, promisedBy: '2026-03-05');

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('Confirmed for despatch. It has not left yet.')
        ->assertSee('Promised to leave by 2026-03-05.')
        ->assertDontSee('It all left on');
});

it('never renders part of a purchase as the whole of it', function () {
    [$purchase] = routedPurchase();
    acknowledge($purchase);
    despatch($purchase, ['line-1' => 2]);

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('Part of this has been despatched.')
        ->assertDontSee('This has been despatched.')
        // The quantities, so "part" is checkable rather than asserted: two of
        // the three on one line, none of the two on the other.
        ->assertSeeInOrder(['line-1', '3', '2'])
        ->assertSeeInOrder(['line-2', '2', '0'])
        // Nothing is fully despatched, so there is no despatch date and no
        // timeliness to claim.
        ->assertDontSee('It all left on');
});

it('renders a full despatch, its date, and that it beat the promise', function () {
    [$purchase] = routedPurchase();
    acknowledge($purchase, promisedBy: '2026-03-05');
    despatch($purchase, ['line-1' => 3, 'line-2' => 2], at: '2026-03-04T08:00:00Z');

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('This has been despatched.')
        ->assertSee('It all left on 2026-03-04.')
        ->assertSee('That was on or before the date promised.')
        // Fully despatched: there is nothing left to break down.
        ->assertDontSee('What has left so far');
});

it('says a despatch was late rather than rounding it into on time', function () {
    [$purchase] = routedPurchase();
    acknowledge($purchase, promisedBy: '2026-03-02');
    despatch($purchase, ['line-1' => 3, 'line-2' => 2], at: '2026-03-04T08:00:00Z');

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('That was later than the date promised.')
        ->assertDontSee('That was on or before the date promised.');
});

it('renders an unmeasurable despatch as unmeasurable and never as late', function () {
    [$purchase] = routedPurchase();

    // Acknowledged with no promise: onTime folds to null, which is "there was
    // nothing to measure against" and is a different fact from false.
    acknowledge($purchase, promisedBy: null);
    despatch($purchase, ['line-1' => 3, 'line-2' => 2]);

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('It all left on 2026-03-04.')
        ->assertSee('There was no promised date to measure that against.')
        ->assertDontSee('That was later than the date promised.')
        ->assertDontSee('That was on or before the date promised.');
});

it('renders the carrier and the tracking reference the supplier reported', function () {
    [$purchase] = routedPurchase();
    despatch($purchase, ['line-1' => 3], causeRef: 'SHIP-1', trackingRef: 'TRACK-1');
    despatch($purchase, ['line-2' => 2], causeRef: 'SHIP-2', trackingRef: 'TRACK-2');

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('dhl')
        ->assertSee('TRACK-1')
        ->assertSee('TRACK-2');
});

it('shows one tracking reference once, however many despatches quoted it', function () {
    [$purchase] = routedPurchase();
    despatch($purchase, ['line-1' => 3], causeRef: 'SHIP-1', trackingRef: 'TRACK-SAME');
    despatch($purchase, ['line-2' => 2], causeRef: 'SHIP-2', trackingRef: 'TRACK-SAME');

    $html = Livewire::test(OrderDespatch::class, mountFor())->html();

    expect(substr_count($html, 'TRACK-SAME'))->toBe(2); // the wire:key and the text, once each
});

it('shows a tracking reference with no carrier rather than guessing one', function () {
    [$purchase] = routedPurchase();
    despatch($purchase, ['line-1' => 3, 'line-2' => 2], carrier: null, trackingRef: 'TRACK-NOCARRIER');

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('TRACK-NOCARRIER')
        ->assertSee('we have not been told which carrier');
});

it('records a cancellation a supplier despatched anyway, without hiding either half', function () {
    [$purchase] = routedPurchase();
    (new CancelPurchase())($purchase, 'merchant-changed-mind', at('2026-03-02T00:00:00Z'));
    despatch($purchase, ['line-1' => 3, 'line-2' => 2]);

    // The fold keeps the terminal state and still accumulates the quantities,
    // because the goods really did leave. Rendering only one of the two would
    // hide the fact the merchant and the shopper both need.
    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('This was cancelled.')
        ->assertSee('What has left so far')
        ->assertSeeInOrder(['line-1', '3', '3']);
});

it('tells a shopper a rejection happened without publishing the supplier code behind it', function () {
    [$purchase] = routedPurchase();
    raiseProblem($purchase, code: 'acme_address_invalid', field: 'destination.postcode');

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('This could not be despatched. The store has been told.')
        // The problem code is the supplier's own vocabulary and can carry its
        // name; the rejected field is the merchant's to act on, not the
        // shopper's to read off a page.
        ->assertDontSee('acme_address_invalid')
        ->assertDontSee('destination.postcode');
});

it('answers an erased destination exactly as it answers an unknown one', function () {
    [$purchase] = routedPurchase();
    despatch($purchase, ['line-1' => 3, 'line-2' => 2]);

    (new RedactDestination())('tenant-a', 'ship-to-1');

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSee('There is no despatch information for this order.')
        ->assertDontSee('TRACK-1');
});

it('publishes no supplier and no cost on a purchase that has all of both', function () {
    [$purchase, $supplier] = routedPurchase();
    acknowledge($purchase, promisedBy: '2026-03-05');
    despatch($purchase, ['line-1' => 3, 'line-2' => 2]);

    $html = Livewire::test(OrderDespatch::class, mountFor())->html();

    // Everything the two queries handed this page and it dropped: who supplies
    // it, what the merchant expected to pay, what it was actually charged, and
    // the supplier's own reference for the purchase.
    $secrets = [
        (string) $supplier->reference,
        'acme', 'ACME', 'Acme Supply', 'ACME-product-1',
        'PROV-1',
        '40.00', '35.00', 'GBP',
    ];

    foreach ($secrets as $secret) {
        expect($html)->not->toContain($secret);
    }
});

it('offers a shopper no way to influence who fulfils anything', function () {
    [$purchase] = routedPurchase();
    despatch($purchase, ['line-1' => 3]);

    $html = Livewire::test(OrderDespatch::class, mountFor())->html();

    // Host fault 1 was a hidden form field naming the party who got paid. This
    // page has no field at all, hidden or otherwise.
    foreach (['<form', '<input', '<select', '<button', 'wire:model', 'wire:click', 'wire:submit'] as $control) {
        expect($html)->not->toContain($control);
    }
});

it('mounts with exactly the three references a host already holds', function () {
    routedPurchase();

    Livewire::test(OrderDespatch::class, mountFor())
        ->assertSet('tenantId', 'tenant-a')
        ->assertSet('orderRef', 'order-1')
        ->assertSet('destinationRef', 'ship-to-1')
        ->assertSet('failure', null);
});
