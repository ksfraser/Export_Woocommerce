<?php
/**
 * WooCommerce Sync Hooks for FrontAccounting
 * 
 * Provides integration hooks for the WooCommerce sync module.
 * 
 * @since 1.0.0
 */

// Ensure hooks class exists
if (!class_exists('hooks')) {
    class hooks {}
}

// Security constants
if (!defined('SS_WOOCOMMERCE_SYNC')) {
    define('SS_WOOCOMMERCE_SYNC', 116 << 8);  // Stock area section
}

if (!defined('SA_WOOCOMMERCE_SYNC')) {
    define('SA_WOOCOMMERCE_SYNC', SS_WOOCOMMERCE_SYNC | 1);
}

if (!defined('SA_WOOCOMMERCE_IMPORT')) {
    define('SA_WOOCOMMERCE_IMPORT', SS_WOOCOMMERCE_SYNC | 2);
}

if (!defined('SA_WOOCOMMERCE_EXPORT')) {
    define('SA_WOOCOMMERCE_EXPORT', SS_WOOCOMMERCE_SYNC | 4);
}

if (!defined('SA_WOOCOMMERCE_STAGING')) {
    define('SA_WOOCOMMERCE_STAGING', SS_WOOCOMMERCE_SYNC | 8);
}

/**
 * WooCommerce Sync Hooks Class
 */
class hooks_ksf_FA_Woocommerce extends hooks
{
    /** @var string Module name */
    var $module_name = 'ksf_FA_Woocommerce';
    
    /** @var string Module path */
    var $module_path;

    public function __construct()
    {
        $this->module_name = 'ksf_FA_Woocommerce';
        $this->module_path = apply_filters('ksf_FA_Woocommerce_module_path', dirname(__FILE__));
    }

    public function module_path()
    {
        return $this->module_path;
    }
    
