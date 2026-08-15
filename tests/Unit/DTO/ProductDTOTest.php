<?php
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\DTO;

use ksfraser\FrontAccounting\Woocommerce\DTO\ProductDTO;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProductDTO
 */
class ProductDTOTest extends TestCase
{
    public function testCanBeCreatedWithValidData(): void
    {
        $data = [
            'id' => 123,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'permalink' => 'https://example.com/product/test-product',
            'date_created' => '2023-01-01 10:00:00',
            'date_modified' => '2023-01-02 11:00:00',
            'type' => 'simple',
            'status' => 'publish',
            'featured' => false,
            'catalog_visibility' => 'visible',
            'description' => '<p>This is a test product</p>',
            'short_description' => '<p>A test product</p>',
            'sku' => 'TEST-123',
            'price' => 29.99,
            'regular_price' => '',
            'sale_price' => '',
            'date_on_sale_from' => '',
            'date_on_sale_to' => '',
            'total_sales' => 0,
            'tax_status' => 'taxable',
            'tax_class' => '',
            'manage_stock' => true,
            'stock_quantity' => null,
            'in_stock' => true,
            'backorders' => 'no',
            'backorders_allowed' => false,
            'backordered' => false,
            'sold_individually' => false,
            'weight' => '',
            'dimensions' => [
                'length' => '',
                'width' => '',
                'height' => ''
            ],
            'shipping_required' => true,
            'shipping_taxable' => true,
            'shipping_class' => '',
            'shipping_class_id' => 0,
            'reviews_allowed' => true,
            'average_rating' => '0',
            'rating_count' => 0,
            'related_ids' => [124, 125],
            'upsell_ids' => [],
            'cross_sell_ids' => [],
            'parent_id' => 0,
            'purchase_note' => '',
            'attributes' => [],
            'default_attributes' => [],
            'variations' => [],
            'grouped_products' => [],
            'menu_order' => 0,
            'meta_data' => [],
        ];
        
        $dto = new ProductDTO($data);
        
        $this->assertInstanceOf(ProductDTO::class, $dto);
        $this->assertEquals(123, $dto->getId());
        $this->assertEquals('Test Product', $dto->getName());
        $this->assertEquals('test-product', $dto->getSlug());
        $this->assertEquals('https://example.com/product/test-product', $dto->getPermalink());
        $this->assertEquals('2023-01-01 10:00:00', $dto->getDateCreated());
        $this->assertEquals('2023-01-02 11:00:00', $dto->getDateModified());
        $this->assertEquals('simple', $dto->getType());
        $this->assertEquals('publish', $dto->getStatus());
        $this->assertFalse($dto->getFeatured());
        $this->assertEquals('visible', $dto->getCatalogVisibility());
        $this->assertEquals('<p>This is a test product</p>', $dto->getDescription());
        $this->assertEquals('<p>A test product</p>', $dto->getShortDescription());
        $this->assertEquals('TEST-123', $dto->getSku());
        $this->assertEquals(29.99, $dto->getPrice());
        $this->assertEquals('', $dto->getRegularPrice());
        $this->assertEquals('', $dto->getSalePrice());
        $this->assertEquals('', $dto->getDateOnSaleFrom());
        $this->assertEquals('', $dto->getDateOnSaleTo());
        $this->assertEquals(0, $dto->getTotalSales());
        $this->assertEquals('taxable', $dto->getTaxStatus());
        $this->assertEquals('', $dto->getTaxClass());
        $this->assertTrue($dto->getManageStock());
        $this->assertNull($dto->getStockQuantity());
        $this->assertTrue($dto->getInStock());
        $this->assertEquals('no', $dto->getBackorders());
        $this->assertFalse($dto->getBackordersAllowed());
        $this->assertFalse($dto->getBackordered());
        $this->assertFalse($dto->getSoldIndividually());
        $this->assertEquals('', $dto->getWeight());
        $this->assertEquals(['length' => '', 'width' => '', 'height' => ''], $dto->getDimensions());
        $this->assertTrue($dto->getShippingRequired());
        $this->assertTrue($dto->getShippingTaxable());
        $this->assertEquals('', $dto->getShippingClass());
        $this->assertEquals(0, $dto->getShippingClassId());
        $this->assertTrue($dto->getReviewsAllowed());
        $this->assertEquals('0', $dto->getAverageRating());
        $this->assertEquals(0, $dto->getRatingCount());
        $this->assertEquals([124, 125], $dto->getRelatedIds());
        $this->assertEquals([], $dto->getUpsellIds());
        $this->assertEquals([], $dto->getCrossSellIds());
        $this->assertEquals(0, $dto->getParentId());
        $this->assertEquals('', $dto->getPurchaseNote());
        $this->assertEquals([], $dto->getAttributes());
        $this->assertEquals([], $dto->getDefaultAttributes());
        $this->assertEquals([], $dto->getVariations());
        $this->assertEquals([], $dto->getGroupedProducts());
        $this->assertEquals(0, $dto->getMenuOrder());
        $this->assertEquals([], $dto->getMetaData());
    }

