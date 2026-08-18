<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\DTO;

use ksfraser\FrontAccounting\Woocommerce\DTO\OrderDTO;
use PHPUnit\Framework\TestCase;

class OrderDTOExtendedTest extends TestCase
{
    public function testIsProcessedReturnsTrue(): void
    {
        $dto = new OrderDTO([
            'id' => 1,
            'fa_debtor_no' => 10,
        ]);
        $this->assertTrue($dto->isProcessed());
    }

    public function testIsProcessedReturnsFalse(): void
    {
        $dto = new OrderDTO([
            'id' => 1,
        ]);
        $this->assertFalse($dto->isProcessed());
    }

    public function testGetFaDebtorNoReturnsInt(): void
    {
        $dto = new OrderDTO(['fa_debtor_no' => 42]);
        $this->assertSame(42, $dto->getFaDebtorNo());
    }

    public function testGetFaDebtorNoNull(): void
    {
        $dto = new OrderDTO([]);
        $this->assertNull($dto->getFaDebtorNo());
    }

    public function testGetFaBranchRefReturnsString(): void
    {
        $dto = new OrderDTO(['fa_branch_ref' => 'BR-001']);
        $this->assertEquals('BR-001', $dto->getFaBranchRef());
    }

    public function testGetFaBranchRefNull(): void
    {
        $dto = new OrderDTO([]);
        $this->assertNull($dto->getFaBranchRef());
    }

    public function testGetRawDataReturnsInputArray(): void
    {
        $data = ['id' => 5, 'status' => 'completed', 'custom_key' => 'custom_val'];
        $dto = new OrderDTO($data);
        $this->assertEquals($data, $dto->getRawData());
    }

