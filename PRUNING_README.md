# Google API Services Pruning & Vendor Consolidation

This directory contains scripts to consolidate redundant vendor directories and remove unnecessary Google API services after composer updates, keeping only the services actually used by the gsuite-filestore application.

## Required Services (Kept)

The following Google API services are required and will be preserved:

- **Drive API** (`Google_Service_Drive`) - For file uploads and management
- **Sheets API** (`Google_Service_Sheets`) - For writing data to Google Sheets

## Scripts

### 1. `consolidate-vendor.php`

Consolidates redundant vendor directories between gsuite-filestore and get-credentials.

**Usage:**

```bash
php consolidate-vendor.php
```

**What it does:**

- Removes the redundant `get-credentials/vendor` directory
- Updates autoload paths in get-credentials PHP files
- Creates a vendor symlink for compatibility
- Removes redundant composer.json and composer.lock files
- Shows disk space savings

### 2. `prune-google-services.php`

The main pruning script that removes all unnecessary Google API services.

**Usage:**

```bash
php prune-google-services.php
```

**Features:**

- Safely identifies and removes unused services
- Interactive confirmation prompt
- Detailed progress reporting
- Error handling and reporting

### 3. `post-composer-update.sh`

A shell script wrapper that runs consolidation and pruning, then updates the composer autoloader.

**Usage:**

```bash
./post-composer-update.sh
```

**What it does:**

1. Consolidates vendor directories (if needed)
2. Runs the pruning script
3. Updates composer autoloader with `composer dump-autoload --optimize`

## Workflow

### After Composer Install/Update

1. Run composer as usual:

   ```bash
   composer install
   # or
   composer update
   ```

2. Run the post-composer update script:
   ```bash
   ./post-composer-update.sh
   ```

### Manual Pruning

If you prefer to run the pruning manually:

```bash
php prune-google-services.php
composer dump-autoload --optimize
```

## Safety Features

- **Interactive confirmation**: The script asks for confirmation before removing services
- **Preview**: Shows which services will be removed before proceeding
- **Error handling**: Reports any failures and continues with remaining services
- **Backup recommendation**: Consider backing up your vendor directory before first run

## Expected Results

After pruning, you should see a significant reduction in the size of:

- `vendor/google/apiclient-services/src/` directory
- Overall vendor directory size

The following services will be removed (examples):

- YouTube API
- Analytics API
- Gmail API
- Calendar API
- And 200+ other unused Google services

## Troubleshooting

### Permission Errors

If you get permission errors, ensure the script has write access to the vendor directory:

```bash
chmod -R 755 vendor/
```

### Composer Autoloader Issues

If the autoloader update fails, try:

```bash
composer dump-autoload --optimize --no-dev
```

### Restoring Services

If you need to restore all services:

```bash
composer update google/apiclient
```

## Notes

- This pruning is safe to run multiple times
- The script only removes services that are confirmed to be unused
- Core Google API client functionality is preserved
- The pruning script can be run after any composer update
