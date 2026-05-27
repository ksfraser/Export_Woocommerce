<?php

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\Staging;
use Ksfraser\frontaccounting\Woocommerce\UI\ImportExportDispatcher;
use Ksfraser\frontaccounting\Woocommerce\OrderExporter;
use Ksfraser\frontaccounting\Woocommerce\CustomerExporter;
use Ksfraser\frontaccounting\Woocommerce\CategoryExporter;
use Ksfraser\frontaccounting\Woocommerce\ProductService;
use Ksfraser\frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\frontaccounting\Woocommerce\Staging\CustomerStaging;
use Ksfraser\frontaccounting\Woocommerce\Dao\SyncDao;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;

use Ksfraser\frontaccounting\Woocommerce\Staging\OrderStaging;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

class OrderStagingTest extends TestCase
{
    private $db;
    private $logger;
    private $orderStaging;

    protected function setUp(): void
    {
        $this->db = $this->createMock(DatabaseInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->db->method('getPrefix')->willReturn('fa_');
        
        $this->orderStaging = new OrderStaging($this->db, $this->logger);
    }

    public function testStatusConstants(): void
    {
        $this->assertEquals('staged', OrderStaging::STATUS_STAGED);
        $this->assertEquals('customer_pending', OrderStaging::STATUS_CUSTOMER_PENDING);
        $this->assertEquals('customer_matched', OrderStaging::STATUS_CUSTOMER_MATCHED);
        $this->assertEquals('imported', OrderStaging::STATUS_IMPORTED);
        $this->assertEquals('error', OrderStaging::STATUS_ERROR);
    }

    public function testStageOrder(): void
    {
        $wooOrder = [
            'id' => 12345,
            'status' => 'pending',
            'billing' => ['email' => 'test@example.com'],
            'total' => '99.99',
            'currency' => 'USD'
        ];
        
        $this->db->expects($this->once())
            ->method('execute')
            ->with($this->stringContains("INSERT INTO fa_woo_order_staging"));
        
        $this->db->expects($this->once())
            ->method('query')
            ->with($this->stringContains("LAST_INSERT_ID"))
            ->willReturn([['id' => 1]]);
        
        $id = $this->orderStaging->stageOrder($wooOrder);
        
        $this->assertEquals(1, $id);
    }

    public function testStageOrderWithCustomerStagingId(): void
    {
        $wooOrder = [
            'id' => 12345,
            'status' => 'processing',
            'billing' => ['email' => 'customer@example.com'],
            'total' => '150.00'
        ];
        
        $this->db->expects($this->once())
            ->method('execute')
            ->with($this->stringContains("12345"));
        
        $this->db->expects($this->once())
            ->method('query')
            ->with($this->stringContains("LAST_INSERT_ID"))
            ->willReturn([['id' => 2]]);
        
        $id = $this->orderStaging->stageOrder($wooOrder, 5);
        
        $this->assertEquals(2, $id);
    }

    public function testLinkCustomer(): void
    {
        $this->db->expects($this->once())
            ->method('escape')
            ->willReturnCallback(function($v) { return $v; });
        
        $this->db->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($sql) {
                return strpos($sql, "fa_debtor_no = 10") !== false
                    && strpos($sql, "BR001") !== false
                    && strpos($sql, "customer_matched") !== false;
            }));
        
