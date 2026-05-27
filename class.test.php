<?php


$path_to_root = "../..";

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class test extends woo_interface {
	var $id_test;	//!< Index of table
	function __construct()
	{
	}
	function define_table()
	{
		woo_interface::define_table();
		//$this->fields_array[] = array('name' => 'stock_id', 'label' => 'SKU', 'type' => , 'null' => 'NOT NULL',  'readwrite' => 'readwrite');
		//$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		//$descl = 'varchar(' . DESCRIPTION_LENGTH . ')';



		//$this->fields_array[] = array('name' => 'variablename', 'type' => , 'null' => 'NOT NULL',  'readwrite' => 'readwrite');
		//$this->fields_array[] = array('name' => 'stock_id', 'label' => 'Stock ID', 'type' => , 'null' => 'NOT NULL',  'readwrite' => 'readwrite', /*'foreign_obj' => 'woo_prod_variable_master', 'foreign_column' => 'stock_id'*/ 'comment' => 'Master Product stock_id');
		//$this->fields_array[] = array('name' => 'sku', 'label' => 'SKU', 'type' => , 'null' => 'NOT NULL',  'readwrite' => 'readwrite', 'comment' => 'generated sku for this variable product' );
		$this->fields_array[] = array('name' => 'description', 'label' => 'Description', 'type' => , 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );
		$this->fields_array[] = array('name' => 'inserted_fa', 'label' => 'Inserted into FA', 'type' => 'bool', 'null' => 'NOT NULL',  'readwrite' => 'readwrite', 'default' => '0' );
		$this->fields_array[] = array('name' => 'woo_id', 'label' => 'WooCommerce ID', 'type' => 'int(11)', 'null' => 'NOT NULL',  'readwrite' => 'readwrite', 'default' => '0' );

		//$this->table_details['orderby'] = 'sku';
//		$this->table_details['index'][0]['type'] = 'unique';
//		$this->table_details['index'][0]['columns'] = "stock_id, sku";
//		$this->table_details['index'][0]['keyname'] = "stock_id-sku";
//		$this->table_details['index'][1]['type'] = 'unique';
//		$this->table_details['index'][1]['columns'] = "sku";
//		$this->table_details['index'][1]['keyname'] = "sku";
//
//		//$this->table_details['foreign'][0] = array( 'column' => "variablename", 'foreigntable' => "woo_prod_variable_variables", "foreigncolumn" => "variablename", "on_update" => "restrict", "on_delete" => "restrict" );	
//		//$this->table_details['foreign'][1] = array( 'column' => "stock_id", 'foreigntable' => "woo_prod_variable_master", "foreigncolumn" => "stock_id", "on_update" => "restrict", "on_delete" => "restrict" );
	}
	function form_act
	{
		$this->call_table( 'form_act_completed', "Done It" );
	}
	function form_act_completed
	{	//Need to add code here to do whatever this submodule is for...
	}
	/*********************************************************************************//**
	 *master_form
	 *	Display the summary of items with edit/delete
	 *		
	 *	assumes entry_array has been built (constructor)
	 *	assumes table_details has been built (constructor)
	 *	assumes selected_id has been set (constructor?)
	 *	assumes iam has been set (constructor)
	 *
	 * ***********************************************************************************/
	function master_form()
	{
		global $Ajax;
		$this->notify( __METHOD__ . "::"  . __METHOD__ . ":" . __LINE__, "WARN" );
		$this->create_full();
		div_start('form');
		$count = $this->fields_array2var();
		
		$sql = "SELECT ";
		$rowcount = 0;
		foreach( $this->entry_array as $row )
		{
			if( $rowcount > 0 ) $sql .= ", ";
			$sql .= $row['name'];
			$rowcount++;
		}
		$sql .= " from " . $this->table_details['tablename'];
		if( isset( $this->table_details['orderby'] ) )
			$sql .= " ORDER BY " . $this->table_details['orderby'];
	
		$this->notify( __METHOD__ . ":" . __METHOD__ . ":" . __LINE__ . ":" . $sql, "WARN" );
		$this->notify( __METHOD__ . ":" . __METHOD__ . ":" . __LINE__ . ":" . " Display data", "WARN" );
		$this->display_table_with_edit( $sql, $this->entry_array, $this->table_details['primarykey'] );
		div_end();
		div_start('generate');
		div_end();
	}

	
}
