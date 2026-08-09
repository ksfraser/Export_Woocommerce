<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\DTO;

use Ksfraser\frontaccounting\Woocommerce\DTO\ProductDTO;
use PHPUnit\Framework\TestCase;

class ProductDTOExtendedTest extends TestCase
{
    public function testIsVariableReturnsTrue(): void
    {
        $dto = new ProductDTO(['type' => 'variable']);
        $this->assertTrue($dto->isVariable());
    }

    public function testIsVariableReturnsFalse(): void
    {
        $dto = new ProductDTO(['type' => 'simple']);
        $this->assertFalse($dto->isVariable());
    }

    public function testIsVariationReturnsTrue(): void
    {
        $dto = new ProductDTO(['type' => 'variation']);
        $this->assertTrue($dto->isVariation());
    }

    public function testIsVariationReturnsFalse(): void
    {
        $dto = new ProductDTO(['type' => 'simple']);
        $this->assertFalse($dto->isVariation());
    }

    public function testToArrayWithStockQuantity(): void
    {
        $dto = new ProductDTO([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'type' => 'simple',
            'price' => 10.00,
            'stock_quantity' => 5,
        ]);

        $result = $dto->toArray();

        $this->assertEquals('TEST-001', $result['sku']);
        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals('simple', $result['type']);
        $this->assertEquals('10', $result['regular_price']);
        $this->assertEquals(5, $result['stock_quantity']);
        $this->assertTrue($result['manage_stock']);
        $this->assertEquals('instock', $result['stock_status']);
        $this->assertArrayNotHasKey('in_stock', $result);
    }

    public function testToArrayWithDescription(): void
    {
        $dto = new ProductDTO([
            'sku' => 'TEST-001',
            'name' => 'Test',
            'description' => 'Full description',
        ]);

        $result = $dto->toArray();
        $this->assertEquals('Full description', $result['description']);
    }

    public function testToArrayWithWeight(): void
    {
        $dto = new ProductDTO([
            'sku' => 'TEST-001',
            'name' => 'Test',
            'weight' => 2.5,
        ]);

        $result = $dto->toArray();
        $this->assertEquals('2.5', $result['weight']);
    }

    public function testToArrayWithDimensions(): void
    {
        $dto = new ProductDTO([
            'sku' => 'TEST-001',
            'name' => 'Test',
            'dimensions' => ['length' => '10', 'width' => '5', 'height' => '2'],
        ]);

        $result = $dto->toArray();
        $this->assertEquals(['length' => '10', 'width' => '5', 'height' => '2'], $result['dimensions']);
    }

    public function testToArrayWithAttributes(): void
    {
        $dto = new ProductDTO([
            'sku' => 'TEST-001',
            'name' => 'Test',
            'attributes' => [['name' => 'Color', 'options' => ['Red', 'Blue']]],
        ]);

        $result = $dto->toArray();
        $this->assertCount(1, $result['attributes']);
    }

    public function testToArrayWithMetaData(): void
    {
        $dto = new ProductDTO([
            'sku' => 'TEST-001',
            'name' => 'Test',
            'meta_data' => [['key' => '_test', 'value' => 'val']],
        ]);

        $result = $dto->toArray();
        $this->assertCount(1, $result['meta_data']);
    }

    public function testToArrayMinimal(): void
    {
        $dto = new ProductDTO([
            'sku' => 'TEST-001',
            'name' => 'Test',
        ]);

        $result = $dto->toArray();
        $this->assertArrayNotHasKey('description', $result);
        $this->assertArrayNotHasKey('stock_quantity', $result);
        $this->assertArrayNotHasKey('weight', $result);
        $this->assertArrayNotHasKey('dimensions', $result);
        $this->assertArrayNotHasKey('attributes', $result);
        $this->assertArrayNotHasKey('meta_data', $result);
    }

    public function testGetWooId(): void
    {
        $dto = new ProductDTO(['woo_id' => 123]);
        $this->assertEquals(123, $dto->getWooId());
    }

    public function testGetWooIdFromId(): void
    {
        $dto = new ProductDTO(['id' => 456]);
        $this->assertEquals(456, $dto->getWooId());
    }

    public function testGetWooIdNull(): void
    {
        $dto = new ProductDTO([]);
        $this->assertNull($dto->getWooId());
    }

    public function testGetStockQuantityAlias(): void
    {
        $dto = new ProductDTO(['stock_quantity' => 10]);
        $this->assertEquals(10, $dto->getStockQuantity());
    }

    public function testGetTypeConstants(): void
    {
        $this->assertEquals('simple', ProductDTO::TYPE_SIMPLE);
        $this->assertEquals('variable', ProductDTO::TYPE_VARIABLE);
        $this->assertEquals('variation', ProductDTO::TYPE_VARIATION);
        $this->assertEquals('grouped', ProductDTO::TYPE_GROUPED);
        $this->assertEquals('external', ProductDTO::TYPE_EXTERNAL);
    }
}
