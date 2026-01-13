<?php

$path_to_root = "../..";

require_once( 'class.woo_interface.php' );
require_once( 'class.woo_prod_variable_master.php' );

/*************************************************************************//**
 *
 *	A variable product is a product that is identicle except for attributes
 *	such as size/color/etc
 *
 *	We are going to use a SKU that is basesku-attr1-attr2-attr3
 *
 *	We will designate attributes with a sort order from most significant to least
 *	on a sku by sku basis
 *
 *	We will provide a facility for this module to create the appropriate
 *	skus so that we just have to designate the base plus the relevant
 *	attributes.
 *
 *	Attributes will have a name plus a slug.  With the default FA sku size
 *	being 20, we need short slugs for each attribute.
 *
 *
 *	THESE variations end up as arrays within the product itself when sent
 *	via REST to WOO.  Need a ->run or similar function to meet the pattern
 *	set out for the image properties.
 *
 * ***************************************************************************/



class woo_prod_variation extends woo_prod_variable_master
{
	var $id_woo_prod_variation;
	var $variablename;
	var $value;
	var $slug;
	var $updated_ts;

	var $id;	//integer 	Variation ID.  read-only
	var $date_created;	//date-time 	The date the variation was created, in the site’s timezone.  read-only
	var $date_modified;	//date-time 	The date the variation was last modified, in the site’s timezone.  read-only
	var $permalink;	//string 	Variation URL.  read-only
	var $sku;	//string 	Unique identifier.
	var $price;	//string 	Current variation price. This is setted from regular_price and sale_price.  read-only
	var $regular_price;	//string 	Variation regular price.
	var $sale_price;	//string 	Variation sale price.
	var $date_on_sale_from;	//string 	Start date of sale price. Date in the YYYY-MM-DD format.
	var $date_on_sale_to;	//string 	Start date of sale price. Date in the YYYY-MM-DD format.
	var $on_sale;	//boolean 	Shows if the variation is on sale.  read-only
	var $purchasable;	//boolean 	Shows if the variation can be bought.  read-only
	var $virtual;	//boolean 	If the variation is virtual. Virtual variations are intangible and aren’t shipped. Default is false.
	var $downloadable;	//boolean 	If the variation is downloadable. Downloadable variations give access to a file upon purchase. Default is false.
	var $downloads;	//array 	List of downloadable files. See Downloads properties.
	var $download_limit;	//integer 	Amount of times the variation can be downloaded, the -1 values means unlimited re-downloads. Default is -1.
	var $download_expiry;	//integer 	Number of days that the customer has up to be able to download the variation, the -1 means that downloads never expires. Default is -1.
	var $tax_status;	//string 	Tax status. Default is taxable. Options: taxable, shipping (Shipping only) and none.
	var $tax_class;	//string 	Tax class.
	var $manage_stock;	//boolean 	Stock management at variation level. Default is false.
	var $stock_quantity;	//integer 	Stock quantity. If is a variable variation this value will be used to control stock for all variations, unless you define stock at variation level.
	var $in_stock;	//boolean 	Controls whether or not the variation is listed as “in stock” or “out of stock” on the frontend. Default is true.
	var $backorders;	//string 	If managing stock, this controls if backorders are allowed. If enabled, stock quantity can go below 0. Default is no. Options are: no (Do not allow), notify (Allow, but notify customer), and yes (Allow).
	var $backorders_allowed;	//boolean 	Shows if backorders are allowed.“ read-only
	var $backordered;	//boolean 	Shows if a variation is on backorder (if the variation have the stock_quantity negative).  read-only
	var $weight;	//string 	Variation weight in decimal format.
	var $dimensions;	//object 	Variation dimensions. See Dimensions properties.
	var $shipping_class;	//string 	Shipping class slug. Shipping classes are used by certain shipping methods to group similar products.
	var $shipping_class_id;	//integer 	Shipping class ID.  read-only
	var $image;	//array 	Variation featured image. Only position 0 will be used. See Images properties.
	var $attributes;	//array 	List of variation attributes. See Variation Attributes properties

