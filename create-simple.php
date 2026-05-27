<?php

require_once( 'wc-master/lib/woocommerce-api.php' );

$options = array(
	'debug'           => true,
	'return_as_array' => false,
	'validate_url'    => false,
	'timeout'         => 30,
	'ssl_verify'      => false,
);

try {

	//$client = new WC_API_Client( 'https://fhsws001/devel/fhs/wordpress', 'ck_a960f347ee0e58ce3ade0df32be5ca7f54a4ffaf', 'cs_ce8df992fa05238888f7fe73fbef612e36a2d564', $options );
	$client = new WC_API_Client( 'https://fhsws001/devel/fhs/wordpress', 'ck_cda95dedab23cf564afdbe2cf9a78012707024ea', 'cs_93c85932f0c86c2b7f58fcb4c28e38bcdcde5361', $options );
	//$client = new WC_API_Client( 'https://fhsws001/devel/fhs/wordpress', 'ck_6142cbd363661b2fb71bc4931517379e47d00a1a', 'cs_3bdad51cfd6e2de115fd2e7eb75b088ed99881e6', $options );
	print_r( $client->products->create( array( 'title' => 'Test Product', 'type' => 'simple', 'regular_price' => '9.99', 'description' => 'test', 'sku' => 'test-prod' ) ) );

} catch ( WC_API_Client_Exception $e ) {

	echo $e->getMessage() . PHP_EOL;
	echo $e->getCode() . PHP_EOL;

	if ( $e instanceof WC_API_Client_HTTP_Exception ) {

		print_r( $e->get_request() );
		print_r( $e->get_response() );
	}
}
