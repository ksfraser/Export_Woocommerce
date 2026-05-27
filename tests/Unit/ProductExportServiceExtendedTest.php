<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class ProductExportServiceExtendedTest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $service;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        
        $this->service = new ProductExportService(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
    }

    public function testUpdateSimpleProductsUpdatesExistingProducts(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00', 'woo_id' => 100, 'woo_last_update' => '2024-01-01'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false && strpos($sql, 'woo_id') !== false => $products,
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('put')->willReturn(['id' => 100, 'date_modified' => '2024-01-15']);

        $result = $this->service->updateSimpleProducts();

        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(1, $result['total']);
    }

    public function testUpdateSimpleProductsHandlesErrors(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00', 'woo_id' => 100],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false && strpos($sql, 'woo_id') !== false => $products,
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('put')->willThrowException(new \Exception('API Error'));

        $result = $this->service->updateSimpleProducts();

        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testUpdateSimpleProductsDebugModeLimitsToOne(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00', 'woo_id' => 100],
            ['stock_id' => 'P002', 'description' => 'Product 2', 'price' => '20.00', 'woo_id' => 200],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false && strpos($sql, 'woo_id') !== false => $products,
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('put')->willReturn(['id' => 100, 'date_modified' => '2024-01-15']);

        $result = $this->service->updateSimpleProducts(true);

        $this->assertEquals(1, $result['updated']);
    }

    public function testMatchProductFindsAndUpdatesWooTable(): void
    {
        $this->mockRestClient->method('get')
            ->willReturn([['id' => 123, 'sku' => 'TEST-001', 'date_modified' => '2024-01-15']]);

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false => [],
                strpos($sql, 'COUNT') !== false => [['cnt' => 1]],
                default => [],
            });

        $result = $this->service->matchProduct('TEST-001');

        $this->assertNotNull($result);
        $this->assertEquals(123, $result['id']);
    }

    public function testMatchProductReturnsNullWhenNotFound(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);

        $result = $this->service->matchProduct('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testProductTagsReturnsTagsWhenTableExists(): void
    {
        $tags = [
            ['tag_name' => 'Summer', 'tag_slug' => 'summer'],
            ['tag_name' => 'Sale', 'tag_slug' => 'sale'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'stock_item_tags') !== false => [['Tables' => '0_stock_item_tags']],
                strpos($sql, 'stock_item_tags') !== false && strpos($sql, 'JOIN') !== false => $tags,
                default => [],
            });

        $result = $this->service->productTags('TEST-001');

        $this->assertCount(2, $result);
        $this->assertEquals('Summer', $result[0]['name']);
        $this->assertEquals('summer', $result[0]['slug']);
    }

    public function testProductTagsReturnsEmptyWhenTableMissing(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->productTags('TEST-001');

        $this->assertEmpty($result);
    }

    public function testProductDefaultAttributesReturnsDefaults(): void
    {
        $defaults = [
            ['attribute_name' => 'color', 'default_value' => 'Red'],
            ['attribute_name' => 'size', 'default_value' => 'M'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false => [['Tables' => '0_product_hierarchy']],
                strpos($sql, 'product_default_attributes') !== false => $defaults,
                default => [],
            });

        $result = $this->service->productDefaultAttributes('VAR-001');

        $this->assertCount(2, $result);
        $this->assertEquals('color', $result[0]['name']);
        $this->assertEquals('Red', $result[0]['option']);
    }

    public function testProductDefaultAttributesReturnsEmptyWhenNoHierarchyTable(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->productDefaultAttributes('VAR-001');

        $this->assertEmpty($result);
    }

    public function testProductVariationsReturnsVariations(): void
    {
        $variations = [
            ['stock_id' => 'VAR-001-S', 'description' => 'Small', 'price' => '10.00', 'instock' => 5, 'sort_order' => 1],
            ['stock_id' => 'VAR-001-M', 'description' => 'Medium', 'price' => '12.00', 'instock' => 3, 'sort_order' => 2],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) use ($variations) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'product_hierarchy') !== false && strpos($sql, 'parent_stock_id') !== false && strpos($sql, 'COUNT') === false) {
                    return $variations;
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return [];
                }
                return [];
            });

        $result = $this->service->productVariations('VAR-001');

        $this->assertCount(2, $result);
        $this->assertEquals('VAR-001-S', $result[0]['sku']);
        $this->assertEquals(5, $result[0]['stock_quantity']);
    }

    public function testProductVariationsReturnsEmptyWhenNoHierarchyTable(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->productVariations('VAR-001');

        $this->assertEmpty($result);
    }

    public function testUpdateWooTableInsertsNewRecord(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->service->updateWooTable('TEST-001', 123, '2024-01-15');

        $this->assertTrue($result);
    }

    public function testUpdateWooTableUpdatesExistingRecord(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'COUNT') !== false => [['cnt' => 1]],
                default => [],
            });

        $result = $this->service->updateWooTable('TEST-001', 123, '2024-01-15');

        $this->assertTrue($result);
    }

    public function testRecodeSkuForExportReplacesSlashes(): void
    {
        $result = $this->service->recodeSkuForExport('CAT/DOG/BIRD');

        $this->assertEquals('CAT_DOG_BIRD', $result);
    }

    public function testRecodeSkuForExportNoSlashes(): void
    {
        $result = $this->service->recodeSkuForExport('SIMPLE-SKU');

        $this->assertEquals('SIMPLE-SKU', $result);
    }

    public function testGetProductBySkuReturnsFullData(): void
    {
        $this->mockRestClient->method('get')
            ->willReturnCallback(fn($endpoint, $params) => match(true) {
                $endpoint === 'products' => [['id' => 123, 'sku' => 'TEST-001']],
                $endpoint === 'products/123' => ['id' => 123, 'sku' => 'TEST-001', 'name' => 'Test Product', 'date_modified' => '2024-01-15'],
                default => [],
            });

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false => [],
                strpos($sql, 'COUNT') !== false => [['cnt' => 1]],
                default => [],
            });

        $result = $this->service->getProductBySku('TEST-001');

        $this->assertNotNull($result);
        $this->assertEquals(123, $result['id']);
        $this->assertEquals('Test Product', $result['name']);
    }

    public function testGetProductBySkuReturnsNullWhenNotFound(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);

        $result = $this->service->getProductBySku('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testExportAllProductsCombinesNewAndUpdates(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false && strpos($sql, 'woo_id') === false => [
                    ['stock_id' => 'P001', 'description' => 'New Product', 'price' => '10.00'],
                ],
                strpos($sql, 'stock_master') !== false && strpos($sql, 'woo_id') !== false => [
                    ['stock_id' => 'P002', 'description' => 'Updated Product', 'price' => '20.00', 'woo_id' => 200],
                ],
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 999]);
        $this->mockRestClient->method('put')->willReturn(['id' => 200, 'date_modified' => '2024-01-15']);

        $result = $this->service->exportAllProducts();

        $this->assertEquals(2, $result['total_exported']);
        $this->assertEquals(1, $result['new']['exported']);
        $this->assertEquals(1, $result['updates']['updated']);
    }
}
