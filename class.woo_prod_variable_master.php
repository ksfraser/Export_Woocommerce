<?php

$path_to_root = "../..";

require_once( 'class.woo_interface.php' );

/*********************************************************************************************
 *Converting a WOO definition to table definition.  AUTHORITATIVE definition to be maintained in woo_interface!

 		//CONVERT Woo definition to table definition
		// \t -> ;\t\/\/ 'type' =>
		// \t -> $this->fields_array[] = array('name' => '
		//integer -> 'int(11)',  'comment' => '
		//string -> varchar(" . STOCK_ID_LENGTH . ")',  'comment' => '
		//date-time -> 'timestamp',  'comment' => '
		//boolean -> 'bool',  'comment' => '
		//array -> 'int(11)',  'foreign_obj' => '',  'comment' => '
		//object -> 'int(11)',  'foreign_obj' => '',  'comment' => '
		//read-only -> 'readwrite' = > 'readonly'
		//^ -> \t\$this->fields_array[] = array('name' => '
		//;\t\/\/ -> ',
		// --> 'comment' => '
		// $ -> );
 * *******************************************************************************************/


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


class woo_prod_variable_master extends woo_interface {
	var $id_woo_prod_variable_master;
	var $stock_id;
	var $updated_ts;
	var $description;	//!< Base description onto which the attributes will add their human readable parts
	function __construct( $v1, $v2, $v3, $v4, $v5 )
	{
		parent::__construct( $v1, $v2, $v3, $v4, $v5 );
	}

	function define_table()
	{
		parent::define_table();
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		$this->fields_array[] = array('name' => 'stock_id', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );
		$this->fields_array[] = array('name' => 'description', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );

		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "stock_id";
		$this->table_details['index'][0]['keyname'] = "stock_id";
	}
	/*
	function master_form()
	{
		$count = 0;
		$this->reset_values();
		foreach( $this->fields_array as $row )
		{
			$var = $row['name'];
			if( isset( $_POST[$var] ) )
			{
				$this->$var = $_POST[$var];
				$count++;
			}
		}
		$key = $this->table_details['primarykey'];
		if( isset( $this->$key ) )
		{
			$this->update_table();
		}
		else if( $count > 0 )
		{
			$this->insert_table();
		}
		$this->reset_values();
		$sql = "SELECT * from " . $this->table_details['tablename'];
		$this->display_table_with_edit( $sql, $this->entry_array, $this->table_details['primarykey'] );
		$this->display_edit_form( $this->entry_array, $this->selected_id, "create_" . $this->iam . "_form" );
	}
	 */

}

?>
