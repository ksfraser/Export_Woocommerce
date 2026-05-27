<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\ProductService;
use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends TestCase
{
    private $mockRestClient;
    private $mockLogger;
    private $mockDb;
    private $service;

    protected function setUp(): void
    {
        $this->mockRestClient = $this->createMock(WooRestClientInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->service = new ProductService($this->mockRestClient, $this->mockLogger, $this->mockDb);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ProductService::class, $this->service);
    }

    public function testGetProductsReturnsProducts(): void
    {
        $products = [
            ['id' => 1, 'name' => 'Product 1'],
            ['id' => 2, 'name' => 'Product 2'],
        ];
        $this->mockRestClient->method('get')->willReturn($products);
        $result = $this->service->getProducts(50);
        $this->assertCount(2, $result);
    }

    public function testGetProductsReturnsEmptyOnError(): void
    {
        $this->mockRestClient->method('get')->willThrowException(new \Exception('API Error'));
        $result = $this->service->getProducts();
        $this->assertEmpty($result);
    }

    public function testGetProductReturnsSingleProduct(): void
    {
        $product = ['id' => 123, 'name' => 'Test Product'];
        $this->mockRestClient->method('get')->willReturn($product);
        $result = $this->service->getProduct(123);
        $this->assertEquals(123, $result['id']);
    }

    public function testGetProductReturnsNullOnError(): void
    {
        $this->mockRestClient->method('get')->willThrowException(new \Exception('Not found'));
        $result = $this->service->getProduct(999);
        $this->assertNull($result);
    }

    public function testFindProductBySkuReturnsProduct(): void
    {
        $this->mockRestClient->method('get')->willReturn([['id' => 123, 'sku' => 'TEST-001']]);
        $result = $this->service->findProductBySku('TEST-001');
        $this->assertEquals(123, $result['id']);
    }

    public function testFindProductBySkuReturnsNullWhenNotFound(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);
        $result = $this->service->findProductBySku('NONEXISTENT');
        $this->assertNull($result);
    }

    public function testFindProductBySkuReturnsNullOnError(): void
    {
        $this->mockRestClient->method('get')->willThrowException(new \Exception('API Error'));
        $result = $this->service->findProductBySku('TEST-001');
        $this->assertNull($result);
    }

    public function testListProductsPaginatesCorrectly(): void
    {
        $this->mockRestClient->method('get')
            ->willReturnCallback(function($endpoint, $params) {
                if ($params['page'] === 1) {
                    return array_fill(0, 100, ['id' => 1]);
                }
                return [['id' => 2]];
            });
        $result = $this->service->listProducts(100);
        $this->assertCount(101, $result);
    }

    public function testListProductsStopsOnEmptyPage(): void
    {
        $this->mockRestClient->method('get')->willReturn([]);
        $result = $this->service->listProducts();
        $this->assertEmpty($result);
    }

    public function testListProductsStopsOnFewerResults(): void
    {
        $this->mockRestClient->method('get')
            ->willReturnCallback(function($endpoint, $params) {
                if ($params['page'] === 1) {
                    return array_fill(0, 50, ['id' => 1]);
                }
                return [];
            });
        $result = $this->service->listProducts(100);
        $this->assertCount(50, $result);
    }

    public function testListProductsRespectsMaxPages(): void
    {
        $this->mockRestClient->method('get')
            ->willReturn(array_fill(0, 100, ['id' => 1]));
        $result = $this->service->listProducts(100);
        $this->assertCount(10000, $result);
    }
}
