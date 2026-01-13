<?php
$configArray[] = array( 'ModuleName' => 'Test',
                        'loadFile' => 'class.test.php',
                        'loadpriority' => 1,
			'taborder' => 2,
			'tabdata' => array('tabtitle' => 'Test', 'action' => 'test', 'form' => 'form_test', 'hidden' => FALSE),
                        'className' => 'test',
                        'objectName' => 'test',   //For multi classes within a module calling each other
                        'tablename' => 'test',     //Check to see if the table exists?
                        );
$configArray[] = array( 'ModuleName' => 'Test',
                        'loadFile' => 'class.test.php',
                        'loadpriority' => 1,
			'taborder' => 2,
			'tabdata' => array('tabtitle' => 'Test', 'action' => 'test', 'form' => 'form_test_completed', 'hidden' => TRUE),
                        'className' => 'test',
                        'objectName' => 'test',   //For multi classes within a module calling each other
                        'tablename' => 'test',     //Check to see if the table exists?
                        );

?>
