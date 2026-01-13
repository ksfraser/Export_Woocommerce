<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

/*********************************************************************************************
 *Converting a WOO definition to table definition

 		//CONVERT Woo definition to table definition
		// \t -> ;\t\/\/ 'type' =>
		// \t -> $this->fields_array[] = array('name' => '
		//integer -> 'int(11)',  'comment' => '
		//string -> varchar(" . STOCK_ID_LENGTH . ")',  'comment' => '
		//date-time -> 'timestamp',  'comment' => '
		//boolean -> 'bool',  'comment' => '
		//array -> 'int(11)',  'foreign_obj' => '',  'comment' => '
		//object -> 'int(11)',  'foreign_obj' => '',  'comment' => '
		//read-only -> 'readwrite' = > 'readonly'
		//^ -> \t\$this->fields_array[] = array('name' => '
		//;\t\/\/ -> ',
		// --> 'comment' => '
		// $ -> );
 * *******************************************************************************************/

require_once( '../ksf_modules_common/class.table_interface.php' );
require_once( '../ksf_modules_common/defines.inc.php' );

class woo_interface extends table_interface
{
	var $wc_client;
	var $woo_cs;
	var $woo_ck;
	var $write_properties_array;	//The list of WOO product properties (above) that are writeable.
	var $properties_array;
	var $foreign_objects_array;	//External objects that become embedded
	var $data_array;
	var $json_data;
	var $table_details;
	var $fields_array;
	var $company_prefix;
	var $client;	//Who is using us.  should be passed as $this 
	var $next_ptr;	//For Linked list
	var $prev_ptr;	//for Linked List
	var $serverURL;
	var $key;
	var $secret;
	var $options;
	var $debug;
	var $request;
	var $response;
	var $code;
	var $message;
	var $server_data_object;
	var $conn_type;
	var $header_array;
	var $enviro;
	var $entry_array;
	var $selected_id;	//!< find_submit sets this
	var $iam;
	var $interestedin;	//!< array of 'events' we are interested in and their associated data

