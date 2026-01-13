<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array appropriately
 * */

/*******************************************************************************************//**
 * This module is for handling frontaccounting to WooCommerce products.
 *
 * Any activities storing product data in FA is done through the woo class. (May be
 * some fcns here that need to be migrated)
 *
 * Any activites connecting to an outside web service will be (eventually) done through the
 * woo_rest class (May be some fcns here that need to be migrated).  Currently use wc-api
 *
 *
 * TODO:
 *	Add variable product sending
 *	FIx Get By SKU so that we can match up product numbers for existing products
 *		so that we can send updates for attributes
 *	Migrate to own REST library once it is rewritten
 *	Be able to request products and do something with them
 *		What if anything do we want to update in FA since it is the source of record.
 *	Extend so that IN STOCK shows OUT for categories that we don't keep on hand
 *	Extend so we can have backorders on some categories/products and not others.
 *	Related Modules waiting to be written:
 *		Related IDs
 *		Cross Sell IDs
 *		Upsell IDs
 *		Parent Posts
 *		(post) Purchase Notes
 *		featured (sale?)
 *		downloadable product details etc
 *		backorders
 *		sell individually
 *		reviews and ratings
 *		tags
 *		multiple categories
 *		grouped products
 *
 *	This routine AS IS is pushing about 13 products a minute from FA to WooCommerce
 *	Note there is logging going on in every routine.
 *	Note that the connection is same machine to same machine
 *		Apache involved on both
 *		MySQL involved on both
 *		Sockets and task queues
 *
 * ***********************************************************************************************/

require_once( 'class.woo_rest.php' );
require_once( 'class.woo_interface.php' );
require_once( 'EXPORT_WOO.inc' );

class woo_product extends woo_interface {
	var $id;	//integer 	Unique identifier for the resource.  read-only
	var $name;	//string 	Product name.
	var $title;
	var $slug;	//string 	Product slug.
	var $permalink;	//string 	Product URL.  read-only
	var $created_at;	//date-time 	The date the product was created, in the site’s timezone.  read-only
	var $updated_at;	//date-time 	The date the product was last modified, in the site’s timezone.  read-only
	var $type;	//string 	Product type. Default is simple. Options (plugins may add new options): simple, grouped, external, variable.
	var $status;	//string 	Product status (post status). Default is publish. Options (plugins may add new options): draft, pending, private and publish.
	var $featured;	//boolean 	Featured product. Default is false.
	var $catalog_visibility;	//string 	Catalog visibility. Default is visible. Options: visible (Catalog and search), catalog (Only in catalog), search (Only in search) and hidden (Hidden from all).
	var $description;	//string 	Product description.
	var $short_description;	//string 	Product short description.
	var $sku;	//string 	Unique identifier.
	var $price;	//string 	Current product price. This is set from regular_price and sale_price.  read-only
	var $regular_price;	//string 	Product regular price.
	var $sale_price;	//string 	Product sale price.
	var $date_on_sale_from;	//string 	Start date of sale price. Date in the YYYY-MM-DD format.
	var $date_on_sale_to;	//string 	Sets the sale end date. Date in the YYYY-MM-DD format.
	var $price_html;	//string 	Price formatted in HTML, e.g. <del><span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&#36;&nbsp;3.00</span></span></del> <ins><span class=\"woocommerce-Price-amount amount\"><span class=\"woocommerce-Price-currencySymbol\">&#36;&nbsp;2.00</span></span></ins> read-only
	var $on_sale;	//boolean 	Shows if the product is on sale.  read-only
	var $purchasable;	//boolean 	Shows if the product can be bought.  read-only
	var $total_sales;	//integer 	Amount of sales.  read-only
	var $virtual;	//boolean 	If the product is virtual. Virtual products are intangible and aren’t shipped. Default is false.
	var $downloadable;	//boolean 	If the product is downloadable. Downloadable products give access to a file upon purchase. Default is false.
	var $downloads;	//array 	List of downloadable files. See Downloads properties.
	var $download_limit;	//integer 	Amount of times the product can be downloaded, the -1 values means unlimited re-downloads. Default is -1.
	var $download_expiry;	//integer 	Number of days that the customer has up to be able to download the product, the -1 means that downloads never expires. Default is -1.
	var $download_type;	//string 	Download type, this controls the schema on the front-end. Default is standard. Options: 'standard' (Standard Product), application (Application/Software) and music (Music).
	var $external_url;	//string 	Product external URL. Only for external products.
	var $button_text;	//string 	Product external button text. Only for external products.
	var $tax_status;	//string 	Tax status. Default is taxable. Options: taxable, shipping (Shipping only) and none.
	var $tax_class;	//string 	Tax class.
	var $manage_stock;	//boolean 	Stock management at product level. Default is false.
	var $managing_stock;	//boolean 	Stock management at product level. Default is false.
	var $stock_quantity;	//integer 	Stock quantity. If is a variable product this value will be used to control stock for all variations, unless you define stock at variation level.
	var $in_stock;	//boolean 	Controls whether or not the product is listed as “in stock” or “out of stock” on the frontend. Default is true.
	var $backorders;	//string 	If managing stock, this controls if backorders are allowed. If enabled, stock quantity can go below 0. Default is no. Options are: no (Do not allow), notify (Allow, but notify customer), and yes (Allow).
	var $backorders_allowed;	//boolean 	Shows if backorders are allowed.  read-only
	var $backordered;	//boolean 	Shows if a product is on backorder (if the product have the stock_quantity negative).  read-only
	var $sold_individually;	//boolean 	Allow one item to be bought in a single order. Default is false.
	var $weight;	//string 	Product weight in decimal format.
	var $dimensions;	//array 	Product dimensions. See Dimensions properties.
	var $shipping_required;	//boolean 	Shows if the product need to be shipped.  read-only
	var $shipping_taxable;	//boolean 	Shows whether or not the product shipping is taxable.  read-only
	var $shipping_class;	//string 	Shipping class slug. Shipping classes are used by certain shipping methods to group similar products.
	var $shipping_class_id;	//integer 	Shipping class ID.  read-only
	var $reviews_allowed;	//boolean 	Allow reviews. Default is true.
	var $average_rating;	//string 	Reviews average rating.  read-only
	var $rating_count;	//integer 	Amount of reviews that the product have.  read-only
	var $related_ids;	//array 	List of related products IDs (integer).  read-only
	var $upsell_ids;	//array 	List of up-sell products IDs (integer). Up-sells are products which you recommend instead of the currently viewed product, for example, products that are more profitable or better quality or more expensive.
	var $cross_sell_ids;	//array 	List of cross-sell products IDs. Cross-sells are products which you promote in the cart, based on the current product.
	var $parent_id;	//integer 	Product parent ID (post_parent).
	var $purchase_note;	//string 	Optional note to send the customer after purchase.
	var $categories;	//array 	List of categories. See Categories properties.
	var $tags;	//array 	List of tags. See Tags properties.
	var $images;	//array 	List of images. See Images properties
	var $attributes;	//array 	List of attributes. See Attributes properties.   USED for variations
	var $default_attributes;	//array 	Defaults variation attributes, used only for variations and pre-selected attributes on the frontend. See Default Attributes properties.
	var $variations;	//array 	List of variations. See Variations properties
	var $grouped_products;	//string 	List of grouped products ID, only for group type products.  read-only
	var $menu_order;		//integer 	Menu order, used to custom sort products.
		/*******/
	var $woo_rest;
	var $header_array;
	var $woo_rest_path_base;
	var $woo_rest_path;
	var $subpath;
	var $woo_products_list;	//!< Array of products returned by woo through get_products for match_product
	var $wc_client;	//!< WC_API_Client to be migrated into the woo_rest class
	var $caller; //!< Class which class instantiated this one
	var $products_sent;
	var $products_updated;
	var $get_product_by_sku_count; //every time we go in there we can get 10 items back from woo_commerce.  Can use to change which page...

