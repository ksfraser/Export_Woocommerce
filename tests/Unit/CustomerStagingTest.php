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

class CustomerStagingTest extends TestCase
{
    private $mockDb;
    private $mockLogger;
    private $staging;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        $this->staging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging(
            $this->mockDb,
            $this->mockLogger
        );
    }

    public function testStageCustomer(): void
    {
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->mockDb->method('execute')->willReturn(true);
        $this->mockDb->method('query')->willReturn([['id' => 123]]);

        $wooData = [
            'id' => 999,
            'billing' => [
                'email' => 'test@example.com',
                'phone' => '555-1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'ACME Inc',
                'address_1' => '123 Main St',
                'city' => 'Springfield',
                'state' => 'IL',
                'postcode' => '62701',
                'country' => 'US'
            ]
        ];

        $result = $this->staging->stageCustomer($wooData);
        $this->assertEquals(123, $result);
    }

    public function testFindMatchesByEmail(): void
    {
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        
        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'email' => 'test@example.com', 'phone' => '555-1234']], // staged
                [ // candidates
                    ['debtor_no' => 100, 'email' => 'test@example.com', 'name' => 'John Doe']
                ]
            );

        $matches = $this->staging->findMatches(1);
        
        $this->assertCount(1, $matches);
        $this->assertEquals(100, $matches[0]['debtor_no']);
        $this->assertGreaterThan(25, $matches[0]['score']); // Email match = 30 points
    }

    public function testFindMatchesByPhone(): void
    {
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        
        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'email' => '', 'phone' => '555-1234', 'first_name' => '', 'last_name' => '', 'company' => '']], // staged
                [ // candidates
                    ['debtor_no' => 100, 'name' => 'Test Co', 'email' => '', 'phone' => '555-1234', 'branch_email' => '', 'contact_name' => '', 'br_address' => '']
                ]
            );

        $matches = $this->staging->findMatches(1);
        
        $this->assertCount(1, $matches);
        $this->assertGreaterThan(20, $matches[0]['score']); // Phone match = 25 points
    }

    public function testNoMatches(): void
    {
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        
        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'email' => 'new@example.com', 'phone' => '999-9999']], // staged
                [] // no candidates
            );

        $matches = $this->staging->findMatches(1);
        
        $this->assertCount(0, $matches);
    }

    public function testImportNewCustomer(): void
    {
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->mockDb->method('execute')->willReturn(true);
        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'raw_data' => json_encode(['billing' => ['email' => 'new@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe']])]], // staged
                [['id' => 456]] // LAST_INSERT_ID for new customer
            );

        $result = $this->staging->importCustomer(1, null);
        
        $this->assertArrayHasKey('debtor_no', $result);
        $this->assertArrayHasKey('branch_ref', $result);
    }

    public function testImportExistingCustomer(): void
    {
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->mockDb->method('execute')->willReturn(true);
        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                [['id' => 1, 'raw_data' => json_encode(['billing' => ['email' => 'existing@example.com']])]], // staged
                [['id' => 789]] // LAST_INSERT_ID for new branch
            );

        $result = $this->staging->importCustomer(1, 100, null); // Use existing customer 100
        
        $this->assertEquals(100, $result['debtor_no']);
    }
}
