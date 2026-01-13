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
