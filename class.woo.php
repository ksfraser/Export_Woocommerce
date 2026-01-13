<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_interface.php' );
//require_once( 'class.EXPORT_WOO.inc.php' ); //Constants

/******************************************************************//**
 * This class is a MVC Model class - designed for handling the WOO table in frontaccounting.
 *
 * The module also currently has the Admin screens attached which should be
 * separated out to follow the MVC framework...
 * *******************************************************************/
class woo extends woo_interface {
		var $stock_id;
		var $updated_ts;
		var $woo_last_update;
		var $woo_id;
		var $category_id;
		var $category;
		var $woo_category_id;
		var $description;
		var $long_description;
		var $units;
		var $price;
		var $instock;
		var $saleprice;
		var $date_on_sale_from;
		var $date_on_sale_to;
		var $external_url;
		var $tax_status;
		var $tax_class;
		var $weight;
		var $length;
		var $width;
		var $height;
		var $shipping_class;
		var $upsell_ids;
		var $crosssell_ids;
		var $parent_id;
		var $attributes;
		var $default_attributes;
		var $variations;
		var $filter_new_only;
		var $force_update; //!< bool grabbed from client

	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		$this->filter_new_only = FALSE;
		if( isset( $client->force_update ) )
			$this->force_update = $client->force_update;

