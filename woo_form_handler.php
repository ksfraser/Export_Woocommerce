<?php
//Only master_form? has the action set to this file.
//It would be an AJAX call.

foreach (glob("{$moduledir}/class.*.php") as $filename)
{
	include_once( $filename );
}

if( isset( $_POST['edit_form'] ) )
{
	//AJAX submit, so class would not be run...
	$my_class = $_POST['my_class'];
	$action = $_POST['action'];
	$return_to = $_POST['return_to'];
	//Should be some fields...
	$goto = new $my_class(null, null, null, null, null );
	$count = $goto->fields_array2var();
	$key = $goto->table_details['primarykey'];
	if( isset( $goto->$key ) )
	{
		$goto->notify( __METHOD__ . ":" . " Key set.  Updating", "WARN" );
		$goto->update_table();
	}
	else if( $count > 0 )
	{
		$goto->notify( __METHOD__ . ":" . " Key NOT set.  Inserting", "WARN" );
		$goto->insert_table();
	}
	$Ajax->activate('form');	
}





?>
