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
