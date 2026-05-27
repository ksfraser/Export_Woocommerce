<?php
$configArray[] = array( 'ModuleName' => 'Woo_Shipping',
                        'loadFile' => 'class.woo_shipping.php',
                        'loadpriority' => 3,
                        'className' => 'woo_shipping',
                        'objectName' => 'woo_shipping',   //For multi classes within a module calling each other
                        'tablename' => 'woo_shipping',     //Check to see if the table exists?
                        );

?>
