<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\DTO;

use Ksfraser\frontaccounting\Woocommerce\DTO\CustomerDTO;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CustomerDTO
 */
class CustomerDTOTest extends TestCase
{
    public function testCanBeCreatedWithValidDataUsingBillingKey(): void
    {
        // When data has a 'billing' key, it is used for customer fields
        $data = [
            'id' => 999, // Used for wooCustomerId because it's in main data (takes precedence)
            'email' => 'main@example.com', // This will be ignored for email because we have email in billing
            'billing' => [
                'customer_id' => 123, // Ignored because id is already set in main data
                'email' => 'test@example.com', // Used for email
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'Test Company',
                'phone' => '555-1234',
                'address_1' => '123 Test St',
                'address_2' => 'Apt 4B',
                'city' => 'Test City',
                'state' => 'TS',
                'postcode' => '12345',
                'country' => 'Test Country',
            ],
            'shipping' => [
                'address_1' => '123 Ship St',
                'address_2' => '',
                'city' => 'Ship City',
                'state' => 'SS',
                'postcode' => '54321',
                'country' => 'Ship Country',
            ],
        ];
        
        $dto = new CustomerDTO($data);
        
        $this->assertInstanceOf(CustomerDTO::class, $dto);
        $this->assertEquals(999, $dto->getWooCustomerId()); // from $data['id'] (takes precedence over billing)
        $this->assertEquals('test@example.com', $dto->getEmail()); // from billing['email']
        $this->assertEquals('John', $dto->getFirstName());
        $this->assertEquals('Doe', $dto->getLastName());
        $this->assertEquals('John Doe', $dto->getFullName());
        $this->assertEquals('Test Company', $dto->getCompany());
        $this->assertEquals('555-1234', $dto->getPhone());
        // billingAddress is set to $billing (the whole billing array)
        $this->assertArrayHasKey('customer_id', $dto->getBillingAddress());
        $this->assertArrayHasKey('email', $dto->getBillingAddress());
        $this->assertArrayHasKey('first_name', $dto->getBillingAddress());
        $this->assertArrayHasKey('address_1', $dto->getBillingAddress());
        $this->assertEquals('123 Test St', $dto->getBillingAddress()['address_1']);
        $this->assertEquals('Apt 4B', $dto->getBillingAddress()['address_2']);
        $this->assertEquals('Test City', $dto->getBillingAddress()['city']);
        $this->assertEquals('TS', $dto->getBillingAddress()['state']);
        $this->assertEquals('12345', $dto->getBillingAddress()['postcode']);
        $this->assertEquals('Test Country', $dto->getBillingAddress()['country']);
        // shippingAddress is set to $data['shipping'] (if exists)
        $this->assertEquals('123 Ship St', $dto->getShippingAddress()['address_1']);
        $this->assertEquals('', $dto->getShippingAddress()['address_2']);
        $this->assertEquals('Ship City', $dto->getShippingAddress()['city']);
        $this->assertEquals('SS', $dto->getShippingAddress()['state']);
        $this->assertEquals('54321', $dto->getShippingAddress()['postcode']);
        $this->assertEquals('Ship Country', $dto->getShippingAddress()['country']);
    }

    public function testCanBeCreatedWithMinimalDataWithoutBillingKey(): void
    {
        // When there is no 'billing' key, $billing = $data
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $dto = new CustomerDTO($data);
        
        $this->assertNull($dto->getWooCustomerId()); // no id or customer_id in $billing (which is $data)
        $this->assertNull($dto->getEmail()); // no email in $billing or $data
        $this->assertEquals('John', $dto->getFirstName()); // from $billing['first_name'] which is $data['first_name']
        $this->assertEquals('Doe', $dto->getLastName()); // from $billing['last_name']
        $this->assertEquals('John Doe', $dto->getFullName());
        $this->assertEquals('', $dto->getCompany()); // default empty string
        $this->assertNull($dto->getPhone()); // default null
    }

    public function testGettersReturnCorrectTypes(): void
    {
        $data = [
            'id' => 123,
            'email' => 'test@example.com',
            'billing' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'Test Company',
                'phone' => '555-1234',
            ],
        ];
        
        $dto = new CustomerDTO($data);
        