        $this->orderStaging->linkCustomer(1, 10, 'BR001');
    }

    public function testGetStagedOrders(): void
    {
        $expected = [
            ['id' => 1, 'woo_order_id' => 123, 'status' => 'staged'],
            ['id' => 2, 'woo_order_id' => 456, 'status' => 'customer_pending']
        ];
        
        $this->db->expects($this->once())
            ->method('query')
            ->with($this->stringContains("SELECT * FROM fa_woo_order_staging"))
            ->willReturn($expected);
        
        $result = $this->orderStaging->getStagedOrders();
        
        $this->assertEquals($expected, $result);
    }

    public function testGetOrdersPendingCustomer(): void
    {
        $this->db->expects($this->once())
            ->method('query')
            ->with($this->callback(function($sql) {
                return strpos($sql, "fa_debtor_no IS NULL") !== false
                    && strpos($sql, "customer_pending") !== false;
            }))
            ->willReturn([]);
        
        $this->orderStaging->getOrdersPendingCustomer();
    }

    public function testGetOrdersReadyForImport(): void
    {
        $expected = [
            ['id' => 1, 'woo_order_id' => 123, 'fa_debtor_no' => 10]
        ];
        
        $this->db->expects($this->once())
            ->method('query')
            ->with($this->callback(function($sql) {
                return strpos($sql, "status = 'customer_matched'") !== false
                    && strpos($sql, "fa_debtor_no IS NOT NULL") !== false;
            }))
            ->willReturn($expected);
        
        $result = $this->orderStaging->getOrdersReadyForImport();
        
        $this->assertEquals($expected, $result);
    }

    public function testMarkImported(): void
    {
        $this->db->expects($this->once())
            ->method('execute')
            ->with($this->callback(function($sql) {
                return strpos($sql, "imported = 1") !== false
                    && strpos($sql, "fa_order_no = 999") !== false
                    && strpos($sql, "status = 'imported'") !== false;
            }));
        
        $this->orderStaging->markImported(1, 999);
    }

    public function testMarkError(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains("error: Failed to process"));
        
        $this->db->expects($this->once())
            ->method('execute')
            ->with($this->stringContains("status = 'error'"));
        
        $this->orderStaging->markError(1, "Failed to process");
    }

    public function testExtractPaymentDetails(): void
    {
        $wooOrder = [
            'payment_method' => 'stripe',
            'payment_method_title' => 'Credit Card (Stripe)',
            'transaction_id' => 'txn_123456',
            'date_paid' => '2024-01-15T10:30:00',
            'total' => '250.00',
            'currency' => 'USD'
        ];
        
        $payment = $this->orderStaging->extractPaymentDetails($wooOrder);
        
        $this->assertEquals('stripe', $payment['method']);
        $this->assertEquals('Credit Card (Stripe)', $payment['method_title']);
        $this->assertEquals('txn_123456', $payment['transaction_id']);
        $this->assertEquals('2024-01-15T10:30:00', $payment['paid']);
        $this->assertEquals('250.00', $payment['amount']);
        $this->assertEquals('USD', $payment['currency']);
    }

    public function testExtractPaymentDetailsMinimal(): void
    {
        $wooOrder = [
            'id' => 123
        ];
        
        $payment = $this->orderStaging->extractPaymentDetails($wooOrder);
        
        $this->assertEquals('', $payment['method']);
        $this->assertEquals('', $payment['method_title']);
        $this->assertEquals('', $payment['transaction_id']);
        $this->assertNull($payment['paid']);
        $this->assertEquals(0, $payment['amount']);
        $this->assertEquals('USD', $payment['currency']);
    }

    public function testStageOrdersBatch(): void
    {
        $orders = [
            ['id' => 1, 'billing' => ['email' => 'a@test.com']],
            ['id' => 2, 'billing' => ['email' => 'b@test.com']]
        ];
        
        $customerStagingIds = ['a@test.com' => 5];
        
        $this->db->method('execute');
        $this->db->method('query')
            ->willReturnOnConsecutiveCalls([['id' => 1]], [['id' => 2]]);
        
        $stagedIds = $this->orderStaging->stageOrders($orders, $customerStagingIds);
        
        $this->assertCount(2, $stagedIds);
        $this->assertEquals([1, 2], $stagedIds);
    }

    public function testProcessPendingOrders(): void
    {
        $stagedOrders = [
            [
                'id' => 1,
                'woo_order_id' => 123,
                'raw_data' => json_encode(['id' => 123]),
                'fa_debtor_no' => 10,
                'fa_branch_ref' => 'BR001'
            ],
            [
                'id' => 2,
                'woo_order_id' => 456,
                'raw_data' => json_encode(['id' => 456]),
                'fa_debtor_no' => 11,
                'fa_branch_ref' => 'BR002'
            ]
        ];
        
        $this->db->method('query')
            ->with($this->stringContains("customer_matched"))
            ->willReturn($stagedOrders);
        
        $callbackCalled = 0;
        $callback = function($wooData, $faDebtorNo, $faBranchRef) use (&$callbackCalled) {
            $callbackCalled++;
            return 1000 + $callbackCalled;
        };
        
        $this->db->method('execute');
        
        $results = $this->orderStaging->processPendingOrders($callback);
        
        $this->assertEquals(2, $results['processed']);
        $this->assertEmpty($results['errors']);
        $this->assertEquals(2, $callbackCalled);
    }

    public function testProcessPendingOrdersWithErrors(): void
    {
        $this->db->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'status') !== false && strpos($sql, 'customer_matched') !== false => [
                    ['id' => 1, 'woo_order_id' => 100, 'raw_data' => json_encode(['id' => 100, 'total' => '50.00']), 'fa_debtor_no' => 10, 'fa_branch_ref' => 'BR001'],
                ],
                default => [],
            });

        $this->db->method('execute')->willReturn(true);

        $callback = function($order) {
            throw new \Exception('Test error');
        };

        $result = $this->orderStaging->processPendingOrders($callback);
        $this->assertEquals(0, $result['processed']);
        $this->assertCount(1, $result['errors']);
    }

    public function testEnsureStagingTable(): void
    {
        $this->db->method('execute')->willReturn(true);
        $this->orderStaging->ensureStagingTable();
        $this->assertTrue(true);
    }
}