    public function testCanBeCreatedWithMinimalData(): void
    {
        $data = [];
        
        $dto = new ProductDTO($data);
        
        $this->assertEquals(0, $dto->getId());
        $this->assertEquals('', $dto->getName());
        $this->assertEquals('', $dto->getSlug());
        $this->assertEquals('', $dto->getPermalink());
        $this->assertNull($dto->getDateCreated());
        $this->assertNull($dto->getDateModified());
        $this->assertEquals('simple', $dto->getType());
        $this->assertEquals('publish', $dto->getStatus());
        $this->assertFalse($dto->getFeatured());
        $this->assertEquals('visible', $dto->getCatalogVisibility());
        $this->assertEquals('', $dto->getDescription());
        $this->assertEquals('', $dto->getShortDescription());
        $this->assertEquals('', $dto->getSku());
        $this->assertEquals(0.0, $dto->getPrice());
        $this->assertEquals('', $dto->getRegularPrice());
        $this->assertEquals('', $dto->getSalePrice());
        $this->assertEquals('', $dto->getDateOnSaleFrom());
        $this->assertEquals('', $dto->getDateOnSaleTo());
        $this->assertEquals(0, $dto->getTotalSales());
        $this->assertEquals('taxable', $dto->getTaxStatus());
        $this->assertEquals('', $dto->getTaxClass());
        $this->assertFalse($dto->getManageStock());
        $this->assertNull($dto->getStockQuantity());
        $this->assertTrue($dto->getInStock());
        $this->assertEquals('no', $dto->getBackorders());
        $this->assertFalse($dto->getBackordersAllowed());
        $this->assertFalse($dto->getBackordered());
        $this->assertFalse($dto->getSoldIndividually());
        $this->assertNull($dto->getWeight());
        $this->assertNull($dto->getDimensions());
        $this->assertTrue($dto->getShippingRequired());
        $this->assertTrue($dto->getShippingTaxable());
        $this->assertEquals('', $dto->getShippingClass());
        $this->assertEquals(0, $dto->getShippingClassId());
        $this->assertTrue($dto->getReviewsAllowed());
        $this->assertEquals('0', $dto->getAverageRating());
        $this->assertEquals(0, $dto->getRatingCount());
        $this->assertEquals(0, $dto->getTotalSales());
        $this->assertEquals('taxable', $dto->getTaxStatus());
        $this->assertEquals('', $dto->getTaxClass());
        $this->assertFalse($dto->getManageStock());
        $this->assertNull($dto->getStockQuantity());
        $this->assertTrue($dto->getInStock());
        $this->assertEquals('no', $dto->getBackorders());
        $this->assertFalse($dto->getBackordersAllowed());
        $this->assertFalse($dto->getBackordered());
        $this->assertFalse($dto->getSoldIndividually());
        $this->assertNull($dto->getWeight());
        $this->assertNull($dto->getDimensions());
        $this->assertTrue($dto->getShippingRequired());
        $this->assertTrue($dto->getShippingTaxable());
        $this->assertEquals('', $dto->getShippingClass());
        $this->assertEquals(0, $dto->getShippingClassId());
        $this->assertTrue($dto->getReviewsAllowed());
        $this->assertEquals('0', $dto->getAverageRating());
        $this->assertEquals(0, $dto->getRatingCount());
        $this->assertEquals([], $dto->getRelatedIds());
        $this->assertEquals([], $dto->getUpsellIds());
        $this->assertEquals([], $dto->getCrossSellIds());
        $this->assertEquals(0, $dto->getParentId());
        $this->assertEquals('', $dto->getPurchaseNote());
        $this->assertEquals([], $dto->getAttributes());
        $this->assertEquals([], $dto->getDefaultAttributes());
        $this->assertEquals([], $dto->getVariations());
        $this->assertEquals([], $dto->getGroupedProducts());
        $this->assertEquals(0, $dto->getMenuOrder());
        $this->assertEquals([], $dto->getMetaData());
    }

