<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

/*******************************************************************************//**
 *
 *	Sends a category to WooCommerce and updates xref table
 *	If gets an error, then requests the data for the category (so can xref)
 *
 *TODO:
 *	Get the master list of categories and then insert into FA any that 
 *		don't exist (imply also xref)
 *	Delete any categories that we don't have in FA (cleanup function - side effects?)
 *	Extend to allow us to change menu order?
 *	Extend to build parent-child relationships
 *
 * BUGS: 
 * 	(unconfirmed/can't reproduce) when there is 2 spaces in the category name/description 
 * 	it doesn't necessarily get added into _xref
 *
 * **********************************************************************************/

require_once( 'class.woo_rest.php' );
require_once( 'class.woo_interface.php' );

class woo_category extends woo_interface{
	var $id;		//	integer 	Unique identifier for the resource.
	var $name;		//	string 	Category name.  required
	var $slug;		//	string 	An alphanumeric identifier for the resource unique to its type.
	var $parent;		//	integer 	The id for the parent of the resource.
	var $description;		//	string 	HTML description of the resource.
	var $display;		//	string 	Category archive display type. Default is default. Options: default, categorys, subcategories and both
	var $image;		//	array 	Image data. See Category Image properties
	var $menu_order;		//	integer 	Menu order, used to custom sort the resource.
	var $count;		// 	integer 	Number of published categorys for the resource.   RO
	var $fa_id;

