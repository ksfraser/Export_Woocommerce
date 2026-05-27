<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\DTO;

use Ksfraser\frontaccounting\Woocommerce\DTO\CustomerDTO;
use PHPUnit\Framework\TestCase;

class CustomerDTOExtendedTest extends TestCase
{
    public function testGetNormalizedPhoneWithPhone(): void
    {
        $dto = new CustomerDTO(['billing' => ['phone' => '555-123-4567']]);
        $this->assertEquals('5551234567', $dto->getNormalizedPhone());
    }

    public function testGetNormalizedPhoneWithoutPhone(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertNull($dto->getNormalizedPhone());
    }

    public function testGetDisplayNameWithCompany(): void
    {
        $dto = new CustomerDTO(['billing' => ['company' => 'ACME Corp', 'first_name' => 'John', 'last_name' => 'Doe']]);
        $this->assertEquals('ACME Corp', $dto->getDisplayName());
    }

    public function testGetDisplayNameWithoutCompany(): void
    {
        $dto = new CustomerDTO(['billing' => ['first_name' => 'John', 'last_name' => 'Doe']]);
        $this->assertEquals('John Doe', $dto->getDisplayName());
    }

    public function testIsImportedReturnsTrue(): void
    {
        $dto = new CustomerDTO(['fa_debtor_no' => 10]);
        $this->assertTrue($dto->isImported());
    }

    public function testIsImportedReturnsFalse(): void
    {
        $dto = new CustomerDTO([]);
        $this->assertFalse($dto->isImported());
    }
}
