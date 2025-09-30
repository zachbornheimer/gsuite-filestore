#!/bin/bash

# Post-Composer Update Script for gsuite-filestore
# This script consolidates vendor directories and prunes unnecessary Google API services

echo "Running post-composer update tasks..."

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed or not in PATH"
    exit 1
fi

# Check if consolidation is needed
if [ -d "get-credentials/vendor" ]; then
    echo "Found redundant vendor directories. Running consolidation..."
    php consolidate-vendor.php
    
    if [ $? -ne 0 ]; then
        echo "✗ Consolidation failed"
        exit 1
    fi
    echo "✓ Vendor directories consolidated"
else
    echo "✓ No vendor consolidation needed"
fi

# Check if the pruning script exists
if [ ! -f "prune-google-services.php" ]; then
    echo "Error: prune-google-services.php not found"
    exit 1
fi

# Run the pruning script
echo "Pruning unnecessary Google API services..."
php prune-google-services.php

# Check if pruning was successful
if [ $? -eq 0 ]; then
    echo ""
    echo "Updating composer autoloader..."
    composer dump-autoload --optimize
    
    if [ $? -eq 0 ]; then
        echo ""
        echo "✓ Post-composer update completed successfully!"
        echo "  - Unnecessary Google API services removed"
        echo "  - Composer autoloader updated"
    else
        echo "✗ Failed to update composer autoloader"
        exit 1
    fi
else
    echo "✗ Pruning failed"
    exit 1
fi
