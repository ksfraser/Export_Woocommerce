<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\VariableProductService;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use ksfraser\FrontAccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class VariableProductServiceCoverageTest extends TestCase
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
        $this->service = new VariableProductService(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
    }

    public function testExportVariableProductParentCreateFailsNoId(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Test']];
                }
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [['sku' => 'VAR-001-S', 'price' => '10.00']];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small']];
                }
                return [];
            });

        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn(['error' => 'failed']);

        $result = $this->service->exportVariableProduct('VAR-001');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Failed to create/update parent product', $result['error']);
    }

    public function testExportVariableProductExceptionCaught(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Test']];
                }
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [['sku' => 'VAR-001-S', 'price' => '10.00']];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small']];
                }
                return [];
            });

        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->service->exportVariableProduct('VAR-001');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API Error', $result['error']);
    }

    public function testBuildVariationsSkipsEmptySku(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [['sku' => '', 'price' => '10.00'], ['sku' => 'VAR-001-S', 'price' => '12.00']];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small']];
                }
                return [];
            });

        $result = $this->service->buildVariations('VAR-001');
        $this->assertCount(1, $result);
        $this->assertEquals('VAR-001-S', $result[0]['sku']);
    }

    public function testBuildVariationsWithoutStockQuantity(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [['sku' => 'VAR-001-S', 'price' => '10.00']];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small']];
                }
                return [];
            });

        $result = $this->service->buildVariations('VAR-001');
        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('stock_quantity', $result[0]);
        $this->assertArrayNotHasKey('manage_stock', $result[0]);
    }

    public function testExportVariationsUpdatesExisting(): void
    {
        $variations = [
            ['sku' => 'VAR-001-S', 'regular_price' => '10.00', 'attributes' => []],
        ];

        $this->mockRestClient->method('get')->willReturn([['id' => 200]]);
        $this->mockRestClient->method('put')->willReturn(['id' => 200]);

        $results = $this->service->exportVariations(100, $variations);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals(200, $results[0]['woo_id']);
    }

    public function testExportVariationsExceptionCaught(): void
    {
        $variations = [
            ['sku' => 'VAR-001-S', 'regular_price' => '10.00', 'attributes' => []],
        ];

        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));

        $results = $this->service->exportVariations(100, $variations);
        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertArrayHasKey('error', $results[0]);
    }

    public function testExportAllVariableProductsNoMasters(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->exportAllVariableProducts();
        $this->assertEquals(0, $result['exported']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(0, $result['total']);
    }

    public function testExportAllVariableProductsAllSucceed(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_master') !== false && strpos($sql, 'WHERE') === false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Product 1']];
                }
                if (strpos($sql, 'woo_prod_variable_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Product 1']];
                }
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [['sku' => 'VAR-001-S', 'price' => '10.00']];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small']];
                }
                return [];
            });

        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn(['id' => 100]);

        $result = $this->service->exportAllVariableProducts();
        $this->assertEquals(1, $result['exported']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testGetVariationAttributesEmptyResult(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->getVariationAttributes('VAR-001');
        $this->assertEmpty($result);
    }

    public function testGetAttributesByNameEmptyResult(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->getAttributesByName('Color');
        $this->assertEmpty($result);
    }

    public function testGetSkuCombosEmptyResult(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->getSkuCombos('VAR-001');
        $this->assertEmpty($result);
    }

    public function testGetSkuFullVariationsEmptyResult(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->getSkuFullVariations('VAR-001');
        $this->assertEmpty($result);
    }

    public function testGetAllVariableMastersEmpty(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->getAllVariableMasters();
        $this->assertEmpty($result);
    }

    public function testBuildProductAttributesEmpty(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->buildProductAttributes('VAR-001');
        $this->assertEmpty($result);
    }
}
