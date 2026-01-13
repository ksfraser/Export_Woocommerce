<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_rest.php' );

class woo_product {
	var $id;	//integer 	Unique identifier for the resource.  read-only
	var $name;	//string 	Product name.
	var $slug;	//string 	Product slug.
	var $permalink;	//string 	Product URL.  read-only
	var $date_created;	//date-time 	The date the product was created, in the site’s timezone.  read-only
	var $date_modified;	//date-time 	The date the product was last modified, in the site’s timezone.  read-only
	var $type;	//string 	Product type. Default is simple. Options (plugins may add new options): simple, grouped, external, variable.
	var $status;	//string 	Product status (post status). Default is publish. Options (plugins may add new options): draft, pending, private and publish.
	var $featured;	//boolean 	Featured product. Default is false.
	var $catalog_visibility;	//string 	Catalog visibility. Default is visible. Options: visible (Catalog and search), catalog (Only in catalog), search (Only in search) and hidden (Hidden from all).
	var $description;	//string 	Product description.
	var $short_description;	//string 	Product short description.
	var $sku;	//string 	Unique identifier.
	var $price;	//string 	Current product price. This is setted from regular_price and sale_price.  read-only
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
	var $attributes;	//array 	List of attributes. See Attributes properties.
	var $default_attributes;	//array 	Defaults variation attributes, used only for variations and pre-selected attributes on the frontend. See Default Attributes properties.
	var $variations;	//array 	List of variations. See Variations properties
	var $grouped_products;	//string 	List of grouped products ID, only for group type products.  read-only
	var $menu_order;		//integer 	Menu order, used to custom sort products.
	var $woo_rest;
	var $write_properties_array;	//The list of WOO product properties (above) that are writeable.
	var $properties_array;
	var $data_array;
	var $json_data;
	var $header_array;
	function __construct( $serverURL = "https://fhsws001.ksfraser.com/devel/fhs/wordpress", $woo_rest_path =  "/wp-json/wc/v1/",
				$key = "ck_b23355fc0b9ee8b1ae073b64538ce4217f7530b3", $secret = "cs_54b294848a424eff342ce5d7918dd17f122b0b56", $enviro = "devel" )
	{
		$subpath = "products";
		$data_array = "";
		$conn_type = "POST" ;
		$header_array = array();
		$header_array['Content-Type'] = "application/json";
		$this->woo_rest = new woo_rest( $serverURL, $subpath, $data_array, $key, $secret, $conn_type, $woo_rest_path, $header_array, $enviro );
		$this->build_write_properties_array();
		$this->build_properties_array();
		return;
	}
	function array2var( $data_array )
	{
		foreach( $this->write_properties_array as $property )
		{
			if( isset( $this->$property ) )
			{
				$this->data_array[$property] = $this->$property;
			}
		}
	}
	function build_data_array()
	{
		foreach( $this->write_properties_array as $property )
		{
			if( isset( $this->$property ) )
			{
				$this->data_array[$property] = $this->$property;
			}
		}
	}
	function build_json_data()
	{
		$this->json_data = json_encode( $this->data_array );
		//echo $this->json_data;
	}
	function build_properties_array()
	{
		/*All properties*/
		$this->properties_array = array(
			'id',
			'name',
			'slug',
			'permalink',
			'date_created',
			'date_modified',
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
		);
	}
	function create_product()
	{
		/*
		curl -X POST http://fhsws001.ksfraser.com/wp-json/wc/v1/products -u consumer_key:consumer_secret -H "Content-Type: application/json" -d '{
		  "name": "Premium Quality",
		  "type": "simple",
		  "regular_price": "21.99",
		  "description": "Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.",
		  "short_description": "Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.",
		  "categories": [
		    {
		      "id": 9
		    },
		    {
		      "id": 14
		    }
		  ],
		  "images": [
		    {
		      "src": "http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_front.jpg",
		      "position": 0
		    },
		    {
		      "src": "http://demo.woothemes.com/woocommerce/wp-content/uploads/sites/56/2013/06/T_2_back.jpg",
		      "position": 1
		    }
		  ]
		}'

WORKS!!  NOTE must be https.  http returns you aren't allowed to create resources...
curl -X POST --insecure https://fhsws001.ksfraser.com/devel/fhs/wordpress/wp-json/wc/v1/products -u ck_b23355fc0b9ee8b1ae073b64538ce4217f7530b3:cs_54b294848a424eff342ce5d7918dd17f122b0b56 -H "Content-Type: application/json" -d '{ "name": "Premium Quality", "type": "simple", "regular_price": "21.99", "description": "Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor quam, feugiat vitae, ultricies eget, tempor sit amet, ante. Donec eu libero sit amet quam egestas semper. Aenean ultricies mi vitae est. Mauris placerat eleifend leo.",  "short_description": "Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas." }'


		 */
		
		$this->build_data_array();
		$this->build_json_data();
		$this->woo_rest->set_content_type( "application/json" );
		$response = $this->woo_rest->write2woo_json( $this->json_data, "POST" );
		 
		/*If by object
		$this->woo_rest->set_content_type( "application/json" );
		$response = $this->woo_rest->write2woo_object( $this, "POST" );
		 */
		display_notification( $response );
		return;
	}
	function retrieve_product()
	{
		/*
		curl https://example.com/wp-json/wc/v1/products/162 -u consumer_key:consumer_secret
		 * 
		 * */
	}
	function update_product()
	{
		/*
		curl -X PUT https://example.com/wp-json/wc/v1/products/162  -u consumer_key:consumer_secret  -H "Content-Type: application/json"  -d '{ "regular_price": "24.54" }'
		 * 
		 * */
	}
	function get_product_by_sku( $sku )
	{
		/*
		 * 	GET
		 *	curl https://example.com/wp-json/wc/v1/products?sku=XYZ -u consumer_key:consumer_secret
		 *	Parameters:
		*		context 	string 	Scope under which the request is made; determines fields present in response. Options: view and edit.
		*		page 	integer 	Current page of the collection.
		*		per_page 	integer 	Maximum number of items to be returned in result set.
		*		search 	string 	Limit results to those matching a string.
		*		after 	string 	Limit response to resources published after a given ISO8601 compliant date.
		*		before 	string 	Limit response to resources published before a given ISO8601 compliant date.
		*		exclude 	string 	Ensure result set excludes specific ids.
		*		include 	string 	Limit result set to specific ids.
		*		offset 	integer 	Offset the result set by a specific number of items.
		*		order 	string 	Order sort attribute ascending or descending. Default is asc. Options: asc and desc.
		*		orderby 	string 	Sort collection by object attribute. Default is date, Options: date, id, include, title and slug.
		*		filter 	string 	Use WP Query arguments to modify the response; private query vars require appropriate authorization.
		*		slug 	string 	Limit result set to products with a specific slug.
		*		status 	string 	Limit result set to products assigned a specific status. Default is any. Options: any, draft, pending, private and publish.
		*		customer 	string 	Limit result set to orders assigned a specific customer.
		*		category 	string 	Limit result set to products assigned a specific category, e.g. ?category=9,14.
		*		tag 	string 	Limit result set to products assigned a specific tag, e.g. ?tag=9,14.
		*		shipping_class 	string 	Limit result set to products assigned a specific shipping class, e.g. ?shipping_class=9,14.
		*		attribute 	string 	Limit result set to products with a specific attribute, e.g. ?attribute=pa_color.
		*		attribute_term 	string 	Limit result set to products with a specific attribute term (required an assigned attribute), e.g. ?attribute=pa_color&attribute_term=9,14.
		*		sku 	string 	Limit result set to products with a specific SKU.
		*/
	}
	function list_products()
	{
		/*
		 * 	GET
		 *	curl https://example.com/wp-json/wc/v1/products -u consumer_key:consumer_secret
		*/
	}
}

?>
