<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array
 * */

require_once( 'class.woo_rest.php' );
require_once( 'class.woo_interface.php' );

class woo_product_attributes extends woo_interface {
	var $id;		//	integer 	Unique identifier for the resource.  read-only
	var $name;		//	string 	Attribute name. required
	var $slug;		//	string 	An alphanumeric identifier for the resource unique to its type.
	var $type;		//	string 	Type of attribute. Default is select. Options: select and text (some plugins can include new types)
	var $order_by;		//	string 	Default sort order. Default is menu_order. Options: menu_order, name, name_num and id.
	var $has_archives;		//	boolean 	Enable/Disable attribute archives. Default is false.
	
	function __construct($serverURL, $key, $secret, $options, $client)
	{
		parent::__construct($serverURL, $key, $secret, $options, $client);
		return;
	}
	function create_table()
	{
		$this->fields_array[] = array('name' => 'product_attributes_id', 'type' => 'int(11)');
		$this->fields_array[] = array('name' => 'id', 'type' => 'int(11)', 'comment' => 'WOOs id');
		$this->fields_array[] = array('name' => 'name', 'type' => 'varchar(32)');
		$this->fields_array[] = array('name' => 'slug', 'type' => 'varchar(32)');
		$this->fields_array[] = array('name' => 'type', 'type' => "enum('select','text')" );
		$this->fields_array[] = array('name' => 'order_by', 'type' => "enum('menu_order','name', 'name_num', 'id')" );
		$this->fields_array[] = array('name' => 'has_archives', 'type' => 'bool');
		$this->fields_array[] = array('name' => 'description', 'type' => 'varchar(255)', 'comment' => 'Not in WOO');
		$this->fields_array[] = array('name' => 'sku_order', 'type' => 'int(11)', 'comment' => 'What order is this attribute included in the SKU.  e.x. color before size -red-xl');
		$this->fields_array[] = array('name' => 'updated_ts', 'type' => 'timestamp', 'null' => 'NOT NULL', 'default' => 'CURRENT_TIMESTAMP');
		$this->table_details['tablename'] = $this->company_prefix . "product_attributes";
		$this->table_details['primarykey'] = "product_attributes_id";

		parent::create_table();
	}
	function build_properties_array()
	{
		/*All properties*/
		$this->properties_array = array(
			'id',
			'name',
			'slug',
			'type',
			'order_by',
			'has_archives'
		);
	}
	function build_write_properties_array()
	{
		/*Took the list of properties, and removed the RO ones*/
		$this->write_properties_array = array(
			'name',
			'slug',
			'type',
			'order_by',
			'has_archives'
		);
	}
	function get_attributes( $id )
	{
		try {
			$response = $this->wc_client->products->get_attributes( $id );
			//print_r( $response );
			$this->extract_data_obj( $response->product );
			//var_dump( $this->id );
		} catch ( WC_API_Client_Exception $e ) {
			echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//echo $e->getMessage() . PHP_EOL;
			$msg = $e->getMessage();
			//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			$code = $e->getCode();
		        echo $code . PHP_EOL;
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				switch( $code ) {
				default:
					echo "<br />" . __FILE__ . ":" . __LINE__ . ":Unhandled Error Code: " . $code . "<br />";
					break;
				}
				echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				print_r( $e->get_request() );
				echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				print_r( $e->get_response() );
			}
		}
	}
	function create_attribute()
	{
		$this->build_data_array();
		try {
			$this->wc_client->products->create_attribute( $this->data_array );
		} catch ( WC_API_Client_Exception $e ) {
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				$code = $e->getCode();
				//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				//var_dump( $code );
				$msg = $e->getMessage();
				//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				//var_dump( $msg );
				switch( $code ){
				default:
						echo "<br />" . __FILE__ . ":" . __LINE__ . "Unhandled Code " . $code . " with message " . $msg . "<br />";
					break;
				}
			}
		}
	}
	function create_attributes()
	{
		/*******************************************
		 * 
		 *	Take the list of attributes out of FA
		 *	and send them to WOO
		 *
		 */
		$attr_sql = "SELECT * from " . $this->table_details['tablename'];
			//This will ensure we send only items that haven't already been inserted.
		$attr_sql .= " WHERE id = ''";
		$attr_sql .= " ORDER BY sku_order";
		//$attr_sql .= " LIMIT 1";
		$res = db_query( $attr_sql, "Couldn't fetch attributes to export" );
		while( $attr_data = db_fetch_assoc( $res ) )
		{
			$this->array2var( $attr_data );
			$this->create_attribute();
		}

	}
}

?>
