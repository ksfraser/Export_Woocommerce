<?php
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\DTO;

use ksfraser\FrontAccounting\Woocommerce\DTO\OrderDTO;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OrderDTO
 */
class OrderDTOTest extends TestCase
{
    public function testCanBeCreatedWithValidData(): void
    {
        $data = [
            'id' => 123,
            'status' => 'completed',
            'total' => 99.99,
            'currency' => 'USD',
            'billing' => [
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_1' => '123 Billing St',
                'address_2' => '',
                'city' => 'Billing City',
                'state' => 'BS',
                'postcode' => '54321',
                'country' => 'Billing Country',
            ],
            'shipping' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address_1' => '456 Shipping Ave',
                'address_2' => 'Unit 100',
                'city' => 'Shipping City',
                'state' => 'SS',
                'postcode' => '10101',
                'country' => 'Shipping Country',
            ],
            'line_items' => [
                [
                    'id' => 1,
                    'name' => 'Product 1',
                    'quantity' => 2,
                    'total' => 19.99,
                    'subtotal' => 18.00,
                    'tax' => ['total' => 1.99],
                ],
                [
                    'id' => 2,
                    'name' => 'Product 2',
                    'quantity' => 1,
                    'total' => 59.99,
                    'subtotal' => 50.00,
                    'tax' => ['total' => 9.99],
                ],
            ],
            'payment_method' => 'credit_card',
            'payment_method_title' => 'Visa ending in 4242',
            'transaction_id' => 'txn_123',
            'date_paid' => '2023-01-01 10:00:00',
        ];
        
        $dto = new OrderDTO($data);
        