	var $force_update; //!< grabbing from client (i.e. EXPORT_WOO)
	var $send_images;

	/***************************************************************************************//**
	 *
	 *
	 * ****************************************************************************************/
	function __construct( $serverURL, $woo_rest_path,
				$key, $secret, $enviro = "devel", $client = null )
	{
		$this->serverURL = $serverURL;
		$this->enviro = $enviro;
		$this->subpath = "products";
		$this->conn_type = "POST" ;
		$this->woo_rest_path_base = $woo_rest_path;
		$this->woo_rest_path = $woo_rest_path;
		$this->caller = $client;
		$options = array();
	
		set_time_limit( 300 );
		parent::__construct($serverURL, $key, $secret, $options, $client);
		$this->products_sent = $this->products_updated = 0;
		if( isset( $client ) )
			if( isset( $client->force_update ) )
				$this->force_update = $client->force_update;
			if( isset( $client->send_images ) )
				$this->send_images = $client->send_images;
			else
				$this->send_images = FALSE;	//testing is failing but that could be a local machine problem
		return;
	}
	//function notify (inherited from woo_interface)
	function new_woo_rest()
	{
		$this->build_data_array();
		$this->woo_rest = new woo_rest( $this->serverURL, $this->subpath, $this->data_array, $this->key, $this->secret, $this->conn_type, $this->woo_rest_path, null, $this->enviro, $this->debug );
	}
	/*********************************************************************************************//**
	 * Woo_Interface now builds the properties_array and write_properties_array from the defined table!
	 * 
	 * ***********************************************************************************************/
	function build_properties_array()
	{
		/*All properties*/
		$this->properties_array = array(
			'id',
			'name',
			'slug',
			'permalink',
			'created_at',
			'updated_at',
			'type',
			'status',
			'featured',
			'catalog_visibility',
			'description',
			'short_description',
			'sku',
			'price',
			'regular_price',
			'sale_price',
			'date_on_sale_from',
			'date_on_sale_to',
			'price_html',
			'on_sale',
			'purchasable',
			'total_sales',
			'virtual',
			'downloadable',
			'downloads',
			'download_limit',
			'download_expiry',
			'download_type',
			'external_url',
			'button_text',
			'tax_status',
			'tax_class',
			'manage_stock',
			'managing_stock',
			'stock_quantity',
			'in_stock',
			'backorders',
			'backorders_allowed',
			'backordered',
			'sold_individually',
			'weight',
			'dimensions',
			'shipping_required',
			'shipping_taxable',
			'shipping_class',
			'shipping_class_id',
			'reviews_allowed',
			'average_rating',
			'rating_count',
			'related_ids',
			'upsell_ids',
			'cross_sell_ids',
			'parent_id',
			'purchase_note',
			'categories',
			'tags',
			'images',
			'attributes',
			'default_attributes',
			'variations',
			'grouped_products',
			'menu_order',
		);
	}
	/*********************************************************************************************//**
	 * Woo_Interface now builds the properties_array and write_properties_array from the defined table!
	 * 
	 * ***********************************************************************************************/
	function build_write_properties_array()
	{
		/*Took the list of properties, and removed the RO ones*/
		$this->write_properties_array = array('name',
			'slug',
			'type',
			'status',
			'featured',
			'catalog_visibility',
			'description',
			'short_description',
			'sku',
			'regular_price',
			'sale_price',
			'date_on_sale_from',
			'date_on_sale_to',
			'virtual',
			'downloadable',
			'downloads',
			'download_limit',
			'download_expiry',
			'download_type',
			'external_url',
			'button_text',
			'tax_status',
			'tax_class',
			'manage_stock',
			'managing_stock',
			'stock_quantity',
			'in_stock',
			'backorders',
			'sold_individually',
			'weight',
			'dimensions',
			'shipping_class',
			'reviews_allowed',
			'upsell_ids',
			'cross_sell_ids',
			'parent_id',
			'purchase_note',
			'categories',
			'tags',
			'images',
			'attributes',
			'default_attributes',
			'variations',
			'menu_order',
			'name', 'title'
		);
	}
	/*@int@*/function send_products()
	{
		$this->products_sent = $this->products_updated = 0;
		try
		{
			$count = $this->send_simple_products();	//sets products_sent
			if( $this->debug < 2 )
				$count += $this->update_simple_products(); //sets products_updated
			//Not worrying about the variable products for the moment...
			return $count;
		}
		catch( Exception $e )
		{
			throw $e;
		}
	}
	/*******************************************************************************************//**
	 * Updates the internal woo table with data from WooCommerce that we ARENT the source of record 
	 *
	 *	requires that ID is set
	 *
	 * @param none
	 * @returns bool did we update WOO
	 * ******************************************************************************************/
	/*@bool@*/function update_wootable_woodata()
	{
		$this->notify( __METHOD__ . ":" . __LINE__ . " Entered " . __METHOD__, "WARN" );
		echo __METHOD__ . ":" . __LINE__ . " Entered " . __METHOD__;
		if( ! isset( $this->updated_at ) )
		{
			$this->updated_at = date("Y-m-d");
		}
		if( ! isset( $this->id ) )
		{
			throw new InvalidArgumentException( __METHOD__ . ":" . __LINE__ . "ID from woo not set" );
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			echo __METHOD__ . ":" . __LINE__ . " Bad Arg Leaving " . __METHOD__;
			return;
		}
		if( ! isset( $this->sku ) )
		{
			throw new InvalidArgumentException( __METHOD__ . ":" . __LINE__ . " SKU from woo not set" );
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			echo __METHOD__ . ":" . __LINE__ . " Bad Arg Leaving " . __METHOD__;
			return;
		}
		require_once( 'class.woo.php' );
		//standard constructor args...	$this->serverURL, $this->key, $this->secret, $this->options, $this
		$woo = new woo( $this->serverURL, $this->key, $this->secret, $this->options, $this );
		$woo->woo_last_update = $this->updated_at;
		$woo->updated_ts = $this->updated_at;
		$woo->woo_id = $this->id;
		$woo->date_on_sale_from = $this->date_on_sale_from;
		$woo->date_on_sale_to = $this->date_on_sale_to;
		$woo->tax_status = $this->tax_status;
		$woo->stock_id = $this->sku;
		try
		{
			echo __METHOD__ . ":" . __LINE__ . " Update WOO ";
			$woo->update_on_sale_data();
			$woo->update_woo_id();
			$woo->update_woo_last_update();
		}
		catch( InvalidArgumentException $e )
		{
			echo "Exception thrown in " . $e->getFile() . " at line " . $e->getLine() . " with message " . $e->getMessage();
			return FALSE;
		}
		catch( Exception $e )
		{
			throw $e;
			return FALSE;
		}
		finally
		{		
			display_notification( __LINE__ . " (Tried) Updated wootable sku " . $this->sku . " with dates and id_" . $this->id . "_" );
			$this->notify( __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN" );
			echo __METHOD__ . ":" . __LINE__ . " Leaving " . __METHOD__;
		}
		return TRUE;
	}
	function delete_by_sku( $sku )
	{
		return null;
		//This code hasn't been designed nor tested so any existing code is unreliable...
		require_once( 'class.woo.php' );
		$woo = new woo( $this->serverURL, $this->key, $this->secret, $this->options, $this );
		$woo->delete_by_sku( $sku );
	}
	/*********************************************************************************//**
	 *
	 * Handle the response codes from the server.
	 *
	 * @return bool 
	 *
	 * ********************************************************************************/
	/*@bool@*/function rest_error_handler( $caller, $exception = null )
	{
		$result = false;
		if( isset( $exception ) )
		{
			if( !isset( $this->code ) )
			{
				$this->code = $exception->getCode();
				$this->message = $exception->getMessage();
			}
		}
		if( isset(  $this->code ) )
		{
			$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " CODE " . $this->code . " and msg " . $this->message, "NOTIFY");
			switch( $this->code ){
			case "woocommerce_rest_product_sku_already_exists":
				//WOO does NOT return the ID like for categories :(
				if ($this->get_product_by_sku( $this->sku ) )
				{
					$this->update_wootable_woodata();	
					return $this->update_product();
				}
				return FALSE;
			break;
			case "woocommerce_api_invalid_remote_product_image":
				//Does WooCommerce insert the product even though the image fails?
				$this->notify("<br />" . __METHOD__ . ":" . __LINE__ . "<Br />", "WARN");
				if( $this->debug > 0 ) //debug!
				{
					echo "<br />" . __METHOD__ . ":" . __LINE__ . "<Br />";
					var_dump( $this->data_array );
				}
				//Just because the image is bad doesn't mean the insert doesn't happen
				//need to update FA so we don't try to send (vice update) every time!
				$response = $exception->get_response();
				var_dump( $response );
				echo "<br /><br />";
				var_dump( $exception->get_request() );
				echo "<br /><br />";
				var_dump( $this->data_array );
				exit();
				/*
				echo "<br /><br />" . __METHOD__ . ":" . __LINE__ . "<Br />";
				var_dump( $response->body );
				echo "<br />Body decoded: " . json_decode( $response->body );
				echo "<br /><br />";
				 */
				/*
				$this->id = $response->product->id;
				$this->update_wootable_woodata();	
				$this->products_sent++;
				 */
				
			break;
			case "woocommerce_api_product_sku_already_exists":
				if( "create_product" == $caller )
				{
					$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ , "NOTIFY");

					if ($this->get_product_by_sku( $this->sku ) )
					{
						$this->notify( __METHOD__  . ":" . __LINE__ , "NOTIFY");
						$this->update_wootable_woodata();	
						//$this->woo2wooproduct( $this->sku );
						$result = $this->update_product();
						$this->notify( __METHOD__  . ":" . __LINE__ , "NOTIFY");
					}
					else
					{
						//assumption match_product goes through list from WOO and finds right one?
						//match runs get_product_by_sku so could simplify...
						$this->id = -1;
						$this->match_product();
						$result = $this->update_wootable_woodata();
					}

				}
				$this->notify( __METHOD__  . ":" . __LINE__, "WARN" );
				return $result;
			break;
			case 400:	//"woocommerce_api_product_sku_already_exists"
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " UNHANDLED CODE " . $this->code . " and msg " . $this->message, "ERROR");
				if( $this->client->remote_img_srv )
					$this->notify( __METHOD__  . ":" . __LINE__ . " If remote server, could be the file doesn't exist for images 1-10", "WARN" );
				var_dump( $exception );
				if( $this->debug > 2 )
					exit();
				return FALSE;
			break;
			case "Invalid SKU":
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN" );
				return FALSE;
			break;
			case "woocommerce_api_no_route":
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__. "WARN no route for URL and Req Method in caller " . $caller . ".  Could be because of SSL Cert issues. wc_client: " . var_dump( $this->wc_client ) . "<br /><br />", "WARN" );
				return FALSE;
			case "woocommerce_api_authentication_error":
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " WARN Auth Error in caller " . $caller . ". Check SSL (https).  wc_client: " . var_dump( $this->wc_client ) . "<br /><br />", "WARN" );
				return FALSE;
			case 302:
				case "Setup_CONFIG":
					$this->notify( __METHOD__  . ":" . __LINE__ . " CODE " . $this->code . ".  Appears to be a Setup Config issue.<br /><br />", "WARN");
					return FALSE;
			break;
			default:
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " UNHANDLED CODE " . $this->code . " and msg " . $this->message. "<br /><br />", "WARN");
				var_dump( $exception );
				if( $this->debug > 2 )
					exit();
				return FALSE;
			break;
			}
		}
	}	
	/************************************************************************************************//**
	 * Get a list of products from WooCommerce.
	 *
	 * WooCommerce by default sends 10 products.
	 *
	 * Sets woo_products_list with the array of product data.
	 *
	 * @param string stock_id/sku
	 * @param int page number
	 * @returns null
	 * ************************************************************************************************/
	/*@null@*/function get_products( $id = null, $page = 1 )
	{
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN" );
		//Get the master list of categories as WOO knows it.
		$args = array();	//array of fields.
		//Woo only sends 10 items back at a time :(/  per_page is supposed to change that...
		//$args['posts_per_page'] = 500;	//No EFFECT
		//$args['per_page'] = 500;	//No apparant EFFECT
		//We can cycle through the 10 at a time using page... tested via example.php and works
		$args['page'] = $page;	//Works in example.php
		$args['filter'] = array( 'category' => 'Kilt', 'limit' => '100' );
		//In web gui product_cat is a filter.  filter_action=Filter is also set though.  Doesn't work in example.php :(
		$response = $this->wc_client->products->get( $id, $args );
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN" );
		if( $this->debug >= 3 )
		{
			print_r( $response );
		}
		$this->woo_products_list = $response->products;
		return;
		
	}
	/************************************************************************************//**
	 * Match returned products against the sku we have set.
	 *
	 * We can get 10 items to update the woo_id of by not having sku set!
	 *
	 * @param bool should we send the woo_id if requesting a list of products
	 * **************************************************************************************/
	/*@bool@*/function match_product( $sendid = FALSE )
	{
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN" );
		$count = count( $this->woo_products_list );
		if(  $count < 1 )
		{
			//This will return an array of ALL products.  
			if( $sendid and isset( $this->id ) )
				$this->get_products( $this->id );	//can't send id if not set!
			else
				$this->get_products();
		}
		else
		{
			//$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN" );
		}
		//Need to cycle through them to find the one we are looking for :(
		//
		//	array of stdClass which we should be able to cast to woo_categories...
		foreach( $this->woo_products_list as $pobj )
		{
			if( $this->sku == $pobj->sku )
			{
				if( $this->sku != ' ' AND $this->sku != '')
				{
					$this->id = $pobj->id;
					return TRUE;
				}
				else
				{
					$this->notify( __METHOD__ . " Blank SKU: " . $this->sku . " Desc: " . $this->description, "ERROR" );
				}
			}
			else
			{
				//update SKUs with IDs?  Check if they are already set?
				$this->notify( __METHOD__ . ":" . __LINE__  , "NOTIFY" );
				$upd_obj = new woo_product( $this->serverURL, $this->woo_rest_path,
							$this->key, $this->secret, $this->enviro, $this->client);
				$upd_obj->sku = $pobj->sku;
				$upd_obj->id = $pobj->id;
				$upd_obj->update_wootable_woodata();
				if( $this->debug >= 3 )
				{
					echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " var dump<br />";
					var_dump( $pobj );
				}
			}
		}
		return FALSE;
	}

	/****************************************************************************//**
	 * This function should send a new product to WooCommerce, "creating" it.
	 *
	 * Assumption is that this object has all of the data set appropriately
	 * for us to conver the variables into an array, and pass it along.
	 *
	 * ******************************************************************************/
	/*@bool@*/function create_product()
	{
		
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN" );
		//This should reset the time limit so every time we come in here
		//with a new product it should have 20 seconds to round-trip
		//there may be other time limits that come into play but at
		//least from the php interpreter this should reset for each
		//one
		set_time_limit( 60 );
	
		if( isset( $this->wc_client ) )
		{
			try 
			{	$this->build_data_array();
				//if( $this->wc_client->products->create( array( $this ) ) )
				if( $response = $this->wc_client->products->create( $this->data_array ) )
				{
					echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " var dump<br />";
					var_dump( $response );
					$this->id = $response->product->id;
					$this->update_wootable_woodata();	
					$this->products_sent++;
				}
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Leaving Create Product", "WARN");
				return TRUE;
			} 
			catch ( WC_API_Client_Exception $e ) 
			{
				if ( $e instanceof WC_API_Client_HTTP_Exception ) 
				{
					/*
					$this->code = $e->getCode();
					return $this->rest_error_handler( __FUNCTION__, $e );
					 */
					$rep_object = $e->get_response();
					$err = json_decode( $rep_object->body, FALSE );	//err is an object
					if( isset(  $err->errors[0]->code ) )
					{
						$this->code = $err->errors[0]->code;
						return $this->rest_error_handler( __FUNCTION__, $e );	
					}
 
					
					$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Leaving Create Product WC..HTTP Exception", "WARN");
					return FALSE;
				}
				else 
					throw( $e );	//Pass the expection back along else it disappears
			}
		}
		else
		{
			$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Leaving Create Product WC_Client not set", "WARN");
			throw new Exception( "WC Client not set", WC_CLIENT_NOT_SET);
		}
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Leaving Create Product", "WARN");
		return FALSE;
	}
	function retrieve_product()
	{
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN");
		/*
		curl https://example.com/wp-json/wc/v1/products/162 -u consumer_key:consumer_secret
		 * 
		 * */
	}
	/*************************************************************************************************//**
	 * Send product details to WooCommerce of an item they already have.
	 *
	 * ****************************************************************************************************/
	/*@bool@*/function update_product()
	{
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Entering update_product", "WARN");
		/*
		curl -X PUT https://example.com/wp-json/wc/v1/products/162  -u consumer_key:consumer_secret  -H "Content-Type: application/json"  -d '{ "regular_price": "24.54" }'
		 * 
		 * */
		if( isset( $this->wc_client ) )
		{
			try 
			{
				if( isset( $this->id ) AND $this->id > 0 )
				{
					$this->notify( __METHOD__  . ":" . __LINE__ . " Calling ->update", "WARN" );
					$this->build_data_array();
					if( $this->debug > 2 )
					{
						echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " data_array<br />";
						var_dump( $this->data_array );
					}
					//$client->products->update( 724, array( 'title' => 'Yo  another test product' ) ) ); works in example.php
					//$data_r = $this->wc_client->products->update( $this->id,  array( 'products' => $this->data_array ) ); //data_r not even set on exception
					$data_r = $this->wc_client->products->update( $this->id,  $this->data_array ); 
					//$data_r = $this->wc_client->products->update( $this->id, array( 'products' => array( $this ) ) );
					
					if( $this->debug > 2 )
					{
						echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " var dump time! (2x)<br />";
						var_dump( $this->wc_client->products );
						echo "<br /><br />";
						var_dump( $data_r );
						echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " !var dump time! (2x)<br />";
					}
					//Array or Object of newly updated product...
					$this->extract_data_obj( $data_r->product );
					$this->update_wootable_woodata();	
					$this->products_updated++;
					return TRUE;
				}
				else
				{
					throw new InvalidArgumentException( "Woo ID not set so can't update" );
				}
			} 
			catch ( WC_API_Client_Exception $e ) 
			{
				if( $this->debug > 2 )
				{	
					echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " var dump time! (2x)<br />";
					var_dump( $this->wc_client->products );
					echo "<br /><br />";
				}
				if( isset( $data_r ) )
				{
					echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " var dump<br />";
					var_dump( $data_r );
				}
				else
				{
					echo "<br /><br />" . __METHOD__  . ":" . __LINE__ . " var dump<br />";
					print_r( $e->getMessage() );
				}
				echo "<br /><br />";
				if ( $e instanceof WC_API_Client_HTTP_Exception ) 
				{
					$rep_object = $e->get_response();
					//print_r( $rep_object );	//Object has code, body, headers
					$err = json_decode( $rep_object->body, FALSE );	//err is an object
					if( isset(  $err->errors[0]->code ) )
					{
						$this->code = $err->errors[0]->code;
						return $this->rest_error_handler( __FUNCTION__, $e );
					}
					return FALSE;
				}
				/*
				else 
					throw( $e );	//Pass the expection back along else it disappears
				 */
			}
			catch ( InvalidArgumentException $e )
			{
				$this->notify( __METHOD__  . ":" . __LINE__ . $e->getMessage(), "WARN" );
				$this->notify( __METHOD__  . ":" . __LINE__ . " Leaving update_product", "WARN" );
				return FALSE;

			}
			/*
			 * catch ( Exception $e )
			 * {
			 * 	$e->getMessage();
			 *	$e->getCode();
			 *	$e->getFile();
			 *	$e->getLine();
			 * }
			/*
			 * php5 Finally runs whether catches are run or not.  Usually used for cleanup and logging
			finally
			{
			}
			 */
		} //! Not wc_client
		else
		{
		}
		return FALSE;
	}
	function recode_sku( $callback = null )
	{
		//skus with '/' in them will fail GET if not POST
		$this->sku = str_replace( '/', '_', $this->sku );
		if( null != $callback )
			$this->$callback();
		return;
	}
	/*************************************************************************//**
	 * Get the product info from WOO and set our variables
	 *
	 * ERROR_HANDLER assumes we are setting the variables as it then calls update_wootable
	 *
	 * @param string the stock_id (SKU) to search for
	 * @return bool did we find 1 item?
	 * **************************************************************************/
	/*@bool@*/function get_product_by_sku( $sku )
	{
		$this->get_product_by_sku_count++;
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "WARN");
		//from example.php
		//  This is an exact match only (at least for sku)
			//print_r( $client->products->get( null, array( 'filter[sku]' => 'DISC1'  ) ) );

		//$args = array( 'page' => $this->get_product_by_sku_count );
		//We MAY be able to use a "exclude" filter on list products to get the one we want...
		//
			try {
				if( isset( $sku ) )
					$args = array(  'filter[sku]' => $sku );
				else if( isset( $this->sku ) )
					$args = array(  'filter[sku]' => $this->sku );
				else
					throw new InvalidArgumentException( "SKU not set in get_products_by_sku" );
				$data_r = $this->wc_client->products->get( null, $args );
			} catch ( WC_API_Client_Exception $e ) {
				if ( $e instanceof WC_API_Client_HTTP_Exception ) {
					$this->request = $e->get_request();
					$this->response = $e->get_response();
					if( $this->debug > 1 )
					{
						echo "<br />" . __METHOD__  . ":" . __LINE__ . " Request<br />";
						print_r( $e->get_request() );
						echo "<br />" . __METHOD__  . ":" . __LINE__ . " Response<br />";
						print_r( $e->get_response() );
					}
				}
				$this->rest_error_handler( __FUNCTION__, $e );
			}
			catch( Exception $e )
			{
				throw( $e );
			}
			finally
			{
				if( $this->debug > 2 )
				{
					echo __METHOD__  . ":" . __LINE__ . " data_r<br />";
					var_dump( $data_r );
				}
				//Returns a stdClass with ->products (array of stdClasses)
				foreach( $data_r->products as $product )
				{
					echo __METHOD__  . ":" . __LINE__ . " foreach<br />";
					$this->notify( __METHOD__  . ":" . __LINE__ . " Setting data for SKU " . $this->sku, "WARN");
					if( $this->debug > 1 )
					{
						echo __METHOD__  . ":" . __LINE__ . " Setting data for SKU " . $this->sku . "<br /><br />";
						var_dump( $product );
					}
					/*********************************************
					 * As there is only 1 exact match we don't need to do a separate class
					$wp = new woo_product( $this->serverURL, $this->woo_rest_path, $this->key, $this->secret, $this->enviro, $this);
					foreach( $this->properties_array as $var )
					{
						if( isset( $product->$var ) )
						{
							if( $this->debug > 1 )
								echo __METHOD__  . ":" . __LINE__ . " Setting data for " . $var . "<br /><br />";
							$wp->$var = $product->$var;
						}
					}
					if( ! $wp->update_wootable_woodata() )
					{
						if( $this->debug > 1 )
						{
							echo "<br /><br />" . __METHOD__ . ":" . __LINE__ . " Something went wrong :( <br />";
							exit();
						}
						return FALSE;
					}
					else
						return TRUE;
					 *
					 *
					 *
			 		* Exact match, so can update self 
			 		*/
					foreach( $this->properties_array as $var )
					{
						if( isset( $product->$var ) )
						{
							if( $this->debug > 1 )
								echo __METHOD__  . ":" . __LINE__ . " Setting data for " . $var . "<br /><br />";
							$this->$var = $product->$var;
						}
					}
					$this->notify( __METHOD__  . ":" . __LINE__ . " Leaving " . __METHOD__, "WARN");
					return TRUE;
				}
			}
			
		return FALSE;
	}
	function list_products()
	{
		/*
		 * 	GET
		 *	curl https://example.com/wp-json/wc/v1/products -u consumer_key:consumer_secret
		*/
	}
	/*******************************************************************************//**
	 * Grab data out of the WOO table using the woo class and populate our fields.
	 *
	 * Sets type to "simple".  This needs to be overridden in variable products
	 *
	 * @param string stock_id optional if this->stock_id set
	 * @returns bool were we able to process a stock_id
	 * *******************************************************************************/
	/*@bool@*/function woo2wooproduct( $stock_id = null, $caller )
	{
		if( $this->debug >= 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Entering woo2wooproduct with stock_id " . $stock_id . " from caller " . $caller);
		}
		if( isset( $stock_id )  )
			$this->stock_id = $stock_id;
		if( !isset( $this->stock_id ) )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Stock_id not set. Leaving woo2wooproduct");
			return FALSE;
		}
			
		$remove_desc_array = array(  "**Use Custom Form**,", "**Use Custom Order Form**,", );
		$removed_desc_array = array( "",                     "",                           );
		
		require_once( 'class.woo.php' );
		$woo = new woo( $this->serverURL, $this->key, $this->secret, $this->options, $this );
		$woo->stock_id = $this->stock_id;
		$woo->select_product();
		//Need to reset values between each product.
		$this->reset_values();
		foreach( $woo->fields_array as $fieldrow )
		{
			//Set OUR value == to woo's
			if( isset( $woo->$fieldrow['name'] ) )
			{
				$this->notify( __FILE__ . ":" .  __METHOD__ . ":" . __LINE__ . " Setting " . $fieldrow['name'] . " to " . $woo->$fieldrow['name'], "DEBUG" );
				$this->$fieldrow['name'] = utf8_encode( $woo->$fieldrow['name'] );
			}
		}
		//$this->name = utf8_encode( str_replace( $remove_desc_array, $removed_desc_array, $this->name ) );
		//		$this->name = utf8_encode( str_replace( $remove_desc_array , $removed_desc_array , $prod_data['description'] ) );
		if( ! isset( $this->description ) )
		{
			display_notification( __METHOD__  . ":" . __LINE__ . " Leaving woo2wooproduct ERROR no data");
			throw new Exception( __METHOD__ . ":" . __LINE__ . " Description not set" );
		}
		$this->slug = $this->stock_id;
		$this->title = utf8_encode( str_replace( $remove_desc_array, $removed_desc_array, $this->description ) );
		//$this->title = utf8_encode( str_replace( $remove_desc_array , $removed_desc_array , $prod_data['description'] ) );
		$this->permalink = null;
		$this->type = "simple";	//OVERRIDE in calling routine as appropriate.
		$this->status = "publish";
		$this->featured = false;
		$this->catalog_visibility = "visible";
		$this->short_description = utf8_encode( str_replace( $remove_desc_array , $removed_desc_array , $this->description ) );
		$this->description = utf8_encode( str_replace( $remove_desc_array , $removed_desc_array , $this->long_description ) );
		//$this->description = utf8_encode( str_replace( $remove_desc_array , $removed_desc_array , $prod_data['long_description'] ) );
		//$this->short_description = utf8_encode( str_replace( $remove_desc_array , $removed_desc_array , $prod_data['description'] ) );
		if( isset( $this->price ) )
			$this->regular_price = $this->price;
		$this->sale_price = null;
		if( $this->tax_class == "GST" )
			$this->tax_class = "Standard";	//Woo provides Standard, Reduced Rate, Zero
		$this->date_on_sale_from = null;
		$this->date_on_sale_to = null;
		$this->price_html = null;
		//$this->id = $prod_data['woo_id'];
		//$this->sku = $prod_data['stock_id'];
		//$this->regular_price = $prod_data['price'];

		//var_dump( $prod_data );
		$this->virtual = false;
		/*****************************************************************//**
		 *Downloads
		 *
		 * 	id, name, file(url) are properties within an array
		 *
		 * ********************************************************************/
		/*
		 *	$this->download = array( "id" => XXX, "name" => YYY, "file" => ZZZ );
		 * */
		$this->downloadable = false;
		$this->downloads = null;
		$this->download_limit = null;
		$this->download_expiry = null;
		$this->download_type = null;

		$this->manage_stock = true;
		$this->managing_stock = true;
		$this->backorders = "notify";
		$this->sold_individually = false;	//true only allows 1 of this product per order
		$this->in_stock = true;	//Need to extend so that categories that are Special Order do not show IN STOCK in WOO.
		if( isset( $this->instock ) )
			$this->stock_quantity = $this->instock;
		else
			$this->stock_quantity = 0;

		/*Shipping*/
		//TODO:
		//	Extend to check to see if we have the dimension module installed
		//	Use a different class to set these including units and translations
		//$this->shipping_class = 'parcel';
		//$this->weight = '';	//Should be set by foreach woo above
		$dim_var_array = array( 'width', 'length', 'height');
		foreach($dim_var_array as $var )
		{
			if( isset( $this->$var ) && strlen( $this->$var ) > 1 )
			{
				$this->dimensions[$var] = utf8_encode( $this->$var );
			}
			$this->dimensions['unit'] = "cm";	
		}


		/* made obselete above by foreach woo...
		$var_array = array( 'sale_price', 
					'date_on_sale_from', 
					'date_on_sale_to', 
					'external_url', 
					'button_text',
					'tax_status', 
					'tax_class', 
					'shipping_class',
					'upsell_ids',
					'cross_sell_ids',
					'weight'
			);
		foreach($var_array as $var )
		{
			if( isset( $prod_data[$var] ) && strlen( $prod_data[$var] ) > 1 )
			{
				$this->$var = utf8_encode( $prod_data[$var] );
			}
		}

		$dim_var_array = array( 'width', 
					'length', 
					'height'
			);
		foreach($dim_var_array as $var )
		{
			if( isset( $prod_data[$var] ) && strlen( $prod_data[$var] ) > 1 )
			{
				$this->dimensions[$var] = utf8_encode( $prod_data[$var] );
			}
		}
		 !obsolete */

		$this->reviews_allowed = TRUE;
		$this->parent_id = null;
		$this->purchase_note = null;

		if( isset( $this->woo_category_id ) )
			$this->categories = array( "id" => $this->woo_category_id );	//Should also have name and slug?
		//$this->categories = array( "id" => $prod_data['woo_category_id'] );	//Should also have name and slug?
											//Can we add extra categories in FA through foreign_codes?
		//Here we could look at foreign codes for additional categories.

		//$this->tags = array( "id" => XXX, "name" => YYY, "slug" => ZZZ );
		$this->tags = $this->product_tags( $stock_id );
		if( ! $this->send_images )
			$this->images = null;	//Requires an array of arrays
					//images in position 0 are the featured image
					//1.. are in a gallery
					//$image2 = array( 'src' => 'http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_front.jpg', 'position' => 2 );
					//$images = array( $image1, $image2 );

		else
		{
			$this->images = $this->product_images( $stock_id );
			//var_dump( $this->images );
		}
		$this->attributes = $this->product_attributes( $stock_id );
		$this->default_attributes = $this->product_default_attributes( $stock_id );
		$this->variations = $this->product_variations( $stock_id );
		$this->menu_order = "1";
		//$this->attributes = array("");
		//	id, name, position, visible(bool), variation(bool), options
		//$this->default_attributes = "";
		if( isset( $this->woo_id ) AND $this->woo_id > 0 )
			$this->id = $this->woo_id;
		else
			$this->id = null;
		if( isset( $this->stock_id ) )
			$this->sku = $this->stock_id;
		if( $this->debug >= 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Leaving woo2wooproduct");
		}

		return TRUE;
	}
	/**************************************************************************************************************
	 *
	 * 	function send_simple_products
	 *
	 * 	Send a list of simple (no variation like size or color) products
	 * 	to WOO.  This is sending items we HAVE NOT yet sent (those need
	 * 	to be updated, not created)
	 *
	 * 	IN:
	 * 	OUT: number of products sent
	 * 	Sets products_sent by adding the count (vice setting) in case we are called multiple times.
	 * 	@return int count of products sent.  -1 if no new products to send
	 **************************************************************************************************************/
	/*@int@*/function send_simple_products()
	{		
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Entering send_simple_products", "WARN");
		//Check for how many we are expecting to send...
		require_once( 'class.woo.php' );
		$woo = new woo( $this->serverURL, $this->key, $this->secret, $this->options, $this);
		$woo->debug = $this->debug;
		$woo->filter_new_only = TRUE;
		$tosend = $woo->count_new_products();
		if( $tosend <= 0 )
			return -1;
		$this->notify( __METHOD__  . ":" . __LINE__ . " About to send " . $tosend . " rows (all, not simple) to Woo", "WARN" );
		$res = $woo->new_simple_product_ids();
		$sendcount = 0;
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__, "DEBUG");
		foreach( $res as $stock_id )
		{
			if( $this->debug > 1 AND $sendcount > 0 )
			{
				$this->notify( __METHOD__  . ":" . __LINE__ . " Leaving send_simple_products", "WARN");
				return $sendcount;	//Only action 1 item
			}
			$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Calling woo2wooproduct", "NOTIFY");
			$this->woo2wooproduct( $stock_id, __METHOD__ );	//Sets the object variables with data from query
			$this->type = "simple";
			try
			{
				if( $this->create_product() )
					$sendcount++;
			}
			catch( Exception $e )
			{
				$this->notify( $e->getMessage(), "WARN" );
				if( WC_CLIENT_NOT_SET == $e->getCode() )
				{
					//WC-Client not set
					$this->notify( __METHOD__  . ":" . __LINE__ . " Leaving send_simple_products wc_client not set", "WARN" );
					throw $e;
				}
			}
			finally
			{
				return $sendcount;
			}
		}
		$this->notify( __METHOD__  . ":" . __LINE__ . " Leaving send_simple_products", "WARN" );
		$this->products_sent += $sendcount;
		return $sendcount;
	}
	/*****************************************************************************//**
	 *
	 *	Adds to products_updated
	 *
	 * ******************************************************************************/
	function update_simple_products()
	{			
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Entering update_simple_products", "WARN");

		require_once( 'class.woo.php' );
		$woo = new woo( $this->serverURL, $this->key, $this->secret, $this->options, $this);
		$woo->debug = $this->debug;
		$updatecount = 0;
		$res = $woo->select_simple_products_for_update();

		while( $prod_data = db_fetch_assoc( $res ) )
		{
			$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " WHILE LOOP update_simple_products", "WARN");
			if( $this->debug > 1 AND $updatecount > 0 )
			{
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Leaving update_simple_products", "WARN");
				return $updatecount;	//Only action 1 item
			}
			$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Calling woo2wooproduct", "WARN");
			$this->woo2wooproduct( $prod_data['stock_id'], __FUNCTION__);
			$this->type = "simple";
			if( isset( $this->id ) AND $this->id > 0 )
			{
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Calling update PRODUCT", "WARN");
			//try
			//{
				if( $this->update_product() )
					$updatecount++;
				else
					display_notification( __METHOD__  . ":" . __LINE__ . " Product not updated.  DEBUG level: " . $this->debug );
			}
			else
			{
				unset( $this->id );
				$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Calling create PRODUCT", "WARN");
				try
				{
				if( $this->create_product() )
					$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Insert Successful", "WARN");
				else
					$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Insert Failed", "WARN");
				}
				catch( Exception $e )
				{
					if( WC_CLIENT_NOT_SET == $e->getCode() )
					{
						//no wc_client
						throw $e;
					}
				}
			}
			//}
			//catch( Exception $e )
			//{
			//	display_notification( __METHOD__  . ":" . __LINE__ . "caught error code " . $e->getCode() . " and msg " . $e->getMessage() . " from " . $e->getFile() . ":" . $e->getLine() );
			//}
			$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " End WHILE LOOP lap", "WARN");
		}
		$this->notify( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__ . " Leaving update_simple_products", "WARN");
		$this->products_updated += $updatecount;
		return $updatecount;
	}
	function product_tags( $stock_id )
	{
		return null;
		/*
		require_once( 'class.woo_tags.php' );
		$w_imgs = new woo_tags( null, null, null, $stock_id, $this->client );
		return $w_imgs->run();
		 */
	}
	function product_category( $stock_id )
	{
		return null;
		/*
		require_once( 'class.woo_category.php' );
		$w_imgs = new woo_category( null, null, null, $stock_id, $this->client );
		return $w_imgs->run();
		*/
	}
	function product_downloads( $stock_id )
	{
		return null;
		/*
		require_once( 'class.woo_downloads.php' );
		$w_imgs = new woo_downloads( null, null, null, $stock_id, $this->client );
		return $w_imgs->run();
		*/
	}
	function product_dimensions( $stock_id )
	{
		return null;
		/*
		require_once( 'class.woo_dimensions.php' );
		$w_imgs = new woo_dimensions( null, null, null, $stock_id, $this->client );
		return $w_imgs->run();
		*/
	}
	function product_attributes( $stock_id )
	{
		return null;
		/*
		 *	For a Variable Product, the ATTRIBUTES is just a list
		 *	of the possible combinations. 
		 *		id(global)/name, 
		 *		position, 
		 *		visible t/f, 
		 *		variation(t/f can be used for variations), 
		 *		options (values)
		 *	The DEFAULT ATTRIBUTES is the default.
		 *		id/name
		 *		option
		 *
		 *	In a Variation, the ATTRIBUTES is an array of the appropriate
		 *	IDs or NAMEs plus the OPTION.
		 * */
		require_once( 'class.woo_prod_variation_attributes.php' );
		
		$w_attr = new woo_prod_variation_attributes( $this->serverURL, $this->key, $this->secret, $stock_id, $this->client );
		$arr = $w_attr->get_by_sku( $stock_id );
		$retarr = array();
		$name = "";
		foreach( $arr as $sku => $val )
		{
			if( $name != $val['name'] )
			{
				if( isset( $values ) )	//don't set blank first array
				{
					$retarr[] = array( 'visible' => 'true',
			       			'variation' => 'true',
						'name' => $name,
						'options' => $values );
					unset( $values );
				}
				$name = $val['name'];
				$values = array();
				$values[] = $val['option'];
			}
		}
		$retarr[] = array( 'visible' => 'true',
		 		'variation' => 'true',
				'name' => $name,
				'options' => $values );
		return $retarr;
	}
	function product_default_attributes( $stock_id )
	{
		require_once( 'class.woo_prod_variation_attributes.php' );
		$w_attr = new woo_prod_variation_attributes( $this->serverURL, $this->key, $this->secret, $stock_id, $this->client );
		$arr = $w_attr->get_by_sku( $stock_id );
		$retarr = array();
		foreach( $arr as $sku => $val )
		{
			$retarr[] = $val;
		}
		return $retarr;
	}
	function product_variations( $stock_id )
	{
		return null;
		/*
		require_once( 'class.woo_variations.php' );
		$w_imgs = new woo_variations( null, null, null, $stock_id, $this->client );
		return $w_imgs->run();
		*/
	}
	function product_images( $stock_id )
	{
		require_once( 'class.woo_images.php' );
		//standard constructor args...	$this->serverURL, $this->key, $this->secret, $this->options, $this

		echo "<br /><br />" . __METHOD__ . ":" . __LINE__;
		var_dump( $this->client->remote_img_srv );
		if( isset( $this->client->remote_img_srv ) )
			$remote_img_srv = $this->client->remote_img_srv;
		else
			$remote_img_srv = FALSE;
		echo "<br /><br />" . __METHOD__ . ":" . __LINE__;
		var_dump( $remote_img_srv );
		$w_imgs = new woo_images( $stock_id, $this->client, $this->debug, $remote_img_srv );
		return $w_imgs->run();
	}

	function product_images_old( $stock_id )
	{
		//IMAGES
		//If we use local URL we need to build it and send it
		//If we need to use WOOCOMMERCE image gallery, we need the filename
		//With the module to allow extra images, we need to check for that too
		////SHould also check for the existance of the filename in the local company
		//	Default location is (/company/0/images
		$image_array = array();
		$imagecount = 0;
		//if( isset( $this->image_serverurl ) AND isset( $this->image_baseurl ) AND $this->use_img_baseurl == "true" )
		if( isset( $this->client->image_serverurl ) AND isset( $this->client->image_baseurl ) )
		{
			//Assumption running on same machine for image check
			$image_name = $this->image_exists( $stock_id );
			if( null != $image_name )
				/*
			$filename = company_path().'/images/' . item_img_name($stock_id) . ".jpg";
			if( file_exists( $filename ) === TRUE )
				 */
			{
				$image_array[$imagecount]['src']  = $this->client->image_serverurl . '/' . $this->client->image_baseurl . '/' . $image_name;
				$image_array[$imagecount]['position'] = $imagecount;
				$imagecount++;
			}
			if( isset( $this->client->maxpics ) )
			{
				for ( $j = 1; $j <= $this->client->maxpics; $j++ )
				{
					$image_name = $this->image_exists( $stock_id . $j );
					if( null != $image_name )
					/*
					$filename = item_img_name($stock_id) . $j . ".jpg";
					$fullfilename = company_path().'/images/' . $filename;
					if( file_exists( $fullfilename ) === TRUE )
					 */
					{
						$image_array[$imagecount]['src']  = $this->client->image_serverurl . '/'  . $this->client->image_baseurl . '/' . $image_name;
						$image_array[$imagecount]['position'] = $imagecount;
						$imagecount++;
					}
				}
			}
		}
		else
		{
			$image_array[$imagecount]['src'] = $stock_id . '.jpg"';
			$image_array[$imagecount]['position'] = $imagecount;
			$imagecount++;
		}
		return $image_array;
	}
}

?>
