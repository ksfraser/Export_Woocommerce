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

require_once( 'class.woo_billing.php' );

class woo_shipping extends woo_billing {
	function define_table()
	{
		parent::define_table();
		$this->fields_array[0] = array('name' => 'shipping_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->table_details['tablename'] = $this->company_prefix . "woo_shipping";
		$this->table_details['primarykey'] = "shipping_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "first_name,last_name,address_1,city,state";
		$this->table_details['index'][0]['keyname'] = "shipping_customer";
	}
}

?>


/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_billing.php' );

class woo_shipping extends woo_billing {
	function define_table()
	{
		parent::define_table();
		$this->fields_array[0] = array('name' => 'shipping_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->table_details['tablename'] = $this->company_prefix . "woo_shipping";
		$this->table_details['primarykey'] = "shipping_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "first_name,last_name,address_1,city,state";
		$this->table_details['index'][0]['keyname'] = "shipping_customer";
	}
}

?>
