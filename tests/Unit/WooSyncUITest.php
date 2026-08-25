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
use ksfraser\FrontAccounting\Woocommerce\Staging\IsuStagingGateway;
use ksfraser\FrontAccounting\Woocommerce\Dao\SyncDao;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use ksfraser\FrontAccounting\Woocommerce\WooRestClientInterface;

use PHPUnit\Framework\TestCase;

class WooSyncUITest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $mockGateway;
    private $dispatcher;
    private $customerStaging;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockGateway = $this->createMock(IsuStagingGateway::class);

        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');

        $this->customerStaging = new \ksfraser\FrontAccounting\Woocommerce\Staging\CustomerStaging(
            $this->mockDb,
            $this->mockLogger,
            $this->mockGateway
        );

        $productExporter = new \ksfraser\FrontAccounting\Woocommerce\ProductExportService(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
        $orderExporter = new \ksfraser\FrontAccounting\Woocommerce\OrderExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
        $customerExporter = new \ksfraser\FrontAccounting\Woocommerce\CustomerExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
        $categoryExporter = new \ksfraser\FrontAccounting\Woocommerce\CategoryExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );

        $this->dispatcher = new \ksfraser\FrontAccounting\Woocommerce\UI\ImportExportDispatcher(
            $productExporter,
            $orderExporter,
            $customerExporter,
            $categoryExporter,
            $this->customerStaging
        );
    }

    public function testDispatchExportProducts(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn([]);

        $result = $this->dispatcher->dispatch('export_products');

        $this->assertIsArray($result);
    }

    public function testDispatchExportCategories(): void
    {
        $this->mockRestClient->method('post')->willReturn([]);

        $result = $this->dispatcher->dispatch('export_categories');

        $this->assertIsArray($result);
    }

    public function testDispatchImportOrders(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);

        $result = $this->dispatcher->dispatch('import_orders', ['limit' => 5]);

        $this->assertIsArray($result);
    }

    public function testDispatchImportCustomers(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);

        $result = $this->dispatcher->dispatch('import_customers', ['limit' => 5]);

        $this->assertIsArray($result);
    }

    public function testDispatchSyncAll(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);
        $this->mockRestClient->method('post')->willReturn([]);

        $result = $this->dispatcher->dispatch('sync_all');

        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('orders', $result);
        $this->assertArrayHasKey('customers', $result);
    }

    public function testDispatchUnknownAction(): void
    {
        $result = $this->dispatcher->dispatch('unknown_action');

        $this->assertArrayHasKey('error', $result);
    }

    public function testGetStagedCustomersForUI(): void
    {
        $this->mockGateway->method('getStagedCustomers')
            ->willReturn([]);

        $result = $this->dispatcher->getStagedCustomersForUI();

        $this->assertIsArray($result);
    }

    public function testRenderStagingUIRendersTable(): void
    {
        $this->mockGateway->method('getStagedCustomers')
            ->willReturn([]);

        ob_start();
        $this->dispatcher->renderStagingUI();
        $output = ob_get_clean();

        $this->assertStringContainsString('Staged Customers', $output);
        $this->assertStringContainsString('<table', $output);
    }

    public function testStageCustomerStoresData(): void
    {
        $wooData = [
            'id' => 123,
            'billing' => [
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'Test Co'
            ]
        ];

        $this->mockGateway->expects($this->once())
            ->method('stageCustomer')
            ->with($this->callback(function ($data) {
                return $data['source_customer_id'] === '123'
                    && $data['email'] === 'test@example.com';
            }))
            ->willReturn(456);

        $stagingId = $this->customerStaging->stageCustomer($wooData);

        $this->assertEquals(456, $stagingId);
    }

    public function testFindMatchesWithEmptyTable(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn(null);

        $matches = $this->customerStaging->findMatches(1);

        $this->assertEmpty($matches);
    }

    public function testImportCustomerNewCustomer(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'new@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'new@example.com',
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus');

        $this->mockDb->method('query')
            ->willReturn([['id' => 100]]);
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->customerStaging->importCustomer(1, null);

        $this->assertArrayHasKey('debtor_no', $result);
        $this->assertArrayHasKey('branch_ref', $result);
        $this->assertEquals(100, $result['debtor_no']);
    }

    public function testImportCustomerExistingCustomer(): void
    {
        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'existing@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'existing@example.com',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus');

        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->customerStaging->importCustomer(1, 50);

        $this->assertArrayHasKey('debtor_no', $result);
        $this->assertArrayHasKey('branch_ref', $result);
        $this->assertEquals(50, $result['debtor_no']);
    }

    public function testSimulateFormSubmission(): void
    {
        $_POST = [
            'import_staged' => '1',
            'staging_id' => '1',
            'match_1' => 'new'
        ];

        $this->mockGateway->method('getCustomerById')
            ->willReturn([
                'id' => 1,
                'customer_email' => 'form@example.com',
                'raw_json' => json_encode(['billing' => [
                    'email' => 'form@example.com',
                    'first_name' => 'Form',
                    'last_name' => 'Test',
                ]]),
            ]);

        $this->mockGateway->expects($this->once())
            ->method('updateStatus');

        $this->mockDb->method('query')
            ->willReturn([['id' => 999]]);
        $this->mockDb->method('execute')->willReturn(true);

        $result = $this->customerStaging->importCustomer(1, null);

        $this->assertArrayHasKey('debtor_no', $result);

        unset($_POST);
    }
}
