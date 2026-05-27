<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\ProductDataBuilder;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

class ProductDataBuilderExtendedTest extends TestCase
{
    private $mockDb;
    private $mockLogger;
    private $builder;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->builder = new ProductDataBuilder($this->mockDb, $this->mockLogger);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ProductDataBuilder::class, $this->builder);
    }

    public function testBuildSimpleProduct(): void
    {
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test Product', 'price' => '10.00'];
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });
        $result = $this->builder->build($faData);
        $this->assertEquals('TEST-001', $result['sku']);
        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals('simple', $result['type']);
        $this->assertEquals('10.00', $result['regular_price']);
    }

    public function testBuildWithStockQuantity(): void
    {
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test', 'instock' => 50];
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->builder->build($faData);
        $this->assertEquals(50, $result['stock_quantity']);
        $this->assertTrue($result['manage_stock']);
        $this->assertTrue($result['in_stock']);
    }

    public function testBuildWithZeroStock(): void
    {
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test', 'instock' => 0];
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->builder->build($faData);
        $this->assertEquals(0, $result['stock_quantity']);
        $this->assertFalse($result['in_stock']);
    }

    public function testBuildWithLongDescription(): void
    {
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Short', 'long_description' => 'Long desc'];
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->builder->build($faData);
        $this->assertEquals('Long desc', $result['description']);
    }

    public function testAddDimensionsAndWeightFromDimensionsTable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_dimensions') !== false) {
                    return [['Tables' => '0_product_dimensions']];
                }
                if (strpos($sql, 'product_dimensions') !== false && strpos($sql, 'SHOW') === false) {
                    return [[
                        'weight' => 5.5,
                        'weight_unit' => 'lb',
                        'length' => 10,
                        'width' => 5,
                        'height' => 2,
                        'dim_unit' => 'in',
                    ]];
                }
                return [];
            });
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test', 'price' => '10.00'];
        $result = $this->builder->build($faData);
        $this->assertEquals('5.5', $result['weight']);
        $this->assertEquals('lb', $result['weight_unit']);
        $this->assertEquals('10', $result['dimensions']['length']);
        $this->assertEquals('5', $result['dimensions']['width']);
        $this->assertEquals('2', $result['dimensions']['height']);
        $this->assertEquals('in', $result['dimensions']['unit']);
    }

    public function testAddDimensionsAndWeightFallbackToFaData(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test', 'weight' => '3.5'];
        $result = $this->builder->build($faData);
        $this->assertEquals('3.5', $result['weight']);
    }

    public function testAddImagesFromProductMediaTable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_media') !== false) {
                    return [['Tables' => '0_product_media']];
                }
                if (strpos($sql, 'product_media') !== false && strpos($sql, 'SHOW') === false) {
                    return [
                        ['media_url' => 'https://example.com/img1.jpg', 'alt_text' => 'Image 1', 'sort_order' => 1],
                        ['media_url' => 'https://example.com/img2.jpg', 'alt_text' => 'Image 2', 'sort_order' => 2],
                    ];
                }
                return [];
            });
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->builder->build($faData);
        $this->assertCount(2, $result['images']);
        $this->assertEquals('https://example.com/img1.jpg', $result['images'][0]['src']);
        $this->assertEquals('Image 1', $result['images'][0]['alt']);
    }

    public function testAddImagesWithFilePathFallback(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_media') !== false) {
                    return [['Tables' => '0_product_media']];
                }
                if (strpos($sql, 'product_media') !== false && strpos($sql, 'SHOW') === false) {
                    return [['file_path' => '/path/to/img.jpg', 'alt_text' => '', 'sort_order' => 0]];
                }
                return [];
            });
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->builder->build($faData);
        $this->assertEquals('/path/to/img.jpg', $result['images'][0]['src']);
    }

    public function testAddShippingAttributesHazardous(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_attributes') !== false) {
                    return [['Tables' => '0_product_shipping_attributes']];
                }
                if (strpos($sql, 'product_shipping_attributes') !== false && strpos($sql, 'SHOW') === false) {
                    return [['is_hazardous' => 1, 'hs_code' => '1234']];
                }
                return [];
            });
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->builder->build($faData);
        $this->assertEquals('hazardous', $result['shipping_class']);
        $this->assertArrayHasKey('meta_data', $result);
    }

    public function testAddProductIdentifiers(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_identifiers') !== false) {
                    return [['Tables' => '0_product_identifiers']];
                }
                if (strpos($sql, 'product_identifiers') !== false && strpos($sql, 'SHOW') === false) {
                    return [['upc' => '123456789', 'ean' => '1234567890123', 'gtin' => '12345678901234']];
                }
                return [];
            });
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->builder->build($faData);
        $this->assertArrayHasKey('meta_data', $result);
        $metaKeys = array_column($result['meta_data'], 'key');
        $this->assertContains('_upc', $metaKeys);
        $this->assertContains('_ean', $metaKeys);
        $this->assertContains('_gtin', $metaKeys);
    }

    public function testGetProductAttributesForVariableProduct(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [];
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 2]];
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return [
                        ['category_code' => 'color', 'value_label' => 'Red', 'attribute_name' => 'Color', 'attribute_values' => ''],
                        ['category_code' => 'size', 'value_label' => 'M', 'attribute_name' => 'Size', 'attribute_values' => ''],
                    ];
                }
                return [];
            });
        $faData = ['stock_id' => 'VAR-001', 'description' => 'Variable'];
        $result = $this->builder->build($faData);
        $this->assertEquals('variable', $result['type']);
        $this->assertCount(2, $result['attributes']);
    }

    public function testGetProductAttributesWithFallbackValues(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [];
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 1]];
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return [
                        ['category_code' => null, 'value_label' => null, 'attribute_name' => 'Custom', 'attribute_values' => 'A,B,C'],
                    ];
                }
                return [];
            });
        $faData = ['stock_id' => 'VAR-001', 'description' => 'Variable'];
        $result = $this->builder->build($faData);
        $this->assertEquals('variable', $result['type']);
        $this->assertEquals(['A', 'B', 'C'], $result['attributes'][0]['options']);
    }

    public function testDetermineProductTypeSimple(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->builder->determineProductType('TEST-001');
        $this->assertEquals('simple', $result);
    }

    public function testDetermineProductTypeVariation(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    return [['Tables' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [['parent_stock_id' => 'VAR-001']];
                }
                return [];
            });
        $result = $this->builder->determineProductType('VAR-001-S');
        $this->assertEquals('variation', $result);
    }

    public function testDetermineProductTypeVariable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    return [['Tables' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [];
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 3]];
                }
                return [];
            });
        $result = $this->builder->determineProductType('VAR-001');
        $this->assertEquals('variable', $result);
    }
}
