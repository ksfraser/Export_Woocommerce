<?php

/*******************************************
 * If you change the list of properties below, ensure that you also modify
 * build_write_properties_array appropriately
 * */

/*******************************************************************************************//**
 *
 * assumption is we copied images directory to remote server so that we can check the files existance!
 * TODO:
 *	
 * ***********************************************************************************************/

require_once( 'class.woo_rest.php' );
require_once( 'class.woo_interface.php' );

class woo_image {
	var $id;
	var $src;
	var $position;
	var $image_serverurl;
	var $image_baseurl;
	var $image_name;
	var $stock_id;
	var $pic_num;
	var $client;
	var $name;	//!<string for Woo
	var $alt;	//!<string for Woo
	var $debug;
	var $remote_img_srv;	//boolean
	function __construct( $stock_id, $pic_num, $server_url, $base_url, /*unused*/$client, $debug, $remote_img_srv = FALSE )
	{
		$this->stock_id = $stock_id;
		$this->pic_num = $pic_num;
		$this->image_server_url = $server_url;
		$this->image_base_url = $base_url;
		$this->client = $client;
		$this->debug = $debug;
		echo "<br /><br />" . __METHOD__ . ":" . __LINE__;
		$this->remote_img_srv = $remote_img_srv;
		$this->run();
	}
	/***************************************************************************//**
	 * See if the image exists and return its name if it does else NULL
	 *
	 * 	We can grab images from remote servers but the file_exists check
	 * 	will of course fail.  So if it is indicated remote, don't check.
	 *
	 * @param string stock_id
	 * @returns string|null filename or nothing
	 * ****************************************************************************/
	/*@string@*/function image_exists( $stock_id )
	{
		$filename = $this->image_base_url . item_img_name($stock_id) . ".jpg";
		if( $this->debug > 2 )
		{
			echo "<br /><br />" . __METHOD__ . ":" . __LINE__;
			var_dump($this->remote_img_srv);
			echo "<br /><br />" . __METHOD__ . ":" . __LINE__;
		}
		if( $this->remote_img_srv )
		{
			//assumption is we copied images directory to remote server so that we can check the files existance!
			if( file_exists( company_path().'/images/' . item_img_name($stock_id) . ".jpg" ) )
				return $filename;
			else
				return null;
		}
		if( file_exists( $filename ) === TRUE )
			return $filename;
		else
		{
			$filename = company_path().'/images/' . item_img_name($stock_id) . ".jpg";
			if( file_exists( $filename ) === TRUE )
			{
				global $path_to_root;
				$filename = str_replace( $path_to_root, "", $filename );
				if( $this->debug > 1 )
				{
					echo __METHOD__ . ":" . __LINE__ . " Image Filename post str_replace<br /><br />";
					var_dump( $filename );
				}
				return $filename;
			}
			else
				return NULL;
		}
		return NULL;
	}
	/***********************************************************************//**
	 * Called by the constructor to set SRC and POSITION info
	 * 
	 * **************************************************************************/
	function run()
	{
		if( isset( $this->image_server_url ) AND isset( $this->image_base_url ) )
		//if( isset( $this->client->image_serverurl ) AND isset( $this->client->image_baseurl ) )
		{
			if( $this->pic_num > 0 )
				$image_name = $this->image_exists( $this->stock_id . $this->pic_num );
			else
				$image_name = $this->image_exists( $this->stock_id );
			if( null != $image_name )
			{
				$this->src  = $this->image_server_url .  $image_name;
				//$this->src  = $this->image_server_url . '/'  . $image_name;
				$this->position = $this->pic_num;
				$this->id = $this->pic_num;
			}
		}
		else
		{
			$this->src = "";
			//$this->src   = $this->stock_id . '.jpg"';
			$this->position = $this->pic_num;
		}
		$this->name = $this->stock_id;
		$this->alt = $this->stock_id;
	}
}

/*******************************************************************************************//**
 *
 * assumption is we copied images directory to remote server so that we can check the files existance!
 * TODO:
 *	
 * ***********************************************************************************************/
class woo_images {
	var $stock_id;
	var $client;	
	var $image_array;
	var $debug;
	var $remote_img_srv;	//boolean
	function __construct( $stock_id, $client, $debug = 0, $remote_img_srv = FALSE )
	{
		$this->stock_id = $stock_id;
		$this->client = $client;
		$this->debug = $debug;
		$this->remote_img_srv = $remote_img_srv;
			echo "<br /><br />" . __METHOD__ . ":" . __LINE__;
			var_dump($this->remote_img_srv);
		return;
	}
	function run()
	{
		return $this->product_images();
	}
	/******************************************************************************//**
	 *
	 *
	 * @param none
	 * @returns array array of image details (src, position)
	 * *******************************************************************************/
	/*@array@*/function product_images()
	{
		//IMAGES
		//If we use local URL we need to build it and send it
		//If we need to use WOOCOMMERCE image gallery, we need the filename
		//With the module to allow extra images, we need to check for that too
		////SHould also check for the existance of the filename in the local company
		//	Default location is (/company/0/images
		$image_array = array();
		$image = array();
		$imagecount = 0;
		if( isset( $this->client->image_serverurl ) AND isset( $this->client->image_baseurl ) )
		{
			echo "<br /><br />" . __METHOD__ . ":" . __LINE__;
			var_dump($this->remote_img_srv);
			$img = new woo_image($this->stock_id, 0, $this->client->image_serverurl, $this->client->image_baseurl, $this->client, $this->debug, $this->remote_img_srv);
			$image['src']  = $img->src;
			$image['position'] = $img->position;
			$image['name'] = $img->name;
			$image['alt'] = $img->alt;
			$image_array[] = $image;
			$imagecount++;
			unset( $img );
			unset( $image );

			if( isset( $this->client->maxpics ) )
			{
				for ( $j = 1; $j <= $this->client->maxpics; $j++ )
				{
					$img = new woo_image($this->stock_id, $j, $this->client->image_serverurl, $this->client->image_baseurl, $this->client, $this->debug, $this->remote_img_srv);
					if( isset( $img->src ) )
					{
						$image = array ('src' => $img->src, 'position' => $img->position, 'name' => $img->name, 'alt' => $img->alt );
						$image_array[] = $image;
						$imagecount++;
						unset( $image );
					}
					unset( $img );
				}
			}
		}
		$this->image_array = $image_array;
		return $image_array;
	}
}

?>
