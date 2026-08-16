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



class woo_prod_variable_variables extends woo_prod_variable_master
{
	var $id_woo_prod_variable_variables;
	var $variablename;
	var $updated_ts;

	function define_table()
	{
		woo_interface::define_table();
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		$this->fields_array[] = array('name' => 'variablename', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );

		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "variablename";
		$this->table_details['index'][0]['keyname'] = "variablename";
	}
}




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



class woo_prod_variable_variables extends woo_prod_variable_master
{
	var $id_woo_prod_variable_variables;
	var $variablename;
	var $updated_ts;

	function define_table()
	{
		woo_interface::define_table();
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		$this->fields_array[] = array('name' => 'variablename', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );

		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "variablename";
		$this->table_details['index'][0]['keyname'] = "variablename";
	}
}

?>
