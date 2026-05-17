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
class hooks_woocommerce_sync extends hooks
{
    /** @var string Module name */
    var $module_name = 'woocommerce_sync';
    
    /** @var string Module path */
    var $module_path;

    public function __construct()
    {
        global $path_to_root;
        
        $this->module_path = $path_to_root . '/modules/' . $this->module_name;
        
        // Load autoloader if available
        $this->load_autoloader();
    }
    
    /**
     * Load Composer autoloader if exists
     */
    private function load_autoloader(): void
    {
        $autoload = $this->module_path . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
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
     * Ensure database schema is created
     */
    private function ensure_schema(): void
    {
        global $db_connections;
        
        $company = user_company();
        $table_prefix = $db_connections[$company]['tbpref'];
        
        // Check if staging table exists
        $sql = "SHOW TABLES LIKE '{$table_prefix}woo_customer_staging'";
        $result = db_query($sql);
        
        if (db_num_rows($result) == 0) {
            // Create staging table
            $create_sql = "
                CREATE TABLE IF NOT EXISTS {$table_prefix}woo_customer_staging (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    woo_customer_id INT,
                    woo_order_id INT,
                    email VARCHAR(255),
                    phone VARCHAR(50),
                    first_name VARCHAR(100),
                    last_name VARCHAR(100),
                    company VARCHAR(255),
                    address1 VARCHAR(255),
                    address2 VARCHAR(255),
                    city VARCHAR(100),
                    state VARCHAR(100),
                    postcode VARCHAR(20),
                    country VARCHAR(100),
                    raw_data TEXT,
                    imported TINYINT DEFAULT 0,
                    imported_at DATETIME NULL,
                    fa_debtor_no INT NULL,
                    fa_branch_ref VARCHAR(100) NULL,
                    staged_at DATETIME,
                    INDEX idx_email (email),
                    INDEX idx_woo_customer (woo_customer_id),
                    INDEX idx_imported (imported)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            ";
            db_query($create_sql);
        }
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
        
        // Get DB connection
        $db = new mysqli(
            $db_connections[$company]['host'],
            $db_connections[$company]['username'],
            $db_connections[$company]['password'],
            $db_connections[$company]['dbname']
        );
        
        $table_prefix = $db_connections[$company]['tbpref'];
        
        // Get WooCommerce config
        $config = $this->get_woo_config();
        
        // Create services
        $restClient = new \Ksfraser\Frontaccounting\Woocommerce\WooRestClient(
            $config['wc_url'],
            $config['wc_key'],
            $config['wc_secret']
        );
        
        $logger = new \Ksfraser\Frontaccounting\Woocommerce\FileLogger(
            $this->module_path . '/logs/sync.log'
        );
        
        $dbInterface = new class($db, $table_prefix) implements \Ksfraser\Frontaccounting\Woocommerce\DatabaseInterface {
            private $db;
            private $prefix;
            
            public function __construct($db, $prefix) {
                $this->db = $db;
                $this->prefix = $prefix;
            }
            
            public function query(string $sql): array {
                $result = $this->db->query($sql);
                if (!$result) return [];
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                return $rows;
            }
            
            public function execute(string $sql): bool {
                return $this->db->query($sql);
            }
            
            public function getPrefix(): string {
                return $this->prefix;
            }
            
            public function escape(string $value): string {
                return $this->db->real_escape_string($value);
            }
        };
        
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
            $customerStaging
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