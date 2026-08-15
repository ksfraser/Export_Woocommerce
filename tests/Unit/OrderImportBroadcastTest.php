<?php

namespace {
    if (!function_exists('hook_invoke_all')) {
        /**
         * Neutral test double for FrontAccounting's hook_invoke_all().
         *
         * Records the broadcast name and payload so tests can assert the
         * order_imported event fired with the correct data.
         */
        function hook_invoke_all($method, &$data, $opts = null)
        {
            $GLOBALS['ksf_test_broadcasts'][] = [$method, $data, $opts];
        }
    }
}

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit {

use ksfraser\FrontAccounting\Woocommerce\OrderExporter;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the WooCommerce import flow broadcasts order_imported events so
 * other ksf modules (HRM commissions, ProjectManagement revenue) can react.
 *
 * @BABOK Related: FR-WC-003 - Inter-module import event broadcast
 */
class OrderImportBroadcastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['ksf_test_broadcasts'] = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['ksf_test_broadcasts']);
    }

    /**
     * @test
     */
    public function broadcastOrderImportedFiresEventWithDefaults(): void
    {
        $service = $this->newInstanceWithoutConstructor();

        $this->invokeMethod($service, 'broadcastOrderImported', [[]]);

        $broadcasts = $GLOBALS['ksf_test_broadcasts'];
        $this->assertCount(1, $broadcasts);
        $this->assertEquals('order_imported', $broadcasts[0][0]);
        $data = $broadcasts[0][1];
        $this->assertEquals('woocommerce', $data['source']);
        $this->assertArrayHasKey('source_order_id', $data);
        $this->assertArrayHasKey('fa_order_no', $data);
        $this->assertArrayHasKey('fa_trans_type', $data);
        $this->assertArrayHasKey('customer_id', $data);
        $this->assertArrayHasKey('order_total', $data);
        $this->assertArrayHasKey('order_date', $data);
        $this->assertArrayHasKey('currency', $data);
    }

    /**
     * @test
     */
    public function broadcastOrderImportedMergesPayload(): void
    {
        $service = $this->newInstanceWithoutConstructor();

        $this->invokeMethod($service, 'broadcastOrderImported', [[
            'source_order_id' => '42',
            'fa_order_no' => 789,
            'fa_trans_type' => 10,
            'customer_id' => 5,
            'order_total' => 123.45,
            'order_date' => '2026-08-10',
            'currency' => 'GBP',
        ]]);

        $broadcasts = $GLOBALS['ksf_test_broadcasts'];
        $this->assertCount(1, $broadcasts);
        $data = $broadcasts[0][1];
        $this->assertEquals('woocommerce', $data['source']);
        $this->assertEquals('42', $data['source_order_id']);
        $this->assertEquals(789, $data['fa_order_no']);
        $this->assertEquals(10, $data['fa_trans_type']);
        $this->assertEquals(5, $data['customer_id']);
        $this->assertEquals(123.45, $data['order_total']);
        $this->assertEquals('2026-08-10', $data['order_date']);
        $this->assertEquals('GBP', $data['currency']);
    }

    /**
     * @test
     */
    public function extractOrderDateHandlesWooFormat(): void
    {
        $service = $this->newInstanceWithoutConstructor();

        $date = $this->invokeMethod($service, 'extractOrderDate', [[
            'date_created' => '2026-08-10T14:30:00',
        ]]);

        $this->assertEquals('2026-08-10', $date);
    }

    /**
     * @test
     */
    public function extractOrderDateFallsBackToTodayWhenMissing(): void
    {
        $service = $this->newInstanceWithoutConstructor();

        $date = $this->invokeMethod($service, 'extractOrderDate', [[]]);

        $this->assertEquals(date('Y-m-d'), $date);
    }

    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    private function newInstanceWithoutConstructor(): OrderExporter
    {
        $reflection = new \ReflectionClass(OrderExporter::class);
        return $reflection->newInstanceWithoutConstructor();
    }
}
}