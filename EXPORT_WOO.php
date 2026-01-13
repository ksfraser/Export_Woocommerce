<?php
/**********************************************
Name: COAST purchase order export
modified for COAST 1.5.1 and FrontAccounting 2.3.15 by kfraser 
Free software under GNU GPL
***********************************************/

$page_security = 'SA_EXPORTWOO';
$path_to_root="../..";

include($path_to_root . "/includes/session.inc");
add_access_extensions();
set_ext_domain('modules/EXPORT_WOO');

include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/modules/EXPORT_WOO/class.EXPORT_WOO.php");
include_once($path_to_root . "/modules/ksf_modules_common/class.eventloop.php");

error_reporting(E_ALL);
ini_set("display_errors", "on");

global $db; // Allow access to the FA database connection
$debug_sql = 0;  // Change to 1 for debug messages
$prefsDB = "EXPORT_WOO_prefs";


if( isset( $_POST['edit_form'] ) )
{
	//AJAX call
	$cl = $_POST['my_class'];	//set in woo_interface
	require_once( 'class.' . $cl . '.php' );
	$mycl = new $cl( null, null, null, null, null );
	$mycl->form_post_handler();
	$_GET['action'] = $_POST['action'] = $_POST['return_to'];
	unset( $_POST );
	header("Status: 301 Moved Permanently");
	header("Location: " . $_SERVER['REQUEST_URI'] . ($_GET ? "?" . http_build_query( $_GET ) : "" ) );
	

}
else
{
	$eventloop = new eventloop( "." );
	$coastc = new EXPORT_WOO( "defiant.ksfraser.com", "fhs", "fhs", "fhs", "EXPORT_WOO_prefs" );
	$found = $coastc->is_installed();
	$coastc->set_var( 'found', $found );
	$coastc->set_var( 'help_context', "Export to Woo Commerce Interface" );
	$coastc->set_var( 'redirect_to', "EXPORT_WOO.php" );
	$coastc->run();
}

?>
