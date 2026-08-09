<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use Ksfraser\frontaccounting\Woocommerce\Exceptions\WooApiException;
use PHPUnit\Framework\TestCase;

class ProductExportServiceCoverageTest extends TestCase
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

    public function testAddDimensionsAndWeightTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_dimensions') !== false => [],
                default => [],
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test', 'weight' => '5.0'];
        $result = $this->service->buildProductData($faData);

        // Weight from faData is always applied first; table not found means no dimensions mapping
        $this->assertArrayHasKey('weight', $result);
        $this->assertEquals('5.0', $result['weight']);
        $this->assertArrayNotHasKey('dimensions', $result);
    }

    public function testBuildProductDataEmitsStockStatusWhenInStock(): void
    {
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test', 'instock' => 7];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals(7, $result['stock_quantity']);
        $this->assertTrue($result['manage_stock']);
        $this->assertEquals('instock', $result['stock_status']);
        $this->assertArrayNotHasKey('in_stock', $result);
    }

    public function testBuildProductDataEmitsOutOfStockAndOmitsLegacyInStock(): void
    {
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test', 'instock' => 0];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals(0, $result['stock_quantity']);
        $this->assertTrue($result['manage_stock']);
        $this->assertEquals('outofstock', $result['stock_status']);
        $this->assertArrayNotHasKey('in_stock', $result);
    }

    public function testAddDimensionsAndWeightTableExistsWithData(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_dimensions') !== false) {
                    return [['Tables_in_db' => '0_product_dimensions']];
                }
                if (strpos($sql, 'product_dimensions') !== false && strpos($sql, 'SHOW') === false) {
                    return [[
                        'weight' => 2.5,
                        'weight_unit' => 'lb',
                        'length' => 10,
                        'width' => 5,
                        'height' => 3,
                        'dim_unit' => 'in',
                    ]];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals('2.5', $result['weight']);
        $this->assertArrayNotHasKey('weight_unit', $result);
        $this->assertEquals('10', $result['dimensions']['length']);
        $this->assertEquals('5', $result['dimensions']['width']);
        $this->assertEquals('3', $result['dimensions']['height']);
        $this->assertArrayNotHasKey('unit', $result['dimensions']);
    }

    public function testAddDimensionsAndWeightDefaultUnitsNotAdded(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_dimensions') !== false) {
                    return [['Tables_in_db' => '0_product_dimensions']];
                }
                if (strpos($sql, 'product_dimensions') !== false && strpos($sql, 'SHOW') === false) {
                    return [[
                        'weight' => 1.0,
                        'weight_unit' => 'kg',
                        'length' => 10,
                        'width' => 5,
                        'height' => 3,
                        'dim_unit' => 'cm',
                    ]];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertArrayHasKey('weight', $result);
        $this->assertArrayNotHasKey('weight_unit', $result);
        $this->assertArrayNotHasKey('unit', $result['dimensions']);
    }

    public function testAddDimensionsAndWeightEmptyResult(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_dimensions') !== false) {
                    return [['Tables_in_db' => '0_product_dimensions']];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertArrayNotHasKey('weight', $result);
        $this->assertArrayNotHasKey('dimensions', $result);
    }

    public function testAddImagesTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_media') !== false => [],
                default => [],
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertArrayNotHasKey('images', $result);
    }

    public function testAddImagesWithMediaUrl(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_media') !== false) {
                    return [['Tables_in_db' => '0_product_media']];
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
        $result = $this->service->buildProductData($faData);

        $this->assertCount(2, $result['images']);
        $this->assertEquals('https://example.com/img1.jpg', $result['images'][0]['src']);
        $this->assertEquals('Image 1', $result['images'][0]['alt']);
    }

    public function testAddImagesWithFilePathFallback(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_media') !== false) {
                    return [['Tables_in_db' => '0_product_media']];
                }
                if (strpos($sql, 'product_media') !== false && strpos($sql, 'SHOW') === false) {
                    return [['file_path' => '/path/to/img.jpg', 'alt_text' => null, 'sort_order' => 0]];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals('/path/to/img.jpg', $result['images'][0]['src']);
        $this->assertEquals('TEST-001', $result['images'][0]['name']);
    }

    public function testAddShippingAttributesTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_attributes') !== false => [],
                default => [],
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertArrayNotHasKey('shipping_class', $result);
    }

    public function testAddShippingAttributesHazardous(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_attributes') !== false) {
                    return [['Tables_in_db' => '0_product_shipping_attributes']];
                }
                if (strpos($sql, 'product_shipping_attributes') !== false && strpos($sql, 'SHOW') === false) {
                    return [['is_hazardous' => 1, 'hs_code' => '']];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals('hazardous', $result['shipping_class']);
    }

    public function testAddShippingAttributesHsCode(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_attributes') !== false) {
                    return [['Tables_in_db' => '0_product_shipping_attributes']];
                }
                if (strpos($sql, 'product_shipping_attributes') !== false && strpos($sql, 'SHOW') === false) {
                    return [['is_hazardous' => 0, 'hs_code' => '123456']];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertArrayNotHasKey('shipping_class', $result);
        $metaKeys = array_column($result['meta_data'], 'key');
        $this->assertContains('hs_code', $metaKeys);
    }

    public function testAddShippingAttributesBothHazardousAndHsCode(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_attributes') !== false) {
                    return [['Tables_in_db' => '0_product_shipping_attributes']];
                }
                if (strpos($sql, 'product_shipping_attributes') !== false && strpos($sql, 'SHOW') === false) {
                    return [['is_hazardous' => 1, 'hs_code' => '789012']];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals('hazardous', $result['shipping_class']);
        $metaKeys = array_column($result['meta_data'], 'key');
        $this->assertContains('hs_code', $metaKeys);
    }

    public function testAddProductIdentifiersTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_identifiers') !== false => [],
                default => [],
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertArrayNotHasKey('meta_data', $result);
    }

    public function testAddProductIdentifiersAllPresent(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_identifiers') !== false) {
                    return [['Tables_in_db' => '0_product_identifiers']];
                }
                if (strpos($sql, 'product_identifiers') !== false && strpos($sql, 'SHOW') === false) {
                    return [['upc' => '123', 'ean' => '456', 'gtin' => '789']];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $metaKeys = array_column($result['meta_data'], 'key');
        $this->assertContains('_upc', $metaKeys);
        $this->assertContains('_ean', $metaKeys);
        $this->assertContains('_gtin', $metaKeys);
    }

    public function testAddProductIdentifiersPartial(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_identifiers') !== false) {
                    return [['Tables_in_db' => '0_product_identifiers']];
                }
                if (strpos($sql, 'product_identifiers') !== false && strpos($sql, 'SHOW') === false) {
                    return [['upc' => '123', 'ean' => '', 'gtin' => '']];
                }
                return [];
            });

        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $metaKeys = array_column($result['meta_data'], 'key');
        $this->assertContains('_upc', $metaKeys);
        $this->assertNotContains('_ean', $metaKeys);
        $this->assertNotContains('_gtin', $metaKeys);
    }

    public function testGetProductAttributesTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false => [],
                default => [],
            });

        $faData = ['stock_id' => 'VAR-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals('simple', $result['type']);
        $this->assertArrayNotHasKey('attributes', $result);
    }

    public function testGetProductAttributesWithAttributes(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables_in_db' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [];
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 1]];
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return [
                        ['category_code' => 'color', 'value_label' => 'Red', 'attribute_name' => 'Color', 'attribute_values' => ''],
                    ];
                }
                return [];
            });

        $faData = ['stock_id' => 'VAR-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals('variable', $result['type']);
        $this->assertCount(1, $result['attributes']);
        $this->assertEquals('color', $result['attributes'][0]['name']);
        $this->assertEquals(['Red'], $result['attributes'][0]['options']);
        $this->assertTrue($result['attributes'][0]['visible']);
        $this->assertTrue($result['attributes'][0]['variation']);
    }

    public function testGetProductAttributesFallbackToAttributeValues(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables_in_db' => '0_product_hierarchy']];
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

        $faData = ['stock_id' => 'VAR-001', 'description' => 'Test'];
        $result = $this->service->buildProductData($faData);

        $this->assertEquals('variable', $result['type']);
        $this->assertCount(1, $result['attributes']);
        $this->assertEquals(['A', 'B', 'C'], $result['attributes'][0]['options']);
    }

    public function testProductTagsTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'stock_item_tags') !== false => [],
                default => [],
            });

        $result = $this->service->productTags('TEST-001');
        $this->assertEmpty($result);
    }

    public function testProductTagsReturnsTags(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'stock_item_tags') !== false) {
                    return [['Tables_in_db' => '0_stock_item_tags']];
                }
                if (strpos($sql, 'stock_item_tags') !== false && strpos($sql, 'SHOW') === false) {
                    return [
                        ['tag_name' => 'Sale', 'tag_slug' => 'sale'],
                        ['tag_name' => 'New', 'tag_slug' => 'new'],
                    ];
                }
                return [];
            });

        $result = $this->service->productTags('TEST-001');
        $this->assertCount(2, $result);
        $this->assertEquals('Sale', $result[0]['name']);
        $this->assertEquals('sale', $result[0]['slug']);
    }

    public function testProductTagsMissingFields(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'stock_item_tags') !== false) {
                    return [['Tables_in_db' => '0_stock_item_tags']];
                }
                if (strpos($sql, 'stock_item_tags') !== false && strpos($sql, 'SHOW') === false) {
                    return [['tag_name' => '', 'tag_slug' => '']];
                }
                return [];
            });

        $result = $this->service->productTags('TEST-001');
        $this->assertCount(1, $result);
        $this->assertEquals('', $result[0]['name']);
        $this->assertEquals('', $result[0]['slug']);
    }

    public function testProductDefaultAttributesTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false => [],
                default => [],
            });

        $result = $this->service->productDefaultAttributes('VAR-001');
        $this->assertEmpty($result);
    }

    public function testProductDefaultAttributesReturnsDefaults(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables_in_db' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'product_default_attributes') !== false) {
                    return [
                        ['attribute_name' => 'color', 'default_value' => 'Red'],
                        ['attribute_name' => 'size', 'default_value' => 'M'],
                    ];
                }
                return [];
            });

        $result = $this->service->productDefaultAttributes('VAR-001');
        $this->assertCount(2, $result);
        $this->assertEquals('color', $result[0]['name']);
        $this->assertEquals('Red', $result[0]['option']);
    }

    public function testProductVariationsTableDoesNotExist(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false => [],
                default => [],
            });

        $result = $this->service->productVariations('VAR-001');
        $this->assertEmpty($result);
    }

    public function testProductVariationsReturnsVariations(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables_in_db' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'product_hierarchy') !== false && strpos($sql, 'JOIN') !== false) {
                    return [
                        ['stock_id' => 'VAR-001-S', 'description' => 'Small', 'price' => '10.00', 'instock' => 5],
                        ['stock_id' => 'VAR-001-M', 'description' => 'Medium', 'price' => '12.00', 'instock' => 3],
                    ];
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return [];
                }
                return [];
            });

        $result = $this->service->productVariations('VAR-001');
        $this->assertCount(2, $result);
        $this->assertEquals('VAR-001-S', $result[0]['sku']);
        $this->assertEquals('10.00', $result[0]['regular_price']);
        $this->assertEquals(5, $result[0]['stock_quantity']);
    }

    public function testExportVariableProductParentNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->service->exportVariableProduct('NONEXISTENT', []);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Parent product not found', $result['error']);
    }

    public function testExportVariableProductParentCreateFails(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => [['stock_id' => 'VAR-001', 'description' => 'Test']],
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['error' => 'failed']);

        $result = $this->service->exportVariableProduct('VAR-001', []);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Failed to create parent product', $result['error']);
    }

    public function testExportVariableProductParentExistsPut(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => [['stock_id' => 'VAR-001', 'description' => 'Test', 'woo_id' => 100]],
                default => [],
            });

        $this->mockRestClient->method('put')->willReturn(['id' => 100]);

        $result = $this->service->exportVariableProduct('VAR-001', []);
        $this->assertEquals(100, $result['parent_id']);
    }

    public function testExportVariableProductSomeVariationsFail(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => [['stock_id' => 'VAR-001', 'description' => 'Test']],
                default => [],
            });

        $postCallCount = 0;
        $this->mockRestClient->method('post')
            ->willReturnCallback(function() use (&$postCallCount) {
                $postCallCount++;
                if ($postCallCount === 1) {
                    return ['id' => 100];
                }
                return ['id' => 101];
            });

        $variations = [
            ['sku' => 'VAR-001-S', 'price' => '10.00', 'stock' => 5, 'attributes' => []],
            ['sku' => 'VAR-001-M', 'price' => '12.00', 'stock' => 3, 'attributes' => []],
        ];

        $result = $this->service->exportVariableProduct('VAR-001', $variations);
        $this->assertEquals(100, $result['parent_id']);
        $this->assertCount(2, $result['variations']);
    }

    public function testExportVariableProductSendsManageStockAndStockStatus(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => [['stock_id' => 'VAR-001', 'description' => 'Test']],
                default => [],
            });

        $captured = [];
        $this->mockRestClient->method('post')
            ->willReturnCallback(function($endpoint, $data = []) use (&$captured) {
                $id = 100 + count($captured);
                $captured[] = $data;
                return ['id' => $id];
            });

        $variations = [
            ['sku' => 'VAR-001-S', 'price' => '10.00', 'stock' => 5, 'attributes' => []],
            ['sku' => 'VAR-001-M', 'price' => '12.00', 'stock' => 0, 'attributes' => []],
        ];

        $result = $this->service->exportVariableProduct('VAR-001', $variations);
        $this->assertEquals(100, $result['parent_id']);
        $this->assertCount(2, $result['variations']);

        $this->assertTrue($captured[1]['manage_stock']);
        $this->assertEquals(5, $captured[1]['stock_quantity']);
        $this->assertEquals('instock', $captured[1]['stock_status']);

        $this->assertTrue($captured[2]['manage_stock']);
        $this->assertEquals(0, $captured[2]['stock_quantity']);
        $this->assertEquals('outofstock', $captured[2]['stock_status']);
    }

    public function testUpdateSimpleProductsDebugMode(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00', 'inactive' => 0, 'woo_id' => 1, 'woo_last_update' => '2020-01-01', 'updated_ts' => '2020-01-01'],
            ['stock_id' => 'P002', 'description' => 'Product 2', 'price' => '20.00', 'inactive' => 0, 'woo_id' => 2, 'woo_last_update' => '2020-01-01', 'updated_ts' => '2020-01-01'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => $products,
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('put')->willReturn(['id' => 1, 'date_modified' => '2024-01-01']);

        $result = $this->service->updateSimpleProducts(true);
        $this->assertEquals(1, $result['updated']);
    }

    public function testUpdateSimpleProductsSkipsNonSimple(): void
    {
        $products = [
            ['stock_id' => 'VAR-001', 'description' => 'Variable', 'price' => '10.00', 'inactive' => 0, 'woo_id' => 1, 'woo_last_update' => '2020-01-01', 'updated_ts' => '2020-01-01'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) use ($products) {
                if (strpos($sql, 'stock_master') !== false) {
                    return $products;
                }
                if (strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false) {
                    return [['Tables_in_db' => '0_product_hierarchy']];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [];
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 1]];
                }
                return [];
            });

        $result = $this->service->updateSimpleProducts();
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['total']);
    }

    public function testUpdateSimpleProductsPutFails(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00', 'inactive' => 0, 'woo_id' => 1, 'woo_last_update' => '2020-01-01', 'updated_ts' => '2020-01-01'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => $products,
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('put')->willReturn(['error' => 'failed']);

        $result = $this->service->updateSimpleProducts();
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testUpdateSimpleProductsExceptionCaught(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00', 'inactive' => 0, 'woo_id' => 1, 'woo_last_update' => '2020-01-01', 'updated_ts' => '2020-01-01'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => $products,
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('put')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->service->updateSimpleProducts();
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testExportAllProducts(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => [],
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 1]);

        $result = $this->service->exportAllProducts();
        $this->assertArrayHasKey('new', $result);
        $this->assertArrayHasKey('updates', $result);
        $this->assertArrayHasKey('total_exported', $result);
    }

    public function testRecodeSkuForExport(): void
    {
        $result = $this->service->recodeSkuForExport('TEST/001');
        $this->assertEquals('TEST_001', $result);
    }

    public function testMatchProductFound(): void
    {
        $this->mockRestClient->method('get')->willReturn([['id' => 123, 'sku' => 'TEST-001', 'date_modified' => '2024-01-01']]);
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false => [],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->service->matchProduct('TEST-001');
        $this->assertNotNull($result);
        $this->assertEquals(123, $result['id']);
    }

    public function testMatchProductNotFound(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);

        $result = $this->service->matchProduct('NONEXISTENT');
        $this->assertNull($result);
    }

    public function testGetProductBySkuFound(): void
    {
        $this->mockRestClient->method('get')
            ->willReturnCallback(function($endpoint, $params = []) {
                if ($endpoint === 'products') {
                    return [['id' => 123, 'sku' => 'TEST-001']];
                }
                return ['id' => 123, 'sku' => 'TEST-001', 'date_modified' => '2024-01-01'];
            });
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false => [],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->service->getProductBySku('TEST-001');
        $this->assertNotNull($result);
        $this->assertEquals(123, $result['id']);
    }

    public function testGetProductBySkuNotFound(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);

        $result = $this->service->getProductBySku('NONEXISTENT');
        $this->assertNull($result);
    }

    public function testUpdateWooTableInsert(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->service->updateWooTable('TEST-001', 123, '2024-01-01');
        $this->assertTrue($result);
    }

    public function testUpdateWooTableUpdate(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'COUNT') !== false => [['cnt' => 1]],
                default => [],
            });
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->service->updateWooTable('TEST-001', 123, '2024-01-01');
        $this->assertTrue($result);
    }

    public function testUpdateWooTableException(): void
    {
        $this->mockDb->method('query')
            ->willThrowException(new \Exception('DB Error'));

        $result = $this->service->updateWooTable('TEST-001', 123, '2024-01-01');
        $this->assertFalse($result);
    }

    public function testAddProductAttributesException(): void
    {
        $this->mockRestClient->method('put')
            ->willThrowException(new WooApiException('API Error'));

        $result = $this->service->addProductAttributes(123, []);
        $this->assertFalse($result);
    }
}