    /**
     * Load Composer autoloader if exists
     */
    protected function load_autoloader(): void
    {
        $autoloadPath = $this->module_path() . '/vendor/autoload.php';
        
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }
        // If autoload.php doesn't exist, we silently continue.
        // The Composer autoloader should be available in the main FA autoloader.
    }

    /**
     * Module installation check
     */
    public function install(): bool
    {
        return true;
    }

    /**
     * Add menu items under Stock menu
     */
    public function install_options($app): void
    {
        global $path_to_root;

        switch ($app->id) {
            case 'stock':
                $app->add_rapp_function(
                    1,
                    _('WooCommerce Sync'),
                    $path_to_root . '/modules/' . $this->module_name . '/public/index.php',
                    'SA_WOOCOMMERCE_SYNC'
                );
                break;
            case 'orders':
                $app->add_rapp_function(
                    1,
                    _('Import Woo Orders'),
                    $path_to_root . '/modules/' . $this->module_name . '/admin/import_orders.php',
                    'SA_WOOCOMMERCE_IMPORT'
                );
                break;
            case 'customers':
                $app->add_rapp_function(
                    1,
                    _('Import Woo Customers'),
                    $path_to_root . '/modules/' . $this->module_name . '/admin/import_customers.php',
                    'SA_WOOCOMMERCE_IMPORT'
                );
                break;
        }
    }

    /**
     * Define security areas and sections
     */
    public function install_access(): array
    {
        $security_areas = array(
            'SA_WOOCOMMERCE_SYNC' => array(
                SS_WOOCOMMERCE_SYNC | 1,
                _('WooCommerce Sync')
            ),
            'SA_WOOCOMMERCE_IMPORT' => array(
                SS_WOOCOMMERCE_SYNC | 2,
                _('Import WooCommerce Data')
            ),
            'SA_WOOCOMMERCE_EXPORT' => array(
                SS_WOOCOMMERCE_SYNC | 4,
                _('Export to WooCommerce')
            ),
            'SA_WOOCOMMERCE_STAGING' => array(
                SS_WOOCOMMERCE_SYNC | 8,
                _('Review Staging')
            ),
        );

        $security_sections = array(
            SS_WOOCOMMERCE_SYNC => _('WooCommerce Sync'),
        );

        return array($security_areas, $security_sections);
    }

    /**
     * Module activation
     */
    public function activate(): bool
    {
        $this->register_hooks();
        
        // Ensure staging table exists
        $this->ensure_schema();
        
        return true;
    }
    
    /**
     * Register extension hooks
     */
    public function activate_extension($company, $check_only = true): bool
    {
        if (!$check_only) {
            $this->register_hooks();
        }
        return true;
    }

    /**
     * Module deactivation
     */
    public function deactivate(): bool
    {
        // Clear caches
        unset($GLOBALS['woo_sync_services_cache']);
        unset($GLOBALS['woo_sync_config_cache']);
        
        return true;
    }

    /**
     * Register module hooks
     */
    private function register_hooks(): void
    {
        // Add any hook points here for extending other modules
    }

    /**
     * Stock item lifecycle listeners
     *
     * These methods are invoked by ksf_FA_Common's shared ItemEventPublisher
     * via hook_invoke_all('item_created' / 'item_updated', $data). Payload:
     *   ['stock_id' => string, 'event' => 'created'|'updated', 'trigger' => string, ...]
     *
     * @param array &$data Event payload
     * @param array|null $opts Options
     * @return mixed
     */
    public function item_created(&$data, $opts = null) {
        $this->handleItemEvent($data, 'created');
        return null;
    }

    public function item_updated(&$data, $opts = null) {
        $this->handleItemEvent($data, 'updated');
        return null;
    }

    /**
     * Handle an item lifecycle event by pushing the item to WooCommerce.
     *
     * @param array $data Event payload
     * @param string $event 'created' or 'updated'
     * @return void
     */
    private function handleItemEvent($data, $event) {
        if (!isset($data['stock_id']) || $data['stock_id'] === '') {
            return;
        }
        if (!function_exists('user_company')) {
            return;
        }
        if (empty($GLOBALS['db_connections'])) {
            return;
        }
        $listener = $this->buildItemEventListener();
        if ($listener === null) {
            return;
        }
        $stockId = (string) $data['stock_id'];
        try {
            $result = $listener->sync($stockId, $event);
            if ($result['status'] === 'failed') {
                error_log('ksf_FA_Woocommerce: item_' . $event . ' sync failed for ' . $stockId . ': ' . $result['reason']);
            }
        } catch (\Throwable $e) {
            error_log('ksf_FA_Woocommerce: item_' . $event . ' sync error for ' . $stockId . ': ' . $e->getMessage());
        }
    }

    /**
     * Build the item event listener bound to the current FA company.
     *
     * @return \Ksfraser\frontaccounting\Woocommerce\ItemEventListener|null
     */
    private function buildItemEventListener() {
        $autoload = $this->module_path . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        if (!class_exists('\Ksfraser\frontaccounting\Woocommerce\ItemEventListener')) {
            return null;
        }
        try {
            $services = $this->get_services();
            return new \Ksfraser\frontaccounting\Woocommerce\ItemEventListener(
                new \Ksfraser\frontaccounting\Woocommerce\Dao\StockItemDao($services['db']),
                $services['logger'],
                $services['productExporter']
            );
        } catch (\Throwable $e) {
            error_log('ksf_FA_Woocommerce: failed to build item event listener: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Ensure database schema is created
     * 
     * Staging tables are now managed by ksf_FA_ImportStagingProcessing (generic module).
     * Local fallback tables are created on-demand by OrderStaging/CustomerStaging.
     */
    private function ensure_schema(): void
    {
    }
    
    /**
     * Get WooCommerce configuration from FA company preferences
     */
    public function get_woo_config(): array
    {
        if (isset($GLOBALS['woo_sync_config_cache'])) {
            return $GLOBALS['woo_sync_config_cache'];
        }
        
        $config = array(
            'wc_url' => get_company_pref('woocommerce_url') ?: '',
            'wc_key' => get_company_pref('woocommerce_key') ?: '',
            'wc_secret' => get_company_pref('woocommerce_secret') ?: '',
        );
        
        $GLOBALS['woo_sync_config_cache'] = $config;
        return $config;
    }
    
    /**
     * Add preferences to company setup
     */
    public function preferences($selected = false): array
    {
        return array(
            'woocommerce_url' => array(
                'WooCommerce URL',
                'text',
                null,
                '',
                $selected == 'woocommerce_url'
            ),
            'woocommerce_key' => array(
                'WooCommerce API Key',
                'text',
                null,
                '',
                $selected == 'woocommerce_key'
            ),
            'woocommerce_secret' => array(
                'WooCommerce API Secret',
                'text',
                null,
                '',
                $selected == 'woocommerce_secret'
            ),
        );
    }
    
    /**
     * Helper to get service instances (cached)
     */
    public function get_services(): array
    {
        if (isset($GLOBALS['woo_sync_services_cache'])) {
            return $GLOBALS['woo_sync_services_cache'];
        }
        
        global $db_connections;
        $company = user_company();
        
        // Get WooCommerce config
        $config = $this->get_woo_config();
        
        // Create services
        $logger = new \Ksfraser\Frontaccounting\Woocommerce\FileLogger(
            $this->module_path . '/logs/sync.log'
        );
        
        $wooClient = new \Automattic\WooCommerce\Client(
            $config['wc_url'],
            $config['wc_key'],
            $config['wc_secret']
        );
        $restClient = new \Ksfraser\frontaccounting\Woocommerce\WooRestClient($wooClient, $logger);
        
        $dbInterface = new \Ksfraser\frontaccounting\Woocommerce\MysqliDatabase(
            $db_connections[$company]['host'],
            $db_connections[$company]['username'],
            $db_connections[$company]['password'],
            $db_connections[$company]['dbname'],
            $db_connections[$company]['tbpref']
        );
        
        $productExporter = new \Ksfraser\Frontaccounting\Woocommerce\ProductExportService(
            $restClient, $logger, $dbInterface
        );
        $orderExporter = new \Ksfraser\Frontaccounting\Woocommerce\OrderExporter(
            $restClient, $logger, $dbInterface
        );
        $customerExporter = new \Ksfraser\Frontaccounting\Woocommerce\CustomerExporter(
            $restClient, $logger, $dbInterface
        );
        $categoryExporter = new \Ksfraser\Frontaccounting\Woocommerce\CategoryExporter(
            $restClient, $logger, $dbInterface
        );
        $customerStaging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging(
            $dbInterface, $logger
        );
        
        $dispatcher = new \Ksfraser\Frontaccounting\Woocommerce\UI\ImportExportDispatcher(
            $productExporter,
            $orderExporter,
            $customerExporter,
            $categoryExporter,
            $customerStaging,
            $logger
        );
        
        $GLOBALS['woo_sync_services_cache'] = array(
            'productExporter' => $productExporter,
            'orderExporter' => $orderExporter,
            'customerExporter' => $customerExporter,
            'categoryExporter' => $categoryExporter,
            'customerStaging' => $customerStaging,
            'dispatcher' => $dispatcher,
            'logger' => $logger,
            'db' => $dbInterface,
        );
        
        return $GLOBALS['woo_sync_services_cache'];
    }
}