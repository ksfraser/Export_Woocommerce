<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\ProductDataBuilder;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
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
        $this->assertTrue($result['in_stock']);
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
        $this->assertFalse($result['in_stock']);
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
}
