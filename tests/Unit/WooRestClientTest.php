<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\WooRestClient;
use Ksfraser\frontaccounting\Woocommerce\WooRestClientInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\Exceptions\WooApiException;
use Automattic\WooCommerce\Client;
use PHPUnit\Framework\TestCase;

class WooRestClientTest extends TestCase
{
    private $mockWcClient;
    private $mockLogger;
    private $restClient;

    protected function setUp(): void
    {
        $this->mockWcClient = $this->createMock(Client::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->restClient = new WooRestClient($this->mockWcClient, $this->mockLogger);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(WooRestClientInterface::class, $this->restClient);
    }

    public function testGetReturnsArray(): void
    {
        $stdResult = (object)['id' => 1, 'name' => 'Test'];
        $expected = ['id' => 1, 'name' => 'Test'];

        $this->mockWcClient->expects($this->once())
            ->method('get')
            ->with('products', ['per_page' => 10])
            ->willReturn($stdResult);

        $result = $this->restClient->get('products', ['per_page' => 10]);
        $this->assertEquals($expected, $result);
    }

    public function testGetReturnsArrayFromStdClassList(): void
    {
        $stdResults = [
            (object)['id' => 1, 'name' => 'One'],
            (object)['id' => 2, 'name' => 'Two'],
        ];

        $this->mockWcClient->method('get')->willReturn($stdResults);

        $result = $this->restClient->get('products');
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('Two', $result[1]['name']);
    }

    public function testPostReturnsArray(): void
    {
        $stdResult = (object)['id' => 123, 'status' => 'publish'];
        $data = ['name' => 'New Product', 'type' => 'simple'];

        $this->mockWcClient->expects($this->once())
            ->method('post')
            ->with('products', $data)
            ->willReturn($stdResult);

        $result = $this->restClient->post('products', $data);
        $this->assertEquals(['id' => 123, 'status' => 'publish'], $result);
    }

    public function testPutReturnsArray(): void
    {
        $stdResult = (object)['id' => 1, 'name' => 'Updated'];
        $data = ['name' => 'Updated'];

        $this->mockWcClient->expects($this->once())
            ->method('put')
            ->with('products/1', $data)
            ->willReturn($stdResult);

        $result = $this->restClient->put('products/1', $data);
        $this->assertEquals('Updated', $result['name']);
    }

    public function testDeleteReturnsArray(): void
    {
        $stdResult = (object)['id' => 1, 'deleted' => true];

        $this->mockWcClient->expects($this->once())
            ->method('delete')
            ->with('products/1', ['force' => true])
            ->willReturn($stdResult);

        $result = $this->restClient->delete('products/1', ['force' => true]);
        $this->assertTrue($result['deleted']);
    }

    public function testGetLogsInfoOnSuccess(): void
    {
        $this->mockWcClient->method('get')->willReturn((object)['id' => 1]);
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('GET'));
        $this->mockLogger->expects($this->never())->method('error');

        $this->restClient->get('products/1');
    }

    public function testPostLogsInfoOnSuccess(): void
    {
        $this->mockWcClient->method('post')->willReturn((object)['id' => 1]);
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('POST'));
        $this->mockLogger->expects($this->never())->method('error');

        $this->restClient->post('products', ['name' => 'Test']);
    }

    public function testThrowsWooApiExceptionOnHttpError(): void
    {
        $this->mockWcClient->method('get')
            ->willThrowException(new \Exception('HTTP Error: 404 Not Found'));

        $this->expectException(WooApiException::class);
        $this->expectExceptionMessage('WooCommerce API error: HTTP Error: 404 Not Found');

        $this->restClient->get('products/999');
    }

    public function testThrowsWooApiExceptionOnPut(): void
    {
        $this->mockWcClient->method('put')
            ->willThrowException(new \Exception('HTTP Error: 400 Bad Request'));

        $this->expectException(WooApiException::class);

        $this->restClient->put('products/1', []);
    }

    public function testThrowsWooApiExceptionOnPost(): void
    {
        $this->mockWcClient->method('post')
            ->willThrowException(new \Exception('HTTP Error: 500 Internal Server Error'));

        $this->expectException(WooApiException::class);

        $this->restClient->post('products', ['name' => 'Test']);
    }

    public function testThrowsWooApiExceptionOnDelete(): void
    {
        $this->mockWcClient->method('delete')
            ->willThrowException(new \Exception('HTTP Error: 500'));

        $this->expectException(WooApiException::class);

        $this->restClient->delete('products/1');
    }

    public function testLogsErrorOnException(): void
    {
        $this->mockWcClient->method('get')
            ->willThrowException(new \Exception('Connection timeout'));

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Connection timeout'));

        $this->expectException(WooApiException::class);
        $this->restClient->get('products');
    }

    public function testConvertsNestedStdClassToArray(): void
    {
        $nested = (object)[
            'id' => 1,
            'meta_data' => [
                (object)['id' => 10, 'key' => '_sku', 'value' => 'TEST']
            ],
            'categories' => [
                (object)['id' => 5, 'name' => 'Test Category']
            ]
        ];

        $this->mockWcClient->method('get')->willReturn($nested);

        $result = $this->restClient->get('products/1');
        $this->assertEquals(10, $result['meta_data'][0]['id']);
        $this->assertEquals('TEST', $result['meta_data'][0]['value']);
        $this->assertEquals(5, $result['categories'][0]['id']);
    }

    public function testHandlesEmptyResponse(): void
    {
        $this->mockWcClient->method('get')->willReturn((object)[]);

        $result = $this->restClient->get('products/0');
        $this->assertEmpty($result);
    }

    public function testHandlesScalarResponse(): void
    {
        $this->mockWcClient->method('get')
            ->with('system_status')
            ->willReturn((object)['status' => 'ok']);

        $result = $this->restClient->get('system_status');
        $this->assertEquals('ok', $result['status']);
    }
}
