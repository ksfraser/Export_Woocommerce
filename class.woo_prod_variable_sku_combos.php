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
 *	This class/table is for generating this full SKU
 *	We will xref each variable against the master SKU but each
 *	will have a priority
 
 * ***************************************************************************/



class woo_prod_variable_sku_combos extends woo_prod_variable_master
{
	var $id_woo_prod_variable_sku_full;
	var $updated_ts;
	var $stock_id;
	var $variablename;
	var $priority;
	var $minattr;		//!< The minimum number of attributes a master SKU has
	var $maxattr;		//!< The maximum number of attributes a master SKU has


	function define_table()
	{
		woo_interface::define_table();
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		//$this->fields_array[] = array('name' => 'stock_id', 'label' => 'SKU', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite');
		//$this->fields_array[] = array('name' => 'variablename', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'stock_id', 'label' => 'SKU', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite', /*'foreign_obj' => 'woo_prod_variable_master', 'foreign_column' => 'stock_id'*/ );
		$this->fields_array[] = array('name' => 'variablename', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite',/* 'foreign_obj' => 'woo_prod_variable_variables', 'foreign_column' => 'variablename'*/  );
		$this->fields_array[] = array('name' => 'priority', 'type' => "int(11)", 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );

		$this->table_details['orderby'] = 'stock_id, priority';
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "stock_id,variablename";
		$this->table_details['index'][0]['keyname'] = "stock_id-variablename";
//		$this->table_details['foreign'][0] = array( 'column' => "variablename", 'foreigntable' => "woo_prod_variable_variables", "foreigncolumn" => "variablename", "on_update" => "restrict", "on_delete" => "restrict" );	
//		$this->table_details['foreign'][1] = array( 'column' => "stock_id", 'foreigntable' => "woo_prod_variable_master", "foreigncolumn" => "stock_id", "on_update" => "restrict", "on_delete" => "restrict" );	
	}
	/******************************************************************************//**
	 *Get the min and max number of variable attributes a (master) product has 
	 *
	 *	When auto-genning SKUs for variable products, it is easier using
	 *	LEFT JOINs within SQL than to use a recursive build.  But to do so
	 *	without causing excess gens we need to start with the MOST attributes
	 *	first and gen those and work our way to the least so that any master product
	 *	that has had its variables genned doesn't have combos of subsets genned.  Hence
	 *	we need the min and max.
	 *
	 * ********************************************************************************/
	function get_minmax_attributes()
	{
		$sql = "SELECT max(rows) as most, min(rows) as least from 
		 		(select count(*) as rows 
				FROM `" . TB_PREF . "woo_prod_variable_sku_combos` 
				group by stock_id) 
		  	as maxalias";
		$res2 = db_query( $sql, "Couldn't count min/max attributes" );
		while( $row2 = db_fetch_assoc( $res2 ) )
		{
			$this->minattr = $row['least'];
			$this->maxattr = $row['most'];
		}
	}
	
}


?>
