<?php
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\ProductDataBuilder;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

class ProductDataBuilderTest extends TestCase
{
    private $mockDb;
    private $mockLogger;
    private $builder;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb->method('escape')->willReturnCallback(fn($v) => addslashes($v));
        $this->mockDb->method('getPrefix')->willReturn('0_');

        $this->builder = new ProductDataBuilder($this->mockDb, $this->mockLogger);
    }

    public function testBuildSimpleProduct(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => strpos($sql, 'SHOW TABLES') !== false ? [] : []);

        $faData = [
            'stock_id' => 'SIMPLE-001',
            'description' => 'Simple Product',
            'price' => '19.99',
            'long_description' => 'Full description here',
        ];

        $result = $this->builder->build($faData);

        $this->assertEquals('SIMPLE-001', $result['sku']);
        $this->assertEquals('Simple Product', $result['name']);
        $this->assertEquals('simple', $result['type']);
        $this->assertEquals('19.99', $result['regular_price']);
        $this->assertEquals('Full description here', $result['description']);
    }

    public function testBuildWithStockData(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => strpos($sql, 'SHOW TABLES') !== false ? [] : []);

        $faData = [
            'stock_id' => 'STOCK-001',
            'description' => 'Stocked Product',
            'price' => '9.99',
            'instock' => 25,
        ];

        $result = $this->builder->build($faData);

        $this->assertEquals(25, $result['stock_quantity']);
        $this->assertTrue($result['manage_stock']);
        $this->assertEquals('instock', $result['stock_status']);
        $this->assertArrayNotHasKey('in_stock', $result);
    }

    public function testBuildWithZeroStock(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => strpos($sql, 'SHOW TABLES') !== false ? [] : []);

        $faData = [
            'stock_id' => 'OOS-001',
            'description' => 'Out of Stock',
            'price' => '5.00',
            'instock' => 0,
        ];

        $result = $this->builder->build($faData);

        $this->assertEquals(0, $result['stock_quantity']);
        $this->assertEquals('outofstock', $result['stock_status']);
        $this->assertArrayNotHasKey('in_stock', $result);
    }

    public function testBuildWithDimensionsFromTable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_dimensions') !== false => [['table' => '0_product_dimensions']],
                strpos($sql, 'product_dimensions') !== false => [[
                    'weight' => '1.5',
                    'weight_unit' => 'kg',
                    'length' => '10',
                    'width' => '5',
                    'height' => '2',
                    'dim_unit' => 'cm',
                ]],
                default => [],
            });

        $faData = [
            'stock_id' => 'DIM-001',
            'description' => 'Dimensioned Product',
            'price' => '29.99',
        ];

        $result = $this->builder->build($faData);

        $this->assertEquals('1.5', $result['weight']);
        $this->assertEquals('10', $result['dimensions']['length']);
        $this->assertEquals('5', $result['dimensions']['width']);
        $this->assertEquals('2', $result['dimensions']['height']);
    }

    public function testBuildWithImages(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_media') !== false => [['table' => '0_product_media']],
                strpos($sql, 'product_media') !== false => [
                    ['media_url' => 'https://example.com/img1.jpg', 'alt_text' => 'Image 1'],
                    ['media_url' => 'https://example.com/img2.jpg', 'alt_text' => 'Image 2'],
                ],
                default => [],
            });

        $faData = [
            'stock_id' => 'IMG-001',
            'description' => 'Product with Images',
            'price' => '15.00',
        ];

        $result = $this->builder->build($faData);

        $this->assertCount(2, $result['images']);
        $this->assertEquals('https://example.com/img1.jpg', $result['images'][0]['src']);
        $this->assertEquals('Image 1', $result['images'][0]['alt']);
    }

    public function testBuildWithProductIdentifiers(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_identifiers') !== false => [['table' => '0_product_identifiers']],
                strpos($sql, 'product_identifiers') !== false => [[
                    'upc' => '123456789012',
                    'ean' => '5901234567890',
                    'gtin' => '1061414119549',
                ]],
                default => [],
            });

        $faData = [
            'stock_id' => 'ID-001',
            'description' => 'Product with IDs',
            'price' => '10.00',
        ];

        $result = $this->builder->build($faData);

        $this->assertCount(3, $result['meta_data']);
        $metaKeys = array_column($result['meta_data'], 'key');
        $this->assertContains('_upc', $metaKeys);
        $this->assertContains('_ean', $metaKeys);
        $this->assertContains('_gtin', $metaKeys);
    }

    public function testBuildWithShippingAttributes(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_attributes') !== false => [['table' => '0_product_shipping_attributes']],
                strpos($sql, 'product_shipping_attributes') !== false => [[
                    'is_hazardous' => 1,
                    'hs_code' => '8471.30',
                ]],
                default => [],
            });

        $faData = [
            'stock_id' => 'SHIP-001',
            'description' => 'Hazardous Product',
            'price' => '99.00',
        ];

        $result = $this->builder->build($faData);

        $this->assertEquals('hazardous', $result['shipping_class']);
        $metaKeys = array_column($result['meta_data'] ?? [], 'key');
        $this->assertContains('hs_code', $metaKeys);
    }

    public function testBuildVariableProduct(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_hierarchy') !== false => [['table' => '0_product_hierarchy']],
                strpos($sql, 'child_stock_id') !== false => [],
                strpos($sql, 'COUNT') !== false => [['cnt' => 2]],
                default => [],
            });

        $faData = [
            'stock_id' => 'VAR-001',
            'description' => 'Variable Product',
            'price' => '49.99',
        ];

        $result = $this->builder->build($faData);

        $this->assertEquals('variable', $result['type']);
    }

    public function testBuildVariationProduct(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false => [['table' => '0_product_hierarchy']],
                strpos($sql, 'child_stock_id') !== false => [['parent_stock_id' => 'VAR-001']],
                default => [],
            });

        $faData = [
            'stock_id' => 'VAR-001-S',
            'description' => 'Variation S',
            'price' => '29.99',
        ];

        $result = $this->builder->build($faData);

        $this->assertEquals('variation', $result['type']);
    }

    public function testDetermineTypeSimpleWhenNoTable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => strpos($sql, 'SHOW TABLES') !== false ? [] : []);

        $type = $this->builder->determineProductType('SIMPLE-001');
        $this->assertEquals('simple', $type);
    }

    public function testBuildMinimalData(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => strpos($sql, 'SHOW TABLES') !== false ? [] : []);

        $result = $this->builder->build(['stock_id' => 'MIN-001']);

        $this->assertEquals('MIN-001', $result['sku']);
        $this->assertEquals('', $result['name']);
        $this->assertEquals('simple', $result['type']);
        $this->assertEquals('0', $result['regular_price']);
    }

    public function testBuildWithWeightFallback(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => strpos($sql, 'SHOW TABLES') !== false ? [] : []);

        $faData = [
            'stock_id' => 'W-001',
            'description' => 'Weighted Product',
            'price' => '20.00',
            'weight' => 3.5,
        ];

        $result = $this->builder->build($faData);
        $this->assertEquals('3.5', $result['weight']);
    }

    public function testBuildWithTagsFromStage3(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_tags') !== false => [['table' => '0_product_tags']],
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_tag_assignments') !== false => [['table' => '0_product_tag_assignments']],
                strpos($sql, 'JOIN 0_product_tags') !== false => [
                    ['name' => 'Craft', 'slug' => 'craft'],
                    ['name' => 'Organic', 'slug' => 'organic'],
                ],
                default => [],
            });

        $result = $this->builder->build([
            'stock_id' => 'TAG-001',
            'description' => 'Tagged Product',
            'price' => '10.00',
        ]);

        $this->assertEquals([
            ['name' => 'Craft', 'slug' => 'craft'],
            ['name' => 'Organic', 'slug' => 'organic'],
        ], $result['tags']);
    }

    public function testBuildWithShippingClassSlugOverHazardous(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_attributes') !== false => [['table' => '0_product_shipping_attributes']],
                strpos($sql, 'product_shipping_attributes WHERE stock_id') !== false => [
                    ['is_hazardous' => 1, 'hs_code' => null, 'shipping_class_id' => 3],
                ],
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_shipping_classes') !== false => [['table' => '0_product_shipping_classes']],
                strpos($sql, 'product_shipping_classes WHERE id') !== false => [['slug' => 'refrigerated']],
                default => [],
            });

        $result = $this->builder->build([
            'stock_id' => 'SHIP-002',
            'description' => 'Refrigerated Product',
            'price' => '99.00',
        ]);

        $this->assertEquals('refrigerated', $result['shipping_class']);
    }

    public function testBuildWithSoldIndividuallyFromCartRules(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_cart_rules') !== false => [['table' => '0_product_cart_rules']],
                strpos($sql, 'product_cart_rules WHERE stock_id') !== false => [['sold_individually' => 1]],
                default => [],
            });

        $result = $this->builder->build([
            'stock_id' => 'CR-001',
            'description' => 'One Per Order',
            'price' => '8.00',
        ]);

        $this->assertTrue($result['sold_individually']);
    }

    public function testBuildWithRelatedProductsFromWooProductMap(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'product_related_products') !== false => [['table' => '0_product_related_products']],
                strpos($sql, 'FROM 0_product_related_products') !== false => [
                    ['related_stock_id' => 'REL-001', 'relation_type' => 'upsell', 'sort_order' => 1],
                    ['related_stock_id' => 'REL-002', 'relation_type' => 'cross_sell', 'sort_order' => 1],
                ],
                strpos($sql, "woo_product_map WHERE stock_id = 'REL-001'") !== false => [['woo_product_id' => 200]],
                strpos($sql, "woo_product_map WHERE stock_id = 'REL-002'") !== false => [['woo_product_id' => 300]],
                default => [],
            });

        $result = $this->builder->build([
            'stock_id' => 'RL-001',
            'description' => 'With Related',
            'price' => '12.00',
        ]);

        $this->assertEquals([200], $result['upsell_ids']);
        $this->assertEquals([300], $result['cross_sell_ids']);
    }

    public function testBuildWithCategoriesFromMapping(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'woo_category_map') !== false => [['table' => '0_woo_category_map']],
                strpos($sql, '0_stock_master WHERE stock_id') !== false => [['category_id' => 5]],
                strpos($sql, 'woo_category_map WHERE fa_category_id') !== false => [['woo_category_id' => 77]],
                default => [],
            });

        $result = $this->builder->build([
            'stock_id' => 'CAT-001',
            'description' => 'Categorized',
            'price' => '5.00',
        ]);

        $this->assertEquals([['id' => 77]], $result['categories']);
    }

    public function testBuildOmitsCategoriesWithoutMapping(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'SHOW TABLES') !== false && strpos($sql, 'woo_category_map') !== false => [['table' => '0_woo_category_map']],
                strpos($sql, '0_stock_master WHERE stock_id') !== false => [['category_id' => 5]],
                strpos($sql, 'woo_category_map WHERE fa_category_id') !== false => [],
                default => [],
            });

        $result = $this->builder->build([
            'stock_id' => 'CAT-002',
            'description' => 'Unmapped',
            'price' => '6.00',
        ]);

        $this->assertArrayNotHasKey('categories', $result);
    }
}
