<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\Dropshipping\Actions\PublishSupplyOffer;
use Liberu\Ecommerce\Dropshipping\Actions\RecordSupplierReport;
use Liberu\Ecommerce\Dropshipping\Actions\RegisterSupplier;
use Liberu\Ecommerce\Dropshipping\Actions\RoutePurchases;
use Liberu\Ecommerce\Dropshipping\Data\Money;
use Liberu\Ecommerce\Dropshipping\Data\ProblemReport;
use Liberu\Ecommerce\Dropshipping\Data\RequestedLine;
use Liberu\Ecommerce\Dropshipping\Data\ShipmentReport;
use Liberu\Ecommerce\Dropshipping\Data\SupplierReport;
use Liberu\Ecommerce\Dropshipping\Enums\Availability;
use Liberu\Ecommerce\Dropshipping\Enums\EventKind;
use Liberu\Ecommerce\Dropshipping\Livewire\Tests\TestCase;
use Liberu\Ecommerce\Dropshipping\Models\Purchase;
use Liberu\Ecommerce\Dropshipping\Models\Supplier;

pest()->extends(TestCase::class)->in('Feature', 'Unit');

/*
 * Fixtures build every row through the domain's own actions. Tests may name a
 * model; `src/` may not, and a boundary case asserts that.
 */

function at(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'UTC');
}

function supplier(string $tenantId = 'tenant-a', string $code = 'acme', string $timezone = 'Europe/London'): Supplier
{
    return (new RegisterSupplier())(
        tenantId: $tenantId,
        code: $code,
        name: 'Acme Supply',
        currency: 'GBP',
        timezone: $timezone,
        defaultLeadTimeDays: 3,
        credentialRef: 'secret://'.$code,
        isActive: true,
    );
}

function offer(Supplier $supplier, string $productRef = 'product-1', int $costMinor = 1000, int $leadTimeDays = 3): void
{
    (new PublishSupplyOffer())(
        supplier: $supplier,
        productRef: $productRef,
        supplierSku: strtoupper($supplier->code).'-'.$productRef,
        cost: new Money($costMinor, $supplier->currency, $supplier->currency_exponent),
        leadTimeDays: $leadTimeDays,
        availability: Availability::InStock,
        minimumQuantity: 1,
    );
}

function line(string $lineRef = 'line-1', string $productRef = 'product-1', int $quantity = 1): RequestedLine
{
    return new RequestedLine($lineRef, $productRef, $quantity);
}

/**
 * One purchase, of three of one product and two of another, so partial despatch
 * is expressible.
 *
 * @return array{0: Purchase, 1: Supplier}
 */
function routedPurchase(
    string $tenantId = 'tenant-a',
    string $orderRef = 'order-1',
    string $destinationRef = 'ship-to-1',
    string $code = 'acme',
): array {
    $supplier = supplier($tenantId, $code);
    offer($supplier, 'product-1');
    offer($supplier, 'product-2', costMinor: 500);

    $purchases = (new RoutePurchases())(
        $tenantId,
        $orderRef,
        $destinationRef,
        [line('line-1', 'product-1', 3), line('line-2', 'product-2', 2)],
        at('2026-03-01T09:00:00Z'),
    );

    return [$purchases[0], $supplier];
}

function acknowledge(Purchase $purchase, ?string $promisedBy = '2026-03-05', string $ref = 'ACK-1'): void
{
    (new RecordSupplierReport())($purchase, new SupplierReport(
        providerReference: 'PROV-1',
        acknowledgementRef: $ref,
        acknowledgedAt: at('2026-03-01T10:00:00Z'),
        actualCost: new Money(3500, 'GBP'),
        promisedBy: $promisedBy,
    ));
}

/** @param  array<string, int>  $lines */
function despatch(
    Purchase $purchase,
    array $lines,
    string $causeRef = 'SHIP-1',
    string $at = '2026-03-04T08:00:00Z',
    ?string $carrier = 'dhl',
    string $trackingRef = 'TRACK-1',
): void {
    (new RecordSupplierReport())($purchase, new SupplierReport(
        shipments: [new ShipmentReport($causeRef, at($at), $carrier ?? '', $trackingRef, $lines)],
    ));
}

function raiseProblem(Purchase $purchase, string $code = 'address_invalid', string $field = 'destination.postcode'): void
{
    (new RecordSupplierReport())($purchase, new SupplierReport(
        problems: [new ProblemReport('EXC-1', at('2026-03-02T08:00:00Z'), EventKind::Rejected, $code, $field)],
    ));
}

/** The three mount arguments the one component in this package takes. */
function mountFor(string $tenantId = 'tenant-a', string $orderRef = 'order-1', string $destinationRef = 'ship-to-1'): array
{
    return [
        'tenantId' => $tenantId,
        'destinationRef' => $destinationRef,
        'orderRef' => $orderRef,
    ];
}

/** @return list<string> every file under the given package directories, absolute. */
function filesUnder(string ...$directories): array
{
    $paths = [];

    foreach ($directories as $directory) {
        $path = dirname(__DIR__).'/'.$directory;

        if (! is_dir($path)) {
            continue;
        }

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $paths[] = $file->getPathname();
            }
        }
    }

    sort($paths);

    return $paths;
}

/** @return list<string> */
function sourceFiles(): array
{
    return array_values(array_filter(
        filesUnder('src'),
        static fn (string $path): bool => str_ends_with($path, '.php'),
    ));
}

/**
 * A source file with its comments stripped.
 *
 * Every boundary rule here is about what the code does, and these files name the
 * things they refuse to render — so a naive grep for `cost` or `supplier` finds
 * the prose describing the refusal rather than a leak.
 */
function sourceCode(string $path): string
{
    $contents = (string) file_get_contents($path);

    if (str_ends_with($path, '.blade.php')) {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
    }

    if (! str_ends_with($path, '.php')) {
        return $contents;
    }

    $code = '';

    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}
