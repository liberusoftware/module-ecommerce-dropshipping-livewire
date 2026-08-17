<?php

declare(strict_types=1);

use Liberu\Ecommerce\Dropshipping\Exceptions\DropshippingException;
use Liberu\Ecommerce\Dropshipping\Exceptions\NotFound;
use Liberu\Ecommerce\Dropshipping\Livewire\Support\Failures;
use Liberu\Ecommerce\Dropshipping\Livewire\Support\RendersFailures;

/** @return list<class-string<DropshippingException>> */
function domainExceptions(): array
{
    $directory = dirname(__DIR__, 2).'/vendor/liberusoftware/ecommerce-dropshipping/src/Exceptions';
    $classes = [];

    foreach ((array) scandir($directory) as $file) {
        if (! is_string($file) || ! str_ends_with($file, '.php')) {
            continue;
        }

        $class = 'Liberu\\Ecommerce\\Dropshipping\\Exceptions\\'.basename($file, '.php');

        // A class-string that does not autoload degrades a type assertion into a
        // message check that asserts nothing.
        expect(class_exists($class))->toBeTrue("{$class} does not autoload.");

        if (! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

it('classifies every concrete failure the domain publishes', function () {
    $missing = array_values(array_diff(domainExceptions(), array_keys(Failures::table())));

    expect($missing)->toBe([]);
});

it('classifies nothing that is not a domain failure', function () {
    $extra = array_values(array_diff(array_keys(Failures::table()), domainExceptions()));

    expect($extra)->toBe([]);
});

it('answers a missing purchase and somebody else s purchase with one sentence', function () {
    // The domain raises the same NotFound for both on purpose. This surface must
    // not undo that by rendering the exception's own message, which names the
    // reference that was asked for.
    $classified = Failures::classify(NotFound::purchase('dpu_deadbeefdeadbeefdeadbeef'));

    expect($classified)->toBe('There is no despatch information for this order.')
        ->and($classified)->toBe(Failures::classify(NotFound::supplier('acme')));
});

it('renders the same sentence for a refusal as for an order with nothing in it', function () {
    // Two answers a shopper could tell apart is an enumeration oracle even when
    // one of them arrives as an exception and the other as an empty list.
    $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/order-despatch.blade.php');

    expect($blade)->toContain(Failures::classify(NotFound::purchase('dpu_x')));
});

it('falls back to a refusal that says nothing for anything it has never seen', function () {
    $unknown = new class('nobody classified this') extends DropshippingException {};

    expect(Failures::classify($unknown))->toBe(Failures::UNCLASSIFIED);
});

it('never leaks a reference, a supplier, a cost or a tenant into a shopper-facing message', function () {
    // The sentences are written here, not taken from the exception, so a domain
    // message naming a purchase reference, a supplier code or a currency cannot
    // reach the page through the failure banner.
    foreach (Failures::table() as $class => $message) {
        expect($message)->not->toMatch('/d[a-z]{2}_[0-9a-f]{8}/');

        foreach (['tenant', 'supplier', 'cost', 'GBP', 'transmit', 'reconcile'] as $forbidden) {
            expect(mb_strtolower($message))->not->toContain(mb_strtolower($forbidden), $class);
        }
    }
});

it('invites nobody to try again, because nothing here is submitted', function () {
    // No resubmittable flag exists on this surface and none is implied in
    // words: a "try again shortly" on a page with no button is a lie about a
    // loop that cannot even be entered.
    foreach ([...array_values(Failures::table()), Failures::UNCLASSIFIED] as $message) {
        foreach (['shortly', 'try again', 'in a moment', 'please wait', 'refresh'] as $phrase) {
            expect(mb_strtolower($message))->not->toContain($phrase);
        }
    }
});

it('writes the classified sentence onto the component and clears it again', function () {
    $subject = new class()
    {
        use RendersFailures;

        public function apply(DropshippingException $failure): void
        {
            $this->fail($failure);
        }

        public function clear(): void
        {
            $this->clearFailure();
        }
    };

    $subject->apply(NotFound::purchase('dpu_x'));

    expect($subject->failure)->toBe('There is no despatch information for this order.');

    $subject->clear();

    expect($subject->failure)->toBeNull();
});
