<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );

class woo_coupons extends woo_interface {
	
	var $id;	//	integer 	Unique identifier for the object.  	read-only
	var $code;	//	string 	Coupon code.
	var $mandatory;
	var $date_created;	//	date-time 	The date the coupon was created, in the site’s timezone.  	read-only
	var $date_modified;	//	date-time 	The date the coupon was last modified, in the site’s timezone.  	read-only
	var $description;	//	string 	Coupon description.
	var $discount_type;	//	string 	Determines the type of discount that will be applied. Options: fixed_cart, percent, fixed_product and percent_product. Default: fixed_cart.
	var $amount;	//	string 	The amount of discount.
	var $expiry_date;	//	string 	UTC DateTime when the coupon expires.
	var $usage_count;	//	integer 	Number of times the coupon has been used already.	read-only
	var $individual_use;	//	boolean 	Whether coupon can only be used individually.
	var $product_ids;	//	array 	List of product ID’s the coupon can be used on.
	var $exclude_product_ids;	//	array 	List of product ID’s the coupon cannot be used on.
	var $usage_limit;	//	integer 	How many times the coupon can be used.
	var $usage_limit_per_user;	// 	integer 	How many times the coupon can be used per customer.
	var $limit_usage_to_x_items;	// 	integer 	Max number of items in the cart the coupon can be applied to.
	var $free_shipping;	// 	boolean 	Define if can be applied for free shipping.
	var $product_categories;	// 	array 	List of category ID’s the coupon applies to.
	var $excluded_product_categories;	// 	array 	List of category ID’s the coupon does not apply to.
	var $exclude_sale_items;	// 	boolean 	Define if should not apply when have sale items.
	var $minimum_amount;	// 	string 	Minimum order amount that needs to be in the cart before coupon applies.
	var $maximum_amount;	// 	string 	Maximum order amount allowed when using the coupon.
	var $email_restrictions;	// 	array 	List of email addresses that can use this coupon.
	var $used_by;	// 	array 	List of user IDs who have used the coupon. 

	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		if( isset( $client->id ) )
			$this->order_id = $client->id;		
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'woo_coupons_id', 	'type' => 'int(11)', 		'comment' => 'Index.', 'readwrite' => 'read', 'auto_increment' => 'anything');
		$this->fields_array[] = array('name' => 'updated_ts', 		'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'readwrite' => 'read');

		$this->fields_array[] = array('name' => 'id', 			'type' => 'int(11)', 		'comment' => ' 	Item ID', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'code', 		'type' => 'varchar(64)', 	'comment' => ' 	Tax rate code.', 'readwrite' => 'read');

		$this->fields_array[] = array('name' => 'mandatory', 		'type' => 'boolean', 	'comment' => 'Mandatory', 'readwrite' => 'readwrite'); 	
		$this->fields_array[] = array('name' => 'date_created', 	'type' => 'datetime', 		'null' => 'NOT NULL',  'readwrite' => 'read'); 	
		$this->fields_array[] = array('name' => 'date_modified', 	'type' => 'datetime', 		'null' => 'NOT NULL', 'readwrite' => 'read'); 	
		$this->fields_array[] = array('name' => 'description', 		'type' => 'varchar(64)', 	'comment' => 'Coupon Description.', 'readwrite' => 'readwrite'); 	
		$this->fields_array[] = array('name' => 'discount_type', 	'type' => 'varchar(64)', 	'comment' => 'Discount Type', 'readwrite' => 'readwrite'); 
							//Options: fixed_cart, percent, fixed_product and percent_product. Default: fixed_cart.
		$this->fields_array[] = array('name' => 'amount', 		'type' => 'varchar(64)', 	'comment' => 'Amount', 'readwrite' => 'readwrite'); 
		$this->fields_array[] = array('name' => 'expiry_date', 		'type' => 'datetime', 	'comment' => 'Expiry date', 'readwrite' => 'readwrite'); 
		$this->fields_array[] = array('name' => 'usage_count', 		'type' => 'int(11)', 		'comment' => 'usage count', 'readwrite' => 'read'); 
		$this->fields_array[] = array('name' => 'individual_use', 	'type' => 'boolean', 	'comment' => 'Individual Use.  Boolean', 'readwrite' => 'readwrite'); 
		//$this->fields_array[] = array('name' => 'product_ids', 		array 	List of product ID’s the coupon can be used on.
		//$this->fields_array[] = array('name' => 'exclude_product_ids', 		array 	List of product ID’s the coupon cannot be used on.
		$this->fields_array[] = array('name' => 'usage_limit', 		'type' => 'int(11)', 	'comment' => ' How many times can it be used', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'usage_limit_per_user', 'type' => 'int(11)', 	'comment' => 'How many times a customer can use it', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'limit_usage_to_x_items', 	'type' => 'int(11)', 		'comment' => 'max items in cart to use', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'free_shipping', 	 	'type' => 'boolean', 		'comment' => 'Boolean.  Free shipping?', 'readwrite' => 'readwrite');
		//$this->fields_array[] = array('name' => 'product_categories', 	 	array 	List of category ID’s the coupon applies to.
		//$this->fields_array[] = array('name' => 'excluded_product_categories', 	 	array 	List of category ID’s the coupon does not apply to.
		$this->fields_array[] = array('name' => 'exclude_sale_items', 	 	'type' => 'varchar(5)', 		'comment' => 'Boolean.  Exclude sale items?', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'minimum_amount', 	 	'type' => 'varchar(64)', 	'comment' => 'Minimum order amount before coupon applies', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'maximum_amount', 	 	'type' => 'varchar(64)', 	'comment' => 'Max order amount for coupon applies', 'readwrite' => 'readwrite'); 
		//$this->fields_array[] = array('name' => 'email_restrictions', 	 	array 	List of email addresses that can use this coupon.
		//$this->fields_array[] = array('name' => 'used_by', 	 		array 	List of user IDs who have used the coupon. 

		$this->table_details['tablename'] = $this->company_prefix . "woo_coupons";
		$this->table_details['primarykey'] = "woo_coupons_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "id,code";
		$this->table_details['index'][0]['keyname'] = "id-code";
	}
	function in_table_display( $field_array )
	{
		//ASSUMPTION we've already checked the readwrite attribute
		//and this is a writeable fields
		if( strncmp( $field_array['type'], "varchar", 7 ) == 0 
			OR strncmp( $field_array['type'], "int", 3 ) == 0 
		  )
		{

					label_row( $name . "(VC)", $this->$name );
		}
		else
		if( strncmp( $field_array['type'], "timestamp", 7 ) == 0 
			OR strncmp( $field_array['type'], "datetime", 7 ) == 0 
		  )
		{
					label_row( $name . "(DT)", $this->$name );
		}
		else
		if( strncmp( $field_array['type'], "boolean", 7 ) == 0 

		  )
		{
					label_row( $name . "(bool)", $this->$name );
		}

	}
	function coupons_form()
	{
		start_form();

		start_table();
		table_section_title(_("Coupons entry form"));
	//	label_row(_("No Transaction History (no inventory movement):"), NULL);
		label_row("&nbsp;", NULL);
		table_section(1);
		foreach( $this->fields_array as $field )
		{
			$name = $field['name'];
			if( isset( $field['readwrite'] ) )
			{
				if( $field['readwrite'] == 'read' )
				{
					//READ ONLY
					label_row( $name . "(RO)", $this->$name );
				}
				else
				{
					$this->in_table_display( $field );
				}
			}
			else
			{
				$this->in_table_display( $field );
			}
		}
		end_table();
		end_form();
	}
}


?>
