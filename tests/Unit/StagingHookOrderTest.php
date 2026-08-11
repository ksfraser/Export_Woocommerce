<?php

namespace {
    if (!function_exists('hook_invoke')) {
        /**
         * Neutral test double for FrontAccounting's hook_invoke().
         *
         * Records the call arguments so tests can verify the $data / $opts
         * argument order, then leaves $data empty so callers observe the
         * same "no hook result" behaviour as when hook_invoke is absent.
         */
        function hook_invoke($ext, $method, &$data, $opts = null)
        {
            $GLOBALS['ksf_test_hook_calls'][] = [$ext, $method, $data, $opts];
            $data = [];
        }
    }
}

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\Staging {

use Ksfraser\frontaccounting\Woocommerce\Staging\OrderStaging;
use Ksfraser\frontaccounting\Woocommerce\Staging\CustomerStaging;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the inter-module staging hook calls pass $data / $opts to
 * hook_invoke() in the correct order (FA signature: $ext, $method, &$data, $opts).
 */
class StagingHookOrderTest extends TestCase
{
    private $db;
    private $logger;

    protected function setUp(): void
    {
        $this->db = $this->createMock(DatabaseInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $GLOBALS['ksf_test_hook_calls'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['ksf_test_hook_calls']);
    }

    public function testOrderStagingPassesDataAndOptsInCorrectOrder(): void
    {
        $staging = new OrderStaging($this->db, $this->logger);
        $result = $this->invokeMethod($staging, 'callStagingHook', ['stageTransaction', ['source' => 'woocommerce']]);

        $calls = $GLOBALS['ksf_test_hook_calls'];
        $this->assertCount(1, $calls);

        list($ext, $method, $data, $opts) = $calls[0];
        $this->assertEquals('ksf_FA_ImportStagingProcessing', $ext);
        $this->assertEquals('respondToCapabilityRequest', $method);
        // $data is the by-ref result bucket; $opts carries the request payload
        $this->assertArrayNotHasKey('request', $data);
        $this->assertEquals('staging:stageTransaction', $opts['request']);
        $this->assertNull($result);
    }

    public function testCustomerStagingPassesDataAndOptsInCorrectOrder(): void
    {
        $staging = new CustomerStaging($this->db, $this->logger);
        $result = $this->invokeMethod($staging, 'callStagingHook', ['stageCustomer', ['source' => 'woocommerce']]);

        $calls = $GLOBALS['ksf_test_hook_calls'];
        $this->assertCount(1, $calls);

        list($ext, $method, $data, $opts) = $calls[0];
        $this->assertEquals('ksf_FA_ImportStagingProcessing', $ext);
        $this->assertEquals('respondToCapabilityRequest', $method);
        $this->assertArrayNotHasKey('request', $data);
        $this->assertEquals('staging:stageCustomer', $opts['request']);
        $this->assertNull($result);
    }

    /**
     * Helper method to invoke private methods
     */
    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
}
