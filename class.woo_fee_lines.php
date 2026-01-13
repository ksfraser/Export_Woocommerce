<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_fee_lines extends woo_interface {
	var $fee_lines_id;
	var $order_id;
	/*********************Fee Lines********************************/
	var $id;
	var $title;
	var $tax_class;
	var $tax_status;
	var $total;
	var $total_tax;
	var $taxes;
	/*********************Fee Lines********************************/

	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		if( isset( $client->id ) )
			$this->order_id = $client->id;		
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'fee_lines_id', 'type' => 'int(11)', 'comment' => 'Index.', 'readwrite' => 'read', 'auto_increment' => 'anything');
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'order_id', 	'type' => 'int(11)', 	'comment' => 'Order ID this is linked to. Genned by module', 'readwrite' => 'read' );

		$this->fields_array[] = array('name' => 'id', 			'type' => 'int(11)', 		'comment' => ' 	Item ID', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'title', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax rate label.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'tax_class', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax class.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'tax_status', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax status.', 'readwrite' => 'read');		
		$this->fields_array[] = array('name' => 'total', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax total (not including shipping taxes).', 'readwrite' => 'write');
		$this->fields_array[] = array('name' => 'total_tax',	 	'type' => 'varchar(64)', 	'comment' => ' 	Shipping tax total. ', 'readwrite' => 'write');
		$this->fields_array[] = array('name' => 'taxes',	 	'type' => 'varchar(64)', 	'comment' => 'taxes. ', 'readwrite' => 'write');
		
		$this->table_details['tablename'] = $this->company_prefix . "woo_fee_lines";
		$this->table_details['primarykey'] = "fee_lines_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "order_id,tax_class";
		$this->table_details['index'][0]['keyname'] = "order-tax_class";

	}
}

?>
