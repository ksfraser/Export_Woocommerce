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

class WooSyncUITest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $dispatcher;
    private $customerStaging;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        // Don't set default query/execute - set per test with willReturnCallback
        
        $this->customerStaging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging(
            $this->mockDb,
            $this->mockLogger
        );
        
        $productExporter = new \Ksfraser\Frontaccounting\Woocommerce\ProductExportService(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
        $orderExporter = new \Ksfraser\Frontaccounting\Woocommerce\OrderExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
        $customerExporter = new \Ksfraser\Frontaccounting\Woocommerce\CustomerExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
        $categoryExporter = new \Ksfraser\Frontaccounting\Woocommerce\CategoryExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
        
        $this->dispatcher = new \Ksfraser\Frontaccounting\Woocommerce\UI\ImportExportDispatcher(
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
        // Mock empty staging table
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return []; // No staged customers
                }
                return [];
            });
        
        $result = $this->dispatcher->getStagedCustomersForUI();
        
        $this->assertIsArray($result);
    }

    public function testRenderStagingUIRendersTable(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) {
                if (strpos($sql, 'woo_customer_staging') !== false) {
                    return []; // Empty table
                }
                return [];
            });
        
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
        
        // Create a complete mock with all methods
        $mockDb = new class implements \Ksfraser\Frontaccounting\Woocommerce\DatabaseInterface {
            private $callCount = 0;
            
            public function query(string $sql): array
            {
                if (strpos($sql, 'LAST_INSERT_ID') !== false) {
                    return [['id' => 456]];
                }
                return [];
            }
            
            public function execute(string $sql): bool
            {
                return true;
            }
            
            public function getPrefix(): string
            {
                return '0_';
            }
            
            public function escape(string $value): string
            {
                return addslashes($value);
            }
        };
        
        $customerStaging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging(
            $mockDb,
            $this->mockLogger
        );
        
        $stagingId = $customerStaging->stageCustomer($wooData);
        
        $this->assertEquals(456, $stagingId);
    }

    public function testFindMatchesWithEmptyTable(): void
    {
        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'email' => 'test@example.com']], // SELECT staged
                [] // No candidates
            );
        
        $matches = $this->customerStaging->findMatches(1);
        
        $this->assertEmpty($matches);
    }

    public function testImportCustomerNewCustomer(): void
    {
        // Create mock that returns predictable values
        $mockDb = new class implements \Ksfraser\Frontaccounting\Woocommerce\DatabaseInterface {
            private $queryCount = 0;
            
            public function query(string $sql): array
            {
                $this->queryCount++;
                if ($this->queryCount === 1) {
                    // SELECT staged record
                    return [['id' => 1, 'raw_data' => json_encode([
                        'billing' => [
                            'email' => 'new@example.com',
                            'first_name' => 'Jane',
                            'last_name' => 'Doe'
                        ]
                    ])]];
                } elseif ($this->queryCount === 2) {
                    // LAST_INSERT_ID for customer
                    return [['id' => 100]];
                } elseif ($this->queryCount === 3) {
                    // LAST_INSERT_ID for branch
                    return [['id' => 200]];
                }
                return [];
            }
            
            public function execute(string $sql): bool
            {
                return true;
            }
            
            public function getPrefix(): string
            {
                return '0_';
            }
            
            public function escape(string $value): string
            {
                return addslashes($value);
            }
        };
        
        $customerStaging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging(
            $mockDb,
            $this->mockLogger
        );
        
        $result = $customerStaging->importCustomer(1, null);
        
        $this->assertArrayHasKey('debtor_no', $result);
        $this->assertArrayHasKey('branch_ref', $result);
        $this->assertEquals(100, $result['debtor_no']);
    }

    public function testImportCustomerExistingCustomer(): void
    {
        $mockDb = new class implements \Ksfraser\Frontaccounting\Woocommerce\DatabaseInterface {
            private $queryCount = 0;
            
            public function query(string $sql): array
            {
                $this->queryCount++;
                if ($this->queryCount === 1) {
                    return [['id' => 1, 'raw_data' => json_encode([
                        'billing' => ['email' => 'existing@example.com']
                    ])]];
                } elseif ($this->queryCount === 2) {
                    return [['id' => 200]]; // LAST_INSERT_ID for branch
                }
                return [];
            }
            
            public function execute(string $sql): bool
            {
                return true;
            }
            
            public function getPrefix(): string
            {
                return '0_';
            }
            
            public function escape(string $value): string
            {
                return addslashes($value);
            }
        };
        
        $customerStaging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging(
            $mockDb,
            $this->mockLogger
        );
        
        $result = $customerStaging->importCustomer(1, 50); // Use existing customer 50
        
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
        
        $mockDb = new class implements \Ksfraser\Frontaccounting\Woocommerce\DatabaseInterface {
            private $queryCount = 0;
            
            public function query(string $sql): array
            {
                $this->queryCount++;
                if ($this->queryCount === 1) {
                    return [['id' => 1, 'raw_data' => json_encode([
                        'billing' => ['email' => 'form@example.com', 'first_name' => 'Form', 'last_name' => 'Test']
                    ])]];
                } elseif ($this->queryCount === 2) {
                    return [['id' => 999]];
                } elseif ($this->queryCount === 3) {
                    return [['id' => 888]];
                }
                return [];
            }
            
            public function execute(string $sql): bool
            {
                return true;
            }
            
            public function getPrefix(): string
            {
                return '0_';
            }
            
            public function escape(string $value): string
            {
                return addslashes($value);
            }
        };
        
        $customerStaging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging(
            $mockDb,
            $this->mockLogger
        );
        
        $result = $customerStaging->importCustomer(1, null);
        
        $this->assertArrayHasKey('debtor_no', $result);
        
        unset($_POST);
    }
}