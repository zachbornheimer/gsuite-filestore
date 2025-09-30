<?php
/**
 * Vendor Directory Consolidation Script
 * 
 * This script consolidates the redundant vendor directories between
 * gsuite-filestore and get-credentials, using the main vendor directory
 * for both locations.
 * 
 * @author Z. Bornheimer (Zysys)
 * @version 1.0
 */

echo "Vendor Directory Consolidation Script\n";
echo "====================================\n\n";

$baseDir = __DIR__;
$getCredentialsDir = $baseDir . '/get-credentials';
$mainVendorDir = $baseDir . '/vendor';
$getCredentialsVendorDir = $getCredentialsDir . '/vendor';

// Check if directories exist
if (!is_dir($getCredentialsDir)) {
    echo "Error: get-credentials directory not found\n";
    exit(1);
}

if (!is_dir($mainVendorDir)) {
    echo "Error: Main vendor directory not found. Run 'composer install' first.\n";
    exit(1);
}

if (!is_dir($getCredentialsVendorDir)) {
    echo "Error: get-credentials vendor directory not found\n";
    exit(1);
}

echo "Found directories:\n";
echo "  - Main vendor: $mainVendorDir\n";
echo "  - get-credentials vendor: $getCredentialsVendorDir\n\n";

// Check if get-credentials has its own composer.json
$getCredentialsComposerJson = $getCredentialsDir . '/composer.json';
if (!file_exists($getCredentialsComposerJson)) {
    echo "Error: get-credentials/composer.json not found\n";
    exit(1);
}

echo "Current setup:\n";
echo "  - Main gsuite-filestore has its own vendor directory\n";
echo "  - get-credentials has its own separate vendor directory\n";
echo "  - Both contain the same Google API client dependencies\n\n";

echo "This creates redundancy and wastes disk space.\n\n";

// Show size comparison
$mainVendorSize = getDirectorySize($mainVendorDir);
$getCredentialsVendorSize = getDirectorySize($getCredentialsVendorDir);

echo "Directory sizes:\n";
echo "  - Main vendor: " . formatBytes($mainVendorSize) . "\n";
echo "  - get-credentials vendor: " . formatBytes($getCredentialsVendorSize) . "\n";
echo "  - Total redundant space: " . formatBytes($getCredentialsVendorSize) . "\n\n";

echo "Proposed solution:\n";
echo "  1. Remove get-credentials/vendor directory\n";
echo "  2. Update get-credentials files to use main vendor directory\n";
echo "  3. Remove get-credentials/composer.json and composer.lock\n";
echo "  4. Update autoload paths in get-credentials PHP files\n\n";

echo "Are you sure you want to proceed? (y/N): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "Operation cancelled.\n";
    exit(0);
}

echo "\nStarting consolidation...\n";

// Step 1: Backup get-credentials composer files
echo "1. Backing up get-credentials composer files...\n";
if (file_exists($getCredentialsComposerJson)) {
    copy($getCredentialsComposerJson, $getCredentialsDir . '/composer.json.backup');
    echo "   ✓ Backed up composer.json\n";
}

$getCredentialsComposerLock = $getCredentialsDir . '/composer.lock';
if (file_exists($getCredentialsComposerLock)) {
    copy($getCredentialsComposerLock, $getCredentialsDir . '/composer.lock.backup');
    echo "   ✓ Backed up composer.lock\n";
}

// Step 2: Remove get-credentials vendor directory
echo "\n2. Removing get-credentials vendor directory...\n";
if (removeDirectory($getCredentialsVendorDir)) {
    echo "   ✓ Removed get-credentials/vendor directory\n";
} else {
    echo "   ✗ Failed to remove get-credentials/vendor directory\n";
    exit(1);
}

// Step 3: Remove get-credentials composer files
echo "\n3. Removing get-credentials composer files...\n";
if (unlink($getCredentialsComposerJson)) {
    echo "   ✓ Removed composer.json\n";
} else {
    echo "   ✗ Failed to remove composer.json\n";
}

if (file_exists($getCredentialsComposerLock) && unlink($getCredentialsComposerLock)) {
    echo "   ✓ Removed composer.lock\n";
} else {
    echo "   ✓ composer.lock not found or already removed\n";
}

// Step 4: Update autoload paths in get-credentials PHP files
echo "\n4. Updating autoload paths in get-credentials PHP files...\n";

$phpFiles = ['index.php', 'oauth2callback.php'];
foreach ($phpFiles as $file) {
    $filePath = $getCredentialsDir . '/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $newContent = str_replace(
            "require_once __DIR__ . '/vendor/autoload.php';",
            "require_once __DIR__ . '/../vendor/autoload.php';",
            $content
        );
        
        if ($content !== $newContent) {
            file_put_contents($filePath, $newContent);
            echo "   ✓ Updated $file\n";
        } else {
            echo "   ✓ $file already uses correct path\n";
        }
    }
}

// Step 5: Create a symlink as backup (optional)
echo "\n5. Creating vendor symlink for compatibility...\n";
$symlinkPath = $getCredentialsDir . '/vendor';
if (symlink('../vendor', $symlinkPath)) {
    echo "   ✓ Created vendor symlink\n";
} else {
    echo "   ✗ Failed to create vendor symlink (this is optional)\n";
}

echo "\nConsolidation completed!\n";
echo "=======================\n";
echo "✓ Removed redundant vendor directory\n";
echo "✓ Updated autoload paths\n";
echo "✓ Created vendor symlink for compatibility\n";
echo "✓ Freed up " . formatBytes($getCredentialsVendorSize) . " of disk space\n\n";

echo "Next steps:\n";
echo "1. Test that get-credentials still works\n";
echo "2. Run the pruning script to remove unused Google services\n";
echo "3. Consider running: composer dump-autoload --optimize\n\n";

/**
 * Recursively remove a directory and all its contents
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

/**
 * Get directory size in bytes
 */
function getDirectorySize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    }
    return $size;
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
