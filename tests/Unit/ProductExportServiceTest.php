<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;
use ksfraser\FrontAccounting\Woocommerce\UI\ImportExportDispatcher;
use ksfraser\FrontAccounting\Woocommerce\OrderExporter;
use ksfraser\FrontAccounting\Woocommerce\CustomerExporter;
use ksfraser\FrontAccounting\Woocommerce\CategoryExporter;
use ksfraser\FrontAccounting\Woocommerce\ProductService;
use ksfraser\FrontAccounting\Woocommerce\ProductExportService;
use ksfraser\FrontAccounting\Woocommerce\Staging\OrderStaging;
use ksfraser\FrontAccounting\Woocommerce\Staging\CustomerStaging;
use ksfraser\FrontAccounting\Woocommerce\Dao\SyncDao;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use ksfraser\FrontAccounting\Woocommerce\WooRestClientInterface;

use PHPUnit\Framework\TestCase;

class ProductExportServiceTest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $service;
    private $queryCallCount = 0;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        
        // Set up default mocks
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        
        $this->service = new \ksfraser\FrontAccounting\Woocommerce\ProductExportService(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
    }

    public function testCanBeInstantiatedWithDependencies(): void
    {
        $this->assertInstanceOf('ksfraser\FrontAccounting\Woocommerce\ProductExportService', $this->service);
    }

    public function testBuildProductDataFromFA(): void
    {
        $faData = ['stock_id' => 'TEST-001', 'description' => 'Test Product'];
        $wooData = $this->service->buildProductData($faData);
        
        $this->assertEquals('TEST-001', $wooData['sku']);
        $this->assertEquals('Test Product', $wooData['name']);
    }

    /**
     * Route DB query results by SQL substring; unmatched queries return [].
     */
    private function stubQuery(array $routes): void
    {
        $this->mockDb->method('query')->willReturnCallback(function (string $sql) use ($routes) {
            foreach ($routes as $needle => $result) {
                if (strpos($sql, $needle) !== false) {
                    return $result;
                }
            }
            return [];
        });
    }

    public function testBuildProductDataAddsTagsFromStage3(): void
    {
        $this->stubQuery([
            "SHOW TABLES LIKE '0_product_tags'" => [['x' => '0_product_tags']],
            "SHOW TABLES LIKE '0_product_tag_assignments'" => [['x' => '0_product_tag_assignments']],
            'JOIN 0_product_tags' => [
                ['name' => 'Craft', 'slug' => 'craft'],
                ['name' => 'Organic', 'slug' => 'organic'],
            ],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertEquals([
            ['name' => 'Craft', 'slug' => 'craft'],
            ['name' => 'Organic', 'slug' => 'organic'],
        ], $wooData['tags']);
    }

    public function testBuildProductDataAddsShippingClassSlugOverHazardous(): void
    {
        $this->stubQuery([
            "SHOW TABLES LIKE '0_product_shipping_attributes'" => [['x' => '0_product_shipping_attributes']],
            '0_product_shipping_attributes WHERE stock_id' => [
                ['is_hazardous' => '1', 'hs_code' => null, 'shipping_class_id' => '3'],
            ],
            "SHOW TABLES LIKE '0_product_shipping_classes'" => [['x' => '0_product_shipping_classes']],
            '0_product_shipping_classes WHERE id' => [['slug' => 'refrigerated']],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertEquals('refrigerated', $wooData['shipping_class']);
    }

    public function testBuildProductDataAddsSoldIndividuallyFromCartRules(): void
    {
        $this->stubQuery([
            "SHOW TABLES LIKE '0_product_cart_rules'" => [['x' => '0_product_cart_rules']],
            '0_product_cart_rules WHERE stock_id' => [['stock_id' => 'SKU-001', 'sold_individually' => '1']],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertTrue($wooData['sold_individually']);
    }

    public function testBuildProductDataResolvesRelatedProductsViaWooProductMap(): void
    {
        $this->stubQuery([
            "SHOW TABLES LIKE '0_product_related_products'" => [['x' => '0_product_related_products']],
            'FROM 0_product_related_products' => [
                ['related_stock_id' => 'REL-001', 'relation_type' => 'upsell', 'sort_order' => '1'],
                ['related_stock_id' => 'REL-002', 'relation_type' => 'cross_sell', 'sort_order' => '1'],
            ],
            "woo_product_map WHERE stock_id = 'REL-001'" => [['woo_product_id' => 200]],
            "woo_product_map WHERE stock_id = 'REL-002'" => [['woo_product_id' => 300]],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertEquals([200], $wooData['upsell_ids']);
        $this->assertEquals([300], $wooData['cross_sell_ids']);
    }

    public function testBuildProductDataSkipsRelatedProductsWithoutWooMapping(): void
    {
        $this->stubQuery([
            "SHOW TABLES LIKE '0_product_related_products'" => [['x' => '0_product_related_products']],
            'FROM 0_product_related_products' => [
                ['related_stock_id' => 'REL-003', 'relation_type' => 'upsell', 'sort_order' => '1'],
            ],
            "woo_product_map WHERE stock_id = 'REL-003'" => [],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertArrayNotHasKey('upsell_ids', $wooData);
        $this->assertArrayNotHasKey('cross_sell_ids', $wooData);
    }

    public function testBuildProductDataLeavesStage3KeysAbsentWhenTablesMissing(): void
    {
        $this->stubQuery([
            'SHOW TABLES' => [],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertArrayNotHasKey('tags', $wooData);
        $this->assertArrayNotHasKey('shipping_class', $wooData);
        $this->assertArrayNotHasKey('sold_individually', $wooData);
        $this->assertArrayNotHasKey('upsell_ids', $wooData);
        $this->assertArrayNotHasKey('cross_sell_ids', $wooData);
        $this->assertArrayNotHasKey('categories', $wooData);
    }

    public function testBuildProductDataAddsCategoriesFromMapping(): void
    {
        $this->stubQuery([
            "SHOW TABLES LIKE '0_woo_category_map'" => [['x' => '0_woo_category_map']],
            '0_stock_master WHERE stock_id' => [['category_id' => 5]],
            'woo_category_map WHERE fa_category_id' => [['woo_category_id' => 77]],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertEquals([['id' => 77]], $wooData['categories']);
    }

    public function testBuildProductDataOmitsCategoriesWithoutMapping(): void
    {
        $this->stubQuery([
            "SHOW TABLES LIKE '0_woo_category_map'" => [['x' => '0_woo_category_map']],
            '0_stock_master WHERE stock_id' => [['category_id' => 5]],
            'woo_category_map WHERE fa_category_id' => [],
        ]);

        $wooData = $this->service->buildProductData(['stock_id' => 'SKU-001', 'description' => 'Test']);

        $this->assertArrayNotHasKey('categories', $wooData);
    }

    public function testExportProductCreatesNewWhenNoWooId(): void
    {
        $this->mockRestClient->method('post')->willReturn(['id' => 123]);
        
        $result = $this->service->exportProduct(['stock_id' => 'TEST-001', 'description' => 'Test']);
        $this->assertEquals(123, $result['id']);
    }

    public function testExportProductUpdatesExistingWhenWooIdPresent(): void
    {
        $this->mockRestClient->method('put')->willReturn(['id' => 456]);
        
        $result = $this->service->exportProduct(['stock_id' => 'TEST-001', 'woo_id' => 456, 'description' => 'Test']);
        $this->assertEquals(456, $result['id']);
    }

    public function testExportAllSimpleProductsExportsSimpleProducts(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00'],
            ['stock_id' => 'P002', 'description' => 'Product 2', 'price' => '20.00'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_master') !== false => $products,
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 999]);

        $result = $this->service->exportAllSimpleProducts();

        $this->assertEquals(2, $result['exported']);
        $this->assertEquals(2, $result['total']);
    }

    public function testExportAllSimpleProductsHandlesErrors(): void
    {
        $products = [
            ['stock_id' => 'P001', 'description' => 'Product 1', 'price' => '10.00'],
        ];

        $this->mockDb->method('query')->willReturn($products);
        $this->mockRestClient->method('post')->willThrowException(new \Exception('API Error'));

        $result = $this->service->exportAllSimpleProducts();

        $this->assertEquals(0, $result['exported']);
        $this->assertEquals(1, $result['total']);
    }

    public function testGetAllProducts(): void
    {
        $this->mockRestClient->method('get')->willReturn([['id' => 1, 'name' => 'Product 1']]);
        
        $result = $this->service->getProducts();
        $this->assertCount(1, $result);
    }

    public function testFindProductBySku(): void
    {
        $this->mockRestClient->method('get')->willReturn([['id' => 123, 'sku' => 'TEST-001']]);
        
        $result = $this->service->findProductBySku('TEST-001');
        $this->assertEquals(123, $result['id']);
    }

    public function testDeleteProductBySku(): void
    {
        $this->mockRestClient->method('get')->willReturn([['id' => 123, 'sku' => 'TEST-001']]);
        $this->mockRestClient->method('delete')->willReturn(['deleted' => true]);
        
        $result = $this->service->deleteProductBySku('TEST-001');
        $this->assertTrue($result['deleted']);
    }

    public function testBuildVariableProductData(): void
    {
        $this->setupMockForVariableProduct();
        
        $faData = [
            'stock_id' => 'VAR-001',
            'description' => 'Variable Product'
        ];
        
        $wooData = $this->service->buildProductData($faData);
        
        $this->assertEquals('variable', $wooData['type']);
    }

    public function testAddProductAttributes(): void
    {
        $attributes = [
            ['name' => 'Color', 'options' => ['Red', 'Blue'], 'visible' => true],
            ['name' => 'Size', 'options' => ['S', 'M', 'L'], 'visible' => true]
        ];
        
        $this->mockRestClient->method('put')->willReturn(['id' => 123]);
        
        $result = $this->service->addProductAttributes(123, $attributes);
        
        $this->assertTrue($result);
    }

    public function testCreateProductVariation(): void
    {
        $variationData = [
            'sku' => 'VAR-001-S-RED',
            'attributes' => ['Color' => 'Red', 'Size' => 'S'],
            'price' => 29.99
        ];
        
        $this->mockRestClient->method('post')->willReturn(['id' => 789]);
        
        $result = $this->service->createVariation(123, $variationData);
        
        $this->assertEquals(789, $result['id']);
    }

    public function testExportVariableProduct(): void
    {
        $variations = [
            ['sku' => 'VAR-001-S', 'price' => 29.99, 'attributes' => ['Size' => 'S']],
            ['sku' => 'VAR-001-M', 'price' => 34.99, 'attributes' => ['Size' => 'M']]
        ];
        
        // Mock DB for getting parent product data
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'stock_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Variable Product']];
                }
                return [];
            });
        
        $this->mockRestClient->method('post')->willReturn(['id' => 999]);
        
        $result = $this->service->exportVariableProduct('VAR-001', $variations);
        
        $this->assertEquals(999, $result['parent_id']);
    }

    public function testDetermineProductTypeSimple(): void
    {
        // Mock: no product_hierarchy table -> simple
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    return []; // Table doesn't exist
                }
                return [];
            });
        
        $faData = ['stock_id' => 'SIMPLE-001', 'description' => 'Simple Product'];
        $data = $this->service->buildProductData($faData);
        
        $this->assertEquals('simple', $data['type']);
    }
    
    public function testDetermineProductTypeVariation(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    return [['Tables_in_db' => '0_product_hierarchy']]; // Table exists
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [['parent_stock_id' => 'VAR-001']]; // Has parent -> variation
                }
                return [];
            });
        
        $faData = ['stock_id' => 'VAR-001-S', 'description' => 'Variation'];
        $data = $this->service->buildProductData($faData);
        
        $this->assertEquals('variation', $data['type']);
    }
    
    public function testDetermineProductTypeVariable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    if (strpos($sql, 'product_hierarchy') !== false) {
                        return [['Tables_in_db' => '0_product_hierarchy']]; // Table exists
                    }
                    return []; // Other tables don't exist
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return []; // No parent
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 2]]; // Has children -> variable
                }
                return [];
            });
        
        $faData = ['stock_id' => 'VAR-001', 'description' => 'Variable Product'];
        $data = $this->service->buildProductData($faData);
        
        $this->assertEquals('variable', $data['type']);
    }
    
    public function testRecodeSkuUpdatesWooCommerceAndDb(): void
    {
        $this->mockRestClient->method('get')
            ->with('products', ['sku' => 'OLD-001'])
            ->willReturn([['id' => 123, 'sku' => 'OLD-001']]);

        $this->mockRestClient->method('put')
            ->with('products/123', $this->arrayHasKey('sku'))
            ->willReturn(['id' => 123, 'sku' => 'NEW-001']);

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false => [],
                default => [],
            });

        $result = $this->service->recodeSku('OLD-001', 'NEW-001');

        $this->assertTrue($result);
    }

    public function testRecodeSkuReturnsFalseWhenProductNotFound(): void
    {
        $this->mockRestClient->method('get')
            ->with('products', ['sku' => 'NONEXISTENT'])
            ->willReturn([]);

        $result = $this->service->recodeSku('NONEXISTENT', 'NEW-SKU');

        $this->assertFalse($result);
    }

    public function testRecodeSkuReturnsFalseOnApiError(): void
    {
        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->service->recodeSku('OLD-001', 'NEW-001');

        $this->assertFalse($result);
    }

    public function testRecodeAllSkusRecodesMultipleProducts(): void
    {
        $skuMap = [
            ['old_sku' => 'OLD-001', 'new_sku' => 'NEW-001'],
            ['old_sku' => 'OLD-002', 'new_sku' => 'NEW-002'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'sku_recode_map') !== false => $skuMap,
                default => [],
            });

        $this->mockRestClient->method('get')->willReturn([['id' => 123]]);
        $this->mockRestClient->method('put')->willReturn(['id' => 123]);

        $result = $this->service->recodeAllSkus();

        $this->assertEquals(2, $result['recoded']);
        $this->assertEquals(2, $result['total']);
    }

    public function testRecodeAllSkusHandlesEmptyMap(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'sku_recode_map') !== false => [],
                default => [],
            });

        $result = $this->service->recodeAllSkus();

        $this->assertEquals(0, $result['recoded']);
        $this->assertEquals(0, $result['total']);
    }

    private function setupMockForVariableProduct(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    if (strpos($sql, 'product_hierarchy') !== false) {
                        return [['Tables_in_db' => '0_product_hierarchy']];
                    }
                    return []; // Other tables don't exist
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return []; // No parent
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 1]]; // Has children
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return []; // No attributes
                }
                return [];
            });
    }

    public function testExportProductDelegatesVariableTypeToExportVariableProduct(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    if (strpos($sql, 'product_hierarchy') !== false) {
                        return [['Tables_in_db' => '0_product_hierarchy']];
                    }
                    return [];
                }
                if (strpos($sql, 'parent_stock_id') !== false && strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 2]];
                }
                if (strpos($sql, 'product_hierarchy') !== false && strpos($sql, 'ph.parent_stock_id') !== false) {
                    return [
                        ['stock_id' => 'VAR-001-S', 'description' => 'Small', 'price' => '10.00', 'instock' => 5],
                        ['stock_id' => 'VAR-001-L', 'description' => 'Large', 'price' => '12.00', 'instock' => 3],
                    ];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [];
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return [];
                }
                if (strpos($sql, 'stock_master') !== false && strpos($sql, 'SELECT') !== false && strpos($sql, 'ph.') === false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Variable Product', 'price' => '10.00']];
                }
                return [];
            });

        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn(['id' => 500]);

        $result = $this->service->exportProduct([
            'stock_id' => 'VAR-001',
            'description' => 'Variable Product',
        ]);

        $this->assertArrayHasKey('parent_id', $result);
        $this->assertEquals(500, $result['parent_id']);
        $this->assertCount(2, $result['variations']);
    }

    public function testExportAllProductsIncludesVariableProducts(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    if (strpos($sql, 'product_hierarchy') !== false) {
                        return [['Tables_in_db' => '0_product_hierarchy']];
                    }
                    return [];
                }
                if (strpos($sql, 'DISTINCT') !== false) {
                    return [['parent_stock_id' => 'VAR-001']];
                }
                if (strpos($sql, 'COUNT') !== false && strpos($sql, 'parent_stock_id') !== false) {
                    return [['cnt' => 1]];
                }
                if (strpos($sql, 'child_stock_id') !== false) {
                    return [];
                }
                if (strpos($sql, 'product_hierarchy') !== false && strpos($sql, 'ph.parent_stock_id') !== false) {
                    return [
                        ['stock_id' => 'VAR-001-S', 'description' => 'Small', 'price' => '10.00', 'instock' => 5],
                    ];
                }
                if (strpos($sql, 'product_hierarchy') !== false && strpos($sql, 'sm.stock_id') !== false) {
                    return [
                        ['stock_id' => 'VAR-001-S', 'description' => 'Small', 'price' => '10.00', 'instock' => 5],
                    ];
                }
                if (strpos($sql, 'product_attribute_assignments') !== false) {
                    return [];
                }
                if (strpos($sql, 'stock_master') !== false && strpos($sql, 'SELECT') !== false && strpos($sql, 'ph.') === false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Variable Product', 'price' => '10.00']];
                }
                if (strpos($sql, 'stock_master') !== false && strpos($sql, 'DISTINCT') === false && strpos($sql, 'WHERE') === false) {
                    return [];
                }
                return [];
            });

        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn(['id' => 500]);

        $result = $this->service->exportAllProducts();

        $this->assertArrayHasKey('variable', $result);
        $this->assertEquals(1, $result['variable']['exported']);
        $this->assertEquals(1, $result['total_exported']);
    }

    public function testExportAllVariableProductsHandlesNoHierarchyTable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'SHOW TABLES') !== false) {
                    return [];
                }
                return [];
            });

        $result = $this->service->exportAllVariableProducts();

        $this->assertEquals(0, $result['exported']);
        $this->assertEquals(0, $result['total']);
    }

    public function testExportVariableProductUpsertsExistingVariations(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'stock_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Variable Product']];
                }
                return [];
            });

        $callCount = 0;
        $this->mockRestClient->method('post')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            return ['id' => 500];
        });
        $this->mockRestClient->method('get')->willReturnCallback(function ($endpoint) {
            if (strpos($endpoint, '/variations') !== false) {
                return [['id' => 100, 'sku' => 'VAR-001-S']];
            }
            return [];
        });
        $this->mockRestClient->method('put')->willReturn(['id' => 100]);

        $variations = [
            ['sku' => 'VAR-001-S', 'regular_price' => '29.99', 'attributes' => [['name' => 'Size', 'option' => 'S']]],
        ];

        $result = $this->service->exportVariableProduct('VAR-001', $variations);

        $this->assertEquals(500, $result['parent_id']);
        $this->assertCount(1, $result['variations']);
        $this->assertEquals(100, $result['variations'][0]);
    }

    public function testExportVariableProductHandlesBothKeyFormats(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'stock_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Variable Product']];
                }
                return [];
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 600]);
        $this->mockRestClient->method('get')->willReturn([]);

        $variations = [
            ['sku' => 'VAR-001-S', 'stock_quantity' => 10, 'regular_price' => '29.99', 'attributes' => []],
            ['sku' => 'VAR-001-M', 'stock' => 5, 'price' => '34.99', 'attributes' => []],
        ];

        $result = $this->service->exportVariableProduct('VAR-001', $variations);

        $this->assertEquals(600, $result['parent_id']);
        $this->assertCount(2, $result['variations']);
    }

    public function testExportVariableProductVariationErrorIsolation(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'stock_master') !== false) {
                    return [['stock_id' => 'VAR-001', 'description' => 'Variable Product']];
                }
                return [];
            });

        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturnCallback(function ($endpoint) {
            if (strpos($endpoint, '/variations') !== false && strpos($endpoint, 'products/') === 0) {
                throw new \Exception('Variation API error');
            }
            return ['id' => 500];
        });

        $variations = [
            ['sku' => 'VAR-001-S', 'price' => '29.99', 'attributes' => []],
        ];

        $result = $this->service->exportVariableProduct('VAR-001', $variations);

        $this->assertEquals(500, $result['parent_id']);
        $this->assertCount(0, $result['variations']);
    }
}
