<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\VariableProductService;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class VariableProductServiceTest extends TestCase
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

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(VariableProductService::class, $this->service);
    }

    public function testGetVariableMasterReturnsMasterRecord(): void
    {
        $master = ['stock_id' => 'VAR-001', 'description' => 'Variable Product Base'];

        $this->mockDb->method('query')->willReturn([$master]);

        $result = $this->service->getVariableMaster('VAR-001');

        $this->assertNotNull($result);
        $this->assertEquals('VAR-001', $result['stock_id']);
        $this->assertEquals('Variable Product Base', $result['description']);
    }

    public function testGetVariableMasterReturnsNullWhenNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->getVariableMaster('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testGetAllVariableMasters(): void
    {
        $masters = [
            ['stock_id' => 'VAR-001', 'description' => 'Product 1'],
            ['stock_id' => 'VAR-002', 'description' => 'Product 2'],
        ];

        $this->mockDb->method('query')->willReturn($masters);

        $result = $this->service->getAllVariableMasters();

        $this->assertCount(2, $result);
    }

    public function testGetVariationAttributesReturnsAttributesBySku(): void
    {
        $attributes = [
            ['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small'],
            ['sku' => 'VAR-001-S', 'name' => 'Color', 'option' => 'Red'],
            ['sku' => 'VAR-001-M', 'name' => 'Size', 'option' => 'Medium'],
        ];

        $this->mockDb->method('query')->willReturn($attributes);

        $result = $this->service->getVariationAttributes('VAR-001-S');

        $this->assertArrayHasKey('VAR-001-S', $result);
        $this->assertCount(2, $result['VAR-001-S']);
        $this->assertEquals('Size', $result['VAR-001-S'][0]['name']);
        $this->assertEquals('Small', $result['VAR-001-S'][0]['option']);
    }

    public function testGetVariationAttributesFuzzyMatch(): void
    {
        $attributes = [
            ['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small'],
            ['sku' => 'VAR-001-M', 'name' => 'Size', 'option' => 'Medium'],
            ['sku' => 'VAR-001-L', 'name' => 'Size', 'option' => 'Large'],
        ];

        $this->mockDb->method('query')->willReturn($attributes);

        $result = $this->service->getVariationAttributes('VAR-001', true);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('VAR-001-S', $result);
        $this->assertArrayHasKey('VAR-001-M', $result);
    }

    public function testGetAttributesByName(): void
    {
        $attributes = [
            ['sku' => 'VAR-001-S', 'name' => 'Color', 'option' => 'Red'],
            ['sku' => 'VAR-001-M', 'name' => 'Color', 'option' => 'Blue'],
        ];

        $this->mockDb->method('query')->willReturn($attributes);

        $result = $this->service->getAttributesByName('Color');

        $this->assertArrayHasKey('Color', $result);
        $this->assertCount(2, $result['Color']);
    }

    public function testGetSkuCombos(): void
    {
        $combos = [
            ['stock_id' => 'VAR-001', 'variablename' => 'Size', 'priority' => 1],
            ['stock_id' => 'VAR-001', 'variablename' => 'Color', 'priority' => 2],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) use ($combos) {
                $GLOBALS['__last_woo_sql'] = $sql;
                return $combos;
            });

        $result = $this->service->getSkuCombos('VAR-001');

        $this->assertCount(2, $result);
        $this->assertStringContainsString("stock_id = 'VAR-001'", $GLOBALS['__last_woo_sql']);
        $this->assertStringContainsString('ORDER BY priority', $GLOBALS['__last_woo_sql']);
    }

    public function testGetSkuFullVariations(): void
    {
        $variations = [
            ['stock_id' => 'VAR-001', 'sku' => 'VAR-001-S', 'description' => 'Size Small', 'inserted_fa' => 1, 'woo_id' => 0],
            ['stock_id' => 'VAR-001', 'sku' => 'VAR-001-M', 'description' => 'Size Medium', 'inserted_fa' => 1, 'woo_id' => 0],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) use ($variations) {
                $GLOBALS['__last_woo_sql'] = $sql;
                return $variations;
            });

        $result = $this->service->getSkuFullVariations('VAR-001');

        $this->assertCount(2, $result);
        $this->assertEquals('VAR-001-S', $result[0]['sku']);
        $this->assertStringContainsString("stock_id = 'VAR-001'", $GLOBALS['__last_woo_sql']);
        $this->assertStringContainsString('ORDER BY sku', $GLOBALS['__last_woo_sql']);
    }

    public function testBuildVariations(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [
                        ['sku' => 'VAR-001-S', 'price' => '10.00', 'stock_quantity' => 5],
                        ['sku' => 'VAR-001-M', 'price' => '12.00', 'stock_quantity' => 3],
                    ];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [
                        ['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small'],
                        ['sku' => 'VAR-001-M', 'name' => 'Size', 'option' => 'Medium'],
                    ];
                }
                return [];
            });

        $result = $this->service->buildVariations('VAR-001');

        $this->assertCount(2, $result);
        $this->assertEquals('VAR-001-S', $result[0]['sku']);
        $this->assertEquals('10.00', $result[0]['regular_price']);
        $this->assertEquals(5, $result[0]['stock_quantity']);
        $this->assertTrue($result[0]['manage_stock']);
    }

    public function testBuildProductAttributes(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                return [
                    ['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small'],
                    ['sku' => 'VAR-001-M', 'name' => 'Size', 'option' => 'Medium'],
                    ['sku' => 'VAR-001-S', 'name' => 'Color', 'option' => 'Red'],
                    ['sku' => 'VAR-001-M', 'name' => 'Color', 'option' => 'Red'],
                ];
            });

        $result = $this->service->buildProductAttributes('VAR-001');

        $this->assertCount(2, $result);
        
        $sizeAttr = null;
        $colorAttr = null;
        foreach ($result as $attr) {
            if ($attr['name'] === 'Size') $sizeAttr = $attr;
            if ($attr['name'] === 'Color') $colorAttr = $attr;
        }

        $this->assertNotNull($sizeAttr);
        $this->assertTrue($sizeAttr['visible']);
        $this->assertTrue($sizeAttr['variation']);
        $this->assertContains('Small', $sizeAttr['options']);
        $this->assertContains('Medium', $sizeAttr['options']);

        $this->assertNotNull($colorAttr);
        $this->assertCount(1, $colorAttr['options']);
        $this->assertContains('Red', $colorAttr['options']);
    }

    public function testExportVariableProductReturnsErrorWhenMasterNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->exportVariableProduct('NONEXISTENT');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
    }

    public function testExportVariableProductReturnsErrorWhenNoVariations(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Test Product']];
                }
                return [];
            });

        $result = $this->service->exportVariableProduct('VAR-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('No variations', $result['error']);
    }

    public function testExportVariableProductCreatesNewParent(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Test Product']];
                }
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [['sku' => 'VAR-001-S', 'price' => '10.00', 'stock_quantity' => 5]];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small']];
                }
                return [];
            });

        $this->mockRestClient->method('get')
            ->willReturn([]);

        $this->mockRestClient->method('post')
            ->willReturnCallback(function($endpoint, $data) {
                if (strpos($endpoint, 'products') !== false && strpos($endpoint, 'variations') === false) {
                    return ['id' => 100, 'type' => 'variable'];
                }
                return ['id' => 101];
            });

        $result = $this->service->exportVariableProduct('VAR-001');

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['parent_id']);
        $this->assertEquals(1, $result['variation_count']);
    }

    public function testExportVariableProductUpdatesExistingParent(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Test Product']];
                }
                if (strpos($sql, 'woo_prod_variable_sku_full') !== false) {
                    return [['sku' => 'VAR-001-S', 'price' => '10.00', 'stock_quantity' => 5]];
                }
                if (strpos($sql, 'woo_prod_variation_attributes') !== false) {
                    return [['sku' => 'VAR-001-S', 'name' => 'Size', 'option' => 'Small']];
                }
                return [];
            });

        $this->mockRestClient->method('get')
            ->willReturn([['id' => 100, 'sku' => 'VAR-001']]);

        $this->mockRestClient->method('put')
            ->willReturn(['id' => 100, 'type' => 'variable']);

        $this->mockRestClient->method('post')
            ->willReturn(['id' => 101]);

        $result = $this->service->exportVariableProduct('VAR-001');

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['parent_id']);
    }

    public function testExportAllVariableProducts(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_prod_variable_master') !== false && strpos($sql, 'WHERE') === false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Product 1']];
                }
                return [];
            });

        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn(['id' => 100]);

        $result = $this->service->exportAllVariableProducts();

        $this->assertEquals(0, $result['exported']);
        $this->assertEquals(1, $result['failed']);
        $this->assertEquals(1, $result['total']);
    }

    public function testExportVariations(): void
    {
        $variations = [
            ['sku' => 'VAR-001-S', 'regular_price' => '10.00', 'attributes' => [['name' => 'Size', 'option' => 'Small']]],
            ['sku' => 'VAR-001-M', 'regular_price' => '12.00', 'attributes' => [['name' => 'Size', 'option' => 'Medium']]],
        ];

        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn(['id' => 101]);

        $results = $this->service->exportVariations(100, $variations);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals(101, $results[0]['woo_id']);
    }

    public function testExportVariationsHandlesErrors(): void
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
}
