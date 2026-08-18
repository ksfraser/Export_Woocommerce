<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\DTO;

use ksfraser\FrontAccounting\Woocommerce\DTO\ProductDTO;
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

    // --- getStockId tests ---

    public function testGetStockIdFromStockId(): void
    {
        $dto = new ProductDTO(['stock_id' => 'SID-001']);
        $this->assertEquals('SID-001', $dto->getStockId());
    }

    public function testGetStockIdFallsBackToSku(): void
    {
        $dto = new ProductDTO(['sku' => 'SKU-002']);
        $this->assertEquals('SKU-002', $dto->getStockId());
    }

    public function testGetStockIdEmpty(): void
    {
        $dto = new ProductDTO([]);
        $this->assertEquals('', $dto->getStockId());
    }

    public function testGetStockIdStockIdOverridesSku(): void
    {
        $dto = new ProductDTO(['stock_id' => 'SID-003', 'sku' => 'SKU-003']);
        $this->assertEquals('SID-003', $dto->getStockId());
    }

    // --- getStockQty tests ---

    public function testGetStockQtyReturnsInt(): void
    {
        $dto = new ProductDTO(['stock_quantity' => 42]);
        $this->assertSame(42, $dto->getStockQty());
    }

    public function testGetStockQtyNull(): void
    {
        $dto = new ProductDTO([]);
        $this->assertNull($dto->getStockQty());
    }

    public function testGetStockQtyZero(): void
    {
        $dto = new ProductDTO(['stock_quantity' => 0]);
        $this->assertSame(0, $dto->getStockQty());
    }

    // --- getStockStatus tests ---

    public function testGetStockStatusExplicitInStock(): void
    {
        $dto = new ProductDTO(['stock_status' => 'instock']);
        $this->assertEquals('instock', $dto->getStockStatus());
    }

    public function testGetStockStatusExplicitOutOfStock(): void
    {
        $dto = new ProductDTO(['stock_status' => 'outofstock']);
        $this->assertEquals('outofstock', $dto->getStockStatus());
    }

    public function testGetStockStatusExplicitOnBackorder(): void
    {
        $dto = new ProductDTO(['stock_status' => 'onbackorder']);
        $this->assertEquals('onbackorder', $dto->getStockStatus());
    }

    public function testGetStockStatusDerivedFromQtyPositive(): void
    {
        $dto = new ProductDTO(['stock_quantity' => 10]);
        $this->assertEquals('instock', $dto->getStockStatus());
    }

    public function testGetStockStatusDerivedZeroWithBackordersAllowed(): void
    {
        $dto = new ProductDTO([
            'stock_quantity' => 0,
            'backorders' => 'yes',
        ]);
        $this->assertEquals('onbackorder', $dto->getStockStatus());
    }

    public function testGetStockStatusDerivedZeroWithBackordersNotify(): void
    {
        $dto = new ProductDTO([
            'stock_quantity' => 0,
            'backorders' => 'notify',
        ]);
        $this->assertEquals('onbackorder', $dto->getStockStatus());
    }

    public function testGetStockStatusDerivedZeroWithoutBackorders(): void
    {
        $dto = new ProductDTO([
            'stock_quantity' => 0,
            'backorders' => 'no',
        ]);
        $this->assertEquals('outofstock', $dto->getStockStatus());
    }

    public function testGetStockStatusDefaultWhenNoQtyNoStatus(): void
    {
        $dto = new ProductDTO([]);
        $this->assertEquals('instock', $dto->getStockStatus());
    }

    public function testGetStockStatusInvalidValueIgnored(): void
    {
        // Invalid stock_status is ignored, falls through to derivation
        $dto = new ProductDTO([
            'stock_status' => 'invalid_value',
            'stock_quantity' => 5,
        ]);
        $this->assertEquals('instock', $dto->getStockStatus());
    }

    public function testGetStockStatusExplicitOverridesDerivation(): void
    {
        // Explicit outofstock even with positive qty
        $dto = new ProductDTO([
            'stock_status' => 'outofstock',
            'stock_quantity' => 10,
        ]);
        $this->assertEquals('outofstock', $dto->getStockStatus());
    }

    // --- Backorder derivation tests ---

    public function testBackordersAllowedYes(): void
    {
        $dto = new ProductDTO(['backorders' => 'yes']);
        $this->assertTrue($dto->getBackordersAllowed());
    }

    public function testBackordersAllowedNotify(): void
    {
        $dto = new ProductDTO(['backorders' => 'notify']);
        $this->assertTrue($dto->getBackordersAllowed());
    }

    public function testBackordersAllowedNo(): void
    {
        $dto = new ProductDTO(['backorders' => 'no']);
        $this->assertFalse($dto->getBackordersAllowed());
    }

    // --- Deprecated getInStock consistency ---

    public function testGetInStockMatchesGetStockStatusInStock(): void
    {
        $dto = new ProductDTO(['stock_status' => 'instock']);
        $this->assertTrue($dto->getInStock());
        $this->assertEquals('instock', $dto->getStockStatus());
    }

    public function testGetInStockMatchesGetStockStatusOutOfStock(): void
    {
        $dto = new ProductDTO(['stock_status' => 'outofstock']);
        $this->assertFalse($dto->getInStock());
    }

    public function testGetInStockMatchesGetStockStatusOnBackorder(): void
    {
        $dto = new ProductDTO(['stock_status' => 'onbackorder']);
        $this->assertFalse($dto->getInStock());
    }

    // --- Dimensions edge cases ---

    public function testDimensionsStringConvertedToNull(): void
    {
        $dto = new ProductDTO(['dimensions' => '10x5x2']);
        $this->assertNull($dto->getDimensions());
    }

    public function testDimensionsArrayKept(): void
    {
        $dims = ['length' => '10', 'width' => '5', 'height' => '2'];
        $dto = new ProductDTO(['dimensions' => $dims]);
        $this->assertEquals($dims, $dto->getDimensions());
    }

    // --- Weight edge cases ---

    public function testWeightEmptyStringReturnsNull(): void
    {
        $dto = new ProductDTO(['weight' => '']);
        $this->assertNull($dto->getWeight());
    }

    public function testWeightZeroReturnsZero(): void
    {
        $dto = new ProductDTO(['weight' => 0]);
        $this->assertSame(0.0, $dto->getWeight());
    }

    public function testWeightZeroStringReturnsZero(): void
    {
        $dto = new ProductDTO(['weight' => '0']);
        $this->assertSame(0.0, $dto->getWeight());
    }

    public function testWeightNumericStringReturnsFloat(): void
    {
        $dto = new ProductDTO(['weight' => '3.14']);
        $this->assertEqualsWithDelta(3.14, $dto->getWeight(), 0.001);
    }

    // --- toArray edge cases ---

    public function testToArrayWithBackorders(): void
    {
        $dto = new ProductDTO([
            'sku' => 'BCK-001',
            'name' => 'Backorder Product',
            'backorders' => 'yes',
        ]);

        $result = $dto->toArray();
        $this->assertEquals('yes', $result['backorders']);
    }

    public function testToArrayWithoutBackorders(): void
    {
        $dto = new ProductDTO([
            'sku' => 'NOBCK-001',
            'backorders' => 'no',
        ]);

        $result = $dto->toArray();
        $this->assertArrayNotHasKey('backorders', $result);
    }

    public function testToArrayStockStatusInStock(): void
    {
        $dto = new ProductDTO(['stock_quantity' => 5]);
        $result = $dto->toArray();
        $this->assertEquals('instock', $result['stock_status']);
    }

    public function testToArrayStockStatusOutOfStock(): void
    {
        $dto = new ProductDTO(['stock_quantity' => 0, 'backorders' => 'no']);
        $result = $dto->toArray();
        $this->assertEquals('outofstock', $result['stock_status']);
    }

    public function testToArrayStockStatusOnBackorder(): void
    {
        $dto = new ProductDTO(['stock_quantity' => 0, 'backorders' => 'yes']);
        $result = $dto->toArray();
        $this->assertEquals('onbackorder', $result['stock_status']);
    }

    public function testToArrayNoStockQuantityNoStockStatus(): void
    {
        $dto = new ProductDTO(['sku' => 'NOSTK-001']);
        $result = $dto->toArray();
        $this->assertArrayNotHasKey('stock_quantity', $result);
        $this->assertArrayNotHasKey('stock_status', $result);
    }

    // --- stock_id input key ---

    public function testStockIdInputKeyTakesPrecedenceOverSku(): void
    {
        $dto = new ProductDTO(['stock_id' => 'SID-PRI', 'sku' => 'SKU-SEC']);
        $this->assertEquals('SID-PRI', $dto->getStockId());
        $this->assertEquals('SKU-SEC', $dto->getSku());
    }

    // --- Type-based methods ---

    public function testIsVariableForTypeGrouped(): void
    {
        $dto = new ProductDTO(['type' => 'grouped']);
        $this->assertFalse($dto->isVariable());
    }

    public function testIsVariationForTypeVariable(): void
    {
        $dto = new ProductDTO(['type' => 'variable']);
        $this->assertFalse($dto->isVariation());
    }

    // --- woo_id override ---

    public function testWooIdTakesPrecedenceOverId(): void
    {
        $dto = new ProductDTO(['woo_id' => 111, 'id' => 222]);
        $this->assertEquals(111, $dto->getWooId());
    }

    // --- Array inputs for non-array fields ---

    public function testRelatedIdsNonArrayDefaultsToEmpty(): void
    {
        $dto = new ProductDTO(['related_ids' => 'not_an_array']);
        $this->assertEquals([], $dto->getRelatedIds());
    }

    public function testAttributesNonArrayDefaultsToEmpty(): void
    {
        $dto = new ProductDTO(['attributes' => 'not_an_array']);
        $this->assertEquals([], $dto->getAttributes());
    }

    public function testVariationsNonArrayDefaultsToEmpty(): void
    {
        $dto = new ProductDTO(['variations' => 'not_an_array']);
        $this->assertEquals([], $dto->getVariations());
    }

    public function testMetaDataNonArrayDefaultsToEmpty(): void
    {
        $dto = new ProductDTO(['meta_data' => 'not_an_array']);
        $this->assertEquals([], $dto->getMetaData());
    }

    // --- Price fallback ---

    public function testPriceFallsBackToRegularPrice(): void
    {
        $dto = new ProductDTO(['regular_price' => '25.50']);
        $this->assertEqualsWithDelta(25.50, $dto->getPrice(), 0.001);
    }

    public function testPriceDirectTakesPrecedenceOverRegularPrice(): void
    {
        $dto = new ProductDTO(['price' => 19.99, 'regular_price' => '25.50']);
        $this->assertEqualsWithDelta(19.99, $dto->getPrice(), 0.001);
    }

    // --- Immutability ---

    public function testNoSetterMethodsExist(): void
    {
        $dto = new ProductDTO(['name' => 'Test']);
        $reflection = new \ReflectionObject($dto);
        $methods = $reflection->getMethods();
        $setterCount = 0;
        foreach ($methods as $method) {
            if (strpos($method->getName(), 'set') === 0) {
                $setterCount++;
            }
        }
        $this->assertEquals(0, $setterCount, 'ProductDTO should not have setter methods');
    }
}
