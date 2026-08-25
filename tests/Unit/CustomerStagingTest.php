<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\Staging\CustomerStaging;
use ksfraser\FrontAccounting\Woocommerce\Staging\IsuStagingGateway;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

class CustomerStagingTest extends TestCase
{
    private $mockDb;
    private $mockLogger;
    private $mockGateway;
    private $staging;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockGateway = $this->createMock(IsuStagingGateway::class);

        $this->staging = new CustomerStaging(
            $this->mockDb,
            $this->mockLogger,
            $this->mockGateway
        );
    }

    public function testStageCustomer(): void
    {
        $this->mockGateway->expects($this->once())
            ->method('stageCustomer')
            ->with($this->callback(function ($data) {
                return $data['source_customer_id'] === '999'
                    && $data['email'] === 'test@example.com'
                    && $data['name'] === 'ACME Inc';
            }))
            ->willReturn(123);

        $wooData = [
            'id' => 999,
            'billing' => [
                'email' => 'test@example.com',
                'phone' => '555-1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'ACME Inc',
                'address_1' => '123 Main St',
                'city' => 'Springfield',
                'state' => 'IL',
                'postcode' => '62701',
                'country' => 'US'
            ]
        ];

        $result = $this->staging->stageCustomer($wooData);
        $this->assertEquals(123, $result);
    }

    public function testStageCustomerReturnsZeroOnFailure(): void
    {
        $this->mockGateway->expects($this->once())
            ->method('stageCustomer')
            ->willReturn(0);

        $result = $this->staging->stageCustomer(['billing' => []]);
        $this->assertEquals(0, $result);
    }

    public function testFindMatchesByEmail(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'test@example.com',
                'customer_phone' => '555-1234',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'test@example.com',
                    'phone' => '555-1234',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'company' => '',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 100, 'email' => 'test@example.com', 'name' => 'John Doe',
                 'branch_ref' => '', 'br_name' => '', 'contact_name' => '',
                 'phone' => '', 'branch_email' => '', 'br_address' => '']
            ]);

        $matches = $this->staging->findMatches(1);

        $this->assertCount(1, $matches);
        $this->assertEquals(100, $matches[0]['debtor_no']);
        $this->assertGreaterThan(25, $matches[0]['score']);
    }

    public function testFindMatchesByPhone(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => '',
                'customer_phone' => '555-1234',
                'raw_json' => json_encode(['billing' => [
                    'email' => '',
                    'phone' => '555-1234',
                    'first_name' => '',
                    'last_name' => '',
                    'company' => '',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 100, 'name' => 'Test Co', 'email' => '',
                 'phone' => '555-1234', 'branch_email' => '', 'contact_name' => '',
                 'br_name' => '', 'br_address' => '',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $matches = $this->staging->findMatches(1);

        $this->assertCount(1, $matches);
        $this->assertGreaterThan(20, $matches[0]['score']);
    }

    public function testNoMatches(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'new@example.com',
                'customer_phone' => '999-9999',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'new@example.com',
                    'phone' => '999-9999',
                ]]),
            ]);

        $this->mockDb->method('query')->willReturn([]);

        $matches = $this->staging->findMatches(1);

        $this->assertCount(0, $matches);
    }

    public function testImportNewCustomer(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'new@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'new@example.com',
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'imported', $this->callback(function ($fields) {
                return isset($fields['fa_debtor_no']) && isset($fields['fa_branch_ref']);
            }));

        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [['id' => 456]] // LAST_INSERT_ID for new customer
            );
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->staging->importCustomer(1, null);

        $this->assertArrayHasKey('debtor_no', $result);
        $this->assertArrayHasKey('branch_ref', $result);
    }

    public function testImportExistingCustomer(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'existing@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'existing@example.com',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus');

        $this->mockDb->method('execute')->willReturn(true);
        $this->mockDb->method('query')
            ->willReturn([['id' => 789]]);

        $result = $this->staging->importCustomer(1, 100, null);

        $this->assertEquals(100, $result['debtor_no']);
    }

    public function testGetStagedCustomers(): void
    {
        $expected = [
            ['id' => 1, 'email' => 'a@example.com'],
            ['id' => 2, 'email' => 'b@example.com'],
        ];

        $this->mockGateway->expects($this->once())
            ->method('getStagedCustomers')
            ->willReturn($expected);

        $result = $this->staging->getStagedCustomers();

        $this->assertCount(2, $result);
    }

    public function testEnsureStagingTableIsNoOp(): void
    {
        $this->staging->ensureStagingTable();
        $this->assertTrue(true);
    }
}