	/******************************************************************************************//**
	 *
	 * @param string WooCommerce Store URL
	 * @param string the OAuth Key
	 * @param string the OAuth secret
	 * @param array options
	 * @return null
	 * *******************************************************************************************/
	/*@void@*/function __construct($serverURL = " ", $key, $secret, $options, $client = null)
	{
		$this->iam = get_class( $this );
		$this->table_details = array();
		$this->fields_array = array();
		$this->client = $client;
		if( isset( $this->client->debug ) )
			$this->debug = $this->client->debug;
		else
			$this->debug = 0;
		$this->serverURL = $serverURL;
		$this->key = $key;
		$this->secret = $secret;
		/*************************************************************
		 *	I want to depreciate the use of WC_API_CLIENT since
		 *	the latest API of WooCommerce uses WP REST interface
		 *	so the WC interface is depreciated
		 * ***********************************************************/
		require_once( 'wc-master/lib/woocommerce-api.php' );
		if( !isset( $options ) OR $options == null )
			$options = array(
				'debug'           => true,
				'return_as_array' => false,
				'validate_url'    => false,
				'timeout'         => 30,
				'ssl_verify'      => false,
			);
		$this->options = $options;
		//$this->wc_client = null;
		//Still used by class.woo_product
		if( strlen( $key ) < 10 )
		{
			if( null != $client )
			{
				$this->wc_client = new WC_API_Client( $serverURL, $client->woo_ck, $client->woo_cs, $options, $client );
			}
			else
				$this->wc_client = null;
		}
		else
			if( strlen( $serverURL) > 10 )
				$this->wc_client = new WC_API_Client( $serverURL, $key, $secret, $options, $client );
			else
				$this->wc_client = null;

		global $db_connections;
		$this->company_prefix = $db_connections[$_SESSION["wa_current_user"]->cur_con]['tbpref'];
		if( strlen( $this->company_prefix ) < 2 )
		{
			if( isset( $this->client->company_prefix ) )
			{
				$this->company_prefix = $this->client->company_prefix;
			}
			else
			{
				$this->company_prefix = "0_";
			}
		}

		$this->define_table();
		$this->write_properties_array = array();
		$this->properties_array = array();
		$this->foreign_objects_array = array();
		$this->build_write_properties_array();
		$this->build_properties_array();
		$this->fields_array2entry();
		$this->build_interestedin();
		$this->register_with_eventloop();
		return;
	}
	function backtrace()
	{
		echo "<br />";
		array_walk(debug_backtrace(),create_function('$a,$b','print "{$a[\'function\']}()(".basename($a[\'file\']).":{$a[\'line\']});<br /> ";'));
	}
	/************************************************************//**
	 *
	 *	tell.  Function to tell the using routine that we took
	 *	an action.  That will let the client pass that data to
	 *	any other plugin routines that are interested in that
	 *	fact.
	 *
	 *	@param msg what event message to pass
	 *	@param method Who triggered that event so that we don't pass back to them into an endless loop
	 *
	 * **************************************************************/
	function tell( $msg, $method )
	{
		$this->notify( __METHOD__ . "::"  . __LINE__, "WARN" );
		if( isset( $this->client ) )	//if not set nobody to tell
			if( isset( $msg ) )	//If not set nothing to pass along...
				if( is_callable( $this->client->eventloop( $msg, $method ) ) )
					$this->client->eventloop( $msg, $method );
	}
	/***************************************************************//**
	 *dummy   
	 *
	 * 	Dummy function so that build_interestedin has something to
	 * 	put in as an example.
	 *
	 * 	@returns FALSE
	 * ******************************************************************/
	function dummy( $obj, $msg )
	{
		return FALSE;
	}
	function register_with_eventloop()
	{
		global $eventloop;
		if( null != $eventloop )
		{
			foreach( $this->interestedin as $key => $val )
			{
				$eventloop->ObserverRegister( $this, $key );
			}
		}
	}
	/***************************************************************//**
	 *build_interestedin
	 *
	 * 	DEMO function that needs to be overridden
	 * 	This function builds the table of events that we
	 * 	want to react to and what handlers we are passing the
	 * 	data to so we can react.
	 * ******************************************************************/
	function build_interestedin()
	{
		//This NEEDS to be overridden
		$this->interestedin[WOO_DUMMY_EVENT]['function'] = "dummy";
	}
	/***************************************************************//**
	 *notified
	 *
	 * 	When we are notified that an event happened, check to see
	 * 	what we want to do about it
	 *
	 * @param $obj Object of who triggered the event
	 * @param $event what event was triggered
	 * @param $msg what message (data) was passed to us because of the event
	 * ******************************************************************/
	function notified( $obj, $event, $msg )
	{
		if( isset( $this->interestedin[$event] ) )
		{
			$tocall = $this->interested[$event]['function'];
			$this->$tocall( $obj, $msg );
		}
	}
	/************************************************************//**
	 *
	 *	register.  Function to tell the using routine that we are
	 *	interested in other plugins having taken an action. 
	 *
	 *	@param msg what event message we are interested in
	 *	@param method Who we are so we can be told
	 *
	 * **************************************************************/
	function register( $msg, $method )
	{
		$this->notify( __METHOD__ . "::"  . __LINE__, "WARN" );
		if( isset( $this->client ) )
			if( is_callable( $this->client->eventregister() ) )
				$this->client->eventregister( $msg, $method );
	}
	/**********************************************************//**
	 * Log to screen depending on level
	 *
	 * Levels
	 * 	ERROR (display error on screen)
	 * 	WARN (debug level 1)
	 * 	NOTIFY (debug level 2)
	 *	DEBUG (debug level 3)
	 *
	 * ***********************************************************/
	function notify( $msg, $level = "ERROR" )
	{
		if( "ERROR" == $level )
		{
			display_error( $msg );
		}
		else if( "WARN" == $level AND $this->debug >= 1)
		{
			display_notification( $msg );
		}
		else if( "NOTIFY" == $level AND $this->debug >= 2)
		{
			display_notification( $msg );
		}
		else if( "DEBUG" == $level AND $this->debug >= 3)
		{
			display_notification( $msg );
		}
		else
		{
			display_notification( $msg );
		}

	}