	function define_table()
	{
		woo_interface::define_table();
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		$slugl = 'varchar(' . SLUG_LENGTH . ')';

		$this->fields_array[] = array('name' => 'id',  'type' => 'int(11)', 'comment' => 'Variation ID.',   'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'date_created',  'type' => 'timestamp', 'comment' => 'The date the variation was created, in the site’s timezone.',   'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'date_modified',  'type' => 'timestamp', 'comment' => 'The date the variation was last modified, in the site’s timezone. ',  'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'permalink',  'type' => $sidl, 'comment' => 'Variation URL.',   'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'sku',  'type' => $sidl,  	'comment' => 'Unique identifier.' );
		$this->fields_array[] = array('name' => 'price',  'type' => $sidl,  'comment' => 'Current variation price. This is setted from regular_price and sale_price.',   'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'regular_price',  'type' => $sidl,  'comment' => 'Variation regular price.', );
		$this->fields_array[] = array('name' => 'sale_price',  'type' => $sidl,  'comment' => 'Variation sale price.', );
		$this->fields_array[] = array('name' => 'date_on_sale_from',  'type' => $sidl,  'comment' => 'Start date of sale price. Date in the YYYY-MM-DD format.', );
		$this->fields_array[] = array('name' => 'date_on_sale_to',  'type' => $sidl,  'comment' => 'Start date of sale price. Date in the YYYY-MM-DD format.', );
		$this->fields_array[] = array('name' => 'on_sale',  'type' => 'bool', 'comment' => 'Shows if the variation is on sale. ',  'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'purchasable',  'type' => 'bool', 'comment' => ' Shows if the variation can be bought.',   'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'virtual',  'type' => 'bool', 'comment' => 'If the variation is virtual. Virtual variations are intangible and aren’t shipped. Default is false.', );
		$this->fields_array[] = array('name' => 'downloadable',  'type' => 'bool', 'comment' => 'If the variation is downloadable. Downloadable variations give access to a file upon purchase. Default is false.', );
		$this->fields_array[] = array('name' => 'downloads',  'type' => 'int(11)', 'foreign_obj' => '', 'comment' => 'List of downloadable files. See Downloads properties.', );
		$this->fields_array[] = array('name' => 'download_limit',  'type' => 'int(11)', 'comment' => 'Amount of times the variation can be downloaded, the -1 values means unlimited re-downloads. Default is -1.', );
		$this->fields_array[] = array('name' => 'download_expiry',  'type' => 'int(11)', 'comment' => 'Number of days that the customer has up to be able to download the variation, the -1 means that downloads never expires. Default is -1.', );
		$this->fields_array[] = array('name' => 'tax_status',  'type' => $sidl, 'comment' => 'Tax status. Default is taxable. Options: taxable, shipping (Shipping only) and none.', );
		$this->fields_array[] = array('name' => 'tax_class',  'type' => $sidl,  'comment' => 'Tax class.', );
		$this->fields_array[] = array('name' => 'manage_stock',  'type' => 'bool', 'comment' => 'Stock management at variation level. Default is false.', );
		$this->fields_array[] = array('name' => 'stock_quantity',  'type' => 'int(11)', 'comment' => 'Stock quantity. If is a variable variation this value will be used to control stock for all variations, unless you define stock at variation level.' );
		$this->fields_array[] = array('name' => 'in_stock',  'type' => 'bool', 'comment' => 'Controls whether or not the variation is listed as in stock or out of stock on the frontend. Default is true.' );
		$this->fields_array[] = array('name' => 'backorders',  'type' => $sidl, 'comment' => ' If managing stock, this controls if backorders are allowed. If enabled, stock quantity can go below 0. Default is no. Options are: no (Do not allow), notify (Allow, but notify customer), and yes (Allow).' );
		$this->fields_array[] = array('name' => 'backorders_allowed',  'type' => 'bool', 'comment' => ' Shows if backorders are allowed.', 'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'backordered',  'type' => 'bool', 'comment' => 'Shows if a variation is on backorder (if the variation have the stock_quantity negative).',   'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'weight',  'type' => $sidl, 'comment' => 'Variation weight in decimal format.' );
		$this->fields_array[] = array('name' => 'dimensions',  'type' => 'int(11)',  'foreign_obj' => '', 'comment' => 'Variation dimensions. See Dimensions properties.' );
		$this->fields_array[] = array('name' => 'shipping_class',  'type' => $sidl, 'comment' => 'Shipping class slug. Shipping classes are used by certain shipping methods to group similar products.' );
		$this->fields_array[] = array('name' => 'shipping_class_id',  'type' => 'int(11)' 	'comment' => 'Shipping class ID.',   'readwrite' = > 'readonly' );
		$this->fields_array[] = array('name' => 'image',  'type' => 'int(11)', 'foreign_obj' => '', 	'comment' => 'Variation featured image. Only position 0 will be used. See Images properties.' );
		$this->fields_array[] = array('name' => 'attributes',  'type' => 'int(11)', 'foreign_obj' => '', 'comment' => 'List of variation attributes. See Variation Attributes properties' );


		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "sku";
		$this->table_details['index'][0]['keyname'] = "sku";
		$this->table_details['foreign'][0] = array( 'column' => "downloads", 'foreigntable' => "woo_prod_downloads", "foreigncolumn" => "id_woo_prod_downloads", "on_update" => "restrict", "on_delete" => "restrict" );
		$this->table_details['foreign'][0] = array( 'column' => "dimensions", 'foreigntable' => "woo_prod_downloads", "foreigncolumn" => "id_woo_prod_downloads", "on_update" => "restrict", "on_delete" => "restrict" );
		$this->table_details['foreign'][0] = array( 'column' => "image", 'foreigntable' => "woo_prod_downloads", "foreigncolumn" => "id_woo_prod_downloads", "on_update" => "restrict", "on_delete" => "restrict" );
		//THe following needs to be an array of woo_prod_variables_values
		//$this->table_details['foreign'][0] = array( 'column' => "attributes", 'foreigntable' => "woo_prod_variables_values", "foreigncolumn" => "id_woo_prod_variables_values", "on_update" => "restrict", "on_delete" => "restrict" );
	}
}

?>
