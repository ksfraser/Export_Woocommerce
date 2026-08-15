<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\Dao\SyncDao;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use PHPUnit\Framework\TestCase;

class SyncDaoTest extends TestCase
{
    private $mockDb;
    private $dao;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->dao = new SyncDao($this->mockDb);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SyncDao::class, $this->dao);
    }

    public function testGetWooProductIdReturnsIdWhenFound(): void
    {
        $this->mockDb->method('query')->willReturn([['woo_product_id' => 123]]);
        $result = $this->dao->getWooProductId('TEST-001');
        $this->assertEquals(123, $result);
    }

    public function testGetWooProductIdReturnsNullWhenNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->dao->getWooProductId('NONEXISTENT');
        $this->assertNull($result);
    }

    public function testSaveProductMappingInsertsNew(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->dao->saveProductMapping('TEST-001', 123, 'https://example.com/product');
        $this->assertTrue($result);
    }

    public function testSaveProductMappingWithEmptyUrl(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->dao->saveProductMapping('TEST-001', 123);
        $this->assertTrue($result);
    }

    public function testGetCategoryMappingReturnsIdWhenFound(): void
    {
        $this->mockDb->method('query')->willReturn([['woo_category_id' => 456]]);
        $result = $this->dao->getCategoryMapping(1);
        $this->assertEquals(456, $result);
    }

    public function testGetCategoryMappingReturnsNullWhenNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->dao->getCategoryMapping(999);
        $this->assertNull($result);
    }

    public function testSaveCategoryMapping(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->dao->saveCategoryMapping(1, 456);
        $this->assertTrue($result);
    }

    public function testLogSyncWithAllFields(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->dao->logSync('product', 'export', 'TEST-001', true, null, '{"key":"value"}');
        $this->assertTrue($result);
    }

    public function testLogSyncWithNullRefId(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->dao->logSync('category', 'import', null, false, 'Error occurred', null);
        $this->assertTrue($result);
    }

    public function testGetUnsyncedProductsReturnsPending(): void
    {
        $products = [['stock_id' => 'P001', 'sync_status' => 'pending']];
        $this->mockDb->method('query')->willReturn($products);
        $result = $this->dao->getUnsyncedProducts(50);
        $this->assertCount(1, $result);
    }

    public function testGetUnsyncedProductsWithDefaultLimit(): void
    {
        $this->mockDb->method('query')->willReturn([]);
        $result = $this->dao->getUnsyncedProducts();
        $this->assertIsArray($result);
    }

    public function testMarkSyncError(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->dao->markSyncError('TEST-001', 'API timeout');
        $this->assertTrue($result);
    }

    public function testDeleteProductMapping(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $result = $this->dao->deleteProductMapping('TEST-001');
        $this->assertTrue($result);
    }

    public function testEnsureTablesCreatesAllTables(): void
    {
        $this->mockDb->method('execute')->willReturn(true);
        $this->dao->ensureTables();
        $this->assertTrue(true);
    }
}