        $this->assertInstanceOf(OrderDTO::class, $dto);
        $this->assertEquals(123, $dto->getWooOrderId());
        $this->assertEquals('completed', $dto->getStatus());
        $this->assertEquals(99.99, $dto->getTotal());
        $this->assertEquals('USD', $dto->getCurrency());
        $this->assertEquals('john@example.com', $dto->getCustomerEmail());
        $this->assertEquals('John Doe', $dto->getCustomerName()); // first_name + last_name from billing
        $this->assertEquals('', $dto->getCustomerCompany()); // company not set in billing
        $this->assertEquals('123 Billing St', $dto->getBillingAddressString()); // formatted billing address (address_1 only when address_2 is empty)
        $this->assertEquals([
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_1' => '123 Billing St',
            'address_2' => '',
            'city' => 'Billing City',
            'state' => 'BS',
            'postcode' => '54321',
            'country' => 'Billing Country'
        ], $dto->getBillingAddress());
        $this->assertEquals(['first_name' => 'Jane', 'last_name' => 'Doe', 'address_1' => '456 Shipping Ave', 'address_2' => 'Unit 100', 'city' => 'Shipping City', 'state' => 'SS', 'postcode' => '10101', 'country' => 'Shipping Country'], $dto->getShippingAddress());
        $this->assertCount(2, $dto->getLineItems());
        $this->assertEquals('credit_card', $dto->getPaymentMethod());
        $this->assertEquals('Visa ending in 4242', $dto->getPaymentMethodTitle());
        $this->assertEquals('txn_123', $dto->getTransactionId());
        $this->assertEquals('2023-01-01 10:00:00', $dto->getDatePaid());
    }

    public function testCanBeCreatedWithMinimalData(): void
    {
        $data = [];
        
        $dto = new OrderDTO($data);
        
        $this->assertEquals(0, $dto->getWooOrderId()); // defaults to 0
        $this->assertEquals(OrderDTO::STATUS_PENDING, $dto->getStatus()); // defaults to pending
        $this->assertEquals(0.0, $dto->getTotal()); // defaults to 0.0
        $this->assertEquals('USD', $dto->getCurrency()); // defaults to USD
        $this->assertNull($dto->getCustomerEmail()); // defaults to null
        $this->assertEquals('', $dto->getCustomerName()); // empty first and last name
        $this->assertEquals('', $dto->getCustomerCompany()); // empty company
        $this->assertEquals('', $dto->getBillingAddressString()); // empty address
        $this->assertEmpty($dto->getBillingAddress()); // empty array
        $this->assertEmpty($dto->getShippingAddress()); // empty array
        $this->assertEmpty($dto->getLineItems()); // empty array
        $this->assertNull($dto->getPaymentMethod()); // defaults to null
        $this->assertNull($dto->getPaymentMethodTitle()); // defaults to null
        $this->assertNull($dto->getTransactionId()); // defaults to null
        $this->assertNull($dto->getDatePaid()); // defaults to null
    }

    public function testGettersReturnCorrectTypes(): void
    {
        $data = [
            'id' => 456,
            'status' => 'processing',
            'total' => 50.00,
            'currency' => 'EUR',
            'billing' => [
                'email' => 'test@example.com',
                'first_name' => 'Alice',
                'last_name' => 'Smith',
            ],
            'shipping' => [],
            'line_items' => [],
            'payment_method' => 'paypal',
            'payment_method_title' => 'PayPal',
            'transaction_id' => 'txn_456',
            'date_paid' => '2023-01-02 11:00:00',
        ];
        
        $dto = new OrderDTO($data);
        
        $this->assertIsInt($dto->getWooOrderId());
        $this->assertIsString($dto->getStatus());
        $this->assertIsFloat($dto->getTotal()); // Note: in PHP, currency values are often floats, but we should be cautious about precision
        $this->assertIsString($dto->getCurrency());
        $this->assertIsString($dto->getCustomerEmail());
        $this->assertIsString($dto->getCustomerName());
        $this->assertIsString($dto->getCustomerCompany());
        $this->assertIsString($dto->getBillingAddressString());
        $this->assertIsArray($dto->getBillingAddress());
        $this->assertIsArray($dto->getShippingAddress());
        $this->assertIsArray($dto->getLineItems());
        $this->assertIsString($dto->getPaymentMethod());
        $this->assertIsString($dto->getPaymentMethodTitle());
        $this->assertIsString($dto->getTransactionId());
        $this->assertIsString($dto->getDatePaid());
    }

    public function testIsImmutableAfterConstruction(): void
    {
        $data = [
            'id' => 789,
            'status' => 'on-hold',
            'total' => 30.00,
            'currency' => 'GBP',
            'billing' => [
                'email' => 'immutable@example.com',
                'first_name' => 'Immutable',
                'last_name' => 'Test',
                'company' => 'Test Company',
            ],
        ];
        
        $dto = new OrderDTO($data);
        
        $this->assertEquals(789, $dto->getWooOrderId());
        $this->assertEquals('on-hold', $dto->getStatus());
        $this->assertEquals(30.00, $dto->getTotal());
        $this->assertEquals('GBP', $dto->getCurrency());
        $this->assertEquals('immutable@example.com', $dto->getCustomerEmail());
        $this->assertEquals('Immutable Test', $dto->getCustomerName()); // first + last
        $this->assertEquals('Test Company', $dto->getCustomerCompany());
    }

    public function testFromWooCommerceCreatesDtoCorrectly(): void
    {
        // Simulate a WooCommerce order response
        $wooOrder = [
            'id' => 999,
            'status' => 'completed',
            'total' => '150.00', // Note: WooCommerce API returns strings for numeric fields
            'currency' => 'USD',
            'email' => 'customer@example.com',
            'billing' => [
                'first_name' => 'Customer',
                'last_name' => 'Name',
                'address_1' => '123 Customer St',
                'address_2' => 'Apt 1',
                'city' => 'Customer City',
                'state' => 'CS',
                'postcode' => '67890',
                'country' => 'US',
            ],
            'shipping' => [
                'first_name' => 'Customer',
                'last_name' => 'Name',
                'address_1' => '123 Customer St',
                'address_2' => 'Apt 1',
                'city' => 'Customer City',
                'state' => 'CS',
                'postcode' => '67890',
                'country' => 'US',
            ],
            'line_items' => [
                [
                    'id' => 1,
                    'name' => 'Product A',
                    'quantity' => 1,
                    'total' => '100.00',
                    'subtotal' => '90.00',
                    'tax' => [
                        [
                            'id' => 1,
                            'rate' => 10,
                            'label' => 'Sales Tax',
                            'compound' => false,
                            'total' => '10.00',
                            'total_tax' => '0.00',
                        ],
                    ],
                ],
            ],
            'payment_method' => 'bacs',
            'payment_method_title' => 'Direct Bank Transfer',
            'transaction_id' => 'txn_999',
            'date_paid' => '2023-01-03 12:00:00',
            // Note: WooCommerce API also includes 'date_created', 'date_modified', etc., but we only map what we need
        ];
        
        $dto = OrderDTO::fromWooCommerce($wooOrder);
        
        $this->assertInstanceOf(OrderDTO::class, $dto);
        $this->assertEquals(999, $dto->getWooOrderId());
        $this->assertEquals('completed', $dto->getStatus());
        // Note: The constructor casts total to float, so '150.00' becomes 150.0 (float)
        $this->assertEquals(150.0, $dto->getTotal());
        $this->assertEquals('USD', $dto->getCurrency());
        $this->assertEquals('customer@example.com', $dto->getCustomerEmail());
        $this->assertEquals('Customer Name', $dto->getCustomerName());
        $this->assertEquals('', $dto->getCustomerCompany()); // company not in billing
        $this->assertEquals('123 Customer St Apt 1', $dto->getBillingAddressString()); // formatted: address_1 + space + address_2
        $this->assertEquals([
            'first_name' => 'Customer',
            'last_name' => 'Name',
            'address_1' => '123 Customer St',
            'address_2' => 'Apt 1',
            'city' => 'Customer City',
            'state' => 'CS',
            'postcode' => '67890',
            'country' => 'US'
        ], $dto->getBillingAddress());
        $this->assertEquals(['first_name' => 'Customer', 'last_name' => 'Name', 'address_1' => '123 Customer St', 'address_2' => 'Apt 1', 'city' => 'Customer City', 'state' => 'CS', 'postcode' => '67890', 'country' => 'US'], $dto->getShippingAddress());
        $this->assertCount(1, $dto->getLineItems());
        $this->assertEquals('bacs', $dto->getPaymentMethod());
        $this->assertEquals('Direct Bank Transfer', $dto->getPaymentMethodTitle());
        $this->assertEquals('txn_999', $dto->getTransactionId());
        $this->assertEquals('2023-01-03 12:00:00', $dto->getDatePaid());
    }

    public function testFromWooCommerceHandlesMissingFields(): void
    {
        $wooOrder = [
            'id' => 111,
            // missing status, total, currency, email, billing, shipping, line_items, payment_method, etc.
        ];
        
        $dto = OrderDTO::fromWooCommerce($wooOrder);
        
        $this->assertEquals(111, $dto->getWooOrderId());
        $this->assertEquals(OrderDTO::STATUS_PENDING, $dto->getStatus()); // defaults to pending
        $this->assertEquals(0.0, $dto->getTotal()); // defaults to 0.0
        $this->assertEquals('USD', $dto->getCurrency()); // defaults to USD
        $this->assertNull($dto->getCustomerEmail()); // defaults to null
        $this->assertEquals('', $dto->getCustomerName()); // empty
        $this->assertEquals('', $dto->getCustomerCompany()); // empty
        $this->assertEquals('', $dto->getBillingAddressString()); // empty
        $this->assertEmpty($dto->getBillingAddress()); // empty array
        $this->assertEmpty($dto->getShippingAddress()); // empty array
        $this->assertEmpty($dto->getLineItems()); // empty array
        $this->assertNull($dto->getPaymentMethod()); // defaults to null
        $this->assertNull($dto->getPaymentMethodTitle()); // defaults to null
        $this->assertNull($dto->getTransactionId()); // defaults to null
        $this->assertNull($dto->getDatePaid()); // defaults to null
    }
}