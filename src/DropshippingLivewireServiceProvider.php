<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Dropshipping\Livewire;

use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Dropshipping\Livewire\Components\OrderDespatch;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Binds nothing, registers no route and schedules nothing.
 *
 * Especially binds neither seam. `TransmitsPurchases` and `ReportsShipments` are
 * unbound by default and this surface does not need either: it renders facts
 * already recorded. Binding one here would be a shopper page deciding how a
 * merchant talks to a supplier.
 */
final class DropshippingLivewireServiceProvider extends ServiceProvider
{
    /**
     * Every component this package publishes, keyed by its registered name.
     *
     * The reflection suite iterates this constant rather than a hand-written
     * list, so a component added without deciding its property partition fails
     * the build instead of shipping unmarked client-writable state.
     *
     * @var array<string, class-string<Component>>
     */
    public const COMPONENTS = [
        'dropshipping::order-despatch' => OrderDespatch::class,
    ];

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'dropshipping-livewire');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/dropshipping-livewire'),
            ], 'views');
        }

        // resolveMissingComponent(), not addNamespace(): Livewire 4's
        // Finder::resolveClassComponentClassName() returns null for a namespaced
        // name before it consults the registry.
        Livewire::resolveMissingComponent(
            static fn (string $name): ?string => self::COMPONENTS[$name] ?? null,
        );
    }
}
