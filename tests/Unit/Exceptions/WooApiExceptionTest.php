<?php

namespace Ksfraser\Tests\frontaccounting\Woocommerce\Exceptions;

use Ksfraser\frontaccounting\Woocommerce\Exceptions\WooApiException;
use PHPUnit\Framework\TestCase;

class WooApiExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $e = new WooApiException();
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testCanSetMessage(): void
    {
        $e = new WooApiException('API error occurred');
        $this->assertSame('API error occurred', $e->getMessage());
    }

    public function testCanSetCode(): void
    {
        $e = new WooApiException('Not found', 404);
        $this->assertSame(404, $e->getCode());
    }

    public function testCanSetPrevious(): void
    {
        $previous = new \Exception('Previous error');
        $e = new WooApiException('Wrapper error', 0, $previous);
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $caught = false;
        try {
            throw new WooApiException('Test exception');
        } catch (WooApiException $e) {
            $caught = true;
            $this->assertSame('Test exception', $e->getMessage());
        }
        $this->assertTrue($caught);
    }

    public function testDefaultMessageIsEmpty(): void
    {
        $e = new WooApiException();
        $this->assertSame('', $e->getMessage());
    }

    public function testDefaultCodeIsZero(): void
    {
        $e = new WooApiException();
        $this->assertSame(0, $e->getCode());
    }
}