	var $woo_rest;
	var $header_array;
	var $dumped_woocategories_count;
	var $loadcount;
	function __construct( $serverURL, $woo_rest_path, $key, $secret,  $options, $client, $enviro = "devel" )
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		$subpath = "products/categories";
		$data_array = array();
		$conn_type = "POST" ;
		$header_array = array();
		$header_array['Content-Type'] = "application/json";
		$this->dumped_woocategories_count = 0;
		$this->loadcount = 0;
		//$this->woo_rest = new woo_rest( $serverURL, $subpath, $data_array, $key, $secret, $conn_type, $woo_rest_path, $header_array, $enviro );
		return;
	}
	function define_table()
	{
		$this->fields_array[] = array('name' => 'woo_category_id', 	'type' => 'int(11)', 		'comment' => 'Index.', 'readwrite' => 'read', 'auto_increment' => 'anything');
		$this->fields_array[] = array('name' => 'updated_ts', 		'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'readwrite' => 'read');

		$this->fields_array[] = array('name' => 'id', 			'type' => 'int(11)', 		'comment' => ' 	Item ID', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'name', 		'type' => 'varchar(64)', 	'comment' => ' 	Category Name.', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'slug', 		'type' => 'varchar(64)', 	'comment' => ' 	Category Slug.', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'parent', 		'type' => 'int(11)', 		'comment' => ' 	Parent Category ID.', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'description', 		'type' => 'varchar(64)', 	'comment' => 'Category Description.', 'readwrite' => 'readwrite'); 	
		$this->fields_array[] = array('name' => 'display', 		'type' => 'varchar(64)', 	'comment' => 'Display Type.  default/caregories/subcategories/both.', 'readwrite' => 'readwrite'); 	
		//$this->fields_array[] = array('name' => 'image', 		'type' => 'int(11)', 		'comment' => ' 	Parent Category ID.', 'readwrite' => 'readwrite');.
		$this->fields_array[] = array('name' => 'menu_order', 		'type' => 'int(11)', 		'comment' => ' Menu Order.', 'readwrite' => 'readwrite');
		$this->fields_array[] = array('name' => 'count', 		'type' => 'int(11)', 		'comment' => ' number of published categories', 'readwrite' => 'read');
		$this->fields_array[] = array('name' => 'fa_id',		'type' => 'int(11)', 		'comment' => ' 	FA Category ID', 'readwrite' => 'read');

		$this->table_details['tablename'] = $this->company_prefix . "woo_category";
		$this->table_details['primarykey'] = "woo_category_id";
		$this->table_details['index'][0]['type'] = 'unique';
		$this->table_details['index'][0]['columns'] = "name";
		$this->table_details['index'][0]['keyname'] = "u-name";
		$this->table_details['index'][1]['type'] = 'unique';
		$this->table_details['index'][1]['columns'] = "slug";
		$this->table_details['index'][1]['keyname'] = "u-slug";
	}
	function build_properties_array()
	{
		/*All properties*/
		$this->properties_array = array(
			'id',
			'name',
			'slug',
			'description',
			'parent',
			'image',
			'menu_order',
			'count'
		);
	}
	function build_write_properties_array()
	{
		/*Took the list of properties, and removed the RO ones*/
		$this->write_properties_array = array(
			'name',
			'slug',
			'description',
			'parent',
			'image',
			'menu_order',
		);
	}
	/**********************************************************************************//**
	 *
	 *
	 * ***********************************************************************************/
	function error_handler( /*@Exception@*/$e )
	{
		if ( $e instanceof WC_API_Client_HTTP_Exception ) 
		{
			//$msg = $e->getMessage();
			////var_dump( $e->get_request() );
			////var_dump( $e->get_response() );
			switch( $e->getCode() ) {
			default:
				echo "<br />" . __FILE__ . ":" . __LINE__ . ":Unhandled Error Code: " . $e->getCode() . "<br />";
				break;
			}
		}
	}
	/**********************************************************************************//**
	 * Return the list of categories as an array of stdClass objects
	 *
	 * Only want to query WooCommerce once per run for the categories
	 *
	 * @param int category_id
	 * @param array arguments
	 * @return array of stdClass Objects of category (woo_category) data.
	 * ***********************************************************************************/
	/*@array of stdClass@*/function get_categories( $id = null, $args = null )
	{		
		if( $this->loadcount > 0 )
		{
			$this->notify( __METHOD__ . ":" . __LINE__ . " We've been here before. Leaving " . __METHOD__, "WARN" );
			//echo __METHOD__ . ":" . __LINE__ . " We've been here before. Leaving " . __METHOD__ ;
			return null;
		}

		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		//Get the master list of categories as WOO knows it.
		try
		{
			if( isset( $this->wc_client ) )
				$response = $this->wc_client->products->get_categories( $id, $args );		
			else
				throw new InvalidArgumentException( "WC_CLIENT not set" );
			if( $this->debug >= 2 )
			{
				echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				print_r( $response );
			}
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return $response->product_categories;
		}
		catch( Exception $e )
		{
			$this->error_handler( $e );
		}
	}
	/**************************************************************************************//**
	 * Get the details of 1 category from WooCommerce
	 *
	 *	requires ID to be set otherwise seeks first match by name
	 *
	 * @param none
	 * @returns bool did we find the category
	 * *************************************************************************************/
	/*@bool@*/function get_category()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		try {
			if( $this->id < 1 )
				$this->id = null;
			$args = null;	//array of fields.
			$response_array = $this->get_categories( $this->id, $args );

			//This will return an array of ALL categories if id is null.  
			//Need to cycle through them to find the one we are looking for :(
			//
			//	Object->product_categories array of stdClass which we should be able to cast to woo_categories...
			if( $this->match_category_name( $response->product_categories ) )
			{
				$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
				return TRUE;
			}
		} catch ( Exception $e ) {
		}
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		return FALSE;
	}
	/************************************************************************************************//**
	 * Match our name against the names in WooCommerce
	 *
	 * 	Sets our details to match WooCommerce
	 *
	 * @param array the response from wc_client query
	 * @returns bool did we find a match
	 * ***********************************************************************************************/
	/*@bool@*/function match_category_name( /*@array of stdClass@*/$product_categories )
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		foreach( $product_categories as $cobj )
		{
			if( $this->name == $cobj->name )
			{
				$this->id = $cobj->id;
				$this->slug = $cobj->slug;
				return TRUE;
			}
			else
			{
				//didn't match.  NEXT!
			}
		}
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		return FALSE;
	}
	/************************************************************************************************//**
	 * Match our slug against the slugs in WooCommerce
	 *
	 * 	Sets our details to match WooCommerce
	 *
	 * @param array the response from wc_client query
	 * @returns bool did we find a match
	 * ***********************************************************************************************/
	/*@bool@*/function match_category_slug( /*@array of stdClass@*/$product_categories )
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		foreach( $product_categories as $cobj )
		{
			if( $this->slug == $cobj->slug )
			{
				$this->id = $cobj->id;
				$this->slug = $cobj->slug;
				return TRUE;
			}
			else
			{
			}
		}
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		return FALSE;
	}
	/************************************************************************************//**
	 * When we don't have the woo category_id, see if one exists with our name
	 *
	 *	Requires that NAME is set
	 *
	 * @returns bool whether one is found
	 * **************************************************************************************/
	/*@bool@*/function seek_category_by_name()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		//In this case we DON'T know the woo category_id
		$this->id = null;
		if( ! isset( $this->name ) )
			throw new InvalidArgumentException( "Category Name not set" );
		if( $this->get_category() )
		{
			//We have found the category and ID
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return TRUE;
		}
		else
		{
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return FALSE;
		}
	}
	/************************************************************************************//**
	 * Take the returned array of categories data from WooCommerce and insert into here/xref
	 *
	 * This function has a check to ensure we only run once.  Else a logic error elsewhere
	 * has this function called repeatedly and we eventually time out.	
	 *
	 * @returns int count of categories we loaded
	 * **************************************************************************************/
	/*@int@*/function load_categories( /*array of objects*/$categories_array )
	{		
		if( $this->loadcount > 0 )
		{
			$this->notify( __METHOD__ . ":" . __LINE__ . " We've been here before. Leaving " . __METHOD__, "WARN" );
			return 0;
		}

		////var_dump( $categories_array );
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		

		$loadcount = 0;
		foreach( $categories_array as $cat )
		{ 
			set_time_limit( 300 );
			try
			{
				//$cat is an object
				if( $loadcount == 0 )
				{
					//dump once for each time we come into load_categories
					//$this->notify( __METHOD__ . ":" . __LINE__ . " Var Dump object ", "WARN" );
					////var_dump( $cat );
					$this->dumped_woocategories_count++;
				}
				foreach( $this->properties_array as $fieldname )
				{
					if( isset( $cat->$fieldname ) )
						$this->$fieldname = $cat->$fieldname;
				}
				$this->get_fa_id_by_category_name();
				//We are seeing entries where the description is blank!
				//$this->notify( __METHOD__ . ":" . __LINE__ . " Var Dump this looking for blank name/description ", "WARN" );
				////var_dump( $this );
				//echo "<br /><br />";
				$this->update_woo_categories_xref();
				$this->insert_table();
				/*
				$wci = $this->select_table( "woo_category_id as woo_category_id", array( 'description' => $this->description ), null, 1 );
				if( isset( $wci ) )
				{
					foreach( $wci as $key => $value )
					{
						$this->$key = $value;
					}
					$this->update_table();
				}
				 */
				$this->reset_values();
				$loadcount++;
			}
			catch( Exception $e )
			{
				$this->notify( __METHOD__ . ":" . __LINE__ . " Exception " . $e->getCode() . " with message " . $e->getMessage() );
			}
		}
		if( $loadcount > 0 and $loadcount > $this->loadcount )
			$this->loadcount = $loadcount;
		else
			$this->loadcount++;
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		//We should also tell any controller event loop listeners that we updated the list
		//table woo among others could then be updated...
		return $loadcount;
	/************************************************************************//**
	 * Given a category description, find the FA Category ID
	 *
	 * @param noe but depends on description
	 * @returns bool success or not.   sets fa_id on success
	 * *************************************************************************/
	/*@bool@*/function get_fa_id_by_category_name()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		//echo __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__ . "<br /><br />";
		try
		{
			$sql = "select category_id as id from " . TB_PREF . "stock_category where description=" . db_escape($this->description);
			//var_dump( $sql );
			$res = db_query( $sql, __LINE__ . " Couldn't select from stock_category" );
			while( $cat_data = db_fetch_assoc( $res ) )
			{
				if( $this->debug > 0 )
					echo __METHOD__ . ":" . __LINE__ . " Setting fa_id to " . $cat_data['id'] . " for description " .  $this->description . "<br /><br />";
				$this->fa_id = $cat_data['id'];
			}
			//echo __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__ . "<br /><br />";
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return TRUE;
		}
		catch( Exception $e )
		{
			throw $e;
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return FALSE;
		}
	}
	}
	/************************************************************************//**
	 * Given a category name, find the FA Category ID
	 *
	 * @param noe but depends on name
	 * @returns bool success or not.   sets fa_id on success
	 * *************************************************************************/
	/*@bool@*/function get_fa_id_by_category_name()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		//echo __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__ . "<br /><br />";
		try
		{
			$sql = "select category_id as id from " . TB_PREF . "stock_category where description=" . db_escape($this->name);
			//var_dump( $sql );
			$res = db_query( $sql, __LINE__ . " Couldn't select from stock_category" );
			while( $cat_data = db_fetch_assoc( $res ) )
			{
				if( $this->debug > 0 )
					echo __METHOD__ . ":" . __LINE__ . " Setting fa_id to " . $cat_data['id'] . " for description " .  $this->name . "<br /><br />";
				$this->fa_id = $cat_data['id'];
			}
			//echo __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__ . "<br />";
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return TRUE;
		}
		catch( Exception $e )
		{
			throw $e;
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return FALSE;
		}
	}
	function update_woo_categories_xref()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		require_once( 'class.woo_categories_xref.php' );
		$xref = new woo_categories_xref( null, null, null, null, $this );
		$xref->fa_cat = $this->fa_id;
		$xref->woo_cat = $this->id;
		$xref->description = $this->description;
		//We are setting blank descriptions.  WHY?
		if( $xref->description == '' )
		{
			$this->notify( __METHOD__ . ":" . __LINE__ . " Blank Description for FA-ID " . $this->fa_id, "WARN" );
			//echo __METHOD__ . ":" . __LINE__ . " Blank Description for FA-ID " . $this->fa_id . "<br /><br />";
			////var_dump( $this );
			////var_dump( $xref );
		}
		else
		{
			$this->notify( __METHOD__ . ":" . __LINE__ . " Description for FA-ID " . $this->fa_id . " is " . $xref->description, "WARN" );
			//echo __METHOD__ . ":" . __LINE__ . " Description for FA-ID " . $this->fa_id . " is " . $xref->description . "<br /><br />";
		}
		$xref->insert_table();
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		return;
	}
	/****************************************************************************************************//**
	 *
	 * 
	 * Will throw an OutOfBoundsException if we need to reset our query to restart
	 *
	 * ****************************************************************************************************/
	/*@bool@*/function create_category()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		$extractcount = 0;
		/*
		curl -X POST http://fhsws001.ksfraser.com/wp-json/wc/v1/products/categories -u consumer_key:consumer_secret -H "Content-Type: application/json" -d '{
			"name": "Clothing",
			  "image": {
			    "src": "http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_front.jpg"
			  }
		}'
		 */
		/*************************************************************************************************************************************************/
		try {
			$this->build_data_array();
			$response = $this->wc_client->products->create_categories(  $this->data_array );
			/*
			if( $this->debug >= 1 )
			{
				echo __FILE__ . ":" . __LINE__ . "<br />";
				//var_dump( $response );
			}
			 */
			$this->id = $response->product_category->id;
			$this->update_woo_categories_xref();
			$this->insert_table();
			//There should be a response, we need to update our xref table so we know what the cat WOO ID is
			//$this->extract_data_obj( $response->product );
			//$this->update_wootable_woodata();	
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			return TRUE;	//Should we return false until the category is updated?

		} catch ( WC_API_Client_Exception $e ) {
			if ( $e instanceof WC_API_Client_HTTP_Exception ) 
			{
				//echo __METHOD__ . ":" . __LINE__ . " We hit an error.  Here is the data we sent! <br />";
				//var_dump( $this->data_array );
				//echo "<br />";
				$this->code = $e->getCode();
				$this->msg = $e->getMessage();
				//Moved into fcn rest_error_handler in other classes...
				switch( $this->code )
				{
					case "400":
					case "woocommerce_api_missing_callback_param":
					case "woocommerce_api_missing_product_category_data":
					case "woocommerce_api_cannot_create_product_category":
						echo "<br />" . __FILE__ . ":" . __LINE__ . " Code " . $this->code . " with message " . $this->msg . "<br />";
						$this->notify( __METHOD__ . ":" . __LINE__ . " Get Categories " . __METHOD__, "WARN" );
						$cat_array = $this->get_categories();
						if( isset( $cat_array ) )
						{
							$this->notify( __METHOD__ . ":" . __LINE__ . " Load Categories into our Woo tables " . __METHOD__, "WARN" );
							$this->load_categories( $cat_array );
							throw new OutOfBoundsException( "Reset and try again" );
						}
						////var_dump( $e->get_request() );
						//NEED to reset the process so that the ones we just LOADED aren't run hitting this error...
					break;
					case "404":
					case "woocommerce_api_no_route":
						echo "<br />" . __FILE__ . ":" . __LINE__ . "No Route (API) " . $this->code . " with message " . $this->msg . "<br />";
						//var_dump( $this->wc_client->products );
					break;
					case "woocommerce_rest_category_sku_already_exists":
						$this->get_category();
						break;
					case "rest_invalid_param":
						break;
					case "term_exists":
						echo "<br />" . __FILE__ . ":" . __LINE__ . "Code " . $this->code . " with message " . $this->msg . "<br />";
						//var_dump( $e->get_request() );
						echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
						//var_dump( $e->get_response() );
						echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
						$this->match_category();
						//$this->id = $response->data;
						echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
						if( $this->id > 0 )
							$this->update_woo_categories_xref();
						echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
						break;
					default:
						echo "<br />" . __FILE__ . ":" . __LINE__ . "Unhandled Code " . $this->code . " with message " . $this->msg . "<br />";
						//var_dump( $this->wc_client );
						exit();
					break;
				}
			}
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		return FALSE;
		}
		
	}
	function retrieve_category()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		if( $this->debug >= 1 )
		{
			echo "<br />" . __METHOD__ . ":" . __LINE__ . "<br />";
		}
		/*
		curl https://example.com/wp-json/wc/v1/products/categories/162 -u consumer_key:consumer_secret
		 * 
		 * */
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
	}
	function update_category()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		if( $this->debug >= 1 )
		{
			echo "<br />" . __METHOD__ . ":" . __LINE__ . "<br />";
		}
		/*
		curl -X PUT https://example.com/wp-json/wc/v1/products/categories/162  -u consumer_key:consumer_secret  -H "Content-Type: application/json"  -d '{ "regular_price": "24.54" }'
		 * 
		 * */
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
	}
	function list_categorys()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		if( $this->debug >= 1 )
		{
			echo "<br />" . __METHOD__ . ":" . __LINE__ . "<br />";
		}
		/*
		 * 	GET
		 *	curl https://example.com/wp-json/wc/v1/products/categories -u consumer_key:consumer_secret
		*/
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
	}
	/****************************************************************************************************//**
	 * Send categories to WooCommerce
	 *
	 * @returns count of categories sent
	 * ****************************************************************************************************/
	/*@int@*/function send_categories_to_woo( )
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entering " . __METHOD__, "WARN" );
		$category_sql = "select category_id, description from " . TB_PREF . "stock_category where category_id not in (select fa_cat from " . TB_PREF . "woo_categories_xref ) order by category_id asc";
		$res = db_query( $category_sql, __LINE__ . " Couldn't select from stock_category" );
		$catcount = 0;
		$sentcount = 0;
		while( $cat_data = db_fetch_assoc( $res ) )
		{
			$catcount++;
			$this->notify( __METHOD__ . ":" . __LINE__ . " Var_dump cat_data from stock_category", "WARN" );
			//var_dump( $cat_data );
			//No point trying to send a blank item to Woo
			if( strlen( $cat_data['description'] ) > 1 )
			{
				$this->id = null;
				$this->name = $cat_data['description'];
				$this->slug= $cat_data['description'];
				$this->description= $cat_data['description'];
				//$this->image;
				$this->menu_order= $cat_data['category_id'];
				$this->fa_id= $cat_data['category_id'];
				if( $this->debug >= 1 )
					$this->notify( __METHOD__ . ":" . __LINE__ . " Sending  " . $this->name . "::ID " . $this->fa_id, "NOTIFY" );
				try
				{
					$ret = $this->create_category();
					if( $ret == TRUE )
						$sentcount++;
				}
				catch( OutOfBoundsException $e )
				{
					//Reset since we sent an item that exists.  Resulting in us loading from WooCommerce the list of categories
					$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving (recursively) " . __METHOD__, "WARN" );
					return $this->send_categories_to_woo() + $sendcount;
				}
			}
			else
				if( $this->debug >= 0 )
					$this->notify( __METHOD__ . ":" . __LINE__ . " CatID " . $cat_data['category_id'] . " Strlen of description < 1.  Sent: " . $sentcount . " Catcount: " . $catcount, "ERROR" );
				//display_notification( "Woo seems to have all of our categories" );
		}
		$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		return $sentcount;
	}

}

?>
