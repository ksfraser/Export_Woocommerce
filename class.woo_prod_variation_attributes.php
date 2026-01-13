<?php

$path_to_root = "../..";

require_once( 'class.woo_interface.php' );

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



class woo_prod_variation_attributes extends woo_interface
{
	var $id_woo_prod_variation_attributes;
	var $updated_ts;
	var $id;	//!< Integer. If a Global Attribute
	var $name;	//!< String. Non Global Attribute.  	xref prod_variables_values::variablename
	var $option;	//!< String.				xref prod_variables_values::human readable
	var $sku;	//!< the SKU that this applies against.

	function define_table()
	{
		woo_interface::define_table();	//defines tablename and prikey!
						//declares updates_ts  and id_ prikey
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		$this->fields_array[] = array('name' => 'id', 'type' => 'int(11)', 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );
		$this->fields_array[] = array('name' => 'name', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );
		$this->fields_array[] = array('name' => 'option', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );
		$this->fields_array[] = array('name' => 'sku', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite', 'comment' => 'SKU that also appears in woo_prod_variable_sku_full' );
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "`sku`, `name`, `option`";
		$this->table_details['index'][0]['keyname'] = "sku-name-option";
	}
	/**********************************************************************************//**
	 *Return the list of attributes for a given SKU
	 *
	 * This function needs the SKU passed in ONLY if the object doesn't already
	 * have the sku set.
	 *
	 * @param sku The sku to search for
	 * @param fuzzy boolean - do we search for exact sku match or sku%.  This will allow
	 *  us to search for the attributes for the parent product.
	 * @returns array of names/options by SKU
	 *
	 * ************************************************************************************/
	function get_by_sku( $sku = null, $fuzzy = false )
	{
		if( !isset( $this->sku ) )
			$this->sku = $sku;
		$sql = "select * from " . TB_PREF . get_class( $this ) . " where sku = '";
		if( $fuzzy )
	        	$sql .= $this->sku . "%'";
		else
	        	$sql .= $this->sku . "'";
		$result = db_query( $sql, __METHOD__ . " Couldn't run query" );
		$resarray = array();
		while( $nextrow = db_fetch( $result ) )
		{
			$resarray[$nextrow['sku']][] = array( $nextrow['name'] => $nextrow['option'] );
		}
		return $resarray;
	}
	/**********************************************************************************//**
	 *Return the list of attributes for a given name
	 *
	 * This function needs the name passed in ONLY if the object doesn't already
	 * have the name set.
	 *
	 * ************************************************************************************/
	function get_by_name( $name = null )
	{
		if( !isset( $this->name ) )
			$this->name = $name;
		$sql = "select * from " . TB_PREF . get_class( $this ) . " where name = '" . $this->name . "'";
		$result = db_query( $sql, __METHOD__ . " Couldn't run query" );
		$resarray = array();
		while( $nextrow = db_fetch( $result ) )
		{
			$resarray[$nextrow['name']][] = array( $nextrow['sku'] => $nextrow['option'] );
		}
		return $resarray;
	}
	/**********************************************************************************//**
	 *Return the list of attributes for a given option
	 *
	 * This function needs the SKU passed in ONLY if the object doesn't already
	 * have the option set.
	 *
	 * ************************************************************************************/
	function get_by_option( $option = null )
	{
		if( !isset( $this->option ) )
			$this->option = $option;
		$sql = "select * from " . TB_PREF . get_class( $this ) . " where option = '" . $this->option . "'";
		$result = db_query( $sql, __METHOD__ . " Couldn't run query" );
		$resarray = array();
		while( $nextrow = db_fetch( $result ) )
		{
			$resarray[$nextrow['option']][] = array( $nextrow['sku'] => $nextrow['name'] );
		}
		return $resarray;
	}
		
}

?>
