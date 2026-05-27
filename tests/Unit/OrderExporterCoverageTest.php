<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\OrderExporter;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class OrderExporterCoverageTest extends TestCase
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
        $this->exporter = new OrderExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
    }

    public function testCreateOrdersOnlyNewOnlyTrue(): void
    {
        $orders = [
            ['orders_id' => 1],
            ['orders_id' => 2],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'WHERE') !== false => $orders,
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 100]);

        $result = $this->exporter->createOrders(true);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testCreateOrdersOnlyNewOnlyFalse(): void
    {
        $orders = [
            ['orders_id' => 1],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'WHERE') !== false => [],
                strpos($sql, 'woo_orders') !== false && strpos($sql, 'WHERE') === false => $orders,
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 100]);

        $result = $this->exporter->createOrders(false);
        $this->assertEquals(1, $result['created']);
    }

    public function testCreateOrdersSomeFail(): void
    {
        $orders = [
            ['orders_id' => 1],
            ['orders_id' => 2],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'WHERE') !== false => $orders,
                default => [],
            });

        $postCallCount = 0;
        $this->mockRestClient->method('post')
            ->willReturnCallback(function() use (&$postCallCount) {
                $postCallCount++;
                if ($postCallCount === 1) {
                    return ['id' => 100];
                }
                return ['error' => 'failed'];
            });

        $result = $this->exporter->createOrders(true);
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testBuildWooOrderDataMinimal(): void
    {
        $faData = [];
        $result = $this->exporter->buildWooOrderData($faData);

        $this->assertEquals('pending', $result['status']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals(0, $result['customer_id']);
        $this->assertEquals('', $result['customer_note']);
    }

    public function testBuildWooOrderDataWithAllFields(): void
    {
        $faData = [
            'status' => 'completed',
            'currency' => 'EUR',
            'customer_id' => 50,
            'customer_note' => 'Please deliver ASAP',
            'billing' => ['first_name' => 'John', 'email' => 'john@example.com'],
            'shipping' => ['first_name' => 'John', 'address_1' => '123 Main St'],
            'line_items' => [['product_id' => 1, 'quantity' => 2]],
            'shipping_lines' => [['method_id' => 'flat_rate']],
            'fee_lines' => [['name' => 'Fee']],
            'coupon_lines' => [['code' => 'SAVE10']],
            'set_paid' => true,
            'transaction_id' => 'TXN-123',
        ];

        $result = $this->exporter->buildWooOrderData($faData);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals(50, $result['customer_id']);
        $this->assertArrayHasKey('billing', $result);
        $this->assertArrayHasKey('shipping', $result);
        $this->assertArrayHasKey('line_items', $result);
        $this->assertArrayHasKey('shipping_lines', $result);
        $this->assertArrayHasKey('fee_lines', $result);
        $this->assertArrayHasKey('coupon_lines', $result);
        $this->assertTrue($result['set_paid']);
        $this->assertEquals('TXN-123', $result['transaction_id']);
    }

    public function testBuildWooOrderDataSetPaidWithoutTransactionId(): void
    {
        $faData = ['set_paid' => true];
        $result = $this->exporter->buildWooOrderData($faData);

        $this->assertTrue($result['set_paid']);
        $this->assertArrayNotHasKey('transaction_id', $result);
    }

    public function testExtractCustomerDataFullBilling(): void
    {
        $order = [
            'customer_id' => 100,
            'billing' => [
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'ACME',
                'address_1' => '123 Main St',
                'address_2' => 'Apt 4',
                'city' => 'Test City',
                'state' => 'TS',
                'postcode' => '12345',
                'country' => 'US',
                'phone' => '555-1234',
            ],
            'shipping' => [
                'address_1' => '456 Other St',
                'city' => 'Other City',
            ],
        ];

        $result = $this->exporter->extractCustomerData($order);

        $this->assertEquals(100, $result['woo_customer_id']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Doe', $result['last_name']);
        $this->assertEquals('ACME', $result['company']);
        $this->assertEquals('123 Main St', $result['address']);
        $this->assertEquals('Apt 4', $result['address_2']);
        $this->assertEquals('Test City', $result['city']);
        $this->assertEquals('TS', $result['state']);
        $this->assertEquals('12345', $result['postcode']);
        $this->assertEquals('US', $result['country']);
        $this->assertEquals('555-1234', $result['phone']);
        $this->assertEquals('456 Other St', $result['shipping_address']);
        $this->assertEquals('Other City', $result['shipping_city']);
    }

    public function testExtractCustomerDataFallbackToCustomer(): void
    {
        $order = [
            'customer' => [
                'id' => 200,
                'email' => 'jane@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
            ],
        ];

        $result = $this->exporter->extractCustomerData($order);

        $this->assertEquals(200, $result['woo_customer_id']);
        $this->assertEquals('jane@example.com', $result['email']);
        $this->assertEquals('Jane', $result['first_name']);
        $this->assertEquals('Smith', $result['last_name']);
    }

    public function testExtractCustomerDataMissingEverything(): void
    {
        $order = [];
        $result = $this->exporter->extractCustomerData($order);

        $this->assertNull($result['woo_customer_id']);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['first_name']);
    }

    public function testExtractCustomerDataMissingEmail(): void
    {
        $order = [
            'billing' => ['first_name' => 'John'],
        ];
        $result = $this->exporter->extractCustomerData($order);

        $this->assertEquals('', $result['email']);
    }

    public function testImportCustomerFromOrderNoEmail(): void
    {
        $order = ['billing' => ['first_name' => 'John']];
        $result = $this->exporter->importCustomerFromOrder($order);

        $this->assertFalse($result['imported']);
        $this->assertEquals('No email address', $result['error']);
    }

    public function testImportCustomerFromOrderExistingCustomer(): void
    {
        $order = ['billing' => ['email' => 'john@example.com']];

        $this->mockDb->method('query')
            ->willReturn([['customer_id' => 50]]);

        $result = $this->exporter->importCustomerFromOrder($order);

        $this->assertTrue($result['imported']);
        $this->assertTrue($result['updated']);
        $this->assertEquals(50, $result['fa_customer_id']);
    }

    public function testImportCustomerFromOrderNewCustomer(): void
    {
        $order = [
            'customer_id' => 100,
            'billing' => [
                'email' => 'new@example.com',
                'first_name' => 'New',
                'last_name' => 'Customer',
                'address_1' => '123 Main St',
                'city' => 'Test City',
                'state' => 'TS',
                'postcode' => '12345',
                'country' => 'US',
                'phone' => '555-1234',
            ],
        ];

        $this->mockDb->method('query')->willReturn([]);

        $result = $this->exporter->importCustomerFromOrder($order);

        $this->assertTrue($result['imported']);
        $this->assertTrue($result['created']);
        $this->assertEquals(0, $result['fa_customer_id']);
        $this->assertEquals(100, $result['woo_customer_id']);
    }

    public function testCreateFAOrderFromWooOrderCompleted(): void
    {
        $wooOrder = [
            'id' => 123,
            'number' => 'WC-123',
            'status' => 'completed',
        ];

        $result = $this->exporter->createFAOrderFromWooOrder($wooOrder);

        $this->assertTrue($result['success']);
        $this->assertEquals('invoice', $result['type']);
        $this->assertEquals(123, $result['woo_order_id']);
    }

    public function testCreateFAOrderFromWooOrderProcessing(): void
    {
        $wooOrder = [
            'id' => 123,
            'number' => 'WC-123',
            'status' => 'processing',
        ];

        $result = $this->exporter->createFAOrderFromWooOrder($wooOrder);

        $this->assertTrue($result['success']);
        $this->assertEquals('sales_order', $result['type']);
    }

    public function testCreateFAOrderFromWooOrderPending(): void
    {
        $wooOrder = [
            'id' => 123,
            'number' => 'WC-123',
            'status' => 'pending',
        ];

        $result = $this->exporter->createFAOrderFromWooOrder($wooOrder);

        $this->assertTrue($result['success']);
        $this->assertEquals('sales_order', $result['type']);
    }

    public function testCreateFAOrderFromWooOrderMissingStatus(): void
    {
        $wooOrder = ['id' => 123, 'number' => 'WC-123'];

        $result = $this->exporter->createFAOrderFromWooOrder($wooOrder);

        $this->assertTrue($result['success']);
        $this->assertEquals('sales_order', $result['type']);
    }

    public function testFindOrCreateFACustomerEmptyEmail(): void
    {
        $customerData = ['email' => ''];
        $result = $this->exporter->extractCustomerData(['billing' => []]);

        $this->assertEquals('', $result['email']);
    }

    public function testFindOrCreateFACustomerExisting(): void
    {
        $this->mockDb->method('query')
            ->willReturn([['customer_id' => 50]]);

        $order = [
            'id' => 123,
            'number' => 'WC-123',
            'billing' => ['email' => 'john@example.com'],
        ];

        $this->mockDb->method('execute')->willReturn(true);

        $customerData = $this->exporter->extractCustomerData($order);

        $existing = $this->mockDb->query("SELECT customer_id FROM 0_customers WHERE email = 'john@example.com'");
        $this->assertEquals(50, $existing[0]['customer_id']);
    }

    public function testCreateOrderReturnsNullOnException(): void
    {
        $this->mockRestClient->method('post')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->createOrder(['status' => 'pending']);
        $this->assertNull($result);
    }

    public function testGetOrdersByStatus(): void
    {
        $orders = [
            ['id' => 1, 'status' => 'completed'],
        ];

        $this->mockRestClient->method('get')->willReturn($orders);

        $result = $this->exporter->getOrdersByStatus('completed');
        $this->assertCount(1, $result);
        $this->assertEquals('completed', $result[0]['status']);
    }

    public function testExtractPaymentDetails(): void
    {
        $order = [
            'payment_method' => 'stripe',
            'payment_method_title' => 'Credit Card',
            'date_paid' => '2024-01-01T00:00:00',
            'transaction_id' => 'TXN-123',
            'total' => '99.99',
            'currency' => 'USD',
        ];

        $result = $this->exporter->extractPaymentDetails($order);

        $this->assertEquals('stripe', $result['method_id']);
        $this->assertEquals('Credit Card', $result['method_title']);
        $this->assertTrue($result['paid']);
        $this->assertEquals('TXN-123', $result['transaction_id']);
        $this->assertEquals('99.99', $result['total']);
    }

    public function testExtractPaymentDetailsMissingFields(): void
    {
        $order = [];
        $result = $this->exporter->extractPaymentDetails($order);

        $this->assertEquals('', $result['method_id']);
        $this->assertFalse($result['paid']);
        $this->assertEquals('0', $result['total']);
    }

    public function testExtractLineItems(): void
    {
        $order = [
            'line_items' => [
                [
                    'id' => 1,
                    'product_id' => 10,
                    'variation_id' => 20,
                    'name' => 'Product 1',
                    'sku' => 'SKU-001',
                    'quantity' => 2,
                    'price' => '29.99',
                    'subtotal' => '59.98',
                    'subtotal_tax' => '5.00',
                    'total' => '59.98',
                    'total_tax' => '5.00',
                    'tax_class' => 'standard',
                    'meta_data' => [['key' => 'color', 'value' => 'Red']],
                ],
            ],
        ];

        $result = $this->exporter->extractLineItems($order);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(10, $result[0]['product_id']);
        $this->assertEquals(2, $result[0]['quantity']);
        $this->assertEquals(29.99, $result[0]['price']);
    }

    public function testExtractLineItemsEmpty(): void
    {
        $order = [];
        $result = $this->exporter->extractLineItems($order);
        $this->assertEmpty($result);
    }

    public function testExtractLineItemsMissingFields(): void
    {
        $order = ['line_items' => [[]]];
        $result = $this->exporter->extractLineItems($order);

        $this->assertCount(1, $result);
        $this->assertEquals(0, $result[0]['id']);
        $this->assertEquals(0, $result[0]['product_id']);
        $this->assertEquals(1, $result[0]['quantity']);
    }

    public function testExtractShippingLines(): void
    {
        $order = [
            'shipping_lines' => [
                ['id' => 1, 'method_id' => 'flat_rate', 'method_title' => 'Flat Rate', 'total' => '10.00', 'total_tax' => '1.00', 'taxes' => []],
            ],
        ];

        $result = $this->exporter->extractShippingLines($order);

        $this->assertCount(1, $result);
        $this->assertEquals('flat_rate', $result[0]['method_id']);
        $this->assertEquals(10.0, $result[0]['total']);
    }

    public function testExtractShippingLinesEmpty(): void
    {
        $order = [];
        $result = $this->exporter->extractShippingLines($order);
        $this->assertEmpty($result);
    }

    public function testExtractTaxLines(): void
    {
        $order = [
            'tax_lines' => [
                ['id' => 1, 'rate_id' => '1', 'code' => 'US-TX', 'title' => 'TX Tax', 'total' => '5.00', 'compound' => false],
            ],
        ];

        $result = $this->exporter->extractTaxLines($order);

        $this->assertCount(1, $result);
        $this->assertEquals('US-TX', $result[0]['code']);
        $this->assertFalse($result[0]['compound']);
    }

    public function testExtractTaxLinesEmpty(): void
    {
        $order = [];
        $result = $this->exporter->extractTaxLines($order);
        $this->assertEmpty($result);
    }

    public function testExtractFeeLines(): void
    {
        $order = [
            'fee_lines' => [
                ['id' => 1, 'name' => 'Processing Fee', 'tax_class' => '', 'tax_status' => 'taxable', 'total' => '5.00', 'total_tax' => '0.50'],
            ],
        ];

        $result = $this->exporter->extractFeeLines($order);

        $this->assertCount(1, $result);
        $this->assertEquals('Processing Fee', $result[0]['name']);
        $this->assertEquals(5.0, $result[0]['total']);
    }

    public function testExtractFeeLinesFallbackToTitle(): void
    {
        $order = [
            'fee_lines' => [
                ['title' => 'Fallback Fee'],
            ],
        ];

        $result = $this->exporter->extractFeeLines($order);

        $this->assertEquals('Fallback Fee', $result[0]['name']);
    }

    public function testExtractFeeLinesEmpty(): void
    {
        $order = [];
        $result = $this->exporter->extractFeeLines($order);
        $this->assertEmpty($result);
    }

    public function testExtractCouponLines(): void
    {
        $order = [
            'coupon_lines' => [
                ['id' => 1, 'code' => 'SAVE10', 'discount' => '10.00', 'discount_tax' => '1.00'],
            ],
        ];

        $result = $this->exporter->extractCouponLines($order);

        $this->assertCount(1, $result);
        $this->assertEquals('SAVE10', $result[0]['code']);
        $this->assertEquals(10.0, $result[0]['discount']);
    }

    public function testExtractCouponLinesEmpty(): void
    {
        $order = [];
        $result = $this->exporter->extractCouponLines($order);
        $this->assertEmpty($result);
    }

    public function testExtractRefunds(): void
    {
        $order = [
            'refunds' => [
                ['id' => 1, 'reason' => 'Customer request', 'total' => '-50.00', 'refunded_by' => 1, 'refunded_payment' => true],
            ],
        ];

        $result = $this->exporter->extractRefunds($order);

        $this->assertCount(1, $result);
        $this->assertEquals('Customer request', $result[0]['reason']);
        $this->assertTrue($result[0]['refunded_payment']);
    }

    public function testExtractRefundsEmpty(): void
    {
        $order = [];
        $result = $this->exporter->extractRefunds($order);
        $this->assertEmpty($result);
    }

    public function testGetOrderTotals(): void
    {
        $order = [
            'subtotal' => '100.00',
            'total_discount' => '10.00',
            'discount_total' => '10.00',
            'discount_tax' => '1.00',
            'shipping_total' => '5.00',
            'shipping_tax' => '0.50',
            'cart_tax' => '8.00',
            'total_tax' => '9.50',
            'total' => '104.50',
            'total_line_items_quantity' => 3,
        ];

        $result = $this->exporter->getOrderTotals($order);

        $this->assertEquals(100.0, $result['subtotal']);
        $this->assertEquals(10.0, $result['total_discount']);
        $this->assertEquals(104.5, $result['total']);
        $this->assertEquals(3, $result['total_line_items_quantity']);
    }

    public function testGetOrderTotalsMissingFields(): void
    {
        $order = [];
        $result = $this->exporter->getOrderTotals($order);

        $this->assertEquals(0.0, $result['subtotal']);
        $this->assertEquals(0.0, $result['total']);
    }

    public function testUpdateOrderWooIdException(): void
    {
        $this->mockDb->method('query')
            ->willThrowException(new \Exception('DB Error'));

        $result = $this->exporter->updateOrderWooId(1, 100);
        $this->assertFalse($result);
    }

    public function testMapOrderStatusAllMappings(): void
    {
        $this->assertEquals('pending', $this->exporter->mapOrderStatus('pending'));
        $this->assertEquals('in_progress', $this->exporter->mapOrderStatus('processing'));
        $this->assertEquals('on_hold', $this->exporter->mapOrderStatus('on-hold'));
        $this->assertEquals('completed', $this->exporter->mapOrderStatus('completed'));
        $this->assertEquals('cancelled', $this->exporter->mapOrderStatus('cancelled'));
        $this->assertEquals('refunded', $this->exporter->mapOrderStatus('refunded'));
        $this->assertEquals('failed', $this->exporter->mapOrderStatus('failed'));
    }

    public function testMapOrderStatusUnknown(): void
    {
        $this->assertEquals('pending', $this->exporter->mapOrderStatus('unknown'));
    }

    public function testGetOrderException(): void
    {
        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->getOrder(123);
        $this->assertNull($result);
    }

    public function testUpdateOrderStatusException(): void
    {
        $this->mockRestClient->method('put')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->updateOrderStatus(123, 'completed');
        $this->assertNull($result);
    }
}
