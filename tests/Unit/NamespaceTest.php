<?php
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\ProductService;
use ksfraser\FrontAccounting\Woocommerce\Staging\CustomerStaging;
use ksfraser\FrontAccounting\Woocommerce\Staging\OrderStaging;
use ksfraser\FrontAccounting\Woocommerce\Workflow\WooSyncStateMachine;
use ksfraser\FrontAccounting\Woocommerce\DTO\CustomerDTO;
use ksfraser\FrontAccounting\Woocommerce\Dao\SyncDao;
use ksfraser\FrontAccounting\Woocommerce\UI\ImportExportDispatcher;

use PHPUnit\Framework\TestCase;

/**
 * Namespace and basic architecture tests
 */
class NamespaceTest extends TestCase
{
    public function testNamespaceExists(): void
    {
        $this->assertTrue(class_exists(ProductService::class));
        $this->assertTrue(class_exists(CustomerStaging::class));
        $this->assertTrue(class_exists(OrderStaging::class));
        $this->assertTrue(class_exists(WooSyncStateMachine::class));
        $this->assertTrue(class_exists(CustomerDTO::class));
        $this->assertTrue(class_exists(SyncDao::class));
        $this->assertTrue(class_exists(ImportExportDispatcher::class));
    }

    public function testNamespaceIsCorrect(): void
    {
        $reflection = new \ReflectionClass(ProductService::class);
        $this->assertEquals('ksfraser\\FrontAccounting\\Woocommerce', $reflection->getNamespaceName());
    }

    public function testUsesFrontAccountingNamespaceConvention(): void
    {
        // Master convention: ksfraser\FrontAccounting\<ModuleName>\
        $this->assertStringContainsString('ksfraser', ProductService::class);
        $this->assertStringContainsString('FrontAccounting', ProductService::class);
        $this->assertStringNotContainsString('Ksfraser', ProductService::class);
    }
}