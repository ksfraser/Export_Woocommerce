<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\Staging\CustomerStaging;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

class CustomerStagingExtendedTest extends TestCase
{
    private $mockDb;
    private $mockLogger;
    private $staging;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->staging = new CustomerStaging($this->mockDb, $this->mockLogger);
    }

    public function testStageCustomerReturnsId(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $this->mockDb->method('query')
            ->willReturn([['id' => 42]]);
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
        $this->mockDb->method('execute')->willReturn(true);
        $this->mockDb->method('query')->willReturn([['id' => 1]]);
        $result = $this->staging->stageCustomer(['billing' => []]);
        $this->assertEquals(1, $result);
    }

    public function testFindMatchesReturnsEmptyWhenStagedNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->staging->findMatches(999);
        $this->assertEmpty($result);
    }

    public function testFindMatchesReturnsScoredMatches(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => 'test@example.com', 'phone' => '555-1234', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'address1' => '123 Main St']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => 'test@example.com', 'branch_email' => '', 'phone' => '555-1234', 'contact_name' => 'John Doe', 'br_name' => 'ACME', 'br_address' => '123 Main St'],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertGreaterThan(0, $result[0]['score']);
    }

    public function testCalculateMatchScoreEmailMatch(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => 'test@example.com', 'phone' => '', 'first_name' => '', 'last_name' => '', 'company' => '', 'address1' => '']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => 'test@example.com', 'branch_email' => '', 'phone' => '', 'contact_name' => '', 'br_name' => '', 'br_address' => ''],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(30.0, $result[0]['score']);
    }

    public function testCalculateMatchScorePhoneMatch(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => '', 'phone' => '555-1234', 'first_name' => '', 'last_name' => '', 'company' => '', 'address1' => '']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => '', 'branch_email' => '', 'phone' => '555-1234', 'contact_name' => '', 'br_name' => '', 'br_address' => ''],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(25.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreCompanyMatch(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => '', 'phone' => '', 'first_name' => '', 'last_name' => '', 'company' => 'ACME Corp', 'address1' => '']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => '', 'branch_email' => '', 'phone' => '', 'contact_name' => '', 'br_name' => '', 'br_address' => ''],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(20.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreContactNameMatch(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => '', 'phone' => '', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => '', 'address1' => '']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => '', 'branch_email' => '', 'phone' => '', 'contact_name' => 'John Doe', 'br_name' => '', 'br_address' => ''],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(20.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreAddressMatch(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => '', 'phone' => '', 'first_name' => '', 'last_name' => '', 'company' => '', 'address1' => '123 Main St']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'Test Corp', 'email' => '', 'branch_email' => '', 'phone' => '', 'contact_name' => '', 'br_name' => '', 'br_address' => '123 Main St, Test City'],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(15.0, $result[0]['score']);
    }

    public function testCalculateMatchScoreMultipleMatches(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => 'test@example.com', 'phone' => '555-1234', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'address1' => '123 Main St']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => 'test@example.com', 'branch_email' => '', 'phone' => '555-1234', 'contact_name' => 'John Doe', 'br_name' => 'ACME', 'br_address' => '123 Main St'],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
        $this->assertEquals(100.0, $result[0]['score']);
    }

    public function testFuzzyMatchExactMatch(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => '', 'phone' => '', 'first_name' => '', 'last_name' => '', 'company' => 'ACME Corp', 'address1' => '']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'ACME Corp', 'email' => '', 'branch_email' => '', 'phone' => '', 'contact_name' => '', 'br_name' => '', 'br_address' => ''],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
    }

    public function testFuzzyMatchSubstring(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return [['id' => 1, 'email' => '', 'phone' => '', 'first_name' => '', 'last_name' => '', 'company' => 'ACME', 'address1' => '']];
                }
                return [
                    ['debtor_no' => 1, 'name' => 'ACME Corporation', 'email' => '', 'branch_email' => '', 'phone' => '', 'contact_name' => '', 'br_name' => '', 'br_address' => ''],
                ];
            });
        $result = $this->staging->findMatches(1);
        $this->assertNotEmpty($result);
    }

    public function testImportCustomerCreatesNewCustomer(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false && strpos($sql, 'SELECT *') !== false) {
                    return [['id' => 1, 'email' => 'test@example.com', 'raw_data' => json_encode(['billing' => ['email' => 'test@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'address_1' => '123 Main St', 'city' => 'Test', 'state' => 'TS', 'postcode' => '12345', 'country' => 'US', 'phone' => '555-1234']])]];
                }
                if (strpos($sql, 'LAST_INSERT_ID') !== false) {
                    return [['id' => 100]];
                }
                return [];
            });
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->staging->importCustomer(1);
        $this->assertArrayHasKey('debtor_no', $result);
        $this->assertArrayHasKey('branch_ref', $result);
    }

    public function testImportCustomerUsesExistingCustomer(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false && strpos($sql, 'SELECT *') !== false) {
                    return [['id' => 1, 'email' => 'test@example.com', 'raw_data' => json_encode(['billing' => ['email' => 'test@example.com']])]];
                }
                return [];
            });
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->staging->importCustomer(1, 50, 'BR-50-001');
        $this->assertEquals(50, $result['debtor_no']);
        $this->assertEquals('BR-50-001', $result['branch_ref']);
    }

    public function testImportCustomerReturnsErrorWhenNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->staging->importCustomer(999);
        $this->assertArrayHasKey('error', $result);
    }

    public function testImportCustomerCreatesBranchWhenNotProvided(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false && strpos($sql, 'SELECT *') !== false) {
                    return [['id' => 1, 'email' => 'test@example.com', 'raw_data' => json_encode(['billing' => ['email' => 'test@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'address_1' => '123 Main St', 'city' => 'Test', 'state' => 'TS', 'postcode' => '12345', 'country' => 'US', 'phone' => '555-1234']])]];
                }
                if (strpos($sql, 'LAST_INSERT_ID') !== false) {
                    return [['id' => 100]];
                }
                return [];
            });
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
        $this->mockDb->method('query')->willReturn($customers);
        $result = $this->staging->getStagedCustomers();
        $this->assertCount(2, $result);
    }

    public function testEnsureStagingTableCreatesTable(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
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
