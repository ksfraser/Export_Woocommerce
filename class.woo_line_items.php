<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_line_items extends woo_interface {
	var $woo_line_items_id;	//!< @var integer index in FA table
/**/	var $order_id;
/**********************Fields from orders->get***********************/
	var $id;	//	integer 	Item ID.  read-only
	var $name;	//	string 	Product name.  read-only
	var $sku;	//	string 	Product SKU.  read-only
	var $product_id;	//	integer 	Product ID.
	var $variation_id;	//	integer 	Variation ID, if applicable.
	var $quantity;	//	integer 	Quantity ordered.
	var $tax_class;	//	string 	Tax class of product.  read-only
	var $price;	//	string 	Product price.  read-only
	var $subtotal;	//	string 	Line subtotal (before discounts).
	var $subtotal_tax;	//	string 	Line subtotal tax (before discounts).
	var $total;	//	string 	Line total (after discounts).
	var $total_tax;	//	string 	Line total tax (after discounts).
	var $taxes;	//	array 	Line taxes with id, total and subtotal.  read-only
	var $meta;	//	array 	Line item meta data with key, label and value. 
/**********************Fields from orders->get***********************/

	/**********************************************************//**
	 * Class to store the line items in an order out of WooCommerce
	 *
	 * assumption is that client->id is the order id since this
	 * is an order line item.
	 *
	 * @param string WooCommerce (Wordpress) server
	 * @param string oAuth key
	 * @param string oAuth secret
	 * @param array options to be passed to wc_client
	 * @param class the client calling us
	 * @returns null
	 * **************************************************************/
	/*@bool@*/function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		if( isset( $client->id ) )
			$this->order_id = $client->id;
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'woo_line_items_id', 	'type' => 'int(11)', 	'comment' => 'Index.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'updated_ts', 		'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'order_id', 	'type' => 'int(11)', 	'comment' => 'Order ID this is linked to. Genned by module', 'readwrite' => 'read' );	
		
		$this->fields_array[] = array('name' => 'id', 		'type' => 'int(11)', 	'comment' => ' 	Item ID.', 'readwrite' => 'read' );
		$this->fields_array[] = array('name' => 'name', 	'type' => 'varchar(64)', 	'comment' => 'Product name.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'sku', 		'type' => 'varchar(64)', 	'comment' => 'Product SKU.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'product_id', 	'type' => 'int(11)', 	'comment' => 'Product ID.');
		$this->fields_array[] = array('name' => 'variation_id', 'type' => 'int(11)', 	'comment' => 'Variation ID, if applicable.');
		$this->fields_array[] = array('name' => 'quantity', 	'type' => 'int(11)', 	'comment' => 'Quantity ordered.');
		$this->fields_array[] = array('name' => 'tax_class', 	'type' => 'varchar(64)', 	'comment' => 'Tax class of product.', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'price', 	'type' => 'varchar(64)', 	'comment' => 'Product price.');
		$this->fields_array[] = array('name' => 'subtotal', 	'type' => 'varchar(64)', 	'comment' => 'Line subtotal (before discounts).');
		$this->fields_array[] = array('name' => 'subtotal_tax', 'type' => 'varchar(64)', 	'comment' => 'Line subtotal tax (before discounts).');
		$this->fields_array[] = array('name' => 'total', 	'type' => 'varchar(64)', 	'comment' => 'Line total (after discounts).');
		$this->fields_array[] = array('name' => 'total_tax', 	'type' => 'varchar(64)', 	'comment' => 'Line total tax (after discounts).');
		$this->fields_array[] = array('name' => 'taxes', 	'type' => 'int(11)', 	'comment' => 'Line taxes with id, total and subtotal.', 'foreign_obj' => 'woo_line_taxes', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'meta', 	'type' => 'int(11)', 	'comment' => 'Line item meta data with key, label and value. ', 'foreign_obj' => 'woo_line_meta');
		$this->table_details['tablename'] = $this->company_prefix . "woo_line_items";
		$this->table_details['primarykey'] = "woo_line_items_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "order_id,product_id";
		$this->table_details['index'][0]['keyname'] = "order-product";
	}
}

?>