	/*********************************************************************************//**
	 *fields_array2var
	 *	Take the data out of POST variables and put them into
	 *	the variables defined as table columns (fields_array)
	 *
	 *	@returns int count of fields set
	 *
	 * ***********************************************************************************/
	/*@int@*/function fields_array2var()
	{
		$count = 0;
		$this->reset_values();
		foreach( $this->fields_array as $row )
		{
			$var = $row['name'];
			if( isset( $_POST[$var] ) )
			{
				$this->$var = $_POST[$var];
				$count++;
			}
		}
		return $count;
	}
	/*********************************************************************************//**
	 *master_form
	 *	Display 2 forms - the summary of items with edit/delete
	 *		The edit/entry form for 1 row of data
	 *	assumes entry_array has been built (constructor)
	 *	assumes table_details has been built (constructor)
	 *	assumes selected_id has been set (constructor?)
	 *	assumes iam has been set (constructor)
	 *
	 * ***********************************************************************************/
	function master_form()
	{
		global $Ajax;
		//var_dump( $_POST );
		//var_dump( $_GET );
		$this->notify( __METHOD__ . "::"  . __LINE__, "WARN" );
		//simple_page_mode();
		div_start('form');
		//$this->notify( __METHOD__ . "::"  . __LINE__ . " Mode: " . $Mode );
		$this->selected_id = find_submit('Edit');
		$count = $this->fields_array2var();
		$key = $this->table_details['primarykey'];
		if( isset( $this->$key ) )
		{
			$this->notify( __METHOD__ . ":" . " Key set.  Updating", "WARN" );
			$this->update_table();
		}
		else if( $count > 0 )
		{
			$this->notify( __METHOD__ . ":" . " Key NOT set.  Inserting", "WARN" );
			$this->insert_table();
		}
		$this->reset_values();
		
		$sql = "SELECT ";
		$rowcount = 0;
		foreach( $this->entry_array as $row )
		{
			if( $rowcount > 0 ) $sql .= ", ";
			$sql .= $row['name'];
			$rowcount++;
		}
		$sql .= " from " . $this->table_details['tablename'];
		if( isset( $this->table_details['orderby'] ) )
			$sql .= " ORDER BY " . $this->table_details['orderby'];
	
		$this->notify( __METHOD__ . ":" . __LINE__ . ":" . $sql, "WARN" );
		$this->notify( __METHOD__ . ":" . __LINE__ . ":" . " Display data", "WARN" );
		$this->display_table_with_edit( $sql, $this->entry_array, $this->table_details['primarykey'] );
		$this->display_edit_form( $this->entry_array, $this->selected_id, "create_" . $this->iam . "_form" );
		div_end();
		//$Ajax->activate('form');
	}
	/*@array@*/function fields_array2entry()
	{
		//debug_print_backtrace();
		//array_walk(debug_backtrace(),create_function('$a,$b','print "{$a[\'function\']}()(".basename($a[\'file\']).":{$a[\'line\']});<br /> ";'));
		$this->notify( __METHOD__ . "::"  . __LINE__ . " Entering " . __METHOD__, "DEBUG" );
		//Take a fields_array definition and conver to the array needed
		//to create edit forms for display_table_with_edit and display_edit_form
		$entry_array = array();
		$count = 0;
		foreach( $this->fields_array as $row )
		{
			$entry_array[$count]['column'] = $row['name'];
			if( !isset( $row['foreign_obj'] ) )
			{
				$this->notify( __METHOD__ . "::"  . __LINE__, "DEBUG" );
				$open = strpos($row['type'], "(");
				if( false !== $open )
				{
					$type = strstr( $row['type'], 0, $open );
					$close = strpos( $row['type'], ")" );
					$num = strstr( $row['type'], $open, $close );
					$entry_array[$count]['type'] = $type;
					$entry_array[$count]['size'] = $num;
				}
				else
				{
					$entry_array[$count]['type'] = $row['type'];
				}
			}
			else
			{
				$this->notify( __METHOD__ . "::"  . __LINE__, "DEBUG" );
				//It is an index into another table.  Should be a drop down in edit form
				$entry_array[$count]['type'] = "dropdown";
				$entry_array[$count]['size'] = "11";
				$entry_array[$count]['foreign_obj'] = $row['foreign_obj'];
				if( isset( $row['foreign_column'] ) )
					$entry_array[$count]['foreign_column']= $row['foreign_column'];
				else
					$entry_array[$count]['foreign_column']= $row['name'];

				//
				//Ensure that foreign_object_array contains the table too...
			}
				$entry_array[$count]['name'] =	$row['name'];
			if( isset( $row['label'] ) )
				$entry_array[$count]['label'] =	$row['label'];
			else
			if( isset( $row['comment'] ) )
				$entry_array[$count]['label'] =	$row['comment'];
			else
				$entry_array[$count]['label'] =	$row['name'];
			if( isset( $row['readwrite'] ) )
				$entry_array[$count]['readwrite'] =	$row['readwrite'];
			else
				$entry_array[$count]['readwrite'] = "readwrite";	//ASSUMING no restriction...
			$count++;
		}
		$this->entry_array = $entry_array;
		$this->notify( __METHOD__ . "::"  . __LINE__ . " Leaving " . __METHOD__, "WARN" );
		return $entry_array;
	}
	function display_table_with_edit( $sql, $headers, $index, $return_to = null )
	{
		$this->notify( __METHOD__ . "::"  . __LINE__, "DEBUG" );
		$columncount = 0;
		foreach( $headers as $row )
		{
			$th[$columncount] = $row['label'];
			$datacol[$columncount] = $row['name'];
			$columncount++;
		}
		//Edit
			$th[$columncount] = "";
			$columncount++;
		//Delete
			$th[$columncount] = "";
			//$th[$columncount] = $row[$index];
			$columncount++;
			//$multi=false, $dummy=false, $action="", $name=""
		start_form( );
		//start_form( false, false, "woo_form_handler.php", "" );
		start_table(TABLESTYLE, "width=80%" );
		//inactive_control_column($th);
		table_header( $th );
		$k=0;

		$result = db_query( $sql, __METHOD__ . " Couldn't run query" );
		while( $nextrow = db_fetch( $result ) )
		{
			alt_table_row_color($k);
			for( $c = 0; $c <= $columncount - 3; $c++ )
			{
				label_cell( $nextrow[$c] );
			}
			edit_button_cell("Edit" . $nextrow[$index], _("Edit") );
			delete_button_cell("Delete" . $nextrow[$index], _("Delete") );
			//inactive_control_cell( $nextrow[$index] );
			end_row();
		}
		//inactive_control_row($th);
		hidden( 'table_with_edit', 1 );
		if( null != $return_to )
			hidden( 'return_to', $return_to );
		end_table();
		end_form();
	}
	function form_post_handler()
	{
		$this->notify( __METHOD__ . "::"  . __LINE__, "WARN" );
		$count = 0;
		if( isset( $_POST['table_with_edit'] ) )
		{
			//Need to load that record and then send to display_edit_form
		}
		else if( isset( $_POST['edit_form'] ) )
		{
			//load variables into our values...
			foreach( $this->properties_array as $var )
			{
				if( isset( $_POST[$var] ) )
				{
					$this->$var = $_POST[$var];
					$count++;
				}
			}
		}
		//$count = count( $this->entry_array );	
		$key = $this->table_details['primarykey'];
		if( isset( $this->$key ) )
		{
			$this->notify( __METHOD__ . ":" . " Key set.  Updating", "WARN" );
			$this->update_table();
		}
		else if( $count > 0 )
		{
			$this->notify( __METHOD__ . ":" . " Key NOT set.  Inserting", "WARN" );
			$this->insert_table();
		}
		else
		{
			echo "<br />" . __METHOD__ . " No values set.  Why are we here?<br />";
			echo "<br />" . __METHOD__ . " Class is " . get_class( $this ) . "<br />";
			var_dump( $_POST );
			$this->wc_client = null;
			echo "<br /><br />" . __METHOD__ . " Class variables are <br />";
			 var_dump( $this );
		}
	}
	function display_edit_form( $form_def, $selected_id = -1, $return_to )
	{
		$this->notify( __METHOD__ . "::"  . __LINE__, "DEBUG" );
		if( $selected_id > -1 )
		{
			//We are editing a row, so need to query for the values
			$sql = "SELECT * from " . $this->table_details['tablename'];
			$sql .= " WHERE " . $this->table_details['primarykey'] . " = '" . $selected_id . "'";
			$res = db_query( $sql, __METHOD__ . " Couldn't query selected" );
			$arr = db_fetch_assoc( $res );
			$this->array2var( $arr );
		}
		start_form(  );
		//start_form(  false, false, "woo_form_handler.php", "" );
		start_table(TABLESTYLE2 );
		foreach( $form_def as $row )
		{
			$var = $row['name'];
			if( $row['readwrite'] == "read" )
			{
				//can't edit this column as it isn't set write nor readwrite
				if( isset( $this->$var ) )
					label_row( _($row['label'] . ":"), $this->$var );
			}
			else
			{
				if( $row['type'] == "varchar" )
					text_row(_($row['label'] . ":"), $row['name'], $this->$var, $row['size'], $row['size']);
				/*
				else if( $row['type'] == "dropdown" )
				{
					$ddsql = "select * from " . $row['foreign_obj'];
					$ddsql .= " ORDER BY " . $row['foreign_column'];
					$this->combo_list_row( $ddsql, $row['foreign_column'], 
								_($row['label'] . ":"), $row['name'], 
								$selected_id, false, false ); 
				}
				 */
				else if( $row['type'] == "bool" )
					check_row(_($row['label'] . ":"), $row['name'] ); 
				else
					text_row(_($row['label'] . ":"), $row['name'], null, $row['size'], $row['size']);
			}
		}


		end_table();
		hidden( 'edit_form', 1 );
		hidden( 'my_class', get_class( $this ) );
		hidden( 'return_to', $return_to );
		hidden( 'action', $return_to );
		submit_center('ADD_ITEM', _("Add Item") );
//		submit_add_or_update_center($selected_id == -1, '', 'both', false);
		end_form();
		if( $this->debug >= 3 ) $this->backtrace();
	}
	function combo_list( $sql, $order_by_field, $name, $selected_id=null, $none_option=false, $submit_on_change=false)
	{
		global $path_to_root;
		include_once( $path_to_root . "/includes/ui/ui_lists.inc" );
		return combo_input($name, $selected_id, $sql, $order_by_field,  'name',
		array(
			'order' => $order_by_field,
			'spec_option' => $none_option,
			'spec_id' => ALL_NUMERIC,
			'select_submit'=> $submit_on_change,
			'async' => false,
		) );
	}
	function combo_list_cells( $sql, $order_by_field, $label, $name, $selected_id = null, $none_option=false, $submit_on_change=false )
	{
		echo "<td>$label</td>";
		echo "<td>";
		$this->combo_list( $sql, $order_by_field, $name, $selected_id, $none_option, $submit_on_change);
		echo "</td>";
	}
	function combo_list_row( $sql, $order_by_field, $label, $name, $selected_id = null, $none_option=false, $submit_on_change=false )
	{
		echo "<tr><td class='label'>$label</td>";
		$this->combo_list_cells( $sql, $order_by_field, $label, $name, $selected_id, $none_option, $submit_on_change);
		echo "</tr>";
	}
	function define_table()
	{
		//Inheriting class MUST extend
		//	$this->fields_array[] = array('name' => 'billing_address_id', 'type' => 'int(11)', 'comment' => '', 'readwrite' => '');  readwrite can be read or write or undefined.
		//	$this->table_details['tablename'] = $this->company_prefix . "woo_billing_address";
		//	$this->table_details['primarykey'] = "billing_address_id";

		//The following should be common to pretty well EVERY table...
		$ind = "id_" . $this->iam;
		$this->fields_array[] = array('name' => $ind, 'type' => 'int(11)', 'auto_increment' => 'yes', 'readwrite' => 'read' );
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP', 'readwrite' => 'read' );
		$this->table_details['tablename'] = $this->company_prefix . $this->iam;
		$this->table_details['primarykey'] = $ind;
		//$this->table_details['index'][0]['type'] = 'unique';
		//$this->table_details['index'][0]['columns'] = "variablename";
		//$this->table_details['index'][0]['keyname'] = "variablename";

	}

