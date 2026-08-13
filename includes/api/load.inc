<?php
/**
 * WooCommerce Module FA Bootstrap
 *
 * Standard FrontAccounting bootstrap shared by module UI pages.
 * Computes the FA root from the deployed location so pages work
 * regardless of the requesting page's directory.
 *
 * @since 1.0.5
 */

$path_to_root = dirname(__DIR__, 4);

include_once($path_to_root . "/includes/session.inc");
add_access_extensions();
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/data_checks.inc");

// Load this module's composer autoloader so namespaced service classes
// (FileLogger, MysqliDatabase, exporters, dispatcher, ...) resolve.
// FA does not provide a global autoloader for extension classes.
$woo_autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($woo_autoload)) {
    require_once $woo_autoload;
}

/**
 * Return the WooCommerce module hooks instance.
 *
 * The hooks class is loaded by FA for every active extension during
 * session startup, so instantiating it here reuses its service builder.
 *
 * @return \hooks_ksf_FA_Woocommerce
 * @since 1.0.6
 */
function woo_module_hooks()
{
    static $instance = null;
    if ($instance === null) {
        $instance = new \hooks_ksf_FA_Woocommerce();
    }
    return $instance;
}

/**
 * Return the shared WooCommerce service instances (cached).
 *
 * @return array
 * @since 1.0.6
 */
function woo_services()
{
    return woo_module_hooks()->get_services();
}
