<?php

require_once( '../ksf_modules_common/db_base.php' );
require_once( $path_to_root . '/purchasing/includes/po_class.inc' );	//Purchase Order (cart)
require_once( '../../purchasing/includes/ui/po_ui.inc' );				//read_po

class generic_orders extends db_base
//class generic_orders
{
	var $order_no;
        var $db_Host;
    	var $db_User;
    	var $db_Password;
    	var $db_Name;
    	var $last_order_no;
	var $vendor;
	var $tabs = array();
	var $found;
	var $purchase_order;
	var $config_values = array();	//What fields to be put on config screen
	var $help_context;
	var $action;			//for choosing what to do, forms
	var $redirect_to;		//script name to redirect to on install
	/*********************************************************************
	 *
	 *	This function must be overridden to work correctly
	 *	The inheriting class MUST set customer_index_name,
	 *	customer_table_name and vendor
	 *
	 *********************************************************************/
	function __construct( $host, $user, $pass, $database, $pref_tablename )
	{
//		echo "Generic constructor pref_tablename: $pref_tablename";
		parent::__construct( $host, $user, $pass, $database, $pref_tablename );
		$this->purchase_order = new purch_order();


	}
	function install()
	{
		$this->create_prefs_tablename();
        	$this->loadprefs();
        	$this->checkprefs();
		if( isset( $this->redirect_to ) )
		{
        		header("Location: " . $this->redirect_to );
		}
	}
	function get_purchase_order()
	{
		//read_po is FA provided method for reading a PO.
		//purchase_order will have an array (line_items) of class po_line_details
		if( !isset( $this->order_no ) )
			return FALSE;
		if( !isset( $this->purchase_order ) )
			return FALSE;
                read_po($this->order_no, $this->purchase_order);
	}
	function get_id_range()
	{
       		$sql = "SELECT MAX(`order_no`) as max FROM `" . $this->company_prefix . "purch_orders`";
    		$result = db_query($sql, "Couldn't get PO ID range" );
 		$this->order_no = max((int)$result['max'], $this->last_order_no+1);

    		return mysql_fetch_assoc($result);
	}
	function get_supplier_order()
	{
       		$sql = "SELECT o.order_no, s.supp_name, FROM `" . $this->company_prefix . "purch_orders` o, `" . $this->company_prefix . "suppliers` s where o.supplier_id = s.supplier_id";
    		$result = db_query($sql, "Couldn't get PO list" );
 		//$this->order_no = max((int)$result['min'], $this->last_order_no+1);
    		return mysql_fetch_assoc($result);
	}
	/*********************************************************************
	 *
	 *	This function must be overridden to work correctly
	 *	The inheriting class MUST set its SQL statement, as well as the
	 *	datasource specific processing into facust.
	 *
	 *********************************************************************/
	function export_orders()
	{
	//	if( !isset( $this->db_connection ) )
	//		$this->connect_db();	//connect to DB setting db_connection used below.
	}
	function loadprefs()
	{
    		// Get last oID exported
		foreach( $this->config_values as $row )
		{
			$this->set_var( $row['pref_name'], $this->get_pref( $row['pref_name'] ) );
		}
	}
	function updateprefs()
	{
		foreach( $this->config_values as $row )
		{
			if( isset( $_POST[$row['pref_name']] ) )
			{
				$this->set_var( $row['pref_name'], $_POST[ $row['pref_name'] ] );
				$this->set_pref( $row['pref_name'], $_POST[ $row['pref_name'] ] );
			}
		}
	}
	function checkprefs()
	{
		$this->updateprefs();
	}
	function action_show_form()
	{
		start_form(true);
	 	start_table(TABLESTYLE2, "width=40%");
		$th = array("Config Variable", "Value");
		table_header($th);
		$k = 0;
		alt_table_row_color($k);
			/* To show a labeled cell...*/
			//label_cell("Table Status");
			//if ($this->found) $table_st = "Found";
			//else $table_st = "<font color=red>Not Found</font>";
			//label_cell($table_st);
			//end_row();
		foreach( $this->config_values as $row )
		{
				text_row($row['label'], $row['pref_name'], $this->$row['pref_name'], 20, 60);
		}
		end_table(1);
		if (!$this->found) {
		    hidden('action', 'create');
		    submit_center('create', 'Create Table');
		} else {
		    hidden('action', 'update');
		    submit_center('update', 'Update Configuration');
		}
		end_form();
		
	}
	function form_export()
	{
		$selected_id = 1;
		$none_option = "";
		$submit_on_change = FALSE;
		$all = FALSE;
		$all_items = TRUE;
		$mode = 1;
		$spec_option = "";
		 start_form(true);

		 start_table(TABLESTYLE2, "width=40%");

		 table_section_title("Export Purchase Order");

		 $company_record = get_company_prefs();

		$this->get_id_range();

		$sql = "SELECT supp_name, order_no FROM " . $this->company_prefix . "purch_orders o, " . $this->company_prefix . "suppliers s where s.supplier_id = o.supplier_id";
		//echo combo_input("SupplierPO", $selected_id, $sql, 'supplier_id', 'supp_name',
/*
		echo combo_input("order_no2", $this->order_no, $sql, 'supp_name', 'order_no',
        		array(
                		//'format' => '_format_add_curr',
            			'order' => array('order_no'),
                		//'search_box' => $mode!=0,
                		'type' => 1,
        			//'search' => array("order_no","supp_name"),
                		//'spec_option' => $spec_option === true ? _("All Suppliers") : $spec_option,
                		'spec_id' => $all_items,
                		'select_submit'=> $submit_on_change,
                		'async' => false,
                		//'sel_hint' => $mode ? _('Press Space tab to filter by name fragment') :
                		//_('Select supplier'),
                		//'show_inactive'=>$all
                	)
		);
*/

		 text_row("Export " . $this->vendor . " Purchase Order ID:", 'order_no', $this->order_no, 10, 10);

		 end_table(1);

		 hidden('action', 'c_export');
		 submit_center('cexport', "Export  " . $this->vendor . " Purchase Orders");

		 end_form();
	}
	function related_tabs()
	{
		$action = $this->action;
		foreach( $this->tabs as $tab )
		{
			if( $action == $tab['action'] )
			{
				echo $tab['title'];
				echo '&nbsp;|&nbsp;';
			}
			else
			{
				if( $tab['hidden'] == FALSE )
				{
					hyperlink_params($_SERVER['PHP_SELF'], 
						_("&" .  $tab['title']), 
						"action=" . $tab['action'], 
						false);
					echo '&nbsp;|&nbsp;';
				}
			}
		}
	}
	function show_form()
	{
		$action = $this->action;
		foreach( $this->tabs as $tab )
		{
			if( $action == $tab['action'] )
			{
				//Call appropriate form
				$form = $tab['form'];
				$this->$form();
			}
		}
	}
	function base_page()
	{
		page(_($this->help_context));
		$this->related_tabs();
	}
	function display()
	{
		$this->base_page();
		$this->show_form();
		end_page();
	}
	function run()
	{
		if ($this->found) {
		        $this->loadprefs();
		}
		else
		{
		        $this->install();
		        $this->set_var( 'action', "show" );
		}
		
		if (isset($_POST['action']))
		{
		        $this->set_var( 'action', $_POST['action'] );
		}
		if (isset($_GET['action']) && $this->found)
		{
		        $this->set_var( 'action', $_GET['action'] );
		}

		$this->display();
	}
}

?>
