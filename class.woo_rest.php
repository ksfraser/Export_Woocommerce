<?php


require_once( __DIR__ . '/../ksf_modules_common/class.rest_client.php' ); 


//class EXPORT_WOO
class woo_rest extends rest_client
{
	var $loggedin;
	var $login_url;
	var $home_url;
	var $environment;
	var $grant_type;
	var $paypal_user;
	//var $URL;	//In base class
	var $server_url;
	var $transactionID;
	var $refundID;
	var $invoiceID;
	var $payment_path = "/v1/payments/payment";
	var $sale_path;		// /v1/payments/sale/<Transaction-Id>;
	var $refund_path;	// /v1/payments/sale/<Transaction-Id>/refund/;
	var $invoice_path = "/v1/invoicing/invoices";
	var $invoice_send;	// /v1/invoicing/invoices/<Invoice-Id>/send;
	var $invoice_remind;	// /v1/invoicing/invoices/<Invoice-Id>/remind;
	var $include_header;
	var $maxrowsallowed;
	var $lastoid;
	var $mailto;
	var $db;
	var $woo_rest_path;
	var $serverURL;
	var $URL;
	var $subpath;		//!< rest API endpoint
	var $data;
	var $authdata;
	var $conn_type;
	var $content_type;
	var $header_array;	//no longer has effect but can't change interface without breaking all callers.
	var $json_data;
	var $args;	//!< array for the constructor of the parent

	function __construct( $serverURL = "http://fhsws001.ksfraser.com/devel/fhs/wordpress", $subpath = "products", $data_array, $key = "ck_39bb90d3194c955e3ff2f8a0673d5735e3834785", $secret = "cs_c60df3106af131d3698bc6c91392c6ef65e17a53", $conn_type = "POST", $woo_rest_path =  "/wp-json/wc/v1/", /*deprec*/$header_array = null, $environment = "devel", $debug = 0 )
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		//$this->set_var( 'vendor', "EXPORT_WOO" );
		$this->username = $key;
		$this->password = $secret;
		$this->woo_rest_path = $woo_rest_path;	//AFTER server-woocommerce path
		$this->serverURL = $serverURL;
		$this->subpath = $subpath;
		$this->data_array = $data_array;
		$this->conn_type = $conn_type;
		$this->header_array = $header_array;
		$this->environment = $environment;

		$this->loggedin = FALSE;
		$this->environment = $environment;
		$this->grant_type="client_credentials";
		$this->debug = $debug;
		$this->buildURL();	//makes $this->URL

		$args = array();
		$args['URL'] = $this->URL;
		$args['consumer_key'] = $key;
		$args['consumer_secret'] = $secret;
		$args['data'] = $data_array;
		$args['method'] = $conn_type;
		$args['CURLOPT_COOKIEJAR'][CURLOPT_COOKIEJAR] = "my_cookies.txt";
		$args['CURLOPT_COOKIEFILE'][CURLOPT_COOKIEFILE] = "my_cookies.txt";
		if( strncasecmp( $this->environment, "PROD", 4 ) == 0 )
		{
			$args['CURLOPT_VERBOSE'] = 0;
		}
		else
		{
			$args['CURLOPT_VERBOSE'][CURLOPT_VERBOSE] = 1;
			$args['CURLOPT_SSL_VERIFYPEER'][CURLOPT_SSL_VERIFYPEER] = FALSE;
			$args['CURLOPT_SSL_VERIFYHOST'][CURLOPT_SSL_VERIFYHOST] = FALSE;
		}
		$this->args = $args;
		parent::__construct( $args );
		//$this->curl_setAuth();	//parent::__construct
	
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
	}
	/* NOT CALLED internally
	function set_content_type( $type )
	{
		$this->content_type = $type;
	}
	 */
	function buildURL()
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		$this->URL = $this->serverURL . $this->woo_rest_path . $this->subpath;
		//When we are called a second time within the same instance of an object
		//we will need to update the URL of the request.  That is why we would
		//be in this routine again...
		if( isset( $this->request->URL ) )
		{
			if( $this->debug == 1 )
			{
				display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
			}
			$this->request->URL = $this->URL;
		}
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
	}
	/******************************************************************************
	 * 
	 * Inspired by httpclient from woocommerce api client
	 * Should be able to call the following with
	 *   ($endpoint, http_build_query($this), $this)
	 *
	 * **************************************************************************/
	function post( $endpoint, $data = [], $client )
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		$this->subpath = $endpoint;
		$this->buildURL();
		//ASSUMING not JSON
		$this->request->data = $data;
		$this->write2woo( "POST", $client );
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
	}
	function put($endpoint, $data = [], $client )
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		$this->subpath = $endpoint;
		$this->buildURL();
		//ASSUMING not JSON
		$this->request->data = $data;
		$this->write2woo( "PUT", $client );
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
	}
	function get($endpoint, $data = [], $client )
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		$this->subpath = $endpoint;
		$this->buildURL();
		$this->request->params = $data;
		$this->write2woo( "GET", $client );
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
	}
	function delete($endpoint, $data = [], $client )
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		$this->subpath = $endpoint;
		$this->buildURL();
		$this->request->params = $data;
		$this->write2woo( "DELETE", $client );
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
	}
	/*********************************************************************************
	 *  !should be able to call...
	 * ******************************************************************************/
	function write2woo_json( $json_data, $c_type, $client = null )
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		$this->request->json_data = $json_data;
		return $this->write2woo( $c_type, $client );
	}
	function write2woo_object( $c_obj, $c_type, $client = null )
	{
		$this->request->data = http_build_query($c_obj);	//http_build_query would take an object and 
									//build an array of the public variables
		return $this->write2woo( $c_type, $client );
	}
	/*@string@*/function write2woo( $c_type = "POST", $client = null )
	{
		if( $this->debug == 1 )
		{
			display_notification( date('H:i:s', time()) . ":" . __METHOD__  . ":" . __LINE__);
		}
		$this->request->method = $c_type;

		if( $this->debug >= 2 )
		{
			echo "<br />" . __FILE__ . ":" . __LINE__ . "<br />";
			var_dump( $this->request );
		}
		if( $this->curl_exec() )	//parent (rest_client)
		{
			if( null != $client )
			{
				$client->request = $this->request;
				if( isset( $this->response->body->code ) )
					$client->code = $this->response->body->code;
				//else
				//	$client->code = $this->response->curlinfoarray->code;
				$client->response = $this->response;
				if( isset(  $this->response->body->message ) )
				{
					$client->message = $this->response->body->message;
				}
			}
			return $this->response->body;
		}
		else
		{
			display_error( "Curl Exec failed::" . $this->errmsg );
			//return FALSE;
			return "Curl Exec failed";
		}
	}

}
?>
