<?php

require_once( 'class.generic_orders.php' ); 


//class EXPORT_WOO
class EXPORT_WOO extends generic_orders
{
	var $include_header;
	var $maxrowsallowed;
	var $lastoid;
	var $mailto;
	var $db;
	var $woo_ck;
	var $woo_cs;
	var $woo_server;
	var $woo_rest_path;
	function __construct( $host, $user, $pass, $database, $pref_tablename )
	{
		global $db;
		$this->db = $db;
		//echo "EXPORT_WOO constructor";
		parent::__construct( $host, $user, $pass, $database, $pref_tablename );
		$this->set_var( 'vendor', "EXPORT_WOO" );
		$this->set_var( 'include_header', TRUE );
		
		$this->config_values[] = array( 'pref_name' => 'lastoid', 'label' => 'Last Order Exported' );
		$this->config_values[] = array( 'pref_name' => 'include_header', 'label' => 'Include Headers' );
		$this->config_values[] = array( 'pref_name' => 'maxrowsallowed', 'label' => 'Maximum Rows Allowed in file' );
		$this->config_values[] = array( 'pref_name' => 'mailto', 'label' => 'Mail CSV to email address' );
		$this->config_values[] = array( 'pref_name' => 'image_serverurl', 'label' => 'Server URL for images (http[s]://servername/FA_base)' );
		$this->config_values[] = array( 'pref_name' => 'image_baseurl', 'label' => 'Base URL for images (/company/0/images)' );
		$this->config_values[] = array( 'pref_name' => 'woo_server', 'label' => 'Base URL for WOO server (...wordpress)' );
		$this->config_values[] = array( 'pref_name' => 'woo_rest_path', 'label' => 'Path for REST API ("/wp-json/wc/v1/)' );
		
		//The forms/actions for this module
		//Hidden tabs are just action handlers, without accompying GUI elements.
		//$this->tabs[] = array( 'title' => '', 'action' => '', 'form' => '', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Configuration', 'action' => 'config', 'form' => 'action_show_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Purchase Order Export', 'action' => 'cexport', 'form' => 'form_export', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Install Module', 'action' => 'create', 'form' => 'install', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Config Updated', 'action' => 'update', 'form' => 'checkprefs', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Purchase Order Exported', 'action' => 'c_export', 'form' => 'export_orders', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'All Products Export', 'action' => 'productsexport', 'form' => 'form_products_export', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'All Products Exported', 'action' => 'pexed', 'form' => 'form_products_exported', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'QOH Populated', 'action' => 'qoh', 'form' => 'populate_qoh', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'WOO Populated', 'action' => 'woo', 'form' => 'populate_woo', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Missing Products from WOO', 'action' => 'missingwoo', 'form' => 'missing_woo', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Export File', 'action' => 'exportfile', 'form' => 'export_file_form', 'hidden' => FALSE );
/*
		$this->tabs[] = array( 'title' => 'Product Select and REST Export', 'action' => 'export_rest_product', 'form' => 'export_rest_product_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Products REST Exported', 'action' => 'exported_rest_product', 'form' => 'exported_rest_product_form', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Products REST Export', 'action' => 'export_rest_products', 'form' => 'export_rest_products_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Products REST Exported', 'action' => 'exported_rest_products', 'form' => 'exported_rest_products_form', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Coupons REST Export', 'action' => 'export_rest_coupon', 'form' => 'export_rest_coupon_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Coupons REST Exported', 'action' => 'exported_rest_coupon', 'form' => 'exported_rest_coupon_form', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Customers REST Export', 'action' => 'export_rest_customer', 'form' => 'export_rest_customer_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Customers REST Exported', 'action' => 'exported_rest_customer', 'form' => 'exported_rest_customer_form', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Orders REST Export', 'action' => 'export_rest_orders', 'form' => 'export_rest_orders_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Orders REST Exported', 'action' => 'exported_rest_orders', 'form' => 'exported_rest_orders_form', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Refunds REST Export', 'action' => 'export_rest_refunds', 'form' => 'export_rest_refunds_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Refunds REST Exported', 'action' => 'exported_rest_refunds', 'form' => 'exported_rest_refunds_form', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Taxes REST Export', 'action' => 'export_rest_taxes', 'form' => 'export_rest_taxes_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Taxes REST Exported', 'action' => 'exported_rest_taxes', 'form' => 'exported_rest_taxes_form', 'hidden' => TRUE );
 */
		$this->woo_ck = "ck_b23355fc0b9ee8b1ae073b64538ce4217f7530b3";
		$this->woo_cs = "cs_54b294848a424eff342ce5d7918dd17f122b0b56";
	}
	function open_write_file( $filename )
	{
		return fopen( $filename, 'w' );
	}
	function write_line( $fp, $line )
	{
		fwrite( $fp, $line . "\n" );
	}
	function file_finish( $fp )
	{
		fflush( $fp );
		fclose( $fp );
	}
	function form_products_export()
	{
		//$this->call_table( 'pexed', "Export" );
		$this->call_table( 'qoh', "QOH" );
	}
	function call_table( $action, $msg )
	{
                start_form(true);
                 start_table(TABLESTYLE2, "width=40%");
                 table_section_title( $msg );
                 hidden('action', $action );
                 end_table(1);
                 end_form();
	}
	/***********************************************************************
	*
	*	Function missing_woo
	*
	*	To show which products did not make it into the end table.
	*	Causes:
	*		No Transactions (no inventory)
	*		Missing Price 1 (Retail)
	*
	*	Allow user to update the items based upon missing data...
	*
	************************************************************************/
	function missing_woo()
	{
	
            	display_notification("Missing from WOO");
		$missing_sql = "select sm.stock_id, sm.description, c.description, sm.inactive, sm.editable 
				from 0_stock_master sm, 0_stock_category c
				where sm.category_id = c.category_id and sm.stock_id not in (select stock_id from 0_woo)";
		 global $all_items;
	  	//stock_items_list($name, $selected_id, $all_option, $submit_on_change,
	        //        array('cells'=>true, 'show_inactive'=>$all), $editkey);
		//function stock_items_list($name, $selected_id=null, $all_option=false,
	        				//$submit_on_change=false, $opts=array(), $editkey = false)
	
		$selected_id = "0";
		$name = "";
		$editkey = TRUE;
		$opts = array('cells'=>true, 'show_inactive'=>'1');
		$all_option = FALSE;
		$submit_on_change = TRUE;
		//$submit_on_change = false;
	
	
	        //if ($editkey)
	                set_editor('item', $name, $editkey);
	
		start_form();

		start_table();
		table_section_title(_("Possible Causes of problems leading to these items being missing from WOO"));
		label_row(_("No retail price set (Price type 1):"), NULL);
	//	label_row(_("No Transaction History (no inventory movement):"), NULL);
		label_row("&nbsp;", NULL);
		label_row("Press F4 to pop open a window to edit the item details", null);
		//label_row("&nbsp;", $stock_img_link);
//		end_table();

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
	        //if ($editkey)
	         //       $ret .= add_edit_combo('item');
			echo $ret;
	  	//echo stock_items_list($name, $selected_id, $all_option, $submit_on_change,
	        //        array('cells'=>true, 'show_inactive'=>$all), $editkey);
//		$this->sales_pricing();
		end_table();
		end_form();

	}
	function sales_pricing()
	{
		global $path_to_root;
/*
		start_table(TABLESTYLE_NOBORDER);
        	start_row();
    		stock_items_list_cells(_("Select an item:"), 'stock_id', null,
          		_('New item'), true, check_value('show_inactive'));
        	check_cells(_("Show inactive:"), 'show_inactive', null, true);
        	end_row();
        	end_table();

        	if (get_post('_show_inactive_update')) {
                	$Ajax->activate('stock_id');
                	set_focus('stock_id');
        	}
		else
		{
        		hidden('stock_id', get_post('stock_id'));
		}
*/

		div_start('details');
		
		$stock_id = get_post('stock_id');
		if (!$stock_id)
		        unset($_POST['_tabs_sel']); // force settings tab for new customer
		tabbed_content_start('tabs', array(
		                'sales_pricing' => array(_('S&ales Pricing'), $stock_id),
		                'standard_cost' => array(_('Standard &Costs'), $stock_id),
		                'movement' => array(_('&Transactions'), $stock_id),
		));
		
		switch (get_post('_tabs_sel')) {
		        default:
			case 'sales_pricing':
		        	$_GET['stock_id'] = $stock_id;
		                $_GET['popup'] = 1;
		                include_once($path_to_root."/inventory/prices.php");
		                break;
		        case 'standard_cost':
		                $_GET['stock_id'] = $stock_id;
		                $_GET['popup'] = 1;
		                include_once($path_to_root."/inventory/cost_update.php");
		                break;
		        case 'movement':
		                $_GET['stock_id'] = $stock_id;
		                $_GET['popup'] = 1;
		                include_once($path_to_root."/inventory/inquiry/stock_movements.php");
		                break;
	 	};
		br();
		tabbed_content_end();
		div_end();
		
		hidden('popup', @$_REQUEST['popup']);
	}
	function populate_qoh()
	{
            	display_notification("QOH");
/*
		$qoh = "create table if not exists 0_qoh SELECT 
				stock_id, SUM(qty) as instock
			FROM 
				0_stock_moves
			GROUP BY stock_id";
            		//AND tran_date <= '$date'";
		$res = db_query( $qoh, "Couldn't create table of stock on hand" );
		var_dump( $res );
*/
		$qoh2 = "insert ignore into 0_qoh SELECT 
				stock_id, 0 as instock
			FROM 
				0_stock_master
			WHERE
				inactive='0'";
		$res = db_query( $qoh2, "Couldn't create table of stock on hand" );

		$qoh2 = "replace into 0_qoh SELECT 
				stock_id, SUM(qty) as instock
			FROM 
				0_stock_moves
			GROUP BY stock_id";
		$res = db_query( $qoh2, "Couldn't create table of stock on hand" );
		//var_dump( $res );

		$res = db_query( "select count(*) from 0_qoh", "Couldn't count QOH" );
		$count = db_fetch_row( $res );
            	display_notification("$count[0] rows of items created.");
		$this->call_table( 'woo', "WOO" );
	}
	/************************************************************************
	 *
	 *	Populate WOO
 	 *	This Function populates the WOO table.
	 *
	 *	TODO:
	 *		Alter table to have Virtual and Downloadable columns
	 *		Alter table to have Shipping data (weight, dimensions)
	 *		Create Query that updates Virtual (service) products
	 *		Create Query that updates Downloadable products
	 *		Create Query that updates Shipping data
	 *		Once the CRM is in place, alter table for crosssell and upsell
	 *		Create Query to update crosssell and upsell
	 *
	 ************************************************************************/
	function populate_woo()
	{
            	display_notification("WOO");
/*
		$sql = "create table if not exists 0_woo select 
				sm.stock_id, 
				sm.category_id, 
				sc.description as category, 
				sm.description, 
				sm.long_description, 
				sm.units, 
				p.price,
				mv.instock 
			from 
				0_stock_master sm, 
				0_prices p, 
				0_stock_category sc 
				0_stock_moves mv
			where 
				sm.stock_id = p.stock_id 
				and p.sales_type_id='1' 
				and mv.stock_id = sm.stock_id
				and sm.category_id = sc.category_id";
		db_query( $sql, "Couldn't create table of inventory to export" );
*/
		$sql2 = "replace into 0_woo select 
				sm.stock_id, 
				sm.category_id, 
				sc.description as category, 
				sm.description, 
				sm.long_description, 
				sm.units, 
				p.price,
				mv.instock 
			from 
				0_stock_master sm, 
				0_prices p, 
				0_stock_category sc,
				0_qoh mv
			where 
				sm.stock_id = p.stock_id 
				and p.sales_type_id='1' 
				and mv.stock_id = sm.stock_id
				and sm.category_id = sc.category_id";
            	//display_notification("WOO1");
		$res = db_query( $sql2 );
		//$res = db_query( $sql, "Couldnt populate WOO" );
            	//display_notification("WOO2");
		//var_dump( $res );
            	display_notification("WOO3");

		$res2 = db_query( "select count(*) from 0_woo", "Couldn't count WOO" );
            	//display_notification("WOO4");
		$count = db_fetch_row( $res2 );
            	display_notification("$count[0] rows of items created.");
		$this->call_table( 'pexed', "Create Export File for WOO" );
	}
	function export_file_form()
	{
		$this->call_table( 'pexed', "Create Export File for WOO" );
	}
/*
	//export_rest_coupon	export_rest_customer_form	exported_rest_customer_form
	function export_rest_coupon_form()
	{
		$this->call_table( 'export_rest_coupon', "Send Coupons via REST to WOO" );
	}
	function export_rest_customer_form()
	{
		$this->call_table( 'export_rest_customer', "Send Customers via REST to WOO" );
	}
	function export_rest_orders_form()
	{
		$this->call_table( 'export_rest_orders', "Send Orders via REST to WOO" );
	}
	function export_rest_refunds_form()
	{
		$this->call_table( 'export_rest_refunds', "Send Refunds via REST to WOO" );
	}
	function export_rest_products_form()
	{
		$this->call_table( 'export_rest_products', "Send Products via REST to WOO" );
	}
 */
	/***********************************************************************
	*
	*	Function export_rest_product_form
	*
	*	To show products and send 1 to WOO
	*
	*	Allow user to update the items based upon missing data...
	*
	************************************************************************/
	function exported_rest_products_form()
	{
		/*
		$prod_sql = 	"select * 
				from 0_woo";
		if( isset( $_POST['stock_id'] ) )
		{
			display_notification("Export Product" . $_POST['stock_id']);
			$prod_sql .= " where stock_id = " . $_POST['stock_id'];
		}
		$res = db_query( $prod_sql, "Couldn't select product(s) for export" );
		 */
		//var_dump( $res );
/*
		require_once( 'class.woo_product.php' );
		while( $prod_data = db_fetch_row( $res ) )
		{
			$woo_product = new woo_product( $this->woo_server, $this->woo_rest_path, $this->woo_ck, $this->woo_cs );
			$woo_product->slug = "test-product";
			$woo_product->type = "simple";
			$woo_product->status = "publish";
			$woo_product->featured = "0";
			$woo_product->virtual = "0";
			$woo_product->downloadable = "0";
			$woo_product->tax_status = "taxable";
			$woo_product->tax_class = "GST";
			$woo_product->manage_stock = "1";
			$woo_product->catalog_visibility = "visible";
			$woo_product->backorders = "yes";
			$woo_product->sold_individually = "0";
			$woo_product->weight = "1.0";
			$woo_product->reviews_allowed = "1";
			$woo_product->menu_order = "1";
			//$woo_product->dimensions = array();
			//$woo_product->shipping_class = "";
			//$woo_product->upsell_ids = "";
			//$woo_product->cross_sell_ids = "";
			//$woo_product->parent_id = "";
			$woo_product->categories = array( $row['category'] );
			//$woo_product->tags = array("");
			//$woo_product->images = array("");
			//$woo_product->attributes = array("");
			//$woo_product->default_attributes = "";
			//$woo_product->variations = array("");
			$woo_product->sku = $row['stock_id'];
			$woo_product->name = $row['description'];
			$woo_product->description = $row['long_description'];
			$woo_product->short_description = $row['description'];
			$woo_product->regular_price = $row['price'];
			$woo_product->stock_quantity =$row['instock'];
			$woo_product->in_stock = "1";

			$woo_product->create_product();
		}
 */
            	display_notification("Product selected.");
		$this->call_table( 'export_rest_product', "Export Another" );
/*	
			if( isset( $this->image_serverurl ) AND isset( $this->image_baseurl ) )
			{
				$line  .= $this->image_serverurl . '/' . $this->image_baseurl . '/' . $row['stock_id'] . '.jpg",';
			}
			else
			{
				$line  .= '"https://defiant.ksfraser.com/fhs/frontaccounting/company/0/images/' . $row['stock_id'] . '.jpg",';
			}
 */
	}
	/***********************************************************************
	*
	*	Function export_rest_product_form
	*
	*	To show products and send 1 to WOO
	*
	*	Allow user to update the items based upon missing data...
	*
	************************************************************************/
	function export_rest_product_form()
	{
		$missing_sql = "select sm.stock_id, sm.description, c.description, sm.inactive, sm.editable 
				from 0_stock_master sm, 0_stock_category c
				where sm.category_id = c.category_id and sm.stock_id in (select stock_id from 0_woo)";
		 global $all_items;
	  	//stock_items_list($name, $selected_id, $all_option, $submit_on_change,
	        //        array('cells'=>true, 'show_inactive'=>$all), $editkey);
		//function stock_items_list($name, $selected_id=null, $all_option=false,
	        				//$submit_on_change=false, $opts=array(), $editkey = false)
		$selected_id = "0";
		$name = "";
		$editkey = TRUE;
		$opts = array('cells'=>true, 'show_inactive'=>'1');
		$all_option = FALSE;
		$submit_on_change = TRUE;
	        //if ($editkey)
	                set_editor('item', $name, $editkey);
		start_form();

		start_table();
		table_section_title(_("Export a Product to WOO via REST"));
	//	label_row(_("No Transaction History (no inventory movement):"), NULL);
		label_row("&nbsp;", NULL);
		label_row("Press F4 to pop open a window to edit the item details.  The details WON'T make it to WOO until the 'All Products Export' routine is rerun", null);

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
	  	//echo stock_items_list($name, $selected_id, $all_option, $submit_on_change,
	        //        array('cells'=>true, 'show_inactive'=>$all), $editkey);
		end_table(); 
		submit_center( "exported_rest_products", "Export" );
		end_form();
	}
	function form_pricebook()
	{
		$fname = 'PriceBook.csv';
		$filename = '../../tmp/' . $fname;

		$fp = $this->open_write_file( $filename );
			$hline  = '"stock_id",';
			$hline .= '"category",';
			$hline .= '"Title",';
			$hline .= '"price",';
			$hline .= '"barcode",';
			$this->write_line( $fp, $hline );
		fflush( $fp );

		$woo = "select * from 0_woo order by category, description";
		$result = db_query( $woo, "Couldn't grab inventory to export" );

		$rowcount=0;
		while ($row = db_fetch($result)) 
		{
			$line  = '"' . $row['stock_id'] . '",';
			$line .= '"' . $row['category'] . '",';
			$line .= '"' . $row['description'] . '",';
			$line .= '"' . $row['price'] . '",';
			$line  .= '"*' . strtoupper( $row['stock_id'] ) . '*",';	//For 3of9 Barcode
			$this->write_line( $fp, $line );
			$rowcount++;
		}
		$this->file_finish( $fp );
            	display_notification("$rowcount rows of items created.");
		if( isset( $this->mailto ) )
		{
			$data = file_get_contents( $filename );
			$uu_data = "begin 644 " . $fname . "\n" . convert_uuencode($data) . "end\n";
			$subject = 'Pricebook file';
			$headers = 'From: sales@fraserhighlandshoppe.ca' . "\r\n" .
			    'Reply-To: sales@fraserhighlandshoppe.ca' . "\r\n";
			mail($this->mailto, $subject, $uu_data, $headers);
            		display_notification("email sent to $this->mailto.");
		}
	}
	/************************************************************************
	 *
	 *	exported_rest_coupon_form
 	 *	This Function exports a coupon via REST to Woo
	 *
	 ************************************************************************/
	function exported_rest_coupon_form()
	{
		require_once( 'class.woo_coupon.php' );
		
		$woo_coupon = new woo_coupon( $this->woo_server, $this->woo_rest_path, $this->woo_ck, $this->woo_cs );
		
			$woo_coupon->name = "Test Coupon";
		$woo_coupon->create_coupon();
	
	}
	/************************************************************************
	 *
	 *	exported_rest_customer_form
 	 *	This Function exports a customer via REST to Woo
	 *
	 ************************************************************************/
	function exported_rest_customer_form()
	{
		require_once( 'class.woo_customer.php' );
		
		$woo_customer = new woo_customer( $this->woo_server, $this->woo_rest_path, $this->woo_ck, $this->woo_cs );
		
			$woo_customer->name = "Test customer";
		$woo_customer->create_customer();
	}
	/************************************************************************
	 *
	 *	exported_rest_orders_form
 	 *	This Function exports a order via REST to Woo
	 *
	 ************************************************************************/
	function exported_rest_orders_form()
	{
		require_once( 'class.woo_orders.php' );
		
		$woo_orders = new woo_orders( $this->woo_server, $this->woo_rest_path, $this->woo_ck, $this->woo_cs );
		
			$woo_orders->name = "Test order";
		$woo_orders->create_orders();
	}
	/************************************************************************
	 *
	 *	exported_rest_refunds_form
 	 *	This Function exports a customer via REST to Woo
	 *
	 ************************************************************************/
	function exported_rest_refunds_form()
	{
		require_once( 'class.woo_refunds.php' );
		
		$woo_refunds = new woo_refunds( $this->woo_server, $this->woo_rest_path, $this->woo_ck, $this->woo_cs );
		
			$woo_refunds->name = "Test refunds";
		$woo_refunds->create_refunds();
	}
	/************************************************************************
	 *
	 *	exported_rest_products_form
 	 *	This Function exports a product via REST to Woo
	 *
	 ************************************************************************/
	function exported_rest_products_form()
	{
		require_once( 'class.woo_product.php' );
		
		$woo_prod = new woo_product( $this->woo_server, $this->woo_rest_path, $this->woo_ck, $this->woo_cs );
		
			$woo_prod->name = "Test Product";
			$woo_prod->slug = "test-product";
			$woo_prod->type = "simple";
			$woo_prod->status = "publish";
			$woo_prod->featured = "0";
			$woo_prod->catalog_visibility = "visible";
			$woo_prod->description = "Test Product desc";
			$woo_prod->short_description = "test prod short desc";
			$woo_prod->sku = "p-tp2";
			$woo_prod->regular_price = "9.99";
			$woo_prod->virtual = "0";
			$woo_prod->downloadable = "0";
			//$woo_prod->downloads = "";
			//$woo_prod->download_limit = "";
			//$woo_prod->download_expiry = "";
			//$woo_prod->download_type = "";
			//$woo_prod->external_url = "";
			//$woo_prod->button_text = "";
			$woo_prod->tax_status = "taxable";
			$woo_prod->tax_class = "GST";
			$woo_prod->manage_stock = "1";
			$woo_prod->stock_quantity = "2";
			$woo_prod->in_stock = "1";
			$woo_prod->backorders = "yes";
			$woo_prod->sold_individually = "0";
			$woo_prod->weight = "1.0";
			//$woo_prod->dimensions = array();
			//$woo_prod->shipping_class = "";
			$woo_prod->reviews_allowed = "1";
			//$woo_prod->upsell_ids = "";
			//$woo_prod->cross_sell_ids = "";
			//$woo_prod->parent_id = "";
			$woo_prod->purchase_note = "";
			//$woo_prod->categories = array("test-products");
			//$woo_prod->tags = array("");
			//$woo_prod->images = array("");
			//$woo_prod->attributes = array("");
			//$woo_prod->default_attributes = "";
			//$woo_prod->variations = array("");
			$woo_prod->menu_order = "1";
		$woo_prod->create_product();
	
	}
	/************************************************************************
	 *
	 *	form Products Exported
 	 *	This Function creates the CSV file of exported products
	 *
	 ************************************************************************/
	function form_products_exported()
	{
		//$this->populate_qoh();
		//$this->populate_woo();

		$this->form_pricebook();
		$fname = $this->vendor . '_ALL.csv';
		$filename = '../../tmp/' . $fname;
/*
 *	WooCommerce Fields:
 *		Title
 *		Description
 *		Short Description
 *		SKU
 *		Price
 *		Sale Price
 *		Virtual
 *		Downloadable
 *		Tax Status (Taxable, Not Taxable, Shipping Only)
 *		Tax Class (Standard, Reduced, Zero)
 *		Manage Stock (Y/N)
 *		Stock Qty
 *		Allow Backorders
 *		Sold Individually
 *		Shipping Weight
 *		Shipping Dimensions (Length, Width, Height)
 *		Shipping Class
 *		Linked Products (Cross Sell, Upsell - SKUs comma separated)
 *		Attributes (Name, Value)
 *		Image URL
 *		Product Categories
 *		Product Tags
 */
		$fp = $this->open_write_file( $filename );
			$hline  = '"stock_id",';
			$hline  .= '"SKU",';
			$hline  .= '"Image_URL",';
			//$hline .= '"category_id",';
			$hline .= '"category",';
			$hline .= '"Title",';
			$hline .= '"description",';
			$hline .= '"long_description",';
			//$hline .= '"units",';
			$hline .= '"price",';
			//$hline .= '"saleprice",';
			$hline .= '"instock",';
			$hline .= '"managestock",';
			$hline .= '"virtual",';
			$hline .= '"downloadable",';
			$hline .= '"tax_status",';
			$hline .= '"tax_class",';
			$hline .= '"allow_backorders",';
			$hline .= '"shipping_weight",';
			$hline .= '"shipping_length",';
			$hline .= '"shipping_width",';
			$hline .= '"shipping_height",';
			$hline .= '"shipping_class",';
			$hline .= '"cross_sell_SKUs",';
			$hline .= '"upsell_SKUs",';
			$hline .= '"tags",';
			$this->write_line( $fp, $hline );
		fflush( $fp );

		$woo = "select * from 0_woo";
		$result = db_query( $woo, "Couldn't grab inventory to export" );

		$rowcount=0;
		while ($row = db_fetch($result)) 
		{
			$line  = '"' . $row['stock_id'] . '",';
			$line  .= '"' . $row['stock_id'] . '",';
			//$line  .= '"http://fraserhighlandshoppe.ca/images/' . $row['stock_id'] . '.jpg",';
			if( isset( $this->image_serverurl ) AND isset( $this->image_baseurl ) )
			{
				$line  .= $this->image_serverurl . '/' . $this->image_baseurl . '/' . $row['stock_id'] . '.jpg",';
			}
			else
			{
				$line  .= '"https://defiant.ksfraser.com/fhs/frontaccounting/company/0/images/' . $row['stock_id'] . '.jpg",';
			}
			//$line .= '"' . $row['category_id'] . '",';
			$line .= '"' . $row['category'] . '",';
			$line .= '"' . $row['description'] . '",';
			$line .= '"' . $row['description'] . '",';
			$line .= '"' . $row['long_description'] . '",';
			//$line .= '"' . $row['units'] . '",';
			$line .= '"' . $row['price'] . '",';
			//$line .= '"' . $row['saleprice'] . '",';
			$line .= '"' . $row['instock'] . '",';
			$line .= '"1",';
			$line .= '"0",';
			$line .= '"0",';
			$line .= '"Taxable",';
			$line .= '"Standard",';
			$line .= '"1",';
			$line .= '"1",';	//Shipping weight
			$line .= '"1",';
			$line .= '"1",';
			$line .= '"1",';
			$line .= '"standard",';
			$line .= '"",';
			$line .= '"",';
			$line .= '"",';		//Tags
			$this->write_line( $fp, $line );
			$rowcount++;
		}
		$this->file_finish( $fp );
            	display_notification("$rowcount rows of items created.");
		if( isset( $this->mailto ) )
		{
			$data = file_get_contents( $filename );
			$uu_data = "begin 644 " . $fname . "\n" . convert_uuencode($data) . "end\n";
			$subject = $this->vendor . ' ALL Items CSV file';
			$headers = 'From: sales@fraserhighlandshoppe.ca' . "\r\n" .
			    'Reply-To: sales@fraserhighlandshoppe.ca' . "\r\n";
			mail($this->mailto, $subject, $uu_data, $headers);
            		display_notification("email sent to $this->mailto.");
		}
	}
	function export_orders()
	{
		/*
		 *	The first row is ignored
		 *	Needs MODEL NUMBER and QUANTITY
		 *	Any other columns are ignored (i.e. we can put a description as a third column)
		 *	Only 999 rows are accepted.
		 *	
		 *	If there are more than 999 rows, we will create only the first 999 lines
		 *	and set MAXOIDS to 999 so that this can be run a second time.
		 */
		$headers = array( 'Model', 'Quantity', 'Description' );

		/*
		 *	Table Purchase Orders
		 *		order_no
		 *		supplier_id
		 *
		 *	table Purchase Orders Details	
		 *		po_detail_item (index)
		 *		order_no
		 *		item_code (this is our code)
		 *		description
		 *		quantity_ordered
		 *
		 *
		 */
	
		$inserted = array();
		$ignored = array();
		$failed = array();
            	$ignoredrows = $rowcount = 0;
		if( !isset( $this->db_connection ) )
			$this->connect_db();	//connect to DB setting db_connection used below.
		if( !isset( $this->order_no ) )
		{
			if( isset( $_POST['order_no'] ) )
			{
				$this->set_var( 'order_no', $_POST['order_no'] );
			}
			else if( isset( $_GET['order_no'] ) )
			{
				$this->set_var( 'order_no', $_GET['order_no'] );
			}
			else
			{
				return FALSE;	//ERROR
			}
		}
		/*******************************************************************
		 *
		 *	Open the file and write the header row
		 *
		 *******************************************************************/
		$fname = $this->vendor . '_order_' . $this->order_no . '.csv';
		$filename = '../../tmp/' . $fname;
		$fp = fopen( $filename, 'w' );
		$hline = "";
		if( $this->include_header )
		{
			foreach( $headers as $column )
			{
				$hline .= $column . ",";
			}
			$this->write_line( $fp, $hline );
		}
		/******************************************************************
		 *
		 *	Should be able to use $this->get_purchase_order
		 *	so that this is generic - only need to change the output format...
		 *	
		 *	Could even add in mapping into the prefs so that we can
		 *	change the output on the fly.	
		 *
		 ******************************************************************/
		$this->get_purchase_order();
		foreach( $this->purchase_order->line_items as $po_line_details )
		{
			/*
			 *        var $line_no;
			 *        var $po_detail_rec;
			 *        var $grn_item_id;
			 *        var $stock_id;
			 *        var $item_description;
			 *        var $price;
			 *        var $units;
			 *        var $req_del_date;
			 *        var $tax_type;
			 *        var $tax_type_name;
			 *
			 *        var $quantity;          // current/entry quantity of PO line
			 *        var $qty_inv;   // quantity already invoiced against this line
			 *        var $receive_qty;       // current/entry GRN quantity
			 *        var $qty_received;      // quantity already received against this line
			 *
			 *        var $standard_cost;
			 *        var $descr_editable;
			 */

				$line = '"' . $po_line_details->stock_id . '","' . $po_line_details->quantity . '","' . $po_line_details->item_description . '","' . $po_line_details->line_no . '",';
				$this->write_line( $fp, $line );
				$rowcount++;
		}
		$this->file_finish( $fp );
            	display_notification("$rowcount rows of items created, $ignoredrows rows of items ignored, $this->maxrowsallowed rows allowed.");
		$this->set_pref( 'lastoid', $this->order_no );
		if( isset( $this->mailto ) )
		{
			$data = file_get_contents( $filename );
			$uu_data = "begin 644 " . $fname . "\n" . convert_uuencode($data) . "end\n";
			$subject = $this->vendor . ' Purchase Order CSV file';
			$headers = 'From: sales@fraserhighlandshoppe.ca' . "\r\n" .
			    'Reply-To: sales@fraserhighlandshoppe.ca' . "\r\n";

			mail($this->mailto, $subject, $uu_data, $headers);

		}
	}
}
?>
