<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\DTO;

use ksfraser\FrontAccounting\Woocommerce\DTO\CustomerDTO;
use PHPUnit\Framework\TestCase;

class CustomerDTOExtendedTest extends TestCase
{
    public function testGetNormalizedPhoneWithPhone(): void
    {
        $dto = new CustomerDTO(['billing' => ['phone' => '555-123-4567']]);
        $this->assertEquals('5551234567', $dto->getNormalizedPhone());
    }

    public function testGetNormalizedPhoneWithoutPhone(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertNull($dto->getNormalizedPhone());
    }

    public function testGetDisplayNameWithCompany(): void
    {
        $dto = new CustomerDTO(['billing' => ['company' => 'ACME Corp', 'first_name' => 'John', 'last_name' => 'Doe']]);
        $this->assertEquals('ACME Corp', $dto->getDisplayName());
    }

    public function testGetDisplayNameWithoutCompany(): void
    {
        $dto = new CustomerDTO(['billing' => ['first_name' => 'John', 'last_name' => 'Doe']]);
        $this->assertEquals('John Doe', $dto->getDisplayName());
    }

    public function testIsImportedReturnsTrue(): void
    {
        $dto = new CustomerDTO(['fa_debtor_no' => 10]);
        $this->assertTrue($dto->isImported());
    }

    public function testIsImportedReturnsFalse(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertFalse($dto->isImported());
    }

    // --- getFaDebtorNo tests ---

    public function testGetFaDebtorNoReturnsInt(): void
    {
        $dto = new CustomerDTO(['fa_debtor_no' => 42]);
        $this->assertSame(42, $dto->getFaDebtorNo());
    }

    public function testGetFaDebtorNoNull(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertNull($dto->getFaDebtorNo());
    }

    // --- getFaBranchRef tests ---

    public function testGetFaBranchRefReturnsString(): void
    {
        $dto = new CustomerDTO(['fa_branch_ref' => 'BR-001']);
        $this->assertEquals('BR-001', $dto->getFaBranchRef());
    }

    public function testGetFaBranchRefNull(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertNull($dto->getFaBranchRef());
    }

    // --- getRawData tests ---

    public function testGetRawDataReturnsInputArray(): void
    {
        $data = ['id' => 5, 'email' => 'test@test.com', 'custom_key' => 'val'];
        $dto = new CustomerDTO($data);
        $this->assertEquals($data, $dto->getRawData());
    }

    public function testGetRawDataEmptyArray(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertEquals([], $dto->getRawData());
    }

    // --- billing customer_id fallback ---

    public function testWooCustomerIdFromBillingCustomerIdOnly(): void
    {
        $dto = new CustomerDTO([
            'billing' => ['customer_id' => 77],
        ]);
        $this->assertEquals(77, $dto->getWooCustomerId());
    }

    public function testWooCustomerIdRootIdOverridesBillingCustomerId(): void
    {
        $dto = new CustomerDTO([
            'id' => 100,
            'billing' => ['customer_id' => 77],
        ]);
        $this->assertEquals(100, $dto->getWooCustomerId());
    }

    // --- Immutability ---

    public function testNoSetterMethodsExist(): void
    {
        $dto = new CustomerDTO(['first_name' => 'Test']);
        $reflection = new \ReflectionObject($dto);
        $methods = $reflection->getMethods();
        $setterCount = 0;
        foreach ($methods as $method) {
            if (strpos($method->getName(), 'set') === 0) {
                $setterCount++;
            }
        }
        $this->assertEquals(0, $setterCount, 'CustomerDTO should not have setter methods');
    }

    // --- Full name edge cases ---

    public function testFullNameFirstOnly(): void
    {
        $dto = new CustomerDTO(['billing' => ['first_name' => 'Jane']]);
        $this->assertEquals('Jane', $dto->getFullName());
    }

    public function testFullNameLastOnly(): void
    {
        $dto = new CustomerDTO(['billing' => ['last_name' => 'Doe']]);
        $this->assertEquals('Doe', $dto->getFullName());
    }

    public function testFullNameBothNames(): void
    {
        $dto = new CustomerDTO(['billing' => ['first_name' => 'John', 'last_name' => 'Doe']]);
        $this->assertEquals('John Doe', $dto->getFullName());
    }

    public function testFullNameEmpty(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertEquals('', $dto->getFullName());
    }

    // --- Display name with empty company ---

    public function testDisplayNameEmptyCompanyFallsBackToFullName(): void
    {
        $dto = new CustomerDTO([
            'billing' => ['company' => '', 'first_name' => 'John', 'last_name' => 'Doe'],
        ]);
        $this->assertEquals('John Doe', $dto->getDisplayName());
    }

    // --- Email priority ---

    public function testEmailFromBillingKeyPriorityOverRootKey(): void
    {
        $dto = new CustomerDTO([
            'email' => 'root@example.com',
            'billing' => ['email' => 'billing@example.com'],
        ]);
        $this->assertEquals('billing@example.com', $dto->getEmail());
    }

    public function testEmailFromRootWhenNoBilling(): void
    {
        $dto = new CustomerDTO([
            'email' => 'root@example.com',
        ]);
        $this->assertEquals('root@example.com', $dto->getEmail());
    }

    public function testEmailNullWhenNoBillingNoEmail(): void
    {
        $dto = new CustomerDTO(['first_name' => 'John']);
        $this->assertNull($dto->getEmail());
    }

    // --- Both FA fields set ---

    public function testBothFaFieldsSet(): void
    {
        $dto = new CustomerDTO([
            'fa_debtor_no' => 42,
            'fa_branch_ref' => 'BR-001',
        ]);
        $this->assertTrue($dto->isImported());
        $this->assertSame(42, $dto->getFaDebtorNo());
        $this->assertEquals('BR-001', $dto->getFaBranchRef());
    }

    // --- Shipping address ---

    public function testShippingAddressEmpty(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertEmpty($dto->getShippingAddress());
    }

    public function testShippingAddressProvided(): void
    {
        $shipping = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_1' => '456 Ship St',
            'city' => 'Ship City',
        ];
        $dto = new CustomerDTO(['shipping' => $shipping]);
        $this->assertEquals($shipping, $dto->getShippingAddress());
    }

    // --- Normalized phone edge cases ---

    public function testNormalizedPhoneWithSpecialCharacters(): void
    {
        $dto = new CustomerDTO(['billing' => ['phone' => '+1 (555) 123-4567']]);
        $this->assertEquals('15551234567', $dto->getNormalizedPhone());
    }

    public function testNormalizedPhoneAllDigits(): void
    {
        $dto = new CustomerDTO(['billing' => ['phone' => '5551234567']]);
        $this->assertEquals('5551234567', $dto->getNormalizedPhone());
    }

    // --- fromWooCommerce ---

    public function testFromWooCommercePreservesAllData(): void
    {
        $data = [
            'id' => 100,
            'email' => 'woo@test.com',
            'first_name' => 'Woo',
            'last_name' => 'Commerce',
            'billing' => [
                'email' => 'billing@test.com',
                'first_name' => 'Woo',
                'last_name' => 'Commerce',
                'company' => 'Woo Inc',
                'phone' => '555-0000',
            ],
        ];
        $dto = CustomerDTO::fromWooCommerce($data);
        $this->assertInstanceOf(CustomerDTO::class, $dto);
        $this->assertEquals(100, $dto->getWooCustomerId());
        $this->assertEquals($data, $dto->getRawData());
    }
}
