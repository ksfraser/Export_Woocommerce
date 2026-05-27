<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class qoh extends woo_interface {

	var $first_name; 	//	string 	First name.
	var $last_name; 	//	string 	Last name.
	var $company; 	//	string 	Company name.
	var $address_1; 	//	string 	Address line 1.
	var $address_2; 	//	string 	Address line 2.
	var $city; 	//	string 	City name.
	var $state; 	//	string 	ISO code or name of the state, province or district.
	var $postcode; 	//	string 	Postal code.
	var $country; 	//	string 	ISO code of the country.
	var $email; 	//	string 	Email address.
	var $phone; 	//	string 	Phone number.
	var $customer_id;
	var $order_id;
	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		/*
		if( isset( $client->id ) )
		{
			$classtype=get_class( $client );
			echo "<br />" . __FILE__ . ":" . __LINE__ . " Class of type " . $classtype . "<br />";
			if( $classtype == 'woo_customer' )
				$this->customer_id = $client->id;
			else if( $classtype == 'woo_orders' )
				$this->order_id = $client->id;
		}
		 */
		return;
	}
	function define_table()
	{
		//$this->fields_array[] = array('name' => 'billing_address_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP');
		$this->fields_array[] = array('name' => 'stock_id', 'type' => 'varchar(32)' );
		$this->fields_array[] = array('name' => 'instock', 'type' => 'int(11)' );

		$this->table_details['tablename'] = $this->company_prefix . "qoh";
		$this->table_details['primarykey'] = "stock_id";
		/*
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "order_id,first_name,last_name,address_1,city,state";
		$this->table_details['index'][0]['keyname'] = "order-billing_address_customer";
		$this->table_details['index'][1]['type'] = 'unique';
		$this->table_details['index'][1]['columns'] = "customer_id,first_name,last_name,address_1,city,state";
		$this->table_details['index'][1]['keyname'] = "customer-billing_address_customer";
		 */
	}
	function form_QOH()
	{
				$this->call_table( 'form_QOH_completed', "QOH" );
	}
	function form_QOH_completed()
	{
            	display_notification("QOH");
		$oldcount = $this->count_rows();	//inherited from table_interface
		$qoh2 = "insert ignore into " . TB_PREF . "qoh SELECT 
				stock_id, 0 as instock
			FROM 
				" . TB_PREF . "stock_master
			WHERE
				inactive='0'";
		$res = db_query( $qoh2, "Couldn't create table of stock on hand" );

		$qoh2 = "replace into " . TB_PREF . "qoh SELECT 
				stock_id, SUM(qty) as instock
			FROM 
				" . TB_PREF . "stock_moves
			GROUP BY stock_id";
		$res = db_query( $qoh2, "Couldn't create table of stock on hand" );
		//var_dump( $res );

		$newcount = $this->count_rows();	//inherited from table_interface     	
		display_notification("$newcount rows of items exist in qoh.  Added " . $oldcount - $newcount);
		//$activecount = $stock_master->count_filtered( "inactive='0'" );
		$res = db_query( "select count(*) from " . TB_PREF . "stock_master where inactive='0'", "Couldn't count QOH" );
		$count = db_fetch_row( $res );
            	display_notification("$count[0] rows of active items exist in stock_master.");
		//$this->call_table( 'woo', "WOO" );
	}
}

?>
