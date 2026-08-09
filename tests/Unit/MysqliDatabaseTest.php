<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\MysqliDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Fake mysqli result returned by the fake connection.
 */
class FakeMysqliResult
{
    private array $rows;
    private int $pos = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetch_assoc()
    {
        if ($this->pos >= count($this->rows)) {
            return null;
        }
        return $this->rows[$this->pos++];
    }
}

/**
 * Fake mysqli connection used to unit test MysqliDatabase without a DB server.
 */
class FakeMysqliConnection
{
    private array $rows;
    public $lastQuery;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
        $this->lastQuery = true;
    }

    public function query($sql)
    {
        $this->lastQuery = $sql;
        if (empty($this->rows)) {
            return false;
        }
        return new FakeMysqliResult($this->rows);
    }

    public function real_escape_string($value)
    {
        return addslashes($value);
    }
}

/**
 * @BABOK Related: WooCommerce sync database access
 */
class MysqliDatabaseTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $db = new MysqliDatabase('localhost', 'user', 'pass', 'fa', '0_', new FakeMysqliConnection());
        $this->assertInstanceOf(MysqliDatabase::class, $db);
    }

    public function testImplementsDatabaseInterface(): void
    {
        $db = new MysqliDatabase('localhost', 'user', 'pass', 'fa', '0_', new FakeMysqliConnection());
        $this->assertInstanceOf(DatabaseInterface::class, $db);
    }

    public function testGetPrefix(): void
    {
        $db = new MysqliDatabase('localhost', 'user', 'pass', 'fa', '1_', new FakeMysqliConnection());
        $this->assertSame('1_', $db->getPrefix());
    }

    public function testQueryReturnsRows(): void
    {
        $connection = new FakeMysqliConnection([
            ['stock_id' => 'SH-001', 'description' => 'Widget'],
            ['stock_id' => 'SH-002', 'description' => 'Gadget'],
        ]);
        $db = new MysqliDatabase('localhost', 'user', 'pass', 'fa', '0_', $connection);

        $rows = $db->query('SELECT * FROM 0_stock_master');

        $this->assertCount(2, $rows);
        $this->assertEquals('SH-001', $rows[0]['stock_id']);
        $this->assertSame('SELECT * FROM 0_stock_master', $connection->lastQuery);
    }

    public function testQueryReturnsEmptyArrayWhenNoRows(): void
    {
        $connection = new FakeMysqliConnection();
        $db = new MysqliDatabase('localhost', 'user', 'pass', 'fa', '0_', $connection);

        $rows = $db->query('SELECT * FROM 0_stock_master');

        $this->assertIsArray($rows);
        $this->assertCount(0, $rows);
    }

    public function testExecuteReturnsTrueOnSuccess(): void
    {
        $connection = new FakeMysqliConnection([['ok' => 1]]);
        $db = new MysqliDatabase('localhost', 'user', 'pass', 'fa', '0_', $connection);

        $this->assertTrue($db->execute('INSERT INTO 0_woo_product_map VALUES (1)'));
    }

    public function testEscapeDelegatesToConnection(): void
    {
        $connection = new FakeMysqliConnection();
        $db = new MysqliDatabase('localhost', 'user', 'pass', 'fa', '0_', $connection);

        $this->assertSame('O\\\'Brien', $db->escape("O'Brien"));
    }
}
