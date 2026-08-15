<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;
use ksfraser\FrontAccounting\Woocommerce\UI\ImportExportDispatcher;
use ksfraser\FrontAccounting\Woocommerce\OrderExporter;
use ksfraser\FrontAccounting\Woocommerce\CustomerExporter;
use ksfraser\FrontAccounting\Woocommerce\CategoryExporter;
use ksfraser\FrontAccounting\Woocommerce\ProductService;
use ksfraser\FrontAccounting\Woocommerce\ProductExportService;
use ksfraser\FrontAccounting\Woocommerce\Staging\OrderStaging;
use ksfraser\FrontAccounting\Woocommerce\Staging\CustomerStaging;
use ksfraser\FrontAccounting\Woocommerce\Dao\SyncDao;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use ksfraser\FrontAccounting\Woocommerce\WooRestClientInterface;

use PHPUnit\Framework\TestCase;

class OrderExporterTest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $exporter;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->getMockBuilder('ksfraser\FrontAccounting\Woocommerce\DatabaseInterface')
            ->setMethods(['query', 'execute', 'getPrefix', 'escape'])
            ->getMock();
        $this->mockDb->method('escape')->willReturnCallback(function($val) { return addslashes($val); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        
        $this->exporter = new \ksfraser\FrontAccounting\Woocommerce\OrderExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
    }

    public function testCanGetOrders(): void
    {
        $expectedOrders = [
            ['id' => 1, 'status' => 'pending'],
            ['id' => 2, 'status' => 'completed']
        ];
        
        $this->mockRestClient->method('get')
            ->with('orders', $this->arrayHasKey('per_page'))
            ->willReturn($expectedOrders);
        
        $result = $this->exporter->getOrders();
        
        $this->assertCount(2, $result);
        $this->assertEquals('pending', $result[0]['status']);
    }

    public function testCanGetSingleOrder(): void
    {
        $expectedOrder = ['id' => 123, 'status' => 'processing', 'customer_id' => 456];
        
        $this->mockRestClient->method('get')
            ->with('orders/123')
            ->willReturn($expectedOrder);
        
        $result = $this->exporter->getOrder(123);
        
        $this->assertEquals(123, $result['id']);
        $this->assertEquals('processing', $result['status']);
    }

    public function testCanUpdateOrderStatus(): void
    {
        $updatedOrder = ['id' => 123, 'status' => 'completed'];
        
        $this->mockRestClient->method('put')
            ->with('orders/123', ['status' => 'completed'])
            ->willReturn($updatedOrder);
        
        $result = $this->exporter->updateOrderStatus(123, 'completed');
        
        $this->assertEquals('completed', $result['status']);
    }

    public function testCanImportOrdersToFA(): void
    {
        $orders = [
            ['id' => 1, 'number' => 'WC-001'],
            ['id' => 2, 'number' => 'WC-002']
        ];
        
        $this->mockRestClient->method('get')
            ->willReturn($orders);
        
        $result = $this->exporter->importOrdersToFA();
        
        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(2, $result['total']);
    }

    public function testReturnsEmptyArrayOnGetOrdersError(): void
    {
        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));
        
        $result = $this->exporter->getOrders();
        
        $this->assertEmpty($result);
    }

    public function testCanExtractCustomerDataFromOrder(): void
    {
        $orderWithCustomer = [
            'id' => 123,
            'customer' => [
                'id' => 456,
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe'
            ],
            'billing' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => '',
                'address_1' => '123 Main St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postcode' => '94103',
                'country' => 'US',
                'email' => 'john@example.com',
                'phone' => '555-1234'
            ],
            'shipping' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_1' => '123 Main St'
            ]
        ];
        
        $customerData = $this->exporter->extractCustomerData($orderWithCustomer);
        
        $this->assertEquals(456, $customerData['woo_customer_id']);
        $this->assertEquals('john@example.com', $customerData['email']);
        $this->assertEquals('John', $customerData['first_name']);
    }

    public function testCanImportCustomerFromOrder(): void
    {
        $orderWithCustomer = [
            'id' => 123,
            'customer_id' => 456,
            'billing' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone' => '555-1234',
                'address_1' => '123 Main St',
                'city' => 'San Francisco'
            ]
        ];
        
        $this->mockDb->method('query')
            ->willReturn([]); // No existing customer

        $result = $this->exporter->importCustomerFromOrder($orderWithCustomer);
        
        $this->assertTrue($result['imported']);
        $this->assertEquals(456, $result['woo_customer_id']);
    }

    public function testCanCreateFAOrderFromWooOrder(): void
    {
        $wooOrder = [
            'id' => 123,
            'number' => 'WC-123',
            'status' => 'processing',
            'total' => '99.99',
            'customer_id' => 456,
            'line_items' => [
                ['product_id' => 1, 'quantity' => 2, 'price' => '29.99']
            ],
            'billing' => [
                'email' => 'john@example.com',
                'first_name' => 'John'
            ]
        ];
        
        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [], // No existing order
                [['id' => 999]] // Woo_order_map lookup
            );
        
        $result = $this->exporter->createFAOrderFromWooOrder($wooOrder);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['woo_order_id']);
    }

    public function testCustomerDedupQueriesDebtorsMaster(): void
    {
        // Arrange
        $orderWithCustomer = [
            'id' => 123,
            'customer_id' => 456,
            'billing' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone' => '555-1234'
            ]
        ];
        
        // Existing FA debtor found via debtors_master
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'debtors_master') !== false) {
                    return [['debtor_no' => 77, 'name' => 'John Doe', 'email' => 'john@example.com']];
                }
                return [];
            });
        
        $executed = [];
        $this->mockDb->method('execute')
            ->willReturnCallback(function ($sql) use (&$executed) {
                $executed[] = $sql;
                return true;
            });
        
        // Act
        $result = $this->exporter->importCustomerFromOrder($orderWithCustomer);
        
        // Assert
        $this->assertTrue($result['imported']);
        $this->assertTrue($result['updated']);
        $this->assertEquals(77, $result['fa_customer_id']);
        $this->assertStringContainsString('debtors_master', $executed[0]);
    }

    public function testOrderDedupQueriesWooOrderMapping(): void
    {
        // Arrange
        $orders = [
            ['id' => 1, 'number' => 'WC-001']
        ];
        
        $this->mockRestClient->method('get')
            ->willReturn($orders);
        
        // Existing mapping in woo_order_mapping -> skip import
        $this->mockDb->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'woo_order_mapping') !== false) {
                    return [['id' => 1]];
                }
                return [];
            });
        
        // Act
        $result = $this->exporter->importOrdersToFA();
        
        // Assert
        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['total']);
    }

    public function testImportOrdersFetchesAllPages(): void
    {
        // Arrange
        $pageOne = [];
        for ($i = 1; $i <= 100; $i++) {
            $pageOne[] = ['id' => $i, 'number' => 'WC-' . $i, 'status' => 'pending'];
        }
        
        $requestedPages = [];
        $this->mockRestClient->method('get')
            ->willReturnCallback(function ($endpoint, $params = []) use (&$requestedPages, $pageOne) {
                $requestedPages[] = $params['page'] ?? 1;
                if (($params['page'] ?? 1) === 1) {
                    return $pageOne;
                }
                return []; // Page 2 is empty -> fetch stops
            });
        
        $this->mockDb->method('query')->willReturn([]);
        
        // Act
        $result = $this->exporter->importOrdersToFA();
        
        // Assert
        $this->assertEquals(100, $result['imported']);
        $this->assertEquals(100, $result['total']);
        $this->assertContains(2, $requestedPages, 'Second page should be requested');
    }
}
