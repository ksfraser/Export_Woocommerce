<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_billing extends woo_interface {

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
	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
//		if( isset( $client->id ) )
//			$this->order_id = $client->id;
	
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'billing_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP');
		$this->fields_array[] = array('name' => 'first_name', 	'type' => 'varchar(64)', 	'comment' => ' 	First name.' );
		$this->fields_array[] = array('name' => 'last_name', 	'type' => 'varchar(64)', 	'comment' => ' 	Last name.' );
		$this->fields_array[] = array('name' => 'company', 	'type' => 'varchar(64)', 	'comment' => ' 	Company name.' );
		$this->fields_array[] = array('name' => 'address_1', 	'type' => 'varchar(64)', 	'comment' => ' 	Address line 1.' );
		$this->fields_array[] = array('name' => 'address_2', 	'type' => 'varchar(64)', 	'comment' => ' 	Address line 2.' );
		$this->fields_array[] = array('name' => 'city', 	'type' => 'varchar(64)', 	'comment' => ' 	City name.' );
		$this->fields_array[] = array('name' => 'state', 	'type' => 'varchar(64)', 	'comment' => ' 	ISO code or name of the state, province or district.' );
		$this->fields_array[] = array('name' => 'postcode', 	'type' => 'varchar(64)', 	'comment' => ' 	Postal code.' );
		$this->fields_array[] = array('name' => 'country', 	'type' => 'varchar(64)', 	'comment' => ' 	ISO code of the country.' );
		$this->fields_array[] = array('name' => 'email', 	'type' => 'varchar(64)', 	'comment' => ' 	Email address.' );
		$this->fields_array[] = array('name' => 'phone', 	'type' => 'varchar(64)', 	'comment' => ' 	Phone number.' );
		$this->table_details['tablename'] = $this->company_prefix . "woo_billing";
		$this->table_details['primarykey'] = "billing_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "first_name,last_name,address_1,city,state";
		$this->table_details['index'][0]['keyname'] = "billing_customer";
	}
}

?>
