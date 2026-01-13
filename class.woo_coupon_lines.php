<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_coupon_lines extends woo_interface {

	var $woo_coupon_lines_id;
	var $order_id;
	/*******************Coupon Lines*********************************/
	var $id;
	var $code;
	var $discount;
	var $discount_tax;
	/*******************Coupon Lines*********************************/
		
	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		if( isset( $client->id ) )
			$this->order_id = $client->id;		
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'woo_coupon_lines_id', 	'type' => 'int(11)', 		'comment' => 'Index.', 'readwrite' => 'read', 'auto_increment' => 'anything');
		$this->fields_array[] = array('name' => 'updated_ts', 		'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'order_id', 	'type' => 'int(11)', 	'comment' => 'Order ID this is linked to. Genned by module', 'readwrite' => 'read' );

		$this->fields_array[] = array('name' => 'id', 			'type' => 'int(11)', 		'comment' => ' 	Item ID', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'code', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax rate code.', 'readwrite' => 'read');

		$this->fields_array[] = array('name' => 'discount', 		'type' => 'varchar(64)', 	'comment' => 'Discount Total', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'discount_tax', 	'type' => 'varchar(64)', 	'comment' => 'Discount tax total. ', 'readwrite' => 'write');
		$this->table_details['tablename'] = $this->company_prefix . "woo_coupon_lines";
		$this->table_details['primarykey'] = "woo_coupon_lines_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "order_id,code";
		$this->table_details['index'][0]['keyname'] = "order_id-code";
	}
}


?>
