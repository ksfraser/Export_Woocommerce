<?php
declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for hooks.php - FrontAccounting integration
 */
class HooksTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset global state between tests
        if (isset($GLOBALS['woo_sync_config_cache'])) {
            unset($GLOBALS['woo_sync_config_cache']);
        }
        if (isset($GLOBALS['woo_sync_services_cache'])) {
            unset($GLOBALS['woo_sync_services_cache']);
        }
    }

    public function testHooksClassExists(): void
    {
        $this->assertTrue(class_exists('hooks'));
    }

    public function testHooksWoocommerceSyncClassExists(): void
    {
        $this->assertTrue(class_exists('hooks_woocommerce_sync'));
    }

    public function testHooksWoocommerceSyncHasRequiredProperties(): void
    {
        $hook = new \hooks_woocommerce_sync();
        $this->assertObjectHasProperty('module_name', $hook);
        $this->assertObjectHasProperty('module_path', $hook);
        $this->assertEquals('woocommerce_sync', $hook->module_name);
    }

    public function testSecurityConstantsAreDefined(): void
    {
        // These should be defined by hooks.php
        $this->assertTrue(defined('SS_WOOCOMMERCE_SYNC'));
        $this->assertTrue(defined('SA_WOOCOMMERCE_SYNC'));
        $this->assertTrue(defined('SA_WOOCOMMERCE_IMPORT'));
        $this->assertTrue(defined('SA_WOOCOMMERCE_EXPORT'));
        $this->assertTrue(defined('SA_WOOCOMMERCE_STAGING'));
        
        // Check values match expectations from hooks.php
        // SS_WOOCOMMERCE_SYNC = 116 << 8 = 29696
        $this->assertEquals(116 << 8, SS_WOOCOMMERCE_SYNC);
        
        // SA_WOOCOMMERCE_SYNC = SS_WOOCOMMERCE_SYNC | 1
        $this->assertEquals(SS_WOOCOMMERCE_SYNC | 1, SA_WOOCOMMERCE_SYNC);
        
        // SA_WOOCOMMERCE_IMPORT = SS_WOOCOMMERCE_SYNC | 2
        $this->assertEquals(SS_WOOCOMMERCE_SYNC | 2, SA_WOOCOMMERCE_IMPORT);
        
        // SA_WOOCOMMERCE_EXPORT = SS_WOOCOMMERCE_SYNC | 4
        $this->assertEquals(SS_WOOCOMMERCE_SYNC | 4, SA_WOOCOMMERCE_EXPORT);
        
        // SA_WOOCOMMERCE_STAGING = SS_WOOCOMMERCE_SYNC | 8
        $this->assertEquals(SS_WOOCOMMERCE_SYNC | 8, SA_WOOCOMMERCE_STAGING);
    }

    public function testGetWooConfigReturnsArrayWithExpectedKeys(): void
    {
        // Test that when cache is set, it returns the cache
        $GLOBALS['woo_sync_config_cache'] = ['wc_url' => 'cached_url', 'wc_key' => 'cached_key', 'wc_secret' => 'cached_secret'];
        $hook = new \hooks_woocommerce_sync();
        $config = $hook->get_woo_config();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('wc_url', $config);
        $this->assertArrayHasKey('wc_key', $config);
        $this->assertArrayHasKey('wc_secret', $config);
        $this->assertEquals('cached_url', $config['wc_url']);
        $this->assertEquals('cached_key', $config['wc_key']);
        $this->assertEquals('cached_secret', $config['wc_secret']);

        // Test that when cache is not set, it returns an array with the expected keys
        unset($GLOBALS['woo_sync_config_cache']);
        $config2 = $hook->get_woo_config();
        $this->assertIsArray($config2);
        $this->assertArrayHasKey('wc_url', $config2);
        $this->assertArrayHasKey('wc_key', $config2);
        $this->assertArrayHasKey('wc_secret', $config2);
        // We don't check the values because they depend on get_company_pref which we haven't mocked
    }

    public function testPreferencesMethodReturnsExpectedArray(): void
    {
        $hook = new \hooks_woocommerce_sync();
        $prefs = $hook->preferences(false);
        
        $this->assertIsArray($prefs);
        $this->assertArrayHasKey('woocommerce_url', $prefs);
        $this->assertArrayHasKey('woocommerce_key', $prefs);
        $this->assertArrayHasKey('woocommerce_secret', $prefs);
        
        // Check structure of each preference
        foreach (['woocommerce_url', 'woocommerce_key', 'woocommerce_secret'] as $key) {
            $this->assertArrayHasKey(0, $prefs[$key]); // label
            $this->assertArrayHasKey(1, $prefs[$key]); // type
            $this->assertArrayHasKey(2, $prefs[$key]); // placeholder
            $this->assertArrayHasKey(3, $prefs[$key]); // default
            $this->assertArrayHasKey(4, $prefs[$key]); // selected
        }
    }

    public function testInstallReturnsTrue(): void
    {
        $hook = new \hooks_woocommerce_sync();
        $this->assertTrue($hook->install());
    }

    public function testActivateReturnsTrue(): void
    {
        // We don't mock user_company because it's already defined in bootstrap to return 0.
        // We'll set up $db_connections for company 0.
        global $db_connections;
        $db_connections = [
            0 => [
                'tbpref' => '0_'
            ]
        ];

        // Mock db_query and db_num_rows to simulate that the staging table exists.
        $originalDbQuery = null;
        $originalDbNumRows = null;
        
        if (function_exists('db_query')) {
            $originalDbQuery = 'db_query';
        }
        if (function_exists('db_num_rows')) {
            $originalDbNumRows = 'db_num_rows';
        }
        
        // Define mock functions
        function db_query($sql, $msg = '') {
            // Return a mock result object
            static $mockResult = null;
            if ($mockResult === null) {
                $mockResult = new class {
                    public $num_rows;
                    public function __construct($rows = 0) {
                        $this->num_rows = $rows;
                    }
                };
            }
            // For SHOW TABLES query, return 1 row (table exists)
            if (strpos($sql, 'SHOW TABLES') !== false) {
                $mockResult->num_rows = 1;
            } else {
                $mockResult->num_rows = 0;
            }
            return $mockResult;
        }
        
        function db_num_rows($result) {
            return $result->num_rows;
        }
        
        try {
            // Create a hook instance and directly test that activate returns true
            $hook = new \hooks_woocommerce_sync();
            
            // Activate should return true
            $this->assertTrue($hook->activate());
        } finally {
            // Restore original functions if they existed
            if ($originalDbQuery === null && function_exists('db_query')) {
                unset($GLOBALS['db_query']);
            }
            if ($originalDbNumRows === null && function_exists('db_num_rows')) {
                unset($GLOBALS['db_num_rows']);
            }
        }
    }

    public function testDeactivateReturnsTrue(): void
    {
        $hook = new \hooks_woocommerce_sync();
        $this->assertTrue($hook->deactivate());
    }

    public function testActivateExtensionReturnsTrue(): void
    {
        $hook = new \hooks_woocommerce_sync();
        $this->assertTrue($hook->activate_extension('test_company', false));
        $this->assertTrue($hook->activate_extension('test_company', true)); // check_only = true
    }

    public function testLoadAutoloaderDoesNotThrowExceptionWhenFileMissing(): void
    {
        // Since load_autoloader is now protected, we can test it using reflection
        $hook = new \hooks_woocommerce_sync();
        
        // Mock the module_path method to return a non-existent path
        $reflection = new \ReflectionObject($hook);
        $method = $reflection->getMethod('module_path');
        $method->setAccessible(true);
        $method->invoke($hook, function() { return '/non/existent/path'; });
        
        // This should not throw an exception even if autoload.php doesn't exist
        $this->expectNotToPerformAssertions();
        
        // Now call the protected load_autoloader method via reflection
        $loadMethod = $reflection->getMethod('load_autoloader');
        $loadMethod->setAccessible(true);
        $loadMethod->invoke($hook);
    }
}