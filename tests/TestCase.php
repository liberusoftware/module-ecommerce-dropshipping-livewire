<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Dropshipping\Livewire\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\Dropshipping\DropshippingServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    use RefreshDatabase;

    /**
     * The domain package is a runtime `require` only, and `PackageTestCase`'s
     * discovery cannot see it there: a Liberu module ships
     * `extra.laravel.providers` empty by design and the manifest branch of that
     * discovery runs over `require-dev`.
     *
     * Ordering is not cosmetic. The parent supplies Livewire's provider after
     * ours, and ours calls `Livewire::resolveMissingComponent()` in `boot()`.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [DropshippingServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');

        // Debug on, deliberately, in every case that renders a component:
        // Livewire swallows a TypeError inside a component method whole, so a
        // signature mistake reads as a session problem rather than as a bug.
        $app['config']->set('app.debug', true);

        // Both seams unbound, which is the shipped default and which this
        // surface never needs — it renders facts already recorded.
        $app['config']->set('dropshipping.seams.transmitter', null);
        $app['config']->set('dropshipping.seams.shipment_reporter', null);
    }
}
