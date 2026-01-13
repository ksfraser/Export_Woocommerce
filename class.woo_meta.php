<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_meta extends woo_interface {

	var $id;	//		integer 	Item ID.  read-only
	var $rate_code;	//		string 	Tax rate code.  read-only
	var $rate_id;	//		string 	Tax rate ID.  read-only
	var $label;	//		string 	Tax rate label.  read-only
	var $compound;	//		', 		'type' => 'int(1)', 	'comment' => ' 	Show if is a compound tax rate. Compound tax rates are applied on top of other tax rates.  read-only
	var $tax_total;	//		string 	Tax total (not including shipping taxes).  read-only
	var $shipping_tax_total;	//		string 	Shipping tax total. 
	var $woo_tax_lines_id;
	var $order_id;

	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		if( isset( $client->id ) )
			$this->order_id = $client->id;		
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'woo_tax_lines_id', 	'type' => 'int(11)', 		'comment' => 'Index.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'updated_ts', 		'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'order_id', 		'type' => 'int(11)', 		'comment' => ' 	Item ID.', 'readwrite' => 'read' );	

		$this->fields_array[] = array('name' => 'id', 			'type' => 'int(11)', 		'comment' => ' 	Item ID', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'rate_code', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax rate code.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'rate_id', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax rate ID.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'label', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax rate label.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'compound', 		'type' => 'int(1)', 		'comment' => ' 	Show if is a compound tax rate. Compound tax rates are applied on top of other tax rates.', 'readwrite' => 'write');
		$this->fields_array[] = array('name' => 'tax_total', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax total (not including shipping taxes).', 'readwrite' => 'write');
		$this->fields_array[] = array('name' => 'shipping_tax_total', 	'type' => 'varchar(64)', 	'comment' => ' 	Shipping tax total. ', 'readwrite' => 'write');
		$this->table_details['tablename'] = $this->company_prefix . "woo_tax_lines";
		$this->table_details['primarykey'] = "woo_tax_lines_id";
	}
}

?>
