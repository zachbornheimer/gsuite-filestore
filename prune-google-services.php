<?php
/**
 * Google API Services Pruning Script
 * 
 * This script removes unnecessary Google API services from the vendor directory,
 * keeping only the services actually used by the gsuite-filestore application.
 * 
 * Required services (kept):
 * - Drive API (Google_Service_Drive)
 * - Sheets API (Google_Service_Sheets)
 * 
 * Usage: php prune-google-services.php
 * 
 * @author Z. Bornheimer (Zysys)
 * @version 1.0
 */

// Configuration
$vendorPath = __DIR__ . '/vendor/google/apiclient-services/src';
$requiredServices = [
    'Drive',
    'Sheets'
];

// Safety checks
if (!is_dir($vendorPath)) {
    echo "Error: Vendor directory not found at $vendorPath\n";
    exit(1);
}

if (!is_writable($vendorPath)) {
    echo "Error: Vendor directory is not writable. Please check permissions.\n";
    exit(1);
}

echo "Google API Services Pruning Script\n";
echo "==================================\n\n";

// Get all service directories
$services = array_filter(scandir($vendorPath), function($item) use ($vendorPath) {
    return is_dir($vendorPath . '/' . $item) && !in_array($item, ['.', '..']);
});

echo "Found " . count($services) . " Google API services\n";
echo "Required services: " . implode(', ', $requiredServices) . "\n\n";

// Identify services to remove
$servicesToRemove = array_diff($services, $requiredServices);
$servicesToKeep = array_intersect($services, $requiredServices);

echo "Services to keep: " . implode(', ', $servicesToKeep) . "\n";
echo "Services to remove: " . count($servicesToRemove) . " services\n\n";

if (empty($servicesToRemove)) {
    echo "No services need to be removed. All services are required.\n";
    exit(0);
}

// Show first 10 services that will be removed
$preview = array_slice($servicesToRemove, 0, 10);
echo "Preview of services to be removed:\n";
foreach ($preview as $service) {
    echo "  - $service\n";
}
if (count($servicesToRemove) > 10) {
    echo "  ... and " . (count($servicesToRemove) - 10) . " more\n";
}

echo "\n";

// Confirmation prompt
echo "Are you sure you want to remove " . count($servicesToRemove) . " unused Google API services? (y/N): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "Operation cancelled.\n";
    exit(0);
}

echo "\nStarting removal process...\n";

$removedCount = 0;
$errorCount = 0;

// Remove unnecessary service directories
foreach ($servicesToRemove as $service) {
    $servicePath = $vendorPath . '/' . $service;
    
    if (is_dir($servicePath)) {
        if (removeDirectory($servicePath)) {
            echo "✓ Removed $service\n";
            $removedCount++;
        } else {
            echo "✗ Failed to remove $service\n";
            $errorCount++;
        }
    }
}

// Remove unnecessary service PHP files
$phpFiles = array_filter(scandir($vendorPath), function($item) use ($vendorPath) {
    return is_file($vendorPath . '/' . $item) && pathinfo($item, PATHINFO_EXTENSION) === 'php';
});

foreach ($phpFiles as $phpFile) {
    $serviceName = pathinfo($phpFile, PATHINFO_FILENAME);
    
    if (!in_array($serviceName, $requiredServices)) {
        $filePath = $vendorPath . '/' . $phpFile;
        if (unlink($filePath)) {
            echo "✓ Removed $phpFile\n";
            $removedCount++;
        } else {
            echo "✗ Failed to remove $phpFile\n";
            $errorCount++;
        }
    }
}

echo "\n";
echo "Pruning completed!\n";
echo "==================\n";
echo "Services removed: $removedCount\n";
echo "Errors encountered: $errorCount\n";

if ($errorCount > 0) {
    echo "\nSome services could not be removed. You may need to check file permissions.\n";
    exit(1);
}

echo "\nThe following services have been kept:\n";
foreach ($servicesToKeep as $service) {
    echo "  - $service\n";
}

echo "\nYou can now run 'composer dump-autoload' to update the autoloader.\n";

/**
 * Recursively remove a directory and all its contents
 * 
 * @param string $dir Directory path to remove
 * @return bool True if successful, false otherwise
 */
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}
