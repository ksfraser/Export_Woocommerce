<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\Staging\CustomerStaging;
use ksfraser\FrontAccounting\Woocommerce\Staging\IsuStagingGateway;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

class CustomerStagingExtendedTest extends TestCase
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
        $this->staging = new CustomerStaging($this->mockDb, $this->mockLogger, $this->mockGateway);
    }

    public function testStageCustomerReturnsId(): void
    {
        $this->mockGateway->method('stageCustomer')
            ->willReturn(42);

        $result = $this->staging->stageCustomer([
            'id' => 100,
            'customer_id' => 50,
            'billing' => [
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'ACME',
                'address_1' => '123 Main St',
                'city' => 'Test City',
                'state' => 'TS',
                'postcode' => '12345',
                'country' => 'US',
                'phone' => '555-1234',
            ]
        ]);
        $this->assertEquals(42, $result);
    }

    public function testStageCustomerWithMinimalData(): void
    {
        $this->mockGateway->method('stageCustomer')
            ->willReturn(1);

        $result = $this->staging->stageCustomer(['billing' => []]);
        $this->assertEquals(1, $result);
    }

    public function testFindMatchesReturnsEmptyWhenStagedNotFound(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn(null);

        $result = $this->staging->findMatches(999);
        $this->assertEmpty($result);
    }

    public function testFindMatchesReturnsScoredMatches(): void
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
                    'company' => 'ACME',
                    'address_1' => '123 Main St',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => 'test@example.com',
                 'branch_email' => '', 'phone' => '555-1234', 'contact_name' => 'John Doe',
                 'br_name' => 'ACME', 'br_address' => '123 Main St',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertGreaterThan(0, $result[0]['score']);
    }

    public function testCalculateMatchScoreEmailMatch(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'test@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'test@example.com',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => 'test@example.com',
                 'branch_email' => '', 'phone' => '', 'contact_name' => '',
                 'br_name' => '', 'br_address' => '',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(30.0, $result[0]['score']);
    }

    public function testCalculateMatchScorePhoneMatch(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => '',
                'customer_phone' => '555-1234',
                'raw_json' => json_encode(['billing' => [
                    'phone' => '555-1234',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => '',
                 'branch_email' => '', 'phone' => '555-1234', 'contact_name' => '',
                 'br_name' => '', 'br_address' => '',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(25.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreCompanyMatch(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => '',
                'raw_json' => json_encode(['billing' => [
                    'company' => 'ACME Corp',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => '',
                 'branch_email' => '', 'phone' => '', 'contact_name' => '',
                 'br_name' => '', 'br_address' => '',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(20.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreContactNameMatch(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => '',
                'raw_json' => json_encode(['billing' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => '',
                 'branch_email' => '', 'phone' => '', 'contact_name' => 'John Doe',
                 'br_name' => '', 'br_address' => '',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(20.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreAddressMatch(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => '',
                'raw_json' => json_encode(['billing' => [
                    'address_1' => '123 Main St',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => '',
                 'branch_email' => '', 'phone' => '', 'contact_name' => '',
                 'br_name' => '', 'br_address' => '123 Main St, Test City',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(15.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreMultipleMatches(): void
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
                    'company' => 'ACME',
                    'address_1' => '123 Main St',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => 'test@example.com',
                 'branch_email' => '', 'phone' => '555-1234', 'contact_name' => 'John Doe',
                 'br_name' => 'ACME', 'br_address' => '123 Main St',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(100.0, $result[0]['score']);
    }

    public function testFuzzyMatchExactMatch(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => '',
                'raw_json' => json_encode(['billing' => [
                    'company' => 'ACME Corp',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => '',
                 'branch_email' => '', 'phone' => '', 'contact_name' => '',
                 'br_name' => '', 'br_address' => '',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
    }

    public function testFuzzyMatchSubstring(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => '',
                'raw_json' => json_encode(['billing' => [
                    'company' => 'ACME',
                ]]),
            ]);

        $this->mockDb->method('query')
            ->willReturn([
                ['debtor_no' => 1, 'name' => 'ACME Corporation', 'email' => '',
                 'branch_email' => '', 'phone' => '', 'contact_name' => '',
                 'br_name' => '', 'br_address' => '',
                 'branch_ref' => '', 'curr_code' => 'USD']
            ]);

        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
    }

    public function testImportCustomerCreatesNewCustomer(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'test@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'test@example.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'company' => 'ACME',
                    'address_1' => '123 Main St',
                    'city' => 'Test',
                    'state' => 'TS',
                    'postcode' => '12345',
                    'country' => 'US',
                    'phone' => '555-1234',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'imported', $this->callback(function ($fields) {
                return isset($fields['fa_debtor_no']) && isset($fields['fa_branch_ref']);
            }));

        $this->mockDb->method('query')
            ->willReturn([['id' => 100]]);
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->staging->importCustomer(1);
        $this->assertArrayHasKey('debtor_no', $result);
        $this->assertArrayHasKey('branch_ref', $result);
    }

    public function testImportCustomerUsesExistingCustomer(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'test@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'test@example.com',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus');

        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->staging->importCustomer(1, 50, 'BR-50-001');
        $this->assertEquals(50, $result['debtor_no']);
        $this->assertEquals('BR-50-001', $result['branch_ref']);
    }

    public function testImportCustomerReturnsErrorWhenNotFound(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn(null);

        $result = $this->staging->importCustomer(999);
        $this->assertArrayHasKey('error', $result);
    }

    public function testImportCustomerCreatesBranchWhenNotProvided(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'test@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'test@example.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'company' => 'ACME',
                    'address_1' => '123 Main St',
                    'city' => 'Test',
                    'state' => 'TS',
                    'postcode' => '12345',
                    'country' => 'US',
                    'phone' => '555-1234',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus');

        $this->mockDb->method('query')
            ->willReturn([['id' => 100]]);
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->staging->importCustomer(1, 50);
        $this->assertEquals(50, $result['debtor_no']);
        $this->assertStringStartsWith('BR-50-', $result['branch_ref']);
    }

    public function testGetStagedCustomersReturnsAll(): void
    {
        $customers = [
            ['id' => 1, 'email' => 'a@example.com', 'imported' => 0],
            ['id' => 2, 'email' => 'b@example.com', 'imported' => 1],
        ];

        $this->mockGateway->method('getStagedCustomers')
            ->willReturn($customers);

        $result = $this->staging->getStagedCustomers();
        $this->assertCount(2, $result);
    }

    public function testEnsureStagingTableIsNoOp(): void
    {
        $this->staging->ensureStagingTable();
        $this->assertTrue(true);
    }

    public function testNormalizeAddress(): void
    {
        $method = new \ReflectionMethod(CustomerStaging::class, 'normalizeAddress');
        $method->setAccessible(true);

        $result = $method->invoke($this->staging, '123 Main St, Apt 4');
        $this->assertStringContainsString('123', $result);

        $result = $method->invoke($this->staging, '');
        $this->assertEquals('', $result);
    }
}
