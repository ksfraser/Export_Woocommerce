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
 * ***************************************************************************/



class woo_prod_variables_values extends woo_prod_variable_master
{
	var $id_woo_prod_variables_values;
	var $variablename;
	var $value;
	var $slug;
	var $updated_ts;

	function define_table()
	{
		woo_interface::define_table();
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		$slugl = 'varchar(' . SLUG_LENGTH . ')';
		$this->fields_array[] = array('name' => 'variablename', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );
		$this->fields_array[] = array('name' => 'value', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite', 'comment' => 'Human Readable' );
		$this->fields_array[] = array('name' => 'slug', 'type' => $slugl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite', 'comment' => 'machine short form' );

		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "variablename";
		$this->table_details['index'][0]['keyname'] = "variablename";
		$this->table_details['foreign'][0] = array( 'column' => "variablename", 'foreigntable' => "woo_prod_variable_variables", "foreigncolumn" => "variablename", "on_update" => "restrict", "on_delete" => "restrict" );
	}
}

?>
