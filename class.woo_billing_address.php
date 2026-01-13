<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_billing_address extends woo_address
{
}

class woo_shipping_address extends woo_address
{
}

class woo_address extends woo_interface {

	var $address_id;
	/**********************Shipping**********************************/
	var $first_name; 	//	string 	First name.
	var $last_name; 	//	string 	Last name.
	var $company; 	//	string 	Company name.
	var $address_1; 	//	string 	Address line 1.
	var $address_2; 	//	string 	Address line 2.
	var $city; 	//	string 	City name.
	var $state; 	//	string 	ISO code or name of the state, province or district.
	var $postcode; 	//	string 	Postal code.
	var $country; 	//	string 	ISO code of the country.
	/**********************Shipping**********************************/
	/**********************Billing ADD**********************************/
	var $email; 	//	string 	Email address.
	var $phone; 	//	string 	Phone number.
	/**********************Billing**********************************/
	var $customer_id;
	var $order_id;
	var $crm_persons_id;
	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		if( isset( $client->id ) )
		{
			$classtype=get_class( $client );
			echo "<br />" . __FILE__ . ":" . __LINE__ . " Class of type " . $classtype . "<br />";
			if( $classtype == 'woo_customer' )
				$this->customer_id = $client->id;
			else if( $classtype == 'woo_orders' )
				$this->order_id = $client->id;
		}
	
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'address_id', 'type' => 'int(11)', 'auto_increment' => 'yup');
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP');
		$this->fields_array[] = array('name' => 'customer_id', 'type' => 'int(11)');
		$this->fields_array[] = array('name' => 'order_id', 'type' => 'int(11)');

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
		$this->fields_array[] = array('name' => 'crm_persons_id','type' => 'int(11)' , 		'comment' => 'FK FA CRM_PERSONS ID.', 'foreign_obj' => 'crm_persons' );

		$this->table_details['tablename'] = $this->company_prefix . "woo_address";
		$this->table_details['primarykey'] = "address_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "order_id,first_name,last_name,address_1,city,state";
		$this->table_details['index'][0]['keyname'] = "order-address_customer";
		$this->table_details['index'][1]['type'] = 'unique';
		$this->table_details['index'][1]['columns'] = "customer_id,first_name,last_name,address_1,city,state";
		$this->table_details['index'][1]['keyname'] = "customer-address_customer";
	}
	function woo2fa()
	{
		$this->match_crm_persons_by_email();
	}
	function match_crm_persons_by_email()
	{
		//In order to set the crm_persons_id we need to match our email address against
		//the table to determine the correct record.
		$person_array = array();
		$count = 0;
		$sql = "select p.* from " . $this->company_pref . "crm_persons p ";
		//Do I need to exclude inactive persons?
		$sql .= "where p.email='" . $this->email . "'";
		$res = db_query( $sql, __method__ . " Couldn't search for matching crm_person" );
		while( $p_data = db_fetch_assoc( $res ) )
		{
			//It is possible that the same email is in multiple persons in the CRM.
			//Ideally that would be because multiple contacts within an organization have
			//the same email.  However, we could also have the same person who belongs to
			//multiple organizations being our purchasing contact for each of them.
			//We'd then have to match more closely on addresses.
			$myclass = get_class( $this );
			$person = new $myclass($this->serverURL, $this->key, $this->secret, $this->options, $this->client);
			//Can't use extract_data_array because the column names don't match
			//and I don't have a translation routine written :(
			//$person->extract_data_array( $p_data ); 
			$person->customer_id = $p_data['id'];
			$person->first_name = $p_data['name'];
			$person->last_name = $p_data['name2'];;
			$person->address_1 = $p_data['address'];
			//var $address_2;
			//var $city;
			//var $state;
			//var $postcode;
			//var $country;
			$person->email = $p_data['email'];
			$person->phone  = $p_data['phone'];
			$score = $this->match_score( $person );
			//Should be able to match on at least the name and email.
			if( $score  > 3 )
			{
				$person_array[$count]['person'] = $person;
				$person_array[$count]['score'] = $score;
				$count++;
				$score = 0;
			}
		}
		if( 0 == $count )
		{
			//No matches found
			//Create debtor
			require_once( '../ksf_modules_common/class.fa_customer.php' );
			$customer = new fa_customer();
			/*
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
			 */
			$customer->CustName = $this->first_name . " " . $this->last_name;
			$customer->cust_ref = $this->first_name . " " . $this->last_name;
			$customer->phone = $this->phone;
			$customer->email = $this->email;
			$customer->address = $this->address_1 . '\n';
			$customer->address .= $this->address_2 . '\n';
			$customer->address .= $this->city . ", ";
			$customer->address .= $this->state . '\n';
			$customer->address .= $this->country . ", ";
			$customer->address .= $this->postcode . '\n';
			$customer->notes = $customer->CustName . '\n' . $this->company . '\n' . $customer->address . $this->email . '\n' . $this->phone . '\n';
			$customer->add_new_customer();
		}
		else
		{
		//Sort for best match
			$this->sort( $person_array );
		//Find organizatio/debtor
		//Update _address with person_id
		}
	}
	function treesort( $person_array, $count = 0, $root = array() )
	{
		$current_node = $root;
		while( null != $person_array[$count] )
		{
			if( isset( $current_node['score'] ) )
			{
				if( $person_array[$count]['score'] > $current_node['score'] )
				{
					//go right - score is greater than
					if( isset( $current_node['right'] ) )
					{
						$this->treesort( $person_array, $count, $current_node['right'] ); 
					}
					else
					{
						$current_node['right'] = $this->new_node( $current_node, $person_array[$count] );
						$count++;
					}
				}
				else
				{
					//go left - score is equal or less than
					if( isset( $current_node['left'] ) )
					{
						$this->treesort( $person_array, $count, $current_node['left'] ); 
					}
					else
					{
						$current_node['left'] = $this->new_node( $current_node, $person_array[$count] );
						$count++;
					}

				}
			}
			else
			{
				//first node
				$this->new_node( $current_node, $person_row );
			}
		}
	}
	function new_node( $current_node, $person_row )
	{
		$treenode = array();
		$treenode['score'] = $person_row['score'];
		$treenode['person'] = $person_row['person'];
		$treenode['left'] = null;
		$treenode['right'] = null;
		$treenode['parent'] = $current_node;
		return $treenode;
	}
	/***************************************************************************
	 *
	 *	Possible score of 10
	 *
	 ***************************************************************************/
	/*@int@*/function match_score( $person )
	{
		//Does the address and everything else match?
		$score = 0;
		if( strcasecmp ($this->first_name, $person->first_name ) == 0 )
		{
			$score++;
		}
		if( strcasecmp ($this->last_name, $person->last_name ) == 0 )
		{
			$score++;
		}
		if( strcasecmp ($this->email, $person->email ) == 0 )
		{
			$score++;
		}
		if( strcasecmp ($this->phone, $person->phone ) == 0 )
		{
			$score++;
		}
		//Address will need to be a soft match due to formatting
		if( stristr( $person->address_1, $this->address_1 ) )
		{
			$score++;
		}
		if( stristr( $person->address_1, $this->address_2 ) )
		{
			$score++;
		}
		if( stristr( $person->address_1, $this->city ) )
		{
			$score++;
		}
		if( stristr( $person->address_1, $this->state ) )
		{
			$score++;
		}
		if( stristr( $person->address_1, $this->postcode ) )
		{
			$score++;
		}
		if( stristr( $person->address_1, $this->country ) )
		{
			$score++;
		}

	}
}

?>
