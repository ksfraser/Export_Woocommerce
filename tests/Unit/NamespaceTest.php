<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\ProductService;
use Ksfraser\frontaccounting\Woocommerce\Staging\CustomerStaging;
use Ksfraser\frontaccounting\Woocommerce\Staging\OrderStaging;
use Ksfraser\frontaccounting\Woocommerce\Workflow\WooSyncStateMachine;
use Ksfraser\frontaccounting\Woocommerce\DTO\CustomerDTO;
use Ksfraser\frontaccounting\Woocommerce\Dao\SyncDao;
use Ksfraser\frontaccounting\Woocommerce\UI\ImportExportDispatcher;

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
        $this->assertEquals('Ksfraser\\frontaccounting\\Woocommerce', $reflection->getNamespaceName());
    }

    public function testUsesLowercaseFrontaccounting(): void
    {
        // FA convention is lowercase 'frontaccounting' in namespace
        $this->assertStringContainsString('frontaccounting', ProductService::class);
        $this->assertStringNotContainsString('FrontAccounting', ProductService::class);
    }
}