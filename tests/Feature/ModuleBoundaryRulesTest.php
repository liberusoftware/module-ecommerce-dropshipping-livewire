<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\Dropshipping\Livewire\DropshippingLivewireServiceProvider;
use Livewire\Livewire;

function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

it('reaches for no host application namespace', function () {
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toMatch('/(?:use|new|extends|implements)\s+App\\\\/');
    }
});

it('imports no other presentation framework', function () {
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toContain('Filament\\');
    }
});

it('depends on no sibling module', function () {
    $composer = json_decode((string) file_get_contents(packageRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    $liberu = array_keys(array_filter(
        [...$composer['require'], ...$composer['require-dev']],
        static fn (string $package): bool => str_starts_with($package, 'liberusoftware/'),
        ARRAY_FILTER_USE_KEY,
    ));

    sort($liberu);

    expect($liberu)->toBe([
        'liberusoftware/ecommerce-dropshipping',
        'liberusoftware/package-testbench',
    ]);

    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toMatch('/Liberu\\\\Ecommerce\\\\(?!Dropshipping)/');
    }
});

it('never names a domain model', function () {
    // Everything this package renders comes from a published query. A surface
    // that reached for a model would have taken a shortcut past whatever the
    // query enforces — and in this module the query is a fold.
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toMatch('/use Liberu\\\\.+\\\\Models\\\\/');
    }
});

it('calls no domain action, so nothing a shopper does can move a purchase', function () {
    // Routing, transmission, cancellation, reconciliation and erasure are all
    // decisions somebody takes. None of them is a page load.
    foreach (sourceFiles() as $path) {
        expect(sourceCode($path))->not->toMatch('/use Liberu\\\\Ecommerce\\\\Dropshipping\\\\Actions\\\\/');

        foreach (['RoutePurchases', 'TransmitPurchase', 'ReconcilePurchase', 'CancelPurchase', 'RecordSupplierReport', 'RegisterSupplier', 'PublishSupplyOffer', 'RedactDestination'] as $action) {
            expect(sourceCode($path))->not->toContain($action, $path);
        }
    }
});

it('never lets a shopper near the routing decision', function () {
    // Host fault 1: `is_dropshipped` came from `$request->has('dropship')` and
    // the supplier from `$request->input('supplier_id', 'dropxl')`. This package
    // is the one that has to make that impossible, so it names no supply offer,
    // no sourcing plan and no supplier at all.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        foreach (['SourcingPlanFor', 'SupplyOffersFor', 'FindSupplier', 'SupplierPerformanceOf', 'OutstandingPurchases', 'supplier_id', 'dropxl'] as $forbidden) {
            expect($source)->not->toContain($forbidden, $path);
        }
    }
});

it('renders no form control of any kind', function () {
    foreach (filesUnder('resources') as $path) {
        $source = sourceCode($path);

        foreach (['<form', '<input', '<select', '<textarea', '<button', 'wire:model', 'wire:click', 'wire:submit', 'wire:poll'] as $control) {
            expect($source)->not->toContain($control, $path);
        }
    }
});

it('never writes anything at all', function () {
    foreach (sourceFiles() as $path) {
        $source = sourceCode($path);

        foreach (['->save(', '->create(', '->update(', '->delete(', 'forceFill', 'firstOrCreate', 'increment(', 'decrement(', 'DB::'] as $write) {
            expect($source)->not->toContain($write, $path);
        }
    }
});

it('never aggregates over a relation that restates the tenant', function () {
    // withCount(), whereHas() and friends build the relation from a fresh
    // instance whose tenant_id is null. This package stays out of the argument
    // by reading only through published queries.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        expect($source)->not->toContain('withCount')
            ->and($source)->not->toContain('whereHas')
            ->and($source)->not->toContain('withExists')
            ->and($source)->not->toMatch('/->has\(/');
    }
});

it('uses no join, no raw SQL and no query-builder table access', function () {
    foreach (sourceFiles() as $path) {
        $source = sourceCode($path);

        expect($source)->not->toMatch('/->(?:left|right|inner|cross)?[Jj]oin(?:Sub|Where)?\(/');
        expect($source)->not->toContain('DB::table');
        expect($source)->not->toContain('whereRaw');
        expect($source)->not->toContain('DB::raw');
    }
});

it('uses no framework-foundation helper that illuminate/support does not ship', function () {
    // config(), app(), auth() and friends live in laravel/framework, not in
    // illuminate/support. They pass CI because the testbench drags the framework
    // in, and are a lying constraint for a consumer who installed what this
    // package declared.
    foreach (sourceFiles() as $path) {
        $source = sourceCode($path);

        foreach (['config', 'app', 'auth', 'now', 'resolve', 'request', 'trans', 'session', 'dispatch', 'route', 'url'] as $helper) {
            expect($source)->not->toMatch('/(?<!function )(?<![\w>$:])'.$helper.'\s*\(/', $helper.'() in '.$path);
        }

        expect($source)->not->toMatch('/(?<![\w>$:])__\s*\(/');
    }
});

it('has no float anywhere in the source or the templates', function () {
    // Money is minor units and nothing here renders money at all, so a float
    // would be a figure this package invented.
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        expect($source)->not->toMatch('/\bfloat\b/')
            ->and($source)->not->toContain('(float)')
            ->and($source)->not->toContain('round(')
            ->and($source)->not->toContain('number_format');
    }
});

it('schedules nothing, queues nothing and notifies nobody', function () {
    foreach ([...sourceFiles(), ...filesUnder('resources')] as $path) {
        $source = sourceCode($path);

        foreach (['Schedule', 'dispatchAfterResponse', 'ShouldQueue', 'Artisan::', 'Bus::', 'Notification::', 'Mail::', 'Http::'] as $forbidden) {
            expect($source)->not->toContain($forbidden, $path);
        }
    }
});

it('registers its declared provider and boots nothing by discovery', function () {
    $composer = json_decode((string) file_get_contents(packageRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents(packageRoot().'/module.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['laravel']['providers'] ?? [])->toBe([])
        ->and($manifest['provider'])->toBe(DropshippingLivewireServiceProvider::class)
        ->and($manifest['category'])->toBe('presentation')
        ->and(App::getProvider(DropshippingLivewireServiceProvider::class))->not->toBeNull()
        ->and($composer['version'])->toBe($manifest['version'])
        ->and($composer['extra']['liberu']['name'])->toBe($manifest['name'])
        ->and(array_keys($manifest['requires']['packages']))->toBe(['liberusoftware/ecommerce-dropshipping']);
});

it('declares the domain package as a VCS repository, because it is not on Packagist', function () {
    $composer = json_decode((string) file_get_contents(packageRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['repositories'][0]['type'])->toBe('vcs')
        ->and($composer['repositories'][0]['url'])->toBe('https://github.com/liberusoftware/module-ecommerce-dropshipping');
});

it('binds nothing and registers no route', function () {
    $provider = sourceCode(packageRoot().'/src/DropshippingLivewireServiceProvider.php');

    expect($provider)->not->toContain('->bind(')
        ->and($provider)->not->toContain('->singleton(')
        ->and($provider)->not->toContain('loadRoutesFrom')
        // Neither seam. A shopper page deciding how a merchant talks to a
        // supplier is the wrong package answering the question.
        ->and($provider)->not->toContain('TransmitsPurchases')
        ->and($provider)->not->toContain('ReportsShipments');
});

it('resolves nothing it did not publish', function () {
    // resolveMissingComponent() is consulted for every unresolved name in the
    // application, so a resolver that answered broadly would hijack another
    // package's components.
    expect(Livewire::isDiscoverable('dropshipping::supplier-desk'))->toBeFalse()
        ->and(Livewire::isDiscoverable('loyalty::points-balance'))->toBeFalse();
});

it('ships a view for every component it registers', function () {
    foreach (DropshippingLivewireServiceProvider::COMPONENTS as $name => $class) {
        $view = (string) preg_replace('/^dropshipping::/', '', $name);

        expect(file_exists(packageRoot().'/resources/views/'.$view.'.blade.php'))
            ->toBeTrue("Missing a view for [{$name}].");
    }
});

it('ships every document a module repository owes its consumers', function () {
    foreach (['README.md', 'LICENSE.md', 'CHANGELOG.md', 'docs/domain.md', 'docs/adoption.md', 'docs/runbook.md'] as $file) {
        expect(file_exists(packageRoot().'/'.$file))->toBeTrue("Missing {$file}.");
    }
});

it('documents every component alias and how a host renders it', function () {
    $readme = (string) file_get_contents(packageRoot().'/README.md');
    $adoption = (string) file_get_contents(packageRoot().'/docs/adoption.md');

    foreach (array_keys(DropshippingLivewireServiceProvider::COMPONENTS) as $alias) {
        expect($readme)->toContain($alias)
            ->and($adoption)->toContain($alias);
    }
});

it('tells the host what it owes this component', function () {
    $adoption = (string) file_get_contents(packageRoot().'/docs/adoption.md');

    expect($adoption)->toContain('"type": "vcs"')
        ->and($adoption)->toContain('MODULES_ENABLED')
        // The host authorises the three references before it mounts anything,
        // and nothing in this package can do it for them.
        ->and($adoption)->toContain('destinationRef')
        ->and($adoption)->toContain('idempotency');
});

it('carries no session identifier in any file it ships', function () {
    $files = [
        ...filesUnder('src', 'tests', 'docs', 'resources', '.github'),
        packageRoot().'/README.md',
        packageRoot().'/CHANGELOG.md',
        packageRoot().'/composer.json',
        packageRoot().'/module.json',
    ];

    // The needles are assembled rather than written out, or this file would be
    // the one hit that fails the rule it enforces.
    $needles = [implode('.', ['claude', 'ai']), implode('-', ['Claude', 'Session'])];

    foreach ($files as $path) {
        $contents = (string) file_get_contents($path);

        foreach ($needles as $needle) {
            expect($contents)->not->toContain($needle, $path);
        }
    }
});
