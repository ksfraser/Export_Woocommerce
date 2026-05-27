<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\CategoryExporter;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class CategoryExporterCoverageTest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $exporter;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockDb->method('escape')->willReturnCallback(function($v) { return addslashes($v); });
        $this->mockDb->method('getPrefix')->willReturn('0_');
        $this->exporter = new CategoryExporter(
            $this->mockRestClient,
            $this->mockLogger,
            $this->mockDb
        );
    }

    public function testSendNewCategoriesToWooDebugModeBreaksAfterOne(): void
    {
        $categories = [
            ['category_id' => 1, 'description' => 'Category 1'],
            ['category_id' => 2, 'description' => 'Category 2'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false && strpos($sql, 'NOT IN') !== false => $categories,
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 100]);

        $result = $this->exporter->sendNewCategoriesToWoo(true);
        $this->assertEquals(1, $result['sent']);
    }

    public function testSendNewCategoriesToWooCreateCategoryReturnsFalse(): void
    {
        $categories = [
            ['category_id' => 1, 'description' => 'Category 1'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false && strpos($sql, 'NOT IN') !== false => $categories,
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['error' => 'failed']);

        $result = $this->exporter->sendNewCategoriesToWoo();
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testSendNewCategoriesToWooGenericException(): void
    {
        $categories = [
            ['category_id' => 1, 'description' => 'Category 1'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false && strpos($sql, 'NOT IN') !== false => $categories,
                default => [],
            });

        $this->mockRestClient->method('post')
            ->willThrowException(new \Exception('Generic error'));

        $result = $this->exporter->sendNewCategoriesToWoo();
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function testSendNewCategoriesToWooOutOfBoundsExceptionTriggersRetry(): void
    {
        $categories = [
            ['category_id' => 1, 'description' => 'Category 1'],
        ];

        $callCount = 0;
        $this->mockDb->method('query')
            ->willReturnCallback(function($sql) use ($categories, &$callCount) {
                $callCount++;
                if (strpos($sql, 'stock_category') !== false && strpos($sql, 'NOT IN') !== false) {
                    return $categories;
                }
                if (strpos($sql, 'COUNT') !== false) {
                    return [['cnt' => 0]];
                }
                return [];
            });

        $postCallCount = 0;
        $this->mockRestClient->method('post')
            ->willReturnCallback(function() use (&$postCallCount) {
                $postCallCount++;
                if ($postCallCount === 1) {
                    throw new \Exception('term_exists', 0);
                }
                return ['id' => 100];
            });

        $this->mockRestClient->method('get')
            ->willReturn([['id' => 456, 'name' => 'Category 1']]);

        $result = $this->exporter->sendNewCategoriesToWoo();
        $this->assertEquals(1, $result['sent']);
    }

    public function testCreateCategoryTermExistsNoMatchThrowsOutOfBounds(): void
    {
        $this->mockRestClient->method('post')
            ->willThrowException(new \Exception('term_exists', 0));

        $this->mockRestClient->method('get')
            ->willReturn([]);

        $this->expectException(\OutOfBoundsException::class);
        $this->exporter->createCategory(['name' => 'Test', 'slug' => 'test'], 1);
    }

    public function testCreateCategory400ErrorTriggersRefresh(): void
    {
        $this->mockRestClient->method('post')
            ->willThrowException(new \Exception('400', 400));

        $this->mockRestClient->method('get')
            ->willReturn([]);

        $this->expectException(\OutOfBoundsException::class);
        $this->exporter->createCategory(['name' => 'Test', 'slug' => 'test'], 1);
    }

    public function testCreateCategoryUnknownErrorReturnsFalse(): void
    {
        $this->mockRestClient->method('post')
            ->willThrowException(new \Exception('Unknown error', 999));

        $result = $this->exporter->createCategory(['name' => 'Test', 'slug' => 'test'], 1);
        $this->assertFalse($result);
    }

    public function testCreateCategoryPostReturnsNoId(): void
    {
        $this->mockRestClient->method('post')->willReturn(['error' => 'failed']);

        $result = $this->exporter->createCategory(['name' => 'Test', 'slug' => 'test'], 1);
        $this->assertFalse($result);
    }

    public function testUpdateCategoryXrefExceptionReturnsFalse(): void
    {
        $this->mockDb->method('query')
            ->willThrowException(new \Exception('DB Error'));

        $result = $this->exporter->updateCategoryXref(1, 100, 'Test');
        $this->assertFalse($result);
    }

    public function testLoadCategoriesFromWooWithNullFaId(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Category 1', 'description' => 'Desc 1'],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false => [],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->exporter->loadCategoriesFromWoo();
        $this->assertEquals(1, $result);
    }

    public function testLoadCategoriesFromWooWithMissingFields(): void
    {
        $categories = [
            ['id' => 1],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false => [['id' => 5]],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->exporter->loadCategoriesFromWoo();
        $this->assertEquals(1, $result);
    }

    public function testGetCategoriesExceptionReturnsEmpty(): void
    {
        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->getCategories();
        $this->assertEmpty($result);
    }

    public function testGetCategoryExceptionReturnsNull(): void
    {
        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->getCategory(123);
        $this->assertNull($result);
    }

    public function testFindCategoryByNameReturnsNull(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);

        $result = $this->exporter->findCategoryByName('NonExistent');
        $this->assertNull($result);
    }

    public function testExportCategoryExceptionReturnsFalse(): void
    {
        $this->mockRestClient->method('get')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->exportCategory(['description' => 'Test']);
        $this->assertFalse($result);
    }

    public function testUpdateCategoryExceptionReturnsNull(): void
    {
        $this->mockRestClient->method('put')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->updateCategory(123, ['name' => 'Updated']);
        $this->assertNull($result);
    }

    public function testDeleteCategoryExceptionReturnsFalse(): void
    {
        $this->mockRestClient->method('delete')
            ->willThrowException(new \Exception('API Error'));

        $result = $this->exporter->deleteCategory(123);
        $this->assertFalse($result);
    }

    public function testInsertCategoryToLocalTableException(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Category 1', 'slug' => 'cat-1', 'parent' => 0, 'description' => 'Desc', 'menu_order' => 1],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false => [['id' => 5]],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                strpos($sql, 'woo_category') !== false => throw new \Exception('DB Error'),
                default => [],
            });

        $result = $this->exporter->loadCategoriesFromWoo();
        $this->assertEquals(1, $result);
    }

    public function testRefreshCategoriesFromWoo(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Category 1', 'description' => 'Desc 1'],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false => [['id' => 5]],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->exporter->refreshCategoriesFromWoo();
        $this->assertEquals(1, $result);
    }
}
