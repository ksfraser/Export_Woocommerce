<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

//This is a simple MODEL class.  AFAIK there aren't any bugs nor TODOs.

require_once( 'class.woo_interface.php' );

/******************************************************
 *
 * Current design is this class updates the table.
 * 
 ******************************************************/
class woo_categories_xref extends woo_interface {

	var $fa_cat;
	var $woo_cat;
	var $description;
	var $updated_ts;
	function __construct($serverURL = " ", $key, $secret, $options = null, $client = null)
	{
		//$serverURL = " ", $key, $secret, $options, $client = null
		PARENT::__construct( $serverURL = " ", $key, $secret, $options, $client );
	}
	function define_table()
	{
		//$this->fields_array[] = array('name' => 'categories_xref_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP');
		$this->fields_array[] = array('name' => 'fa_cat', 'type' => 'int(11)');
		$this->fields_array[] = array('name' => 'woo_cat', 'type' => 'int(11)');
		$this->fields_array[] = array('name' => 'description', 	'type' => 'varchar(64)', 	'comment' => ' 	First name.' );
		$this->table_details['tablename'] = $this->company_prefix . "woo_categories_xref";
		$this->table_details['primarykey'] = "fa_cat";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "fa_cat,woo_cat";
		$this->table_details['index'][0]['keyname'] = "fa-woo";
	}
	/************************************************************************************************************//**
	 * Update a cross-ref of FA category x WooCommerce Category
	 *
	 * ************************************************************************************************************/
	/*@bool?@*/function update()
	{
		$updateprod_sql = "update " . TB_PREF . "woo_categories_xref set woo_cat='" . $this->woo_cat . "' where fa_cat='" . $this->fa_cat . "')";
		$res = db_query( $updateprod_sql, __FILE__ . ":" . __LINE__ . "Couldn't update woo_categories_xref" );
		display_notification( "Updated woo_categories_xref with values " . $this->fa_cat . ", " . $this->woo_cat . ", " . $this->description   );
		return $res;	//Hope res is a boolean indicating success/fail on query
	}
	/************************************************************************************************************//**
	 * Insert a cross-ref of FA category x WooCommerce Category
	 *
	 * ************************************************************************************************************/
	/*@bool?@*/function insert()
	{
		$updateprod_sql = "insert ignore into " . TB_PREF . "woo_categories_xref ( fa_cat, woo_cat, description ) values ('" . $this->fa_cat . "', '" . $this->woo_cat . "', '" . $this->description . "')";
		$res = db_query( $updateprod_sql, __FILE__ . ":" . __LINE__ . "Couldn't update woo_categories_xref" );
		display_notification( "Updated woo_categories_xref with values " . $this->fa_cat . ", " . $this->woo_cat . ", " . $this->description   );
		return $res;	//Hope res is a boolean indicating success/fail on query
	}
	/************************************************************************************************************//**
	 * Get the WooCommerce category_id from the FrontAccounting one
	 *
	 * @returns int WooCommerce Category ID
	 * ************************************************************************************************************/
	/*@int@*/function get_woo_cat()
	{
		$sql = "select woo_cat from " . TB_PREF . "woo_categories_xref where fa_cat = '" . $this->fa_cat . "'";
		$res = db_query( $sql, __FILE__ . ":" . __LINE__ . "Couldn't select woo category");
		$assoc = db_fetch_assoc( $res );
		return (int)$assoc['woo_cat'];
	}
	/************************************************************************************************************//**
	 * Get the FrontAccounting category_id from the WooCommerce one
	 *
	 * @returns int FA Category ID
	 * ************************************************************************************************************/
	/*@int@*/function get_fa_cat()
	{
		$sql = "select fa_cat from " . TB_PREF . "woo_categories_xref where woo_cat = '" . $this->woo_cat . "'";
		$res = db_query( $sql, __FILE__ . ":" . __LINE__ . "Couldn't select fa category");
		$assoc = db_fetch_assoc( $res );
		return (int)$assoc['fa_cat'];
	}
}

?>