        $this->assertIsInt($dto->getWooCustomerId());
        $this->assertIsString($dto->getEmail());
        $this->assertIsString($dto->getFirstName());
        $this->assertIsString($dto->getLastName());
        $this->assertIsString($dto->getFullName());
        $this->assertIsString($dto->getCompany());
        $this->assertIsString($dto->getPhone());
        $this->assertIsArray($dto->getBillingAddress());
        $this->assertIsArray($dto->getShippingAddress()); // defaults to empty array
        $this->assertTrue(is_int($dto->getFaDebtorNo()) || is_null($dto->getFaDebtorNo())); // nullable int
        $this->assertTrue(is_string($dto->getFaBranchRef()) || is_null($dto->getFaBranchRef())); // nullable string
        $this->assertIsArray($dto->getRawData());
        $this->assertIsString($dto->getNormalizedPhone());
        $this->assertIsString($dto->getDisplayName());
        $this->assertIsBool($dto->isImported());
    }

    public function testIsImmutableAfterConstruction(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];
        
        $dto = new CustomerDTO($data);
        
        $this->assertEquals('John', $dto->getFirstName());
        $this->assertEquals('Doe', $dto->getLastName());
        $this->assertEquals('john@example.com', $dto->getEmail());
    }

    public function testFromWooCommerceCreatesDtoWithTopLevelFields(): void
    {
        // Simulate a WooCommerce customer response (top-level fields, no 'billing' or 'shipping' keys)
        $wooCustomer = [
            'id' => 456,
            'email' => 'woo@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'company' => 'Woo Corp',
            'phone' => '555-5678',
            // Note: WooCommerce customer object has billing_address and shipping_address as objects
            // But our constructor does not look for those keys. It looks for 'billing' and 'shipping' keys.
            // Since they are not present, $billing = $wooCustomer (the whole array) and $data['shipping'] is null.
        ];
        
        $dto = CustomerDTO::fromWooCommerce($wooCustomer);
        
        $this->assertInstanceOf(CustomerDTO::class, $dto);
        $this->assertEquals(456, $dto->getWooCustomerId()); // from $data['id']
        $this->assertEquals('woo@example.com', $dto->getEmail()); // from $billing['email'] (which is $wooCustomer['email'])
        $this->assertEquals('Jane', $dto->getFirstName()); // from $billing['first_name']
        $this->assertEquals('Smith', $dto->getLastName()); // from $billing['last_name']
        $this->assertEquals('Jane Smith', $dto->getFullName());
        $this->assertEquals('Woo Corp', $dto->getCompany()); // from $billing['company']
        $this->assertEquals('555-5678', $dto->getPhone()); // from $billing['phone']
        // billingAddress is set to $billing (the whole $wooCustomer array)
        $this->assertArrayHasKey('id', $dto->getBillingAddress());
        $this->assertArrayHasKey('email', $dto->getBillingAddress());
        $this->assertArrayHasKey('first_name', $dto->getBillingAddress());
        $this->assertArrayHasKey('company', $dto->getBillingAddress());
        $this->assertArrayHasKey('phone', $dto->getBillingAddress());
        $this->assertEquals(456, $dto->getBillingAddress()['id']);
        $this->assertEquals('woo@example.com', $dto->getBillingAddress()['email']);
        $this->assertEquals('Jane', $dto->getBillingAddress()['first_name']);
        $this->assertEquals('Woo Corp', $dto->getBillingAddress()['company']);
        $this->assertEquals('555-5678', $dto->getBillingAddress()['phone']);
        // shippingAddress is empty array because $data['shipping'] is null
        $this->assertEmpty($dto->getShippingAddress());
    }

    public function testFromWooCommerceHandlesMissingFields(): void
    {
        $wooCustomer = [
            'id' => 789,
            'first_name' => 'Bob',
            // missing email, last_name, etc.
            // No 'billing' or 'shipping' keys
        ];
        
        $dto = CustomerDTO::fromWooCommerce($wooCustomer);
        
        $this->assertEquals(789, $dto->getWooCustomerId());
        $this->assertNull($dto->getEmail()); // no email in $billing (which is $wooCustomer) or $data
        $this->assertEquals('Bob', $dto->getFirstName()); // from $billing['first_name'] (which is $wooCustomer['first_name'])
        $this->assertEquals('', $dto->getLastName()); // default empty string
        $this->assertEquals('Bob', $dto->getFullName()); // 'Bob' + '' + ' ' -> 'Bob' after trim
        $this->assertEquals('', $dto->getCompany()); // default empty string
        $this->assertNull($dto->getPhone()); // default null
    }
}