    public function testGettersReturnCorrectTypes(): void
    {
        $data = [
            'id' => 456,
            'name' => 'Another Product',
            'slug' => 'another-product',
            'permalink' => 'https://example.com/product/another-product',
            'date_created' => '2023-01-03 12:00:00',
            'date_modified' => '2023-01-04 13:00:00',
            'type' => 'variable',
            'status' => 'draft',
            'featured' => true,
            'catalog_visibility' => 'hidden',
            'description' => '<p>Another product description</p>',
            'short_description' => '<p>Short description</p>',
            'sku' => 'ANOTHER-456',
            'price' => 99.99,
            'regular_price' => '109.99',
            'sale_price' => '89.99',
            'date_on_sale_from' => '2023-01-05',
            'date_on_sale_to' => '2023-01-15',
            'total_sales' => 5,
            'tax_status' => 'shipping',
            'tax_class' => 'reduced-rate',
            'manage_stock' => false,
            'stock_quantity' => 10,
            'in_stock' => true,
            'backorders' => 'yes',
            'backorders_allowed' => true,
            'backordered' => false,
            'sold_individually' => true,
            'weight' => '1.5',
            'dimensions' => [
                'length' => '10',
                'width' => '5',
                'height' => '2'
            ],
            'shipping_required' => false,
            'shipping_taxable' => false,
            'shipping_class' => 'large-items',
            'shipping_class_id' => 5,
            'reviews_allowed' => false,
            'average_rating' => '4.5',
            'rating_count' => 12,
            'related_ids' => [457, 458],
            'upsell_ids' => [459],
            'cross_sell_ids' => [460, 461],
            'parent_id' => 0,
            'purchase_note' => 'Thank you for your purchase!',
            'attributes' => [
                [
                    'id' => 1,
                    'name' => 'Color',
                    'position' => 0,
                    'visible' => true,
                    'variation' => true,
                    'options' => ['Red', 'Blue', 'Green']
                ]
            ],
            'default_attributes' => [
                [
                    'name' => 'Color',
                    'value' => 'Red'
                ]
            ],
            'variations' => [
                [
                    'id' => 1001,
                    'name' => 'Product Variation 1',
                    'slug' => 'product-variation-1',
                    'permalink' => 'https://example.com/product/product-variation-1',
                    'date_created' => '2023-01-05 14:00:00',
                    'date_modified' => '2023-01-06 15:00:00',
                    'type' => 'variation',
                    'status' => 'publish',
                    'featured' => false,
                    'catalog_visibility' => 'visible',
                    'description' => '',
                    'short_description' => '',
                    'sku' => 'VAR-001',
                    'price' => 79.99,
                    'regular_price' => '',
                    'sale_price' => '',
                    'date_on_sale_from' => '',
                    'date_on_sale_to' => '',
                    'total_sales' => 0,
                    'tax_status' => 'taxable',
                    'tax_class' => '',
                    'manage_stock' => true,
                    'stock_quantity' => 5,
                    'in_stock' => true,
                    'backorders' => 'no',
                    'backorders_allowed' => false,
                    'backordered' => false,
                    'sold_individually' => false,
                    'weight' => '',
                    'dimensions' => [
                        'length' => '',
                        'width' => '',
                        'height' => ''
                    ],
                    'shipping_required' => true,
                    'shipping_taxable' => true,
                    'shipping_class' => '',
                    'shipping_class_id' => 0,
                    'reviews_allowed' => true,
                    'average_rating' => '0',
                    'rating_count' => 0
                ]
            ],
            'grouped_products' => [1002, 1003],
            'menu_order' => 1,
            'meta_data' => [
                [
                    'id' => 1,
                    'key' => '_test_meta',
                    'value' => 'test_value'
                ]
            ]
        ];
        
        $dto = new ProductDTO($data);
        
        $this->assertIsInt($dto->getId());
        $this->assertIsString($dto->getName());
        $this->assertIsString($dto->getSlug());
        $this->assertIsString($dto->getPermalink());
        $this->assertIsString($dto->getDateCreated());
        $this->assertIsString($dto->getDateModified());
        $this->assertIsString($dto->getType());
        $this->assertIsString($dto->getStatus());
        $this->assertIsBool($dto->getFeatured());
        $this->assertIsString($dto->getCatalogVisibility());
        $this->assertIsString($dto->getDescription());
        $this->assertIsString($dto->getShortDescription());
        $this->assertIsString($dto->getSku());
        $this->assertIsFloat($dto->getPrice());
        $this->assertIsString($dto->getRegularPrice());
        $this->assertIsString($dto->getSalePrice());
        $this->assertIsString($dto->getDateOnSaleFrom());
        $this->assertIsString($dto->getDateOnSaleTo());
        $this->assertIsInt($dto->getTotalSales());
        $this->assertIsString($dto->getTaxStatus());
        $this->assertIsString($dto->getTaxClass());
        $this->assertIsBool($dto->getManageStock());
        $this->assertIsInt($dto->getStockQuantity());
        $this->assertIsBool($dto->getInStock());
        $this->assertIsString($dto->getBackorders());
        $this->assertIsBool($dto->getBackordersAllowed());
        $this->assertIsBool($dto->getBackordered());
        $this->assertIsBool($dto->getSoldIndividually());
        $this->assertIsFloat($dto->getWeight());
        $this->assertIsArray($dto->getDimensions());
        $this->assertIsBool($dto->getShippingRequired());
        $this->assertIsBool($dto->getShippingTaxable());
        $this->assertIsString($dto->getShippingClass());
        $this->assertIsInt($dto->getShippingClassId());
        $this->assertIsBool($dto->getReviewsAllowed());
        $this->assertIsString($dto->getAverageRating());
        $this->assertIsInt($dto->getRatingCount());
        $this->assertIsArray($dto->getRelatedIds());
        $this->assertIsArray($dto->getUpsellIds());
        $this->assertIsArray($dto->getCrossSellIds());
        $this->assertIsInt($dto->getParentId());
        $this->assertIsString($dto->getPurchaseNote());
        $this->assertIsArray($dto->getAttributes());
        $this->assertIsArray($dto->getDefaultAttributes());
        $this->assertIsArray($dto->getVariations());
        $this->assertIsArray($dto->getGroupedProducts());
        $this->assertIsInt($dto->getMenuOrder());
        $this->assertIsArray($dto->getMetaData());
    }

