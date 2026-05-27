#!/bin/sh

#env var 1 ($1) is class name i.e. woo_customer
#env var 2 ($2) is sub-module name i.e. Woo_Customer
#env var 3 ($3) is load priority
#env var 4 ($4) is tab order
#env var 5 ($5) is tab action and form_action 

echo "<?php" > conf.$1.php

echo "\$configArray[] = array( 'ModuleName' => '$2',
                        'loadFile' => 'class.$1.php',
                        'loadpriority' => $3,
			'taborder' => $4,
			'tabdata' => array('tabtitle' => '$2', 'action' => '$5', 'form' => 'form_$5', 'hidden' => FALSE),
                        'className' => '$1',
                        'objectName' => '$1',   //For multi classes within a module calling each other
                        'tablename' => '$1',     //Check to see if the table exists?
                        );
\$configArray[] = array( 'ModuleName' => '$2',
                        'loadFile' => 'class.$1.php',
                        'loadpriority' => $3,
			'taborder' => $4,
			'tabdata' => array('tabtitle' => '$2', 'action' => '$5', 'form' => 'form_$5_completed', 'hidden' => TRUE),
                        'className' => '$1',
                        'objectName' => '$1',   //For multi classes within a module calling each other
                        'tablename' => '$1',     //Check to see if the table exists?
                        );

?>" >> conf.$1.php
