
# ZYSYS FileStore Access Credential Generator
Created by Z. Bornheimer (ZYSYS) - Sept. 2023

Assuming you already have a .json file with the API access credentials (which should include a client_id), you'll need API authorization to access Spreadsheets / Drive, etc.

Note, that in `index.php` and `oauth2callback.php` files, you'll have to set
the proper name for the auth json (call client-secret.json).

Scopes are set in the `index.php` and `oauth2callback.php` files.  Set them
appropriately.  They are defaulting to FULL ACCESS to DRIVE and SPREADSHEETS.

To do that, you'll need this.

`php -S localhost:8080 -t $(pwd)`

1. Run that command and navigate to localhost:8080.
2. You'll log in with the account that is supposed to have access
3. Once oAuth is complete, a credentials.json file will be created.  Move that to `.credentials/zysys-file-store.class.json` which will serve as the authorization codes to the system.


