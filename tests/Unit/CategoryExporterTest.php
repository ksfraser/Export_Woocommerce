<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;
use ksfraser\FrontAccounting\Woocommerce\CategoryExporter;
use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use ksfraser\FrontAccounting\Woocommerce\WooRestClientInterface;

use PHPUnit\Framework\TestCase;

class CategoryExporterTest extends TestCase
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

    public function testCanExportCategory(): void
    {
        $categoryData = [
            'category_id' => 'cat-001',
            'description' => 'Test Category',
            'long_description' => 'Full description'
        ];
        
        $this->mockRestClient->method('post')
            ->willReturn(['id' => 123]);
        
        $result = $this->exporter->exportCategory($categoryData);
        
        $this->assertTrue($result);
    }

    public function testCanBuildCategoryData(): void
    {
        $categoryData = [
            'category_id' => 'cat-001',
            'description' => 'Test Category',
            'long_description' => 'Full description'
        ];
        
        $result = $this->exporter->buildCategoryData($categoryData);
        
        $this->assertEquals('Test Category', $result['name']);
        $this->assertEquals('cat-001', $result['slug']);
    }

    public function testCanFindCategoryByName(): void
    {
        $expectedCategory = ['id' => 456, 'name' => 'Test Category'];
        
        $this->mockRestClient->method('get')
            ->with('products/categories', ['search' => 'Test Category'])
            ->willReturn([$expectedCategory]);
        
        $result = $this->exporter->findCategoryByName('Test Category');
        
        $this->assertEquals(456, $result['id']);
    }

    public function testExportAllCategories(): void
    {
        $categories = [
            ['category_id' => 'cat-001', 'description' => 'Category 1'],
            ['category_id' => 'cat-002', 'description' => 'Category 2']
        ];
        
        $this->mockDb->method('query')->willReturn($categories);
        $this->mockRestClient->method('post')->willReturn(['id' => 123]);
        
        $result = $this->exporter->exportAllCategories();
        
        $this->assertEquals(2, $result['exported']);
        $this->assertEquals(2, $result['total']);
    }

    public function testSendNewCategoriesToWooSendsOnlyNewCategories(): void
    {
        $newCategories = [
            ['category_id' => 1, 'description' => 'New Category'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false && strpos($sql, 'NOT IN') !== false => $newCategories,
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 100]);

        $result = $this->exporter->sendNewCategoriesToWoo();

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testSendNewCategoriesToWooSkipsBlankDescriptions(): void
    {
        $categories = [
            ['category_id' => 2, 'description' => 'Valid Category'],
        ];

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false && strpos($sql, 'NOT IN') !== false => $categories,
                default => [],
            });

        $this->mockRestClient->method('post')->willReturn(['id' => 100]);

        $result = $this->exporter->sendNewCategoriesToWoo();

        $this->assertEquals(1, $result['sent']);
    }

    public function testCreateCategoryReturnsWooIdOnSuccess(): void
    {
        $this->mockRestClient->method('post')->willReturn(['id' => 123]);
        $this->mockDb->method('query')->willReturn([['cnt' => 0]]);

        $result = $this->exporter->createCategory(['name' => 'Test', 'slug' => 'test'], 1);

        $this->assertEquals(123, $result);
    }

    public function testCreateCategoryHandlesTermExistsError(): void
    {
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
            ->willReturn([['id' => 456, 'name' => 'Test']]);

        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false => [['id' => 1]],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->exporter->createCategory(['name' => 'Test', 'slug' => 'test'], 1);

        $this->assertEquals(456, $result);
    }

    public function testMatchCategoryByNameFindsMatch(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Clothing'],
            ['id' => 2, 'name' => 'Electronics'],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);

        $result = $this->exporter->matchCategoryByName('Electronics');

        $this->assertNotNull($result);
        $this->assertEquals(2, $result['id']);
    }

    public function testMatchCategoryByNameReturnsNullWhenNotFound(): void
    {
        $this->mockRestClient->method('get')->willReturn([
            ['id' => 1, 'name' => 'Clothing'],
        ]);

        $result = $this->exporter->matchCategoryByName('NonExistent');

        $this->assertNull($result);
    }

    public function testMatchCategoryBySlugFindsMatch(): void
    {
        $categories = [
            ['id' => 1, 'slug' => 'clothing'],
            ['id' => 2, 'slug' => 'electronics'],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);

        $result = $this->exporter->matchCategoryBySlug('electronics');

        $this->assertNotNull($result);
        $this->assertEquals(2, $result['id']);
    }

    public function testGetCategoriesReturnsCategoriesFromWoo(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Category 1'],
            ['id' => 2, 'name' => 'Category 2'],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);

        $result = $this->exporter->getCategories();

        $this->assertCount(2, $result);
    }

    public function testGetCategoryReturnsSingleCategory(): void
    {
        $category = ['id' => 123, 'name' => 'Test Category'];

        $this->mockRestClient->method('get')
            ->with('products/categories/123', [])
            ->willReturn($category);

        $result = $this->exporter->getCategory(123);

        $this->assertEquals(123, $result['id']);
    }

    public function testGetFaIdByCategoryNameFindsId(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false => [['id' => 5]],
                default => [],
            });

        $result = $this->exporter->getFaIdByCategoryName('Test Category');

        $this->assertEquals(5, $result);
    }

    public function testGetFaIdByCategoryNameReturnsNullWhenNotFound(): void
    {
        $this->mockDb->method('query')->willReturn([]);

        $result = $this->exporter->getFaIdByCategoryName('NonExistent');

        $this->assertNull($result);
    }

    public function testUpdateCategoryXrefInsertsNewRecord(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->exporter->updateCategoryXref(1, 100, 'Test Category');

        $this->assertTrue($result);
    }

    public function testUpdateCategoryXrefUpdatesExistingRecord(): void
    {
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'COUNT') !== false => [['cnt' => 1]],
                default => [],
            });

        $result = $this->exporter->updateCategoryXref(1, 100, 'Test Category');

        $this->assertTrue($result);
    }

    public function testLoadCategoriesFromWooLoadsCategories(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Category 1', 'description' => 'Desc 1'],
            ['id' => 2, 'name' => 'Category 2', 'description' => 'Desc 2'],
        ];

        $this->mockRestClient->method('get')->willReturn($categories);
        $this->mockDb->method('query')
            ->willReturnCallback(fn($sql) => match(true) {
                strpos($sql, 'stock_category') !== false => [['id' => 5]],
                strpos($sql, 'COUNT') !== false => [['cnt' => 0]],
                default => [],
            });

        $result = $this->exporter->loadCategoriesFromWoo();

        $this->assertEquals(2, $result);
    }

    public function testUpdateCategoryCallsPutEndpoint(): void
    {
        $this->mockRestClient->method('put')
            ->willReturn(['id' => 123, 'name' => 'Updated']);

        $result = $this->exporter->updateCategory(123, ['name' => 'Updated']);

        $this->assertEquals('Updated', $result['name']);
    }

    public function testDeleteCategoryReturnsTrueOnSuccess(): void
    {
        $this->mockRestClient->method('delete')->willReturn(['deleted' => true]);

        $result = $this->exporter->deleteCategory(123);

        $this->assertTrue($result);
    }

    public function testSanitizeSlugProducesValidSlug(): void
    {
        $categoryData = [
            'category_id' => 'cat-001',
            'description' => 'Test Category With Spaces!',
        ];

        $this->mockRestClient->method('post')->willReturn(['id' => 123]);

        $result = $this->exporter->exportCategory($categoryData);

        $this->assertTrue($result);
    }
}
