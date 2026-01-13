<?php
$configArray[] = array( 'ModuleName' => 'Woo_Customer',
                        'loadFile' => 'class.woo_customer.php',
                        'loadpriority' => 2,
                        'className' => 'woo_customer',
                        'objectName' => 'woo_customer',   //For multi classes within a module calling each other
                        'tablename' => 'woo_customer',     //Check to see if the table exists?
                        );

?>
