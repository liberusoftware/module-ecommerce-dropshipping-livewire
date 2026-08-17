<?php

declare(strict_types=1);

use Liberu\Ecommerce\Dropshipping\Enums\PurchaseState;
use Liberu\Ecommerce\Dropshipping\Livewire\Support\Despatch;

it('words every state the domain publishes', function () {
    foreach (PurchaseState::cases() as $state) {
        expect(Despatch::wording($state))->not->toBeEmpty();
    }
});

it('never says a partly despatched purchase has been despatched', function () {
    // A supplier shipping two of three is not the purchase leaving, and the two
    // sentences must not be the same sentence.
    expect(Despatch::wording(PurchaseState::PartiallyShipped))
        ->not->toBe(Despatch::wording(PurchaseState::Shipped))
        ->and(Despatch::wording(PurchaseState::PartiallyShipped))->toContain('Part of this');
});

it('says nothing has left for every state where nothing has', function () {
    foreach ([PurchaseState::Draft, PurchaseState::Transmitting, PurchaseState::Acknowledged] as $state) {
        expect(mb_strtolower(Despatch::wording($state)))->toContain('not');
    }
});

it('tells a shopper nothing about who is despatching or what it costs', function () {
    foreach (PurchaseState::cases() as $state) {
        $wording = mb_strtolower(Despatch::wording($state));

        foreach (['supplier', 'cost', 'price', 'margin', 'vendor', 'warehouse'] as $forbidden) {
            expect($wording)->not->toContain($forbidden, $state->value);
        }
    }
});

it('does not tell a shopper whether the store has asked anybody yet', function () {
    // Draft and Transmitting differ only in whether a supplier has been asked,
    // which is the merchant's business and not a fact about the parcel.
    expect(Despatch::wording(PurchaseState::Draft))
        ->toBe(Despatch::wording(PurchaseState::Transmitting));
});
