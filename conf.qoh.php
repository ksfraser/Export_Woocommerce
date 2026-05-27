<?php
$configArray[] = array( 'ModuleName' => 'QOH',
                        'loadFile' => 'class.qoh.php',
                        'loadpriority' => 1,
			'taborder' => 1,
			'tabdata' => array('tabtitle' => 'Populate QOH', 'action' => 'QOH', 'form' => 'form_QOH', 'hidden' => FALSE),
                        'className' => 'qoh',
                        'objectName' => 'qoh',   //For multi classes within a module calling each other
                        'tablename' => 'qoh',     //Check to see if the table exists?
                        );
$configArray[] = array( 'ModuleName' => 'QOH',
                        'loadFile' => 'class.qoh.php',
                        'loadpriority' => 1,
			'taborder' => 1,
			'tabdata' => array('tabtitle' => 'QOH', 'action' => 'QOH', 'form' => 'form_QOH_completed', 'hidden' => TRUE),
                        'className' => 'qoh',
                        'objectName' => 'qoh',   //For multi classes within a module calling each other
                        'tablename' => 'qoh',     //Check to see if the table exists?
                        );

?>