	function build_write_properties_array()
	{
				/*Took the list of properties, and removed the RO ones*/
		foreach( $this->fields_array as $row )
		{
			if( isset( $row['foreign_obj'] ) )
			{
				//$this->foreign_objects_array[] = trim( $row['name'] );
			}
			else
			if( isset( $row['readwrite'] ) )
			{
				if( strncmp( $row['readwrite'], "read", 4 ) <> 0 )
				{
					//Not READONLY
					$this->write_properties_array[] = trim( $row['name'] );
				}
			}
			else
			{
				//Assuming NOT set therefore RW
				$this->write_properties_array[] = trim( $row['name'] );
			}
		}
	}
	function build_properties_array()
	{
		/*All properties*/
		foreach( $this->fields_array as $row )
		{
			//echo "<br />" . __LINE__ . "<br />";
			//var_dump( $row );
			if( isset( $row['foreign_obj'] ) )
			{
		//		echo __LINE__ . " Foreign Object " . $row['name'] . "<br />";
				$this->foreign_objects_array[] = $row['name'];
			}
			else
				$this->properties_array[] = trim( $row['name'] );
		}

	}
	function build_foreign_objects_array()
	{
		//Extending class needs to override!!
	}
	function array2var( $data_array )
	{
		foreach( $this->properties_array as $property )
		{
			if( isset( $data_array[$property] ) )
			{
				$this->$property = $data_array[$property];
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
	/*******************************************************************//**
	 *
	 * 	reset_values.  unset all variables listed in properties_array
	 *
	 * 	As we cycle through a database result set putting values into
	 * 	the object, we want to ensure we don't have any values left over
	 * 	from the previous row.  This unsets all values so that they
	 * 	are cleared.
	 * 
	 * **********************************************************************/
	function reset_values()
	{
		foreach( $this->properties_array as $val )
		{
			unset( $this->$val );
		}
	}

	/***************************************************************
	 *
	 * Extract Data Objects
	 *
	 * Recursively extracts the data object
	 * Builds a double linked list in the process.
	***************************************************************/
	function extract_data_objects( $srvobj_array )
	{
		//Woo sends an array of the objects
		$nextptr = $this;
		$objectcount = 0;
		foreach( $srvobj_array as $obj )
		{
			$newobj = new $this->iam($this->serverURL, $this->key, $this->secret, $this->options, $this );
			//Do the recursive extract.
			$newobj->extract_data_obj( $obj );
			//Add into Linked List
			$nextptr->next_ptr = $newobj;
			$newobj->prev_ptr = $nextptr;
			$nextptr = $newobj;
			$objectcount++;
			//The next time through the loop does another...
		}
		return $objectcount;
	}
	/*int count of properties extracted*/
	/*@int@*/function extract_data_array( $assoc_array )
	{
		$extract_count = 0;
		foreach( $this->properties_array as $property )
		{
			if( isset( $assoc_array[$property] ) )
			{
				$this->$property = $assoc_array[$property];
				$extract_count++;
			}
		}
		//Should also handle FK indexes, but for now...
		return $extract_count;
	}

	/*int count of properties extracted*/
	/*@int@*/function extract_data_obj( $srvobj )
	{
		if( $this->debug >= 3 )
		{
			echo "<br />" . __FILE__ . ":" . __LINE__ . "<br /><br />";
			var_dump( $srvobj );
		}
		$extract_count = 0;
		foreach( $this->properties_array as $property )
		{
			if( isset( $srvobj->$property ) )
			{
				$this->$property = $srvobj->$property;
				$extract_count++;
			}
		}
		if( $this->debug >= 3 )
		{
			echo __FILE__ . ":" . __LINE__ . "<br />Extracted " . $extract_count . " properties<br />";
		}
		//
		//echo __FILE__ . ":" . __LINE__ . "<br /><br />";
		//var_dump( $this->foreign_objects_array );
		foreach( $this->foreign_objects_array as $foa )
		{
			echo __FILE__ . ":" . __LINE__ . "<br />Foreign Object " . $foa . "<br />";
			if( isset( $srvobj->$foa ) )
			{
				if( $this->debug > 0 )
				{
					echo "<br /><br />" . __FILE__ . ":" . __LINE__ . "<br />Extract for class " . $foa . "<br />";
					var_dump( $srvobj->$foa );
				}
				require_once( 'class.woo_' . $foa . '.php' );
				if( is_array( $srvobj->$foa ) )
				{
					foreach( $srvobj->$foa as $obj )
					{
						$newclassname = "woo_" . $foa;
						$newobj = new $newclassname($this->serverURL, $this->key, $this->secret, $this->options, $this);
						$ret = $newobj->extract_data_obj( $obj );
						if( $ret > 0 )
						{
							$newobj->insert_table();
							$this->$foa = $this->$foa + 1;	//Count of the numbers of foreign rows.
							if( $this->debug > 0 )
							{
								echo "<br />" . __FILE__ . ":" . __LINE__ . "<br /><br />";
								var_dump( $newobj );
							}
						}
						unset( $newobj );	//Free the memory
					}
				}
				else
				if( is_object( $srvobj->$foa ) )
				{
					$newclassname = "woo_" . $foa;
					$newobj = new $newclassname($this->serverURL, $this->key, $this->secret, $this->options, $this);
					$ret = $newobj->extract_data_obj( $srvobj->$foa );
					if( $ret > 0 )
					{
						$newobj->insert_table();
						$this->$foa = $this->$foa + 1;	//Count of the numbers of foreign rows.
						if( $this->debug > 0 )
						{
							echo "<br />" . __FILE__ . ":" . __LINE__ . "<br /><br />";
							var_dump( $newobj );
						}
					}
					unset( $newobj );	//Free the memory
				}
			}
		}
		return $extract_count;
	}
	function build_json_data()
	{
		$this->json_data = json_encode( $this->data_array );
		//echo $this->json_data;
	}
	/*@bool@*/function prep_json_for_send( $func = NULL )
	{
		$this->build_data_array();
		$this->build_json_data();
		if( $this->json_data == FALSE )
		{
			$this->notify( __LINE__ . " " . $func . " Failed to build JSON data", "ERROR" );
			return FALSE;
		}
		else
			return TRUE;
	}
	function ll_walk_insert_fa()
	{
		$this->notify( __METHOD__ . "::"  . __LINE__, "WARN" );
		$nextptr = $this->next_ptr;
		while( $nextptr != NULL )
		{
			//if "id" not in the table, insert else update
			if( $this->check_table_for_id() )
				$nextptr->update_table();
			else
				$nextptr->insert_table();
			$nextptr = $nextptr->next_ptr;
		}
	}
	function ll_walk_update_fa()
	{
		$this->notify( __METHOD__ . "::"  . __LINE__, "WARN" );
		$nextptr = $this->next_ptr;
		while( $nextptr != NULL )
		{
			$nextptr->update_table();
			$nextptr = $nextptr->next_ptr;
		}
	}

}

?>
