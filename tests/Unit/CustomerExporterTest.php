<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\CustomerExporter;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use ksfraser\FrontAccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class CustomerExporterTest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $exporter;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->exporter = new CustomerExporter($this->mockRestClient, $this->mockLogger, $this->mockDb);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CustomerExporter::class, $this->exporter);
    }

    public function testExportCustomerReturnsResult(): void
    {
        $this->mockRestClient->method('post')->willReturn(['id' => 123]);
        $result = $this->exporter->exportCustomer(['email' => 'test@example.com']);
        $this->assertEquals(123, $result['id']);
    }

    public function testExportCustomerHandlesError(): void
    {
        $this->mockRestClient->method('post')->willThrowException(new \Exception('API Error'));
        $result = $this->exporter->exportCustomer(['email' => 'test@example.com']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testGetCustomerReturnsCustomer(): void
    {
        $this->mockRestClient->method('get')->willReturn(['id' => 123, 'email' => 'test@example.com']);
        $result = $this->exporter->getCustomer(123);
        $this->assertEquals(123, $result['id']);
    }

    public function testGetCustomerReturnsNullOnError(): void
    {
        $this->mockRestClient->method('get')->willThrowException(new \Exception('Not found'));
        $result = $this->exporter->getCustomer(999);
        $this->assertNull($result);
    }

    public function testFindCustomerByEmailReturnsCustomer(): void
    {
        $this->mockRestClient->method('get')->willReturn(['id' => 123, 'email' => 'test@example.com']);
        $result = $this->exporter->findCustomerByEmail('test@example.com');
        $this->assertEquals(123, $result['id']);
    }

    public function testFindCustomerByEmailReturnsNullOnError(): void
    {
        $this->mockRestClient->method('get')->willThrowException(new \Exception('Not found'));
        $result = $this->exporter->findCustomerByEmail('nonexistent@example.com');
        $this->assertNull($result);
    }

    public function testUpdateCustomerReturnsResult(): void
    {
        $this->mockRestClient->method('put')->willReturn(['id' => 123, 'email' => 'updated@example.com']);
        $result = $this->exporter->updateCustomer(123, ['email' => 'updated@example.com']);
        $this->assertEquals('updated@example.com', $result['email']);
    }

    public function testUpdateCustomerReturnsNullOnError(): void
    {
        $this->mockRestClient->method('put')->willThrowException(new \Exception('Update failed'));
        $result = $this->exporter->updateCustomer(123, ['email' => 'updated@example.com']);
        $this->assertNull($result);
    }

    public function testListCustomersReturnsCustomers(): void
    {
        $customers = [
            ['id' => 1, 'email' => 'a@example.com'],
            ['id' => 2, 'email' => 'b@example.com'],
        ];
        $this->mockRestClient->method('get')->willReturn($customers);
        $result = $this->exporter->listCustomers();
        $this->assertCount(2, $result);
    }

    public function testListCustomersReturnsEmptyOnError(): void
    {
        $this->mockRestClient->method('get')->willThrowException(new \Exception('API Error'));
        $result = $this->exporter->listCustomers();
        $this->assertEmpty($result);
    }

    public function testListCustomersWithFilters(): void
    {
        $this->mockRestClient->method('get')->willReturn([['id' => 1]]);
        $result = $this->exporter->listCustomers(['role' => 'customer']);
        $this->assertCount(1, $result);
    }

    public function testExportAllCustomersCreatesNewWhenNoMapping(): void
    {
        $debtorRows = [
            [
                'debtor_no' => 1, 'name' => 'John Doe', 'email' => 'a@example.com',
                'curr_code' => 'USD', 'tax_group_id' => 1,
                'branch_ref' => 'BR-1', 'br_name' => 'Acme Inc', 'contact_name' => '',
                'phone' => '555-0100', 'branch_email' => '', 'br_address' => '123 Main St',
            ],
        ];
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->mockDb->method('query')->willReturnCallback(function ($sql) use ($debtorRows) {
            if (strpos($sql, 'woo_customer_map') !== false) {
                return [];
            }
            return $debtorRows;
        });
        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn(['id' => 456]);
        $result = $this->exporter->exportAllCustomers();
        $this->assertEquals(1, $result['exported']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(1, $result['total']);
    }

    public function testExportAllCustomersUpdatesWhenMapped(): void
    {
        $debtorRows = [
            [
                'debtor_no' => 1, 'name' => 'John Doe', 'email' => 'a@example.com',
                'curr_code' => 'USD', 'tax_group_id' => 1,
                'branch_ref' => 'BR-1', 'br_name' => 'Acme Inc', 'contact_name' => '',
                'phone' => '555-0100', 'branch_email' => '', 'br_address' => '123 Main St',
            ],
        ];
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->mockDb->method('query')->willReturnCallback(function ($sql) use ($debtorRows) {
            if (strpos($sql, 'woo_customer_map') !== false) {
                return [['woo_customer_id' => 456]];
            }
            return $debtorRows;
        });
        $this->mockRestClient->method('put')->willReturn(['id' => 456]);
        $result = $this->exporter->exportAllCustomers();
        $this->assertEquals(0, $result['exported']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(1, $result['total']);
    }

    public function testBuildCustomerDataWithContactName(): void
    {
        $faData = [
            'debtor_no' => 1,
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'phone' => '555-1234',
            'br_address' => '123 Main St\nTest City, TS 12345\nUS',
            'contact_name' => 'Johnny Do',
            'br_name' => 'Acme Corp',
            'branch_email' => 'branch@example.com',
        ];
        $result = $this->exporter->buildCustomerData($faData);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('Johnny', $result['first_name']);
        $this->assertEquals('Do', $result['last_name']);
        $this->assertEquals('Acme Corp', $result['company']);
        $this->assertEquals('Acme Corp', $result['billing']['company']);
        $this->assertEquals('555-1234', $result['billing']['phone']);
        $this->assertEquals('555-1234', $result['billing']['phone']);
        $this->assertEquals('123 Main St\nTest City, TS 12345\nUS', $result['billing']['address_1']);
    }

    public function testBuildCustomerDataWithoutContactName(): void
    {
        $faData = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '555-5678',
            'br_address' => '456 Oak Ave',
            'contact_name' => '',
            'br_name' => '',
        ];
        $result = $this->exporter->buildCustomerData($faData);
        $this->assertEquals('jane@example.com', $result['email']);
        $this->assertEquals('Jane', $result['first_name']);
        $this->assertEquals('Smith', $result['last_name']);
        $this->assertEquals('', $result['company']);
    }

    public function testBuildCustomerDataFallsBackToNameEmail(): void
    {
        $faData = [
            'name' => 'Solo Name',
            'email' => 'solo@example.com',
        ];
        $result = $this->exporter->buildCustomerData($faData);
        $this->assertEquals('solo@example.com', $result['email']);
        $this->assertEquals('Solo', $result['first_name']);
        $this->assertEquals('Name', $result['last_name']);
    }

    public function testBuildCustomerDataWithEmptyFields(): void
    {
        $result = $this->exporter->buildCustomerData([]);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['first_name']);
        $this->assertEquals('', $result['last_name']);
    }
}
