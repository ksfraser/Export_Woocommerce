<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\UI\ImportExportDispatcher;
use Ksfraser\frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\frontaccounting\Woocommerce\OrderExporter;
use Ksfraser\frontaccounting\Woocommerce\CustomerExporter;
use Ksfraser\frontaccounting\Woocommerce\CategoryExporter;
use Ksfraser\frontaccounting\Woocommerce\Staging\CustomerStaging;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class ImportExportDispatcherTest extends TestCase
{
    private $mockProductExporter;
    private $mockOrderExporter;
    private $mockCustomerExporter;
    private $mockCategoryExporter;
    private $mockCustomerStaging;
    private $dispatcher;

    protected function setUp(): void
    {
        $this->mockProductExporter = $this->createMock(ProductExportService::class);
        $this->mockOrderExporter = $this->createMock(OrderExporter::class);
        $this->mockCustomerExporter = $this->createMock(CustomerExporter::class);
        $this->mockCategoryExporter = $this->createMock(CategoryExporter::class);
        $this->mockCustomerStaging = $this->createMock(CustomerStaging::class);

        $this->dispatcher = new ImportExportDispatcher(
            $this->mockProductExporter,
            $this->mockOrderExporter,
            $this->mockCustomerExporter,
            $this->mockCategoryExporter,
            $this->mockCustomerStaging
        );
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ImportExportDispatcher::class, $this->dispatcher);
    }

    public function testDispatchExportProducts(): void
    {
        $this->mockProductExporter->method('exportAllSimpleProducts')
            ->willReturn(['exported' => 5, 'total' => 10]);

        $result = $this->dispatcher->dispatch(ImportExportDispatcher::ACTION_EXPORT_PRODUCTS);

        $this->assertEquals(5, $result['exported']);
    }

    public function testDispatchExportCategories(): void
    {
        $this->mockCategoryExporter->method('exportAllCategories')
            ->willReturn(['exported' => 3, 'total' => 5]);

        $result = $this->dispatcher->dispatch(ImportExportDispatcher::ACTION_EXPORT_CATEGORIES);

        $this->assertEquals(3, $result['exported']);
    }

    public function testDispatchImportOrders(): void
    {
        $this->mockOrderExporter->method('importOrdersToFA')
            ->willReturn(['imported' => 2, 'total' => 5]);

        $result = $this->dispatcher->dispatch(ImportExportDispatcher::ACTION_IMPORT_ORDERS, ['limit' => 5]);

        $this->assertEquals(2, $result['imported']);
    }

    public function testDispatchStageCustomers(): void
    {
        $this->mockCustomerExporter->method('listCustomers')
            ->willReturn([['id' => 1, 'email' => 'a@example.com'], ['id' => 2, 'email' => 'b@example.com']]);
        $this->mockCustomerStaging->method('stageCustomer')
            ->willReturnOnConsecutiveCalls(1, 2);

        $result = $this->dispatcher->dispatch(ImportExportDispatcher::ACTION_IMPORT_CUSTOMERS);

        $this->assertEquals(2, $result['staged']);
    }

    public function testDispatchExportCustomers(): void
    {
        $this->mockCustomerExporter->method('exportAllCustomers')
            ->willReturn(['exported' => 4, 'updated' => 2, 'errors' => 0, 'total' => 6]);

        $result = $this->dispatcher->dispatch(ImportExportDispatcher::ACTION_EXPORT_CUSTOMERS);

        $this->assertEquals(4, $result['exported']);
        $this->assertEquals(2, $result['updated']);
    }

    public function testDispatchSyncAll(): void
    {
        $this->mockProductExporter->method('exportAllSimpleProducts')
            ->willReturn(['exported' => 5, 'total' => 10]);
        $this->mockCategoryExporter->method('exportAllCategories')
            ->willReturn(['exported' => 3, 'total' => 5]);
        $this->mockOrderExporter->method('importOrdersToFA')
            ->willReturn(['imported' => 2, 'total' => 5]);
        $this->mockCustomerExporter->method('exportAllCustomers')
            ->willReturn(['exported' => 4, 'total' => 8]);

        $result = $this->dispatcher->dispatch(ImportExportDispatcher::ACTION_SYNC_ALL);

        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('orders', $result);
        $this->assertArrayHasKey('customers', $result);
    }

    public function testDispatchUnknownAction(): void
    {
        $result = $this->dispatcher->dispatch('unknown_action');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Unknown action', $result['error']);
    }

    public function testDispatchExportProductsWithLimit(): void
    {
        $this->mockProductExporter->method('exportAllSimpleProducts')
            ->with(5)
            ->willReturn(['exported' => 3, 'total' => 5]);

        $result = $this->dispatcher->dispatch(ImportExportDispatcher::ACTION_EXPORT_PRODUCTS, ['limit' => 5]);

        $this->assertEquals(3, $result['exported']);
    }

    public function testGetStagedCustomersForUI(): void
    {
        $staged = [
            ['id' => 1, 'email' => 'test@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'imported' => 0, 'staged_at' => '2024-01-15'],
        ];

        $this->mockCustomerStaging->method('getStagedCustomers')->willReturn($staged);
        $this->mockCustomerStaging->method('findMatches')->willReturn([
            ['debtor_no' => 1, 'name' => 'ACME Corp', 'company' => 'ACME', 'score' => 85.0]
        ]);

        $result = $this->dispatcher->getStagedCustomersForUI();

        $this->assertCount(1, $result);
        $this->assertEquals('test@example.com', $result[0]['email']);
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertFalse($result[0]['imported']);
        $this->assertCount(1, $result[0]['matches']);
    }

    public function testGetStagedCustomersForUIImportedCustomer(): void
    {
        $staged = [
            ['id' => 1, 'email' => 'test@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'imported' => 1, 'staged_at' => '2024-01-15'],
        ];

        $this->mockCustomerStaging->method('getStagedCustomers')->willReturn($staged);

        $result = $this->dispatcher->getStagedCustomersForUI();

        $this->assertTrue($result[0]['imported']);
        $this->assertEmpty($result[0]['matches']);
    }

    public function testRenderStagingUIOutputsHtml(): void
    {
        $staged = [
            ['id' => 1, 'email' => 'test@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'imported' => 0, 'staged_at' => '2024-01-15'],
        ];

        $this->mockCustomerStaging->method('getStagedCustomers')->willReturn($staged);
        $this->mockCustomerStaging->method('findMatches')->willReturn([
            ['debtor_no' => 1, 'name' => 'ACME Corp', 'company' => 'ACME', 'score' => 85.0]
        ]);

        ob_start();
        $this->dispatcher->renderStagingUI();
        $output = ob_get_clean();

        $this->assertStringContainsString('Staged Customers', $output);
        $this->assertStringContainsString('test@example.com', $output);
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('Create New', $output);
    }

    public function testRenderStagingUIImportedCustomer(): void
    {
        $staged = [
            ['id' => 1, 'email' => 'test@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'company' => 'ACME', 'imported' => 1, 'staged_at' => '2024-01-15'],
        ];

        $this->mockCustomerStaging->method('getStagedCustomers')->willReturn($staged);

        ob_start();
        $this->dispatcher->renderStagingUI();
        $output = ob_get_clean();

        $this->assertStringContainsString('Staged Customers', $output);
        $this->assertStringContainsString('Imported', $output);
        $this->assertStringNotContainsString('Create New', $output);
    }
}
