<?php<?php
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

<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_billing_address.php' );

class woo_shipping_address extends woo_billing_address {
	function define_table()
	{
		parent::define_table();
		$this->fields_array[0] = array('name' => 'shipping_address_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->table_details['tablename'] = $this->company_prefix . "woo_shipping_address";
		$this->table_details['primarykey'] = "shipping_address_id";
		$this->table_details['index'][0]['keyname'] = "order-shipping_address_customer";
		$this->table_details['index'][1]['keyname'] = "customer-shipping_address_customer";
	}
}

?>


/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_billing_address.php' );

class woo_shipping_address extends woo_billing_address {
	function define_table()
	{
		parent::define_table();
		$this->fields_array[0] = array('name' => 'shipping_address_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->table_details['tablename'] = $this->company_prefix . "woo_shipping_address";
		$this->table_details['primarykey'] = "shipping_address_id";
		$this->table_details['index'][0]['keyname'] = "order-shipping_address_customer";
		$this->table_details['index'][1]['keyname'] = "customer-shipping_address_customer";
	}
}

?>
