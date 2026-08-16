<?php
/**
 * @deprecated 
 * This file contains legacy code that has been superseded by the
 * refactored module at src/Ksfraser/FrontAccounting/Woocommerce/
 * 
 * The new implementation provides:
 * - TDD with 65+ passing tests
 * - PSR-4 autoloading
 * - SOLID principles
 * - FA hooks integration
 * - Customer staging with matching
 * - Order staging (when customer not matched)
 * 
 * DO NOT USE - Use the new module instead.
 */



/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_payment_details extends woo_interface {
	/********READ***********/
	var $method_id;		//!< @var string Payment method ID.
	var $method_title;	//!< @var string Payment method title.
	var $paid;		//!< @var bool
	/********WRITE***********/
	var $payment_method;	//	string 	
	var $payment_method_title;	//	string 	
 	var $set_paid;		//!< @var boolean 	Define if the order is paid. 	write-only
							//It will set the status to processing and reduce stock items. Default is false.  
	var $transaction_id;	//	string 	Unique transaction ID. In write-mode only, is available if set_paid is true.

/*
	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		return;
	}
	function define_table()
	{
	}
 */
}




/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_payment_details extends woo_interface {
	/********READ***********/
	var $method_id;		//!< @var string Payment method ID.
	var $method_title;	//!< @var string Payment method title.
	var $paid;		//!< @var bool
	/********WRITE***********/
	var $payment_method;	//	string 	
	var $payment_method_title;	//	string 	
 	var $set_paid;		//!< @var boolean 	Define if the order is paid. 	write-only
							//It will set the status to processing and reduce stock items. Default is false.  
	var $transaction_id;	//	string 	Unique transaction ID. In write-mode only, is available if set_paid is true.

/*
	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		return;
	}
	function define_table()
	{
	}
 */
}

?>
