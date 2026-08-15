<?php
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit;

use ksfraser\FrontAccounting\Woocommerce\FileLogger;
use PHPUnit\Framework\TestCase;

/**
 * @BABOK Related: WooCommerce sync logging
 */
class FileLoggerTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/woo_file_logger_' . uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            @unlink($this->logPath);
        }
    }

    public function testCanBeInstantiated(): void
    {
        $logger = new FileLogger($this->logPath);
        $this->assertInstanceOf(FileLogger::class, $logger);
    }

    public function testInfoWritesToFile(): void
    {
        $logger = new FileLogger($this->logPath);

        $logger->info('Product exported');

        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('Product exported', $content);
    }

    public function testWarningWritesToFile(): void
    {
        $logger = new FileLogger($this->logPath);

        $logger->warning('Low stock');

        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('Low stock', $content);
    }

    public function testErrorWritesToFile(): void
    {
        $logger = new FileLogger($this->logPath);

        $logger->error('API failure');

        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('API failure', $content);
    }

    public function testAppendsMultipleEntries(): void
    {
        $logger = new FileLogger($this->logPath);

        $logger->info('First');
        $logger->info('Second');

        $content = file_get_contents($this->logPath);
        $this->assertSame(2, substr_count($content, 'INFO'));
        $this->assertStringContainsString('First', $content);
        $this->assertStringContainsString('Second', $content);
    }

    public function testCreatesMissingDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/woo_logger_' . uniqid('', true);
        $logger = new FileLogger($dir . '/nested/sync.log');

        $logger->info('Started');

        $this->assertFileExists($dir . '/nested/sync.log');
        @unlink($dir . '/nested/sync.log');
        @rmdir($dir . '/nested');
        @rmdir($dir);
    }
}
