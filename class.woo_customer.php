<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

/************************************************************************************
 * We need to then push these customers (billing address, shipping address, ...) into
 * FAs debtors, contacts, persons.
 *
 * Need to search persons for matching email.
 * cross in contacts to find the entity (corporation/person)
 * update billing/shipping addresses?
 *
 * ************************************************************************/



require_once( 'class.woo_interface.php' );

class woo_customer extends woo_interface {
	var $id;	//	integer 	Unique identifier for the resource.  read-only
	var $date_created;	//	date-time 	The date the customer was created, in the site’s timezone.  read-only
	var $date_modified;	//	date-time 	The date the customer was last modified, in the site’s timezone.  read-only
	var $email;	//	string 	The email address for the customer.
	var $first_name;	//	string 	Customer first name.
	var $last_name;	//	string 	Customer last name.
	var $username;	//	string 	Customer login name. Can be generated automatically from the customer’s email address if the option woocommerce_registration_generate_username is equal to yes
	var $role;
	var $password;	//	string 	Customer password. Can be generated automatically with wp_generate_password() if the “Automatically generate customer password” option is enabled, check the index meta for generate_password write-only
	var $last_order_id;	//string
	var $last_order_date;	//datestamp
/**/	var $last_order;	//	array 	Last order data. See Customer Last Order properties.  read-only
	var $orders_count;	//	integer 	Quantity of orders made by the customer.  read-only
	var $total_spent;	//	string 	Total amount spent.  read-only
	var $avatar_url;	//	string 	Avatar URL.
	var $billing_address;	//	array 	List of billing address data. See Billing Address properties.
	var $shipping_address;	//	array 	List of shipping address data. See Shipping Address properties.
	
	var $woo_rest;
	var $header_array;

	function __construct( $serverURL = null, $key = null, $secret = null, $options = null, $client = null)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client );
		//$this->ObserverRegister( $this, "NOTIFY_SEARCH_REMOTE_UPC", 1 );	//For EVENTLOOP.
	}
	/***************************************************************//**
	 *build_interestedin
	 *
	 * 	This function builds the table of events that we
	 * 	want to react to and what handlers we are passing the
	 * 	data to so we can react.
	 * ******************************************************************/
	function build_interestedin()
	{
		//This NEEDS to be overridden
		$this->interestedin[FA_CUSTOMER_CREATED]['function'] = "handle_FA_new_customer";
	}
	function handle_FA_new_customer( $obj, $msg )
	{
		return FALSE;
	}

	function define_table()
	{
		$this->fields_array[] = array('name' => 'customer_id', 'type' => 'int(11)', 'auto_increment' => 'yes');
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP');
		$this->fields_array[] = array('name' => 'id', 'type' => 'int(11)', 'comment' => 'WOOs id');
		$this->fields_array[] = array('name' => 'created_at',	 	'type' => 'datetime', 	'comment' => 'The date the order was created, in the site’s timezone.  ' );
		$this->fields_array[] = array('name' => 'modified_at',	 	'type' => 'datetime', 	'comment' => 'The date the order was last modified, in the site’s timezone.  ' );
		$this->fields_array[] = array('name' => 'email', 		'type' => 'varchar(64)', 'comment' => 'Email Address.  ' );
		$this->fields_array[] = array('name' => 'first_name', 		'type' => 'varchar(64)', 'comment' => 'First Name.  ' );
		$this->fields_array[] = array('name' => 'last_name', 		'type' => 'varchar(64)', 'comment' => 'Last Name.  ' );
		$this->fields_array[] = array('name' => 'username', 		'type' => 'varchar(64)', 'comment' => 'User Name.  ' );
		$this->fields_array[] = array('name' => 'password', 		'type' => 'varchar(64)', 'comment' => 'Password.  ' );
		$this->fields_array[] = array('name' => 'role', 		'type' => 'varchar(64)', 'comment' => 'Wordpress Role' );
		$this->fields_array[] = array('name' => 'last_order', 		'type' => 'int(11)' , 	'comment' => 'FK	Last Order.  RO', 'foreign_obj' => 'woo_last_order', 'readwrite' => 'read' );
		$this->fields_array[] = array('name' => 'last_order_id', 	'type' => 'int(11)' , 	'comment' => 'Last Order ID', 'readwrite' => 'read' );
		$this->fields_array[] = array('name' => 'last_order_date',	'type' => 'datetime', 	'comment' => 'The date of last order.' );
		$this->fields_array[] = array('name' => 'orders_count',		'type' => 'int(11)' , 	'comment' => 'Number of orders the User has placed.', 'readwrite' => 'read' );
		$this->fields_array[] = array('name' => 'total_spent', 		'type' => 'varchar(64)', 'comment' => 'Grand total spent.  ', 'readwrite' => 'read' );
		$this->fields_array[] = array('name' => 'avatar_url', 		'type' => 'varchar(64)', 'comment' => 'Avatar.  ' );
		$this->fields_array[] = array('name' => 'billing_address', 		'type' => 'int(11)' , 	'comment' => 'FK	Billing address. See Customer Billing Address properties.', 'foreign_obj' => 'woo_billing_address' );
		$this->fields_array[] = array('name' => 'shipping_address', 		'type' => 'int(11)' , 	'comment' => 'FK	Shipping address. ', 'foreign_obj' => 'woo_billing_address' );
		$this->fields_array[] = array('name' => 'debtor_no',		'type' => 'int(11)', 	'comment' => 'FA cust_branch debtor_number for customer');

		$this->table_details['tablename'] = $this->company_prefix . "woo_customer";
		$this->table_details['primarykey'] = "customer_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "first_name,last_name,email";
		$this->table_details['index'][0]['keyname'] = "person-email";
	}
	function create_customer()
	{
		/*
			curl -X POST https://example.com/wp-json/wc/v1/customers \
			    -u consumer_key:consumer_secret \
			    -H "Content-Type: application/json" \
			    -d '{
			  "email": "john.doe@example.com",
			  "first_name": "John",
			  "last_name": "Doe",
			  "username": "john.doe",
			  "billing": {
			    "first_name": "John",
			    "last_name": "Doe",
			    "company": "",
			    "address_1": "969 Market",
			    "address_2": "",
			    "city": "San Francisco",
			    "state": "CA",
			    "postcode": "94103",
			    "country": "US",
			    "email": "john.doe@example.com",
			    "phone": "(555) 555-5555"
			  },
			  "shipping": {
			    "first_name": "John",
			    "last_name": "Doe",
			    "company": "",
			    "address_1": "969 Market",
			    "address_2": "",
			    "city": "San Francisco",
			    "state": "CA",
			    "postcode": "94103",
			    "country": "US"
			  }
			}'
					
		 */
		try {
			// $client->customers->create( array( 'first_name' => 'John', 'last_name' => 'Galt' ) )
			$response = $this->wc_client->customers->create( array( $this ) );
			//print_r( $response );
			$extractcount = $this->extract_data_objects( $response->orders );	//This builds a linked list.  Will need to walk it.
		} catch ( WC_API_Client_Exception $e ) {
			$msg = $e->getMessage();
			$code = $e->getCode();
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				switch( $code ) {
				default:
					echo "<br />" . __FILE__ . ":" . __LINE__ . ":Unhandled Error Code: " . $code . "<br />";
					break;
				}
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_request() );
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_response() );
			}
		}