    public function testIsImmutableAfterConstruction(): void
    {
        $data = [
            'id' => 789,
            'name' => 'Immutable Product',
            'slug' => 'immutable-product',
        ];
        
        $dto = new ProductDTO($data);
        
        $this->assertEquals(789, $dto->getId());
        $this->assertEquals('Immutable Product', $dto->getName());
        $this->assertEquals('immutable-product', $dto->getSlug());
        
        // We can't actually test immutability since PHP doesn't have true immutability
        // But we can verify that there are no setter methods
        $reflection = new \ReflectionObject($dto);
        $methods = $reflection->getMethods();
        $setterCount = 0;
        foreach ($methods as $method) {
            if (strpos($method->getName(), 'set') === 0) {
                $setterCount++;
            }
        }
        $this->assertEquals(0, $setterCount, 'ProductDTO should not have any setter methods');
    }

    public function testFromWooCommerceCreatesDtoCorrectly(): void
    {
        // Simulate a WooCommerce product response
        $wooProduct = [
            'id' => 999,
            'name' => 'WooCommerce Product',
            'slug' => 'woocommerce-product',
            'permalink' => 'https://example.com/product/woocommerce-product',
            'date_created' => '2023-01-07T16:00:00',
            'date_modified' => '2023-01-08T17:00:00',
            'type' => 'grouped',
            'status' => 'publish',
            'featured' => true,
            'catalog_visibility' => 'catalog',
            'description' => '<p>WooCommerce product description</p>',
            'short_description' => '<p>WC product</p>',
            'sku' => 'WC-999',
            'price' => '149.99',
            'regular_price' => '',
            'sale_price' => '',
            'date_on_sale_from' => '',
            'date_on_sale_to' => '',
            'total_sales' => '10',
            'tax_status' => 'taxable',
            'tax_class' => '',
            'manage_stock' => false,
            'stock_quantity' => null,
            'in_stock' => true,
            'backorders' => 'no',
            'backorders_allowed' => false,
            'backordered' => false,
            'sold_individually' => false,
            'weight' => '2.5',
            'dimensions' => [
                'length' => '15',
                'width' => '10',
                'height' => '5'
            ],
            'shipping_required' => true,
            'shipping_taxable' => true,
            'shipping_class' => 'medium-items',
            'shipping_class_id' => 3,
            'reviews_allowed' => true,
            'average_rating' => '4.2',
            'rating_count' => 25,
            'related_ids' => [1000, 1001],
            'upsell_ids' => [1002],
            'cross_sell_ids' => [1003, 1004],
            'parent_id' => 0,
            'purchase_note' => 'Thanks for buying from WooCommerce!',
            'attributes' => [
                [
                    'id' => 2,
                    'name' => 'Size',
                    'position' => 1,
                    'visible' => true,
                    'variation' => true,
                    'options' => ['Small', 'Medium', 'Large']
                ]
            ],
            'default_attributes' => [],
            'variations' => [],
            'grouped_products' => [2000, 2001, 2002],
            'menu_order' => 2,
            'meta_data' => [
                [
                    'id' => 2,
                    'key' => '_wc_test_meta',
                    'value' => 'wc_test_value'
                ],
                [
                    'id' => 3,
                    'key' => '_another_meta',
                    'value' => 'another_value'
                ]
            ]
        ];
        
        $dto = ProductDTO::fromWooCommerce($wooProduct);
        
        $this->assertInstanceOf(ProductDTO::class, $dto);
        $this->assertEquals(999, $dto->getId());
        $this->assertEquals('WooCommerce Product', $dto->getName());
        $this->assertEquals('woocommerce-product', $dto->getSlug());
        $this->assertEquals('https://example.com/product/woocommerce-product', $dto->getPermalink());
        $this->assertEquals('2023-01-07T16:00:00', $dto->getDateCreated());
        $this->assertEquals('2023-01-08T17:00:00', $dto->getDateModified());
        $this->assertEquals('grouped', $dto->getType());
        $this->assertEquals('publish', $dto->getStatus());
        $this->assertTrue($dto->getFeatured());
        $this->assertEquals('catalog', $dto->getCatalogVisibility());
        $this->assertEquals('<p>WooCommerce product description</p>', $dto->getDescription());
        $this->assertEquals('<p>WC product</p>', $dto->getShortDescription());
        $this->assertEquals('WC-999', $dto->getSku());
        $this->assertEquals(149.99, $dto->getPrice()); // String converted to float
        $this->assertEquals('', $dto->getRegularPrice());
        $this->assertEquals('', $dto->getSalePrice());
        $this->assertEquals('', $dto->getDateOnSaleFrom());
        $this->assertEquals('', $dto->getDateOnSaleTo());
        $this->assertEquals(10, $dto->getTotalSales()); // String converted to int
        $this->assertEquals('taxable', $dto->getTaxStatus());
        $this->assertEquals('', $dto->getTaxClass());
        $this->assertFalse($dto->getManageStock());
        $this->assertNull($dto->getStockQuantity());
        $this->assertTrue($dto->getInStock());
        $this->assertEquals('no', $dto->getBackorders());
        $this->assertFalse($dto->getBackordersAllowed());
        $this->assertFalse($dto->getBackordered());
        $this->assertFalse($dto->getSoldIndividually());
        $this->assertEquals(2.5, $dto->getWeight());
        $this->assertEquals(['length' => '15', 'width' => '10', 'height' => '5'], $dto->getDimensions());
        $this->assertTrue($dto->getShippingRequired());
        $this->assertTrue($dto->getShippingTaxable());
        $this->assertEquals('medium-items', $dto->getShippingClass());
        $this->assertEquals(3, $dto->getShippingClassId());
        $this->assertTrue($dto->getReviewsAllowed());
        $this->assertEquals('4.2', $dto->getAverageRating());
        $this->assertEquals(25, $dto->getRatingCount());
        $this->assertEquals([1000, 1001], $dto->getRelatedIds());
        $this->assertEquals([1002], $dto->getUpsellIds());
        $this->assertEquals([1003, 1004], $dto->getCrossSellIds());
        $this->assertEquals(0, $dto->getParentId());
        $this->assertEquals('Thanks for buying from WooCommerce!', $dto->getPurchaseNote());
        $this->assertCount(1, $dto->getAttributes());
        $this->assertEquals([], $dto->getDefaultAttributes());
        $this->assertEquals([], $dto->getVariations());
        $this->assertEquals([2000, 2001, 2002], $dto->getGroupedProducts());
        $this->assertEquals(2, $dto->getMenuOrder());
        $this->assertCount(2, $dto->getMetaData());
    }