    public function testGetRawDataEmptyArray(): void
    {
        $dto = new OrderDTO([]);
        $this->assertEquals([], $dto->getRawData());
    }

    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', OrderDTO::STATUS_PENDING);
        $this->assertEquals('processing', OrderDTO::STATUS_PROCESSING);
        $this->assertEquals('completed', OrderDTO::STATUS_COMPLETED);
        $this->assertEquals('cancelled', OrderDTO::STATUS_CANCELLED);
        $this->assertEquals('refunded', OrderDTO::STATUS_REFUNDED);
        $this->assertEquals('failed', OrderDTO::STATUS_FAILED);
    }

    public function testEmailFromBillingOnly(): void
    {
        $dto = new OrderDTO([
            'id' => 1,
            'billing' => ['email' => 'billing@example.com'],
        ]);
        $this->assertEquals('billing@example.com', $dto->getCustomerEmail());
    }

    public function testEmailFromRootLevelOnly(): void
    {
        $dto = new OrderDTO([
            'id' => 1,
            'email' => 'root@example.com',
        ]);
        $this->assertEquals('root@example.com', $dto->getCustomerEmail());
    }

    public function testEmailRootLevelOverridesBilling(): void
    {
        $dto = new OrderDTO([
            'id' => 1,
            'email' => 'root@example.com',
            'billing' => ['email' => 'billing@example.com'],
        ]);
        $this->assertEquals('root@example.com', $dto->getCustomerEmail());
    }

    public function testEmailNullWhenMissing(): void
    {
        $dto = new OrderDTO(['id' => 1]);
        $this->assertNull($dto->getCustomerEmail());
    }

    public function testBillingAddressStringWithBothAddressLines(): void
    {
        $dto = new OrderDTO([
            'billing' => [
                'address_1' => '123 Main St',
                'address_2' => 'Suite 4',
            ],
        ]);
        $this->assertEquals('123 Main St Suite 4', $dto->getBillingAddressString());
    }

    public function testBillingAddressStringAddress2Empty(): void
    {
        $dto = new OrderDTO([
            'billing' => [
                'address_1' => '456 Oak Ave',
                'address_2' => '',
            ],
        ]);
        $this->assertEquals('456 Oak Ave', $dto->getBillingAddressString());
    }

    public function testBillingAddressStringNoAddress2(): void
    {
        $dto = new OrderDTO([
            'billing' => [
                'address_1' => '789 Pine Rd',
            ],
        ]);
        $this->assertEquals('789 Pine Rd', $dto->getBillingAddressString());
    }

    public function testBillingAddressStringEmpty(): void
    {
        $dto = new OrderDTO([]);
        $this->assertEquals('', $dto->getBillingAddressString());
    }

    public function testCustomerNameFirstAndLast(): void
    {
        $dto = new OrderDTO([
            'billing' => ['first_name' => 'John', 'last_name' => 'Doe'],
        ]);
        $this->assertEquals('John Doe', $dto->getCustomerName());
    }

    public function testCustomerNameFirstOnly(): void
    {
        $dto = new OrderDTO([
            'billing' => ['first_name' => 'John'],
        ]);
        $this->assertEquals('John', $dto->getCustomerName());
    }

    public function testCustomerNameEmpty(): void
    {
        $dto = new OrderDTO([]);
        $this->assertEquals('', $dto->getCustomerName());
    }

    public function testCustomerCompanyFromBilling(): void
    {
        $dto = new OrderDTO([
            'billing' => ['company' => 'ACME Inc'],
        ]);
        $this->assertEquals('ACME Inc', $dto->getCustomerCompany());
    }

    public function testCustomerCompanyEmpty(): void
    {
        $dto = new OrderDTO([]);
        $this->assertEquals('', $dto->getCustomerCompany());
    }

    public function testIsProcessedWithFaBranchRefButNoDebtor(): void
    {
        $dto = new OrderDTO(['fa_branch_ref' => 'BR-001']);
        $this->assertFalse($dto->isProcessed());
    }

    public function testIsProcessedWithBothFaFields(): void
    {
        $dto = new OrderDTO(['fa_debtor_no' => 10, 'fa_branch_ref' => 'BR-001']);
        $this->assertTrue($dto->isProcessed());
    }

    public function testDefaultStatusIsPending(): void
    {
        $dto = new OrderDTO([]);
        $this->assertEquals('pending', $dto->getStatus());
    }

    public function testDefaultCurrencyIsUSD(): void
    {
        $dto = new OrderDTO([]);
        $this->assertEquals('USD', $dto->getCurrency());
    }

    public function testTotalCastToFloat(): void
    {
        $dto = new OrderDTO(['total' => '123.45']);
        $this->assertEqualsWithDelta(123.45, $dto->getTotal(), 0.001);
    }

    public function testLineItemsEmpty(): void
    {
        $dto = new OrderDTO([]);
        $this->assertEmpty($dto->getLineItems());
    }

    public function testLineItemsProvided(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Item 1', 'quantity' => 2],
            ['id' => 2, 'name' => 'Item 2', 'quantity' => 1],
        ];
        $dto = new OrderDTO(['line_items' => $items]);
        $this->assertCount(2, $dto->getLineItems());
    }

    public function testPaymentFields(): void
    {
        $dto = new OrderDTO([
            'payment_method' => 'stripe',
            'payment_method_title' => 'Credit Card (Stripe)',
            'transaction_id' => 'txn_stripe_123',
            'date_paid' => '2024-01-15 10:30:00',
        ]);
        $this->assertEquals('stripe', $dto->getPaymentMethod());
        $this->assertEquals('Credit Card (Stripe)', $dto->getPaymentMethodTitle());
        $this->assertEquals('txn_stripe_123', $dto->getTransactionId());
        $this->assertEquals('2024-01-15 10:30:00', $dto->getDatePaid());
    }

    public function testPaymentFieldsNullDefaults(): void
    {
        $dto = new OrderDTO([]);
        $this->assertNull($dto->getPaymentMethod());
        $this->assertNull($dto->getPaymentMethodTitle());
        $this->assertNull($dto->getTransactionId());
        $this->assertNull($dto->getDatePaid());
    }

    public function testImmutability(): void
    {
        $dto = new OrderDTO(['id' => 1, 'status' => 'completed']);
        $reflection = new \ReflectionObject($dto);
        $methods = $reflection->getMethods();
        $setterCount = 0;
        foreach ($methods as $method) {
            if (strpos($method->getName(), 'set') === 0) {
                $setterCount++;
            }
        }
        $this->assertEquals(0, $setterCount, 'OrderDTO should not have setter methods');
    }
}