/*	
		$this->build_data_array();
		$this->build_json_data();
		$this->woo_rest->set_content_type( "application/json" );
		$response = $this->woo_rest->write2woo_json( $this->json_data, "POST" );
		
		display_notification( $response );
 */
		$this->ll_walk_insert_fa();
		return $extractcount;
	}
	/*
	 * Assuming the id is already set
	 */
	function get_customer()
	{
		/*
		 *
			curl https://example.com/wp-json/wc/v1/customers/2 -u consumer_key:consumer_secret
		 *  
		 * */
		try {
			$this->debug = 1;
			$response = $this->wc_client->customers->get( $this->id );
			print_r( $response );
			$extractcount = $this->extract_data_objects( $response->customers );	//This builds a linked list.  Will need to walk it.
		} catch ( WC_API_Client_Exception $e ) {
			$msg = $e->getMessage();
			$code = $e->getCode();
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				switch( $code ) {
				default:
					echo "<br />" . __FILE__ . ":" . __LINE__ . ":Unhandled Error Code: " . $code . "<br />";
					break;
				}
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_request() );
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_response() );
			}
		}
		$this->ll_walk_insert_fa();
		return $extractcount;
	}
	function update_customer()
	{
		/*
			curl -X PUT https://example.com/wp-json/wc/v1/customers/2 -u consumer_key:consumer_secret
			    -H "Content-Type: application/json" 
			    -d '{
			  "first_name": "James",
			  "billing": {
			    "first_name": "James"
			  },
			  "shipping": {
			    "first_name": "James"
			  }
			}'
		 * 
		 * */
		try {
			// $client->customers->update( $customer_id, array( 'first_name' => 'John', 'last_name' => 'Galt' ) )
			$response = $this->wc_client->customers->update( $this->id, array( $this ) );
			//print_r( $response );
			$extractcount = $this->extract_data_objects( $response->orders );	//This builds a linked list.  Will need to walk it.
		} catch ( WC_API_Client_Exception $e ) {
			$msg = $e->getMessage();
			$code = $e->getCode();
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				switch( $code ) {
				default:
					echo "<br />" . __FILE__ . ":" . __LINE__ . ":Unhandled Error Code: " . $code . "<br />";
					break;
				}
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_request() );
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_response() );
			}
		}
	}
	function list_customers()
	{
		/*
		 * 	GET
		 */
		try {
			$response = $this->wc_client->customers->get();
			//print_r( $response );
			$extractcount = $this->extract_data_objects( $response->orders );	//This builds a linked list.  Will need to walk it.
		} catch ( WC_API_Client_Exception $e ) {
			$msg = $e->getMessage();
			$code = $e->getCode();
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				switch( $code ) {
				default:
					echo "<br />" . __FILE__ . ":" . __LINE__ . ":Unhandled Error Code: " . $code . "<br />";
					break;
				}
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_request() );
			//	echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//	print_r( $e->get_response() );
			}
		}
		$this->ll_walk_insert_fa();
		return $extractcount;
	}
	// customers
	//print_r( $client->customers->get() );
	//print_r( $client->customers->get( $customer_id ) );
	//print_r( $client->customers->get_by_email( 'help@woothemes.com' ) );
	//print_r( $client->customers->create( array( 'email' => 'woothemes@mailinator.com' ) ) );
	//print_r( $client->customers->update( $customer_id, array( 'first_name' => 'John', 'last_name' => 'Galt' ) ) );
	//print_r( $client->customers->delete( $customer_id ) );
	//print_r( $client->customers->get_count( array( 'filter[limit]' => '-1' ) ) );
	//print_r( $client->customers->get_orders( $customer_id ) );
	//print_r( $client->customers->get_downloads( $customer_id ) );
	//$customer = $client->customers->get( $customer_id );
	//$customer->customer->last_name = 'New Last Name';
	//print_r( $client->customers->update( $customer_id, (array) $customer ) );
}

?>
