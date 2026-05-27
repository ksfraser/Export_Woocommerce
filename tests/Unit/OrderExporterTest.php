<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;
use Ksfraser\frontaccounting\Woocommerce\UI\ImportExportDispatcher;
use Ksfraser\frontaccounting\Woocommerce\OrderExporter;
use Ksfraser\frontaccounting\Woocommerce\CustomerExporter;
use Ksfraser\frontaccounting\Woocommerce\CategoryExporter;
use Ksfraser\frontaccounting\Woocommerce\ProductService;
use Ksfraser\frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\frontaccounting\Woocommerce\Staging\OrderStaging;
use Ksfraser\frontaccounting\Woocommerce\Staging\CustomerStaging;
use Ksfraser\frontaccounting\Woocommerce\Dao\SyncDao;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;

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
        $this->mockDb = $this->getMockBuilder('Ksfraser\Frontaccounting\Woocommerce\DatabaseInterface')
            ->setMethods(['query', 'execute', 'getPrefix', 'escape'])
            ->getMock();
        $this->mockDb->method('escape')->willReturnCallback(function($val) { return addslashes($val); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        
        $this->exporter = new \Ksfraser\Frontaccounting\Woocommerce\OrderExporter(
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
}
