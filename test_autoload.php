<?php
require 'vendor/autoload.php';

echo "Testing autoloading...\n";

$class = 'Ksfraser\Frontaccounting\Woocommerce\WooRestClientInterface';
echo "Checking if class exists: $class\n";
echo "Result: " . (interface_exists($class) ? 'YES' : 'NO') . "\n";

// Try to manually include
echo "\nManually including file...\n";
include 'src/Ksfraser/Frontaccounting/Woocommerce/WooRestClientInterface.php';
echo "After include, checking again...\n";
echo "Result: " . (interface_exists($class) ? 'YES' : 'NO') . "\n";

// List what's in the file
echo "\nFile contents (first 30 lines):\n";
echo file_get_contents('src/Ksfraser/Frontaccounting/Woocommerce/WooRestClientInterface.php', false, null, 0, 1000);
