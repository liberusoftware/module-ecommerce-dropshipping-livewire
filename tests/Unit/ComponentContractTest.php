<?php

declare(strict_types=1);

use Liberu\Ecommerce\Dropshipping\Livewire\Components\OrderDespatch;
use Liberu\Ecommerce\Dropshipping\Livewire\DropshippingLivewireServiceProvider;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * The property partition, enforced over every component this package registers
 * rather than over a hand-written list.
 *
 * The writable set is empty and the submittable set is empty with it. On the
 * loyalty surface a shopper at least chose a reward; here there is no choice a
 * shopper is entitled to make, because the choice this module is about — which
 * supplier fulfils a line — is exactly the one the host let a hidden form field
 * make.
 *
 * A Livewire return value is a surface too: a public method that answered with a
 * PurchaseView would publish both costs and the supplier over the wire without
 * rendering a character of it. So the public method set is stated exactly.
 */

/** @return array<int, array{class-string<Component>}> */
function registeredComponents(): array
{
    // Nested rows: a flat list of class-strings is PHP's callable-array syntax,
    // and Pest calls it instead of iterating it.
    return array_map(
        static fn (string $class): array => [$class],
        array_values(DropshippingLivewireServiceProvider::COMPONENTS),
    );
}

/** @return list<ReflectionProperty> */
function publicStateOf(string $component): array
{
    return array_values(array_filter(
        (new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC),
        static fn (ReflectionProperty $property): bool => ! $property->isStatic(),
    ));
}

it('publishes exactly the alias the documentation promises', function () {
    expect(DropshippingLivewireServiceProvider::COMPONENTS)->toBe([
        'dropshipping::order-despatch' => OrderDespatch::class,
    ]);
});

it('resolves every registered component by its published name', function () {
    foreach (DropshippingLivewireServiceProvider::COMPONENTS as $name => $class) {
        expect(Livewire::isDiscoverable($name))->toBeTrue("[{$name}] does not resolve.");
        expect(app('livewire')->new($name))->toBeInstanceOf($class);
    }
});

it('marks every public property either locked or validated, never both and never neither', function (string $component) {
    $properties = publicStateOf($component);

    expect($properties)->not->toBeEmpty();

    foreach ($properties as $property) {
        $locked = $property->getAttributes(Locked::class) !== [];
        $validated = $property->getAttributes(Validate::class) !== [];
        $name = $component.'::$'.$property->getName();

        expect($locked || $validated)->toBeTrue("{$name} is neither #[Locked] nor #[Validate].");
        expect($locked && $validated)->toBeFalse("{$name} is both #[Locked] and #[Validate].");
    }
})->with(registeredComponents());

it('states the locked set exactly, and leaves nothing writable', function () {
    $writable = [];
    $locked = [];

    foreach (publicStateOf(OrderDespatch::class) as $property) {
        $property->getAttributes(Validate::class) !== []
            ? $writable[] = $property->getName()
            : $locked[] = $property->getName();
    }

    sort($locked);

    // "Some things are locked" is not the guarantee. "These, and nothing else,
    // and none of them by a browser" is.
    expect($writable)->toBe([])
        ->and($locked)->toBe(['destinationRef', 'failure', 'orderRef', 'tenantId']);
});

it('refuses a client write to a locked property at runtime', function (string $property) {
    // The attribute is a claim; this is the check that Livewire honours it.
    Livewire::test(OrderDespatch::class, [
        'tenantId' => 'tenant-a',
        'destinationRef' => 'ship-to-1',
        'orderRef' => 'order-1',
    ])->set($property, 'tampered');
})->with(['tenantId', 'destinationRef', 'orderRef', 'failure'])
    ->throws(CannotUpdateLockedPropertyException::class);

it('exposes no public method that could return a domain object over the wire', function (string $component) {
    $methods = array_values(array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            (new ReflectionClass($component))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $component,
        ),
    ));

    sort($methods);

    expect($methods)->toBe(['render']);
})->with(registeredComponents());

it('holds no money value, no price and no supplier as component state', function (string $component) {
    foreach (publicStateOf($component) as $property) {
        $name = mb_strtolower($property->getName());

        foreach (['price', 'total', 'amount', 'minor', 'money', 'currency', 'cost', 'supplier', 'margin'] as $forbidden) {
            expect(str_contains($name, $forbidden))->toBeFalse($component.'::$'.$property->getName());
        }

        $type = $property->getType();

        expect($type instanceof ReflectionNamedType && $type->getName() === 'float')->toBeFalse();
    }
})->with(registeredComponents());

it('reads no cost and no supplier identity out of the domain data it is handed', function () {
    // PurchaseView carries expectedCost, actualCost, costVariance(),
    // supplierReference and providerReference. Naming one of them in code here
    // is the leak, whether or not a template happens to print it today.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        foreach (['expectedCost', 'actualCost', 'costVariance', 'supplierReference', 'providerReference', 'supplier_sku', 'unit_cost'] as $forbidden) {
            expect(sourceCode($path))->not->toContain($forbidden, $path);
        }
    }
});