		//$this->define_table();
		return;
	}
	function define_table()
	{
		$sidl = 'varchar(' . STOCK_ID_LENGTH . ')';
		$this->fields_array[] = array('name' => 'stock_id', 'label' => 'SKU', 'type' => $sidl, 'null' => 'NOT NULL',  'readwrite' => 'readwrite' );
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP');
		$this->fields_array[] = array('name' => 'woo_last_update', 'type' => 'timestamp', 'null' => 'NOT NULL',);
		$this->fields_array[] = array('name' => 'woo_id', 'type' => 'varchar(32)' );
		$this->fields_array[] = array('name' => 'category_id', 'type' => 'int(11)' );
		$this->fields_array[] = array('name' => 'category', 'type' => 'varchar(64)' );
		$this->fields_array[] = array('name' => 'woo_category_id', 'type' => 'int(11)' );
		$this->fields_array[] = array('name' => 'description', 'type' => 'varchar(200)' );
		$this->fields_array[] = array('name' => 'long_description', 'type' => 'varchar(500)' );
		$this->fields_array[] = array('name' => 'units', 'type' => 'varchar(20)' );
		$this->fields_array[] = array('name' => 'price', 'type' => 'double' );
		$this->fields_array[] = array('name' => 'instock', 'type' => 'int(11)' );
		$this->fields_array[] = array('name' => 'saleprice', 'type' => 'float' );
		$this->fields_array[] = array('name' => 'date_on_sale_from', 'type' => 'date', 'null' => 'NOT NULL');
		$this->fields_array[] = array('name' => 'date_on_sale_to', 'type' => 'date', 'null' => 'NOT NULL');
		$this->fields_array[] = array('name' => 'external_url', 'type' => 'varchar(128)' );
		$this->fields_array[] = array('name' => 'tax_status', 'type' => 'varchar(32)' );
		$this->fields_array[] = array('name' => 'tax_class', 'type' => 'varchar(32)' );
		$this->fields_array[] = array('name' => 'weight', 'type' => 'float' );
		$this->fields_array[] = array('name' => 'length', 'type' => 'float' );
		$this->fields_array[] = array('name' => 'width', 'type' => 'float' );
		$this->fields_array[] = array('name' => 'height', 'type' => 'float' );
		$this->fields_array[] = array('name' => 'shipping_class', 'type' => 'varchar(32)' );
		$this->fields_array[] = array('name' => 'upsell_ids', 'type' => 'varchar(128)' );
		$this->fields_array[] = array('name' => 'crosssell_ids', 'type' => 'varchar(128)' );
		$this->fields_array[] = array('name' => 'parent_id', 'type' => 'varchar(32)' );
		$this->fields_array[] = array('name' => 'attributes', 'type' => 'varchar(255)' );
		$this->fields_array[] = array('name' => 'default_attributes', 'type' => 'varchar(255)' );
		$this->fields_array[] = array('name' => 'variations', 'type' => 'varchar(255)' );

		//$this->table_details['tablename'] = TB_PREF . "woo_categories_xref";
		$this->table_details['tablename'] = $this->company_prefix . "woo";
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
	/**************************************************************//**
	 * Select the details of 1 product.  Requires that stock_id is set
	 *
	 * ****************************************************************/
	function select_product()
	{
		$prod_sql = 	"select stock_id, woo_category_id, description, long_description, price, instock, 
				sale_price, date_on_sale_from, date_on_sale_to, external_url, tax_status, tax_class, 
				weight, length, width, height, shipping_class, upsell_ids, crosssell_ids, parent_id, 
				attributes, default_attributes, variations, woo_id
				from " . TB_PREF . "woo";
		$prod_sql .= " where stock_id = '" . $this->stock_id . "'";
		$res = db_query( $prod_sql, __LINE__ . " Couldn't select product(s) for export" );
		$prod_data = db_fetch_assoc( $res );
		foreach( $this->fields_array as $fieldrow )
		{
			if( isset( $prod_data[ $fieldrow['name'] ] ) )
				$this->$fieldrow['name'] = $prod_data[ $fieldrow['name'] ];
		}
	}
	function insert_product()
	{
		$sql_create = "insert ignore into " . $this->table_details['tablename'] . " ( stock_id"
				. ", category_id"
				. ", description"
				. ", long_description"
				. ", units"
				."	)
			 select 
				sm.stock_id 
				, sm.category_id"
				. ", sm.description"
				. ", sm.long_description"
				. ", sm.units"
			. " from " . TB_PREF . "stock_master sm"
			. " WHERE inactive=0"
			;
		$res = db_query( $sql_create, "Couldnt create items in  WOO" );
		$this->tell( WOO_PRODUCT_INSERT, __METHOD__ );
	}
	function update_product_details()
	{
		$sql_update = "update " . $this->table_details['tablename'] . " woo, " . TB_PREF . "stock_master sm
			set
				woo.category_id = sm.category_id
				, woo.description = sm.description
				, woo.long_description = sm.long_description
				, woo.units = sm.units
			where woo.stock_id = sm.stock_id";
		$res = db_query( $sql_update, "Couldnt update stock_master details in  WOO" );
		$this->tell( WOO_PRODUCT_UPDATE, __METHOD__ );
	}
	function update_prices()
	{
		$sql_update2 = "update " . $this->table_details['tablename'] . " woo,  " . TB_PREF . "prices p
			set
				woo.price = p.price
			where woo.stock_id = p.stock_id
				and p.sales_type_id = '1'";
		$res = db_query( $sql_update2, "Couldnt update prices in  WOO" );
		$this->tell( WOO_PRODUCT_PRICE_UPDATE, __METHOD__ );
	}
	function zero_null_prices()
	{
		$sql_update2a = "update " . $this->table_details['tablename'] . " woo
			set
				woo.price = '0'
			where woo.price is null";
		$res = db_query( $sql_update2a, "Couldnt update NULL prices in  WOO" );
		//$this->tell( WOO_PRODUCT_PRICE_NULL2ZERO, __METHOD__ );
	}
	/*********************************************************************//**
	 * This function updates the QOH count within the WOO table so the SQL statement here is OK.
	 *
	 * It is dependant on either an external QOH module or the included QOH
	 * class/table to hold the values of QOH by product.
	 *
	 * It is also dependant on all of the items ins stock_master have already
	 * been inserted into this table to be updated or the items will be missed.
	 *
	 * ***********************************************************************/
	function update_qoh_count()
	{
		global $path_to_root;
		if( @include_once( '../ksf_qoh/class.ksf_qoh.php' ) )
		{
			//Independant module.  This module is where all
			//future development for QOH will happen.
			include_once($path_to_root . "/modules/ksf_qoh/ksf_qoh.inc.php"); //KSF_QOH_PREFS
			$qoh = new ksf_qoh( KSF_QOH_PREFS );
			$qoh->define_table();
			$qoh_table = $qoh->table_interface->table_details['tablename'];
		}
		else if( @include_once( 'class.qoh.php' ) )
		{
			//included class
			$qoh = new qoh( null, null, null, null, $this );
			$qoh->define_table();
			$qoh_table = $qoh->table_details['tablename'];
		}
		else
		{
			$qoh_table = TB_PREF . "qoh";
		}
		//Grab the count out of the QOH table.  

		$sql_update3 = "update " . $this->table_details['tablename'] . " woo,  " .  $qoh_table . " q
			set
				woo.instock = q.instock
			where woo.stock_id = q.stock_id";
		$res = db_query( $sql_update3, "Couldnt update Quantity On Hand in  WOO" );
		$this->tell( WOO_PRODUCT_QOH_UPDATE, __METHOD__ );
	}
	function update_on_sale_data()
	{
		if( !isset( $this->stock_id ) )
			throw new InvalidArgumentException( "stock_id" );
		$updateprod_sql = "update " . $this->table_details['tablename'] . " set
					date_on_sale_from = '" . $this->date_on_sale_from . "',
					date_on_sale_to = '" . $this->date_on_sale_to . "',
					tax_status = '" . $this->tax_status . "'";
		$updateprod_sql .= " where stock_id = '" . $this->stock_id . "'";
		$res = db_query( $updateprod_sql, "Couldn't update product after export" );
	}
	function update_woo_id()
	{
		if( !isset( $this->stock_id ) )
			throw new InvalidArgumentException( "stock_id" );
		$updateprod_sql = "update " . $this->table_details['tablename'] . " set
					woo_id = '" . $this->woo_id . "'";		
		$updateprod_sql .= " where stock_id = '" . $this->stock_id . "'";
		$res = db_query( $updateprod_sql, "Couldn't update woo_id after export" );
	}
	function update_woo_last_update()
	{
		if( !isset( $this->stock_id ) )
			throw new InvalidArgumentException( "stock_id" );
		$updateprod_sql = "update " . $this->table_details['tablename'] . " set
					woo_last_update = '" . $this->woo_last_update . "'";
		$updateprod_sql .= " where stock_id = '" . $this->stock_id . "'";
		$res = db_query( $updateprod_sql, "Couldn't update woo_id after export" );
	}
	function staledate_specials()
	{
		$sql_update4 = "update " . $this->table_details['tablename'] . " woo
			set
				woo.date_on_sale_to = '2015-01-01'";
		$res = db_query( $sql_update4, "Couldnt invalidate Sales and Specials in  WOO" );
		$sql_update4a = "update " . $this->table_details['tablename'] . " woo
			set
				woo.sale_price = woo.price
			";
		$res = db_query( $sql_update4a, "Couldnt set sales prices to prices in WOO" );
		//$this->tell( WOO_PRODUCT_STALEDATE_SPECIALS, __METHOD__ );
	}
	function update_specials()
	{
		$sql_update4 = "update " . $this->table_details['tablename'] . " woo,  " . TB_PREF . "specials s
			set
				woo.sale_price = s.sale_price,
				woo.date_on_sale_from = s.start,
				woo.date_on_sale_to = s.end
			where woo.stock_id = s.stock_id";
		$res = db_query( $sql_update4, "Couldnt update Sales and Specials in  WOO" );
		$this->tell( WOO_PRODUCT_SPECIALS_UPDATE, __METHOD__ );
	}
	function update_tax_data()
	{
				//$sql_update5 = "update " . TB_PREF . "woo woo,  " . TB_PREF . "taxes t
		//	set
		//		woo.tax_status = t.tax_status,
		//		woo.tax_class = t.tax_class
		//	where woo.stock_id = t.stock_id";
		$sql_update5 = "update " . $this->table_details['tablename'] . " woo
			set
				woo.tax_status = 'taxable',
				woo.tax_class = 'GST'";
		$res = db_query( $sql_update5, "Couldnt update TAX data in  WOO" );
		$this->tell( WOO_PRODUCT_TAXDATA_UPDATE, __METHOD__ );
	}
	function update_shipping_dimensions()
	{
		//need to do a check that the ShipDim module is installed
		$sql_update6 = "update " . $this->table_details['tablename'] . " woo,  " . TB_PREF . "shipdim s
			set
				woo.shipping_class = s.shipping_class,
				woo.length = s.length,
				woo.width = s.width,
				woo.height = s.height,
				woo.weight = s.weight
			where woo.stock_id = s.stock_id";
		$res = db_query( $sql_update6, "Couldnt update Shipping Dimensional data in  WOO" );
		$this->tell( WOO_PRODUCT_SHIPDIM_UPDATE, __METHOD__ );
	}
	function update_crosssells()
	{
		$sql_update7 = "update " . $this->table_details['tablename'] . " woo,  " . TB_PREF . "related s
			set
				woo.upsells_ids = s.upsells_ids,
				woo.crosssells_ids = s.crosssells_ids,
			where woo.stock_id = s.stock_id";
		$res = db_query( $sql_update7, "Couldnt update upsell and cross sell data in  WOO" );
		$this->tell( WOO_PRODUCT_CROSSSELL_UPDATE, __METHOD__ );
	}
	function update_category_data()
	{
		$sql_update8 = "update " . $this->table_details['tablename'] . " woo,  " . TB_PREF . "stock_category s
			set
				woo.category = s.description
			where woo.category_id = s.category_id";
		$res = db_query( $sql_update8, "Couldnt update Category data in  WOO" );
		$this->tell( WOO_PRODUCT_CATEGORY_UPDATE, __METHOD__ );
	}
	function update_category_xref()
	{
		$sql3 = "update " . $this->table_details['tablename'] . " woo, " . TB_PREF . "woo_categories_xref xref set woo.woo_category_id = xref.woo_cat where xref.fa_cat = woo.category_id";
		$res = db_query( $sql3, "Couldnt update categories WOO" );
		//$this->tell( WOO_PRODUCT_CATEGORY_XREF, __METHOD__ );
	}
	/*********************************************************************//**
	 *To show which products did not make it into the end table.
	 *
	*	Causes:
	*		No Transactions (no inventory)
	*		Missing Price 1 (Retail)
	*
	*	Allow user to update the items based upon missing data...
	* TROUBLESHOOTING:
		 	select sm.stock_id, sm.category_id, sc.description as category, sm.description, sm.long_description, sm.units, p.price, mv.instock 
              		from 0_stock_master sm, 0_prices p, 0_stock_category sc, 0_qoh mv, 0_woo_categories_xref woo
			where sm.stock_id = p.stock_id and p.sales_type_id='1' and mv.stock_id = sm.stock_id and sm.category_id = sc.category_id
			and sm.stock_id = 'hd-hat-hornpipe-51';
			select * from 0_qoh where stock_id = 'hd-hat-hornpipe-51';
 			select * from 0_woo where stock_id = 'hd-hat-hornpipe-51';
			*
			*
			* This function should be split into a data portion (model of MVC)
			* and a gui portion in a separate class (view of MVC).
	************************************************************************/
	function missing_from_table()
	{
            	display_notification("Missing from WOO");
		$missing_sql = "select sm.stock_id, sm.description, c.description, sm.inactive, sm.editable 
				from " . TB_PREF . "stock_master sm, " . TB_PREF . "stock_category c
				where sm.category_id = c.category_id and sm.stock_id not in (select stock_id from " . TB_PREF . "woo)";
		 global $all_items;
		$selected_id = "0";
		$name = "";
		$editkey = TRUE;
		$opts = array('cells'=>true, 'show_inactive'=>'1');
		$all_option = FALSE;
		$submit_on_change = TRUE;
                set_editor('item', $name, $editkey);
		start_form();
		start_table();
		table_section_title(_("Possible Causes of problems leading to these items being missing from WOO"));
		label_row(_("Product is inactive (e.g. duplicate or depreciated)"), NULL);
		label_row(_("No retail price set (Price type 1) in prices:"), NULL);
		label_row(_("No total (instock) in QOH (stock_id not in stock_master or issues in stock_moves"), NULL);
		label_row(_("An issue with the Category description/id"), NULL);
	//	label_row(_("No Transaction History (no inventory movement):"), NULL);
		label_row("&nbsp;", NULL);
		label_row("Press F4 to pop open a window to edit the item details", null);
		table_section(1);
	        $ret = combo_input($name, $selected_id, $missing_sql, 'stock_id', 'sm.description',
	        array_merge(
	          array(
	                'format' => '_format_stock_items',
	                'spec_option' => $all_option===true ?  _("All Items") : $all_option,
	                'spec_id' => $all_items,
			'search_box' => true,
	        	'search' => array("sm.stock_id", "c.description","sm.description"),
	                'search_submit' => get_company_pref('no_item_list')!=0,
	                'size'=>10,
	                'select_submit'=> $submit_on_change,
	                'category' => 2,
	                'order' => array('c.description','sm.stock_id')
	          ), $opts) );
			echo $ret;
		end_table();
		end_form();

	}
	/*******************************************************************************//**
	 * This function has been moved into ksf_generate_catalogue so we will call it from here
	 *
	 * This should be split into a VIEW class and a MODEL class.
	 * *********************************************************************************/
	function create_price_book()
	{
		if( @include_once( $path_to_root . "/modules/ksf_generate_catalogue/class.ksf_generate_catalogue.php" ) )
		{
			include_once($path_to_root . "/modules/ksf_generate_catalogue/ksf_generate_catalogue.inc.php"); //ksf_generate_catalogue_prefs
			$cat = new ksf_generate_catalogue( KSF_GENERATE_CATALOGUE_PREFS );
			$cat->create_price_book();
			$cat->email_price_book();
		}
		else 
		{
			display_warning( "This function depends on module ksf_generate_catalogue!" );
			return FALSE;
		}

	}
	/*****************************************************************//**
	 * Return a count of stock_ids belonging to products that are new
	 *
	 * @returns int count
	 * ******************************************************************/
	function count_new_products()
	{
		$count = $this->count_filtered( "woo_id = ''" );
		return $count;
	}
	/*****************************************************************//**
	 * Return an array of stock_ids belonging to simple products that are new
	 *
	 * @returns array stock_ids
	 * ******************************************************************/
	/*@array@*/function new_simple_product_ids()
	{
		$this->filter_new_only = TRUE;
		return $this->simple_product_ids();
	}
	/*****************************************************************//**
	 * Return an array of stock_ids belonging to simple products that are new
	 *
	 * @returns array stock_ids
	 * ******************************************************************/
	/*@array@*/function all_simple_product_ids()
	{
		$this->filter_new_only = FALSE;
		return $this->simple_product_ids();
	}
	/*****************************************************************//**
	 * Return an array of stock_ids belonging to simple products
	 *
	 * @returns array stock_ids
	 * ******************************************************************/
	/*@array@*/function simple_product_ids()
	{
		$res = $this->select_simple_products();
		$resarray = array();

		while( $prod_data = db_fetch_assoc( $res ) )
		{
			$resarray[] = $prod_data['stock_id'];
		}
		return $resarray;
	}
	/****************************************************************//**
	 * Runs an MySQL query returning the mysql_res of stock_ids
	 *
	 * @returns mysql_res
	 * ******************************************************************/
	/*@mysql_res@*/function select_simple_products_for_export()
	{
		return $this->select_simple_products();
	}
	/****************************************************************//**
	 * Runs an MySQL query returning the mysql_res of stock_ids
	 *
	 * Should be split into MODEL and VIEW classes
	 *   Could call VIEW function through db_query
	 * @returns mysql_res
	 * ******************************************************************/
	/*@mysql_res@*/function select_simple_products()
	{
		if( ! defined( $this->table_details['tablename'] ) )
			$this->define_table();
		$prod_sql = 	"select stock_id from " . $this->table_details['tablename'];
		if( $this->filter_new_only )
			$prod_sql .= " WHERE woo_id = ''";	//Otherwise need to do an UPDATE not CREATE
		//This will ensure we send only items that haven't already been inserted.
		$prod_sql .= " AND stock_id not in (SELECT sm.stock_id FROM " . TB_PREF . "stock_master sm 
			INNER JOIN (SELECT stock_id FROM " . TB_PREF . "woo_prod_variable_master GROUP BY stock_id) vm
			ON sm.stock_id LIKE  concat( vm.stock_id, '%') )";
		if( $this->debug == 1 )
		{
			$prod_sql .= " LIMIT 10";
			//$prod_sql .= "ORDER BY RAND() LIMIT 10";
		}
		else
		if( $this->debug >= 2)
		{
			$prod_sql .= "ORDER BY RAND() LIMIT 1";
		}
		//$prod_sql .= " ORDER BY RAND() LIMIT 5";
		
		$res = db_query( $prod_sql, __LINE__ . "Couldn't select product(s) for export" );
		return $res;
	}
	/****************************************************************//**
	 * Runs an MySQL query returning the mysql_res of stock_ids
	 *
	 * Should be split into MODEL and VIEW classes
	 *   Could call VIEW function through db_query
	 * @returns mysql_res
	 * ******************************************************************/
	/*@mysql_res@*/function select_simple_products_for_update()
	{
		$prod_sql = 	"select stock_id 
				from " . $this->table_details['tablename'];
		$prod_sql .= " WHERE ";
		//if( $this->force_update != TRUE )
			$prod_sql .= " updated_ts > woo_last_update AND";	//need to do an UPDATE because we changed something that hasn't been sent
		$prod_sql .= " stock_id not in (SELECT sm.stock_id FROM " . TB_PREF . "stock_master sm 
			INNER JOIN (SELECT stock_id FROM " . TB_PREF . "woo_prod_variable_master GROUP BY stock_id) vm
			ON sm.stock_id LIKE  concat( vm.stock_id, '%') ) limit 10";
		$res = db_query( $prod_sql, __LINE__ . "Couldn't select product(s) for export" );
		return $res;
	}
	function delete_by_sku( $sku )
	{
		$sql = "delete FROM `" . $this->table_details['tablename'] . "` where stock_id = '" . $sku . "'";
		$res = db_query( $sql, "Couldn't delete sku" . $sku );
		$this->notify( "Deleted sku " . $sku, "NOTIFY" );
	}
}

?>
