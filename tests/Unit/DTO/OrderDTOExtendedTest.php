<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\DTO;

use Ksfraser\frontaccounting\Woocommerce\DTO\OrderDTO;
use PHPUnit\Framework\TestCase;

class OrderDTOExtendedTest extends TestCase
{
    public function testIsProcessedReturnsTrue(): void
    {
        $dto = new OrderDTO([
            'id' => 1,
            'fa_debtor_no' => 10,
        ]);
        $this->assertTrue($dto->isProcessed());
    }

    public function testIsProcessedReturnsFalse(): void
    {
        $dto = new OrderDTO([
            'id' => 1,
        ]);
        $this->assertFalse($dto->isProcessed());
    }
}
