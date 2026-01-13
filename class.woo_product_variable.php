<?php


require_once( 'class.woo_product.php' );

class woo_product_variable extends woo_product {
	
	function create_product()
	{
		 */
		
		$this->build_data_array();
		try {
			$this->data_array['title'] = $this->name;
			$this->wc_client->products->create( $this->data_array );
			//print_r( $response );
			//$this->extract_data_obj( $response->product );
			//var_dump( $this->id );
			//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//var_dump( $response );
		} catch ( WC_API_Client_Exception $e ) {
			//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//echo $e->getMessage() . PHP_EOL;
			//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			//echo $e->getCode() . PHP_EOL;
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				//var_dump( $e );
				$code = $e->getCode();
				//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				//var_dump( $code );
				$msg = $e->getMessage();
				//echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				//var_dump( $msg );
				switch( $code ){
				case    "woocommerce_rest_product_sku_already_exists":
						echo "<br />" . __FILE__ . ":" . __LINE__ . "SKU " . $this->sku . " Exists<br />";
						$this->get_product_by_sku( $this->sku );
						$this->update_wootable_woodata();	
						//We should go  update...
					break;
					case	"woocommerce_api_product_sku_already_exists":
					case 400:	//"woocommerce_api_product_sku_already_exists"
					//	echo "<br />" . __FILE__ . ":" . __LINE__ . "SKU " . $this->sku . " Exists<br />";
						$this->get_product_by_sku( $this->sku );
						$this->update_wootable_woodata();	
					//	echo "<br />" . __FILE__ . ":" . __LINE__ . "SKU " . $this->sku . " Exists<br />";
						$woop = $this->client->woo2wooproduct( $this->sku );
					//	echo "<br />" . __FILE__ . ":" . __LINE__ . "SKU " . $this->sku . " Exists<br />";
						$woop->id = $this->id;
						echo "<br />" . __FILE__ . ":" . __LINE__ . "SKU " . $this->sku . " Exists<br />";
						$woop->update_product();
					break;
				default:
					break;
				}
			}
		}
	
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
			
		$this->build_data_array();
		try {
			$this->data_array['title'] = $this->name;
			$this->wc_client->products->update( $this->id, $this->data_array );
		} catch ( WC_API_Client_Exception $e ) {
			echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			echo $e->getMessage() . PHP_EOL;
			echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			echo $e->getCode() . PHP_EOL;
			if ( $e instanceof WC_API_Client_HTTP_Exception ) {
				echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				print_r( $e->get_request() );
				echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
				print_r( $e->get_response() );
			}
		}
		return;
	}
}

?>
