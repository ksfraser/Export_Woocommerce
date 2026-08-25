<?php

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\Staging;

use ksfraser\FrontAccounting\Woocommerce\Staging\OrderStaging;
use ksfraser\FrontAccounting\Woocommerce\Staging\IsuStagingGateway;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

class OrderStagingTest extends TestCase
{
    private $db;
    private $logger;
    private $gateway;
    private $orderStaging;

    protected function setUp(): void
    {
        $this->db = $this->createMock(DatabaseInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->gateway = $this->createMock(IsuStagingGateway::class);

        $this->orderStaging = new OrderStaging($this->db, $this->logger, $this->gateway);
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

        $this->gateway->expects($this->once())
            ->method('stageOrder')
            ->with(
                $this->callback(function ($orderData) {
                    return $orderData['source_order_id'] === '12345'
                        && $orderData['total_amount'] == 99.99;
                }),
                $this->isType('array')
            )
            ->willReturn(1);

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

        $this->gateway->expects($this->once())
            ->method('stageOrder')
            ->willReturn(2);

        $id = $this->orderStaging->stageOrder($wooOrder, 5);

        $this->assertEquals(2, $id);
    }

    public function testStageOrderReturnsZeroOnFailure(): void
    {
        $wooOrder = [
            'id' => 12345,
            'status' => 'pending',
            'billing' => ['email' => 'test@example.com'],
            'total' => '99.99',
            'currency' => 'USD'
        ];

        $this->gateway->expects($this->once())
            ->method('stageOrder')
            ->willReturn(0);

        $id = $this->orderStaging->stageOrder($wooOrder);

        $this->assertEquals(0, $id);
    }

    public function testLinkCustomer(): void
    {
        $this->gateway->expects($this->once())
            ->method('updateStatus')
            ->with(
                1,
                OrderStaging::STATUS_CUSTOMER_MATCHED,
                $this->callback(function ($fields) {
                    return $fields['fa_debtor_no'] === '10'
                        && $fields['fa_branch_ref'] === 'BR001';
                })
            );

        $this->orderStaging->linkCustomer(1, 10, 'BR001');
    }

    public function testGetStagedOrders(): void
    {
        $expected = [
            ['id' => 1, 'source_order_id' => '123', 'status' => 'staged'],
            ['id' => 2, 'source_order_id' => '456', 'status' => 'customer_pending']
        ];

        $this->gateway->expects($this->once())
            ->method('getStagedOrders')
            ->willReturn($expected);

        $result = $this->orderStaging->getStagedOrders();

        $this->assertEquals($expected, $result);
    }

    public function testGetOrdersPendingCustomer(): void
    {
        $this->gateway->method('getByStatus')
            ->willReturnCallback(function ($status) {
                if ($status === 'staged') {
                    return [['id' => 1, 'status' => 'staged']];
                }
                if ($status === 'customer_pending') {
                    return [['id' => 2, 'status' => 'customer_pending']];
                }
                return [];
            });

        $result = $this->orderStaging->getOrdersPendingCustomer();

        $this->assertCount(2, $result);
    }

    public function testGetOrdersReadyForImport(): void
    {
        $expected = [
            ['id' => 1, 'source_order_id' => '123', 'fa_debtor_no' => '10']
        ];

        $this->gateway->expects($this->once())
            ->method('getByStatus')
            ->with(OrderStaging::STATUS_CUSTOMER_MATCHED)
            ->willReturn($expected);

        $result = $this->orderStaging->getOrdersReadyForImport();

        $this->assertEquals($expected, $result);
    }

    public function testMarkImported(): void
    {
        $this->gateway->expects($this->once())
            ->method('updateStatus')
            ->with(
                1,
                OrderStaging::STATUS_IMPORTED,
                $this->callback(function ($fields) {
                    return $fields['fa_invoice_no'] === '999';
                })
            );

        $this->orderStaging->markImported(1, 999);
    }

    public function testMarkError(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains("error: Failed to process"));

        $this->gateway->expects($this->once())
            ->method('updateStatus')
            ->with(
                1,
                OrderStaging::STATUS_ERROR,
                $this->callback(function ($fields) {
                    return $fields['error_log'] === 'Failed to process';
                })
            );

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

        $this->gateway->method('stageOrder')
            ->willReturnOnConsecutiveCalls(1, 2);

        $this->gateway->expects($this->once())
            ->method('updateStatus')
            ->with(
                2,
                OrderStaging::STATUS_CUSTOMER_PENDING,
                $this->anything()
            );

        $stagedIds = $this->orderStaging->stageOrders($orders, $customerStagingIds);

        $this->assertCount(2, $stagedIds);
        $this->assertEquals([1, 2], $stagedIds);
    }

    public function testProcessPendingOrders(): void
    {
        $stagedOrders = [
            [
                'id' => 1,
                'raw_data' => json_encode(['id' => 123]),
                'fa_debtor_no' => '10',
                'fa_branch_ref' => 'BR001'
            ],
            [
                'id' => 2,
                'raw_data' => json_encode(['id' => 456]),
                'fa_debtor_no' => '11',
                'fa_branch_ref' => 'BR002'
            ]
        ];

        $this->gateway->method('getByStatus')
            ->with(OrderStaging::STATUS_CUSTOMER_MATCHED)
            ->willReturn($stagedOrders);

        $this->gateway->method('updateStatus');

        $callbackCalled = 0;
        $callback = function ($wooData, $faDebtorNo, $faBranchRef) use (&$callbackCalled) {
            $callbackCalled++;
            return 1000 + $callbackCalled;
        };

        $results = $this->orderStaging->processPendingOrders($callback);

        $this->assertEquals(2, $results['processed']);
        $this->assertEmpty($results['errors']);
        $this->assertEquals(2, $callbackCalled);
    }

    public function testProcessPendingOrdersWithErrors(): void
    {
        $stagedOrders = [
            [
                'id' => 1,
                'raw_data' => json_encode(['id' => 100, 'total' => '50.00']),
                'fa_debtor_no' => '10',
                'fa_branch_ref' => 'BR001',
            ],
        ];

        $this->gateway->method('getByStatus')
            ->with(OrderStaging::STATUS_CUSTOMER_MATCHED)
            ->willReturn($stagedOrders);

        $this->gateway->method('updateStatus');

        $callback = function ($order) {
            throw new \Exception('Test error');
        };

        $result = $this->orderStaging->processPendingOrders($callback);
        $this->assertEquals(0, $result['processed']);
        $this->assertCount(1, $result['errors']);
    }

    public function testEnsureStagingTableIsNoOp(): void
    {
        $this->orderStaging->ensureStagingTable();
        $this->assertTrue(true);
    }
}
