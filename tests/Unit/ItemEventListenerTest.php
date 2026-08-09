<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\ItemEventListener;
use Ksfraser\frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\frontaccounting\Woocommerce\Dao\StockItemDao;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ItemEventListener - event-driven WooCommerce sync
 *
 * @BABOK Related: FR-WOO-001 item event sync
 */
class ItemEventListenerTest extends TestCase
{
    private $mockStockItemDao;
    private $mockLogger;
    private $mockExporter;
    private $listener;

    protected function setUp(): void
    {
        $this->mockStockItemDao = $this->createMock(StockItemDao::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockExporter = $this->createMock(ProductExportService::class);
        $this->listener = new ItemEventListener(
            $this->mockStockItemDao,
            $this->mockLogger,
            $this->mockExporter
        );
    }

    public function testSyncSkipsEmptyStockId(): void
    {
        $this->mockExporter->expects($this->never())->method('exportProduct');

        $result = $this->listener->sync('', 'created');

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('no_stock_id', $result['reason']);
        $this->assertEquals('created', $result['event']);
    }

    public function testSyncSkipsWhenItemNotFound(): void
    {
        $this->mockStockItemDao->method('getItemForSync')->willReturn(null);
        $this->mockExporter->expects($this->never())->method('exportProduct');

        $result = $this->listener->sync('MISSING-001', 'created');

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('not_found', $result['reason']);
    }

    public function testSyncSkipsInactiveItem(): void
    {
        $this->mockStockItemDao->method('getItemForSync')->willReturn([
            'stock_id' => 'TEST-001', 'description' => 'Test', 'inactive' => 1,
        ]);
        $this->mockExporter->expects($this->never())->method('exportProduct');

        $result = $this->listener->sync('TEST-001', 'updated');

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('inactive', $result['reason']);
    }

    public function testSyncSkipsVariationChild(): void
    {
        $this->mockStockItemDao->method('getItemForSync')->willReturn([
            'stock_id' => 'VAR-001', 'description' => 'Variation', 'inactive' => 0,
        ]);
        $this->mockExporter->method('productType')->willReturn('variation');
        $this->mockExporter->expects($this->never())->method('exportProduct');

        $result = $this->listener->sync('VAR-001', 'created');

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('variation_child', $result['reason']);
    }

    public function testSyncPushesProductOnCreate(): void
    {
        $this->mockStockItemDao->method('getItemForSync')->willReturn([
            'stock_id' => 'TEST-001', 'description' => 'Test Item', 'inactive' => 0,
        ]);
        $this->mockExporter->method('productType')->willReturn('simple');
        $this->mockExporter->method('exportProduct')->willReturn(['id' => 987]);

        $result = $this->listener->sync('TEST-001', 'created');

        $this->assertEquals('pushed', $result['status']);
        $this->assertEquals('created', $result['event']);
        $this->assertEquals(987, $result['woo_id']);
    }

    public function testSyncPushesProductOnUpdate(): void
    {
        $this->mockStockItemDao->method('getItemForSync')->willReturn([
            'stock_id' => 'TEST-001', 'description' => 'Test Item', 'inactive' => 0,
        ]);
        $this->mockExporter->method('productType')->willReturn('simple');
        $this->mockExporter->method('exportProduct')->willReturn(['id' => 987]);

        $result = $this->listener->sync('TEST-001', 'updated');

        $this->assertEquals('pushed', $result['status']);
        $this->assertEquals('updated', $result['event']);
        $this->assertEquals(987, $result['woo_id']);
    }

    public function testSyncPushesProductWithoutWooId(): void
    {
        $this->mockStockItemDao->method('getItemForSync')->willReturn([
            'stock_id' => 'TEST-001', 'description' => 'Test Item', 'inactive' => 0,
        ]);
        $this->mockExporter->method('productType')->willReturn('simple');
        $this->mockExporter->method('exportProduct')->willReturn([]);

        $result = $this->listener->sync('TEST-001', 'created');

        $this->assertEquals('pushed', $result['status']);
        $this->assertArrayHasKey('woo_id', $result);
        $this->assertNull($result['woo_id']);
    }

    public function testSyncReportsFailureOnException(): void
    {
        $this->mockStockItemDao->method('getItemForSync')->willReturn([
            'stock_id' => 'TEST-001', 'description' => 'Test Item', 'inactive' => 0,
        ]);
        $this->mockExporter->method('productType')->willReturn('simple');
        $this->mockExporter->method('exportProduct')->willThrowException(
            new \RuntimeException('API down')
        );

        $result = $this->listener->sync('TEST-001', 'created');

        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('API down', $result['reason']);
    }

    public function testSyncFetchesItemThroughStockItemDao(): void
    {
        $this->mockExporter->method('productType')->willReturn('simple');
        $this->mockExporter->method('exportProduct')->willReturn(['id' => 1]);

        $this->mockStockItemDao->expects($this->once())
            ->method('getItemForSync')
            ->with('TEST-001')
            ->willReturn([
                'stock_id' => 'TEST-001', 'description' => 'Test Item', 'inactive' => 0,
            ]);

        $this->listener->sync('TEST-001', 'created');
    }
}
