<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\Dao\StockItemDao;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for StockItemDao - FA stock_master access
 */
class StockItemDaoTest extends TestCase
{
    private $mockDb;
    private $dao;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockDb->method('escape')->willReturnCallback(function ($v) {
            return addslashes($v);
        });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->dao = new StockItemDao($this->mockDb);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(StockItemDao::class, $this->dao);
    }

    public function testGetItemForSyncReturnsRowWhenFound(): void
    {
        $this->mockDb->method('query')->willReturn([
            ['stock_id' => 'TEST-001', 'description' => 'Test Item', 'inactive' => 0],
        ]);
        $result = $this->dao->getItemForSync('TEST-001');
        $this->assertEquals('TEST-001', $result['stock_id']);
        $this->assertEquals('Test Item', $result['description']);
    }

    public function testGetItemForSyncReturnsNullWhenNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $this->assertNull($this->dao->getItemForSync('NONEXISTENT'));
    }

    public function testGetItemForSyncUsesPrefixAndEscapesStockId(): void
    {
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($sql) {
                return strpos($sql, '0_stock_master') !== false
                    && strpos($sql, "stock_id = 'TEST-001'") !== false;
            }))
            ->willReturn([
                ['stock_id' => 'TEST-001', 'description' => 'Test Item', 'inactive' => 0],
            ]);

        $this->dao->getItemForSync('TEST-001');
    }
}