    public function testFromWooCommerceHandlesMissingFields(): void
    {
        $wooProduct = [
            'id' => 111,
            // missing most fields
        ];
        
        $dto = ProductDTO::fromWooCommerce($wooProduct);
        
        $this->assertEquals(111, $dto->getId());
        $this->assertEquals('', $dto->getName());
        $this->assertEquals('', $dto->getSlug());
        $this->assertEquals('', $dto->getPermalink());
        $this->assertNull($dto->getDateCreated());
        $this->assertNull($dto->getDateModified());
        $this->assertEquals('simple', $dto->getType()); // default
        $this->assertEquals('publish', $dto->getStatus()); // default
        $this->assertFalse($dto->getFeatured()); // default
        $this->assertEquals('visible', $dto->getCatalogVisibility()); // default
        $this->assertEquals('', $dto->getDescription());
        $this->assertEquals('', $dto->getShortDescription());
        $this->assertEquals('', $dto->getSku());
        $this->assertEquals(0.0, $dto->getPrice());
        $this->assertEquals('', $dto->getRegularPrice());
        $this->assertEquals('', $dto->getSalePrice());
        $this->assertEquals('', $dto->getDateOnSaleFrom());
        $this->assertEquals('', $dto->getDateOnSaleTo());
        $this->assertEquals(0, $dto->getTotalSales());
        $this->assertEquals('taxable', $dto->getTaxStatus());
        $this->assertEquals('', $dto->getTaxClass());
        $this->assertFalse($dto->getManageStock());
        $this->assertNull($dto->getStockQuantity());
        $this->assertTrue($dto->getInStock());
        $this->assertEquals('no', $dto->getBackorders());
        $this->assertFalse($dto->getBackordersAllowed());
        $this->assertFalse($dto->getBackordered());
        $this->assertFalse($dto->getSoldIndividually());
        $this->assertNull($dto->getWeight());
        $this->assertNull($dto->getDimensions());
        $this->assertTrue($dto->getShippingRequired());
        $this->assertTrue($dto->getShippingTaxable());
        $this->assertEquals('', $dto->getShippingClass());
        $this->assertEquals(0, $dto->getShippingClassId());
        $this->assertTrue($dto->getReviewsAllowed());
        $this->assertEquals('0', $dto->getAverageRating());
        $this->assertEquals(0, $dto->getRatingCount());
        $this->assertEquals([], $dto->getRelatedIds());
        $this->assertEquals([], $dto->getUpsellIds());
        $this->assertEquals([], $dto->getCrossSellIds());
        $this->assertEquals(0, $dto->getParentId());
        $this->assertEquals('', $dto->getPurchaseNote());
        $this->assertEquals([], $dto->getAttributes());
        $this->assertEquals([], $dto->getDefaultAttributes());
        $this->assertEquals([], $dto->getVariations());
        $this->assertEquals([], $dto->getGroupedProducts());
        $this->assertEquals(0, $dto->getMenuOrder());
        $this->assertEquals([], $dto->getMetaData());
    }
}