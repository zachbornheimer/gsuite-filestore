<?php
/**
 * Zysys_FileStore Class
 * 
 * A PHP class for interacting with Google Drive and Google Sheets using Google APIs.
 * 
 * Author: Z. Bornheimer (Zysys)
 * Version: 0.0.2 alpha
 * Documentation: https://codex.zysys.org/bin/view.cgi/Main/PHPLibrary:OffsiteFileStorageSystemViaG-Suite
 * License: GPLv3 (making use of GPLv2, GPLv3, and Apache 2.0 code)
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

class Zysys_FileStore {

	/**
	 * Class properties
	 */
	protected $variables = array();

	// Private properties to store the access key and client secret
	private $access_key;
	private $client_secret;

	/**
	 * Zysys_FileStore Constructor
	 *
	 * Initializes the Zysys_FileStore object. Handles cases where the first argument is an associative array
	 * with 'access_key' and 'client_secret', or paths to credentials and client secret JSON files.
	 *
	 * @param string|array $access_key_cred_path_or_credentials_array Path to an access key credentials JSON file 
	 *                                                                or an associative array with 'access_key' and 'client_secret'.
	 * @param string $client_secret_path Optional path to the client secret JSON file. Defaults to a generated path.
	 *
	 * @throws Exception If invalid data is provided or required keys are missing.
	 */
	public function __construct( $access_key_cred_path_or_credentials_array, $client_secret_path = null ) {
		// Default client secret path if not provided
		$client_secret_path = $client_secret_path ?? __DIR__ . '/.credentials/' . basename( $_SERVER['SCRIPT_FILENAME'], '.php' ) . '.json';

		// Check if the first argument is an associative array
		if ( is_array( $access_key_cred_path_or_credentials_array ) ) {
			$this->access_key    = $access_key_cred_path_or_credentials_array['access_key'] ?? null;
			$this->client_secret = $access_key_cred_path_or_credentials_array['client_secret'] ?? null;

			if ( is_null( $this->access_key ) || is_null( $this->client_secret ) ) {
				throw new Exception( "The array provided must contain 'access_key' and 'client_secret' keys." );
			}
		} else {
			// Handle as file paths if the first argument is not an array
			if ( ! file_exists( $access_key_cred_path_or_credentials_array ) ) {
				throw new Exception( 'Invalid access key credentials file path provided.' );
			}
			$access_key_data  = $this->_load_json( $access_key_cred_path_or_credentials_array );
			$this->access_key = $access_key_data['access_key'] ?? null;

			if ( ! file_exists( $client_secret_path ) ) {
				throw new Exception( 'Invalid client secret file path provided.' );
			}
			$client_secret_data  = $this->_load_json( $client_secret_path );
			$this->client_secret = $client_secret_data['client_secret'] ?? null;

			if ( is_null( $this->access_key ) || is_null( $this->client_secret ) ) {
				throw new Exception( 'Required keys are missing in the provided files.' );
			}
		}

		// Initialize Google APIs
		$this->_use_gsuite();
		$this->configure();
	}


	/**
	 * Load Google API libraries via Composer
	 */
	protected function _use_gsuite() {
		require_once 'vendor/autoload.php';
	}

	
	protected function _load_json( $path ) {
		if ( ! file_exists( $path ) ) {
			throw new Exception( "File not found: $path" );
		}
		$json = file_get_contents( $path );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( "Invalid JSON in file: $path" );
		}
		return $data;
	}

	/**
	 * Configures the Google Client with the provided access key and client secret.
	 *
	 * This method sets up the Google Client using the `access_key` and `client_secret`
	 * that were set during the construction of the `Zysys_FileStore` object.
	 *
	 * @throws Exception if the Google Client configuration fails.
	 */
	public function configure() {
		// Initialize the Google Client
		$client = new Google_Client();
		$client->setApplicationName( 'Zysys FileStore' );

		// Set the necessary scopes for Sheets and Drive (or any other Google services you need)
		$client->setScopes(
			array(
				Google_Service_Sheets::SPREADSHEETS,
				Google_Service_Drive::DRIVE,
			)
		);

		// Set the access type to offline to ensure we can refresh tokens
		$client->setAccessType( 'offline' );

		// Load previously authorized credentials from the file
		if ( $this->__get( 'credentials_path' ) ) {
			if ( file_exists( $this->__get( 'credentials_path' ) ) ) {
				$accessToken = json_decode( file_get_contents( $this->__get( 'credentials_path' ) ), true );
				$client->setAccessToken( $accessToken );

				// Refresh the token if it's expired
				if ( $client->isAccessTokenExpired() ) {
					$client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken() );

					// Set the updated access token
					$this->__set( 'updated_access_token', $client->getAccessToken() );
				} else {
					$this->__set( 'updated_access_token', false );
				}
			} else {
				throw new Exception( 'Credentials path is invalid or not set.' );
			}
		} else {
			// Set the client secret configuration
			if ( $this->client_secret ) {
				$keys = json_decode( $this->client_secret, true );
				// if there's only 1 key, get the object from the first key
				if ( count( $keys ) === 1 ) {
					$keys = $keys[ array_key_first( $keys ) ];
				}

				$client->setAuthConfig( $keys );
			} else {
				throw new Exception( 'Client secret is missing or not configured.' );
			}
		}

		// Store the configured Google Client and related services
		$this->_var( 'gsuiteAgent', $client );
		$this->_var( 'sheetsAgent', new Google_Service_Sheets( $client ) );
		$this->_var( 'driveAgent', new Google_Service_Drive( $client ) );
		if ($this->access_key) {
			$client->setAccessToken( $this->access_key );
		}
	}

	/**
	 * Set or get variables in the class.
	 * 
	 * @param string $key Variable name.
	 * @param mixed $value Value to set.
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->variables[ $key ] = $value;
	}

	public function __get( $key = '' ) {
		if ( array_key_exists( $key, $this->variables ) ) {
			return $this->variables[ $key ];
		} else {
			return null;
		}
	}

	/**
	 * Set a variable and ensure it is set correctly.
	 * 
	 * @param string $var_name
	 * @param mixed $value
	 * @return int
	 * @throws Exception if the variable could not be set.
	 */
	protected function _var( $var_name, $value ) {
		$this->__set( $var_name, $value );
		if ( $this->__get( $var_name ) !== $value ) {
			throw new Exception( "Critical error: unable to set '$var_name'. Called by: " . debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] );
		}
		return 1;
	}

	/**
	 * Set Google Sheets ID.
	 * 
	 * @param string $id Google Sheets ID.
	 */
	public function gsheet( $id ) {
		$this->_var( 'gsheet_id', $id );
	}

	/**
	 * Set Google Sheets row identifier.
	 * 
	 * @param string $sheet_identifier Google Sheets sheet name.
	 * @param string $location_identifier Row identifier.
	 */
	public function gsheet_row( $sheet_identifier, $location_identifier ) {
		$this->_var( 'gsheet_sheet_name', $sheet_identifier );
		$this->_var( 'gsheet_row_identifier', $location_identifier );
	}

	/**
	 * Append a row to the Google Sheet.
	 * 
	 * @param mixed ...$args Values to append.
	 */
	public function add_row( ...$args ) {
		
		// ensure we have a gsheet_id, gsheet_sheet_name, and gsheet_row_identifier
		foreach ( array( 'gsheet_id', 'gsheet_sheet_name', 'gsheet_row_identifier' ) as $var ) {
			if ( ! $this->__get( $var ) ) {
				throw new Exception( "Error: $var not set." );
			}
		}


		$body   = new Google_Service_Sheets_ValueRange( array( 'values' => array( $args ) ) );
		$params = array( 'valueInputOption' => 'RAW' );
		$range  = sprintf( "'%s'!%s", $this->__get( 'gsheet_sheet_name' ), $this->__get( 'gsheet_row_identifier' ) );

		$this->__get( 'sheetsAgent' )->spreadsheets_values->append( $this->__get( 'gsheet_id' ), $range, $body, $params );
	}

	/**
	 * Set Google Drive parent folder ID.
	 * 
	 * @param string $folderID Google Drive folder ID.
	 */
	public function drive_parent( $folderID ) {
		$this->_var( 'drive_parent_id', $folderID );
	}

	/**
	 * Set the local storage path.
	 * 
	 * @param string $path Directory path.
	 * @throws Exception if the path does not exist or is not writable.
	 */
	public function local_store( $path ) {
		if ( ! is_dir( $path ) ) {
			throw new Exception( "Local store path '$path' does not exist." );
		}

		if ( ! is_writable( $path ) ) {
			throw new Exception( "Local store path '$path' is not writable." );
		}

		$this->_var( 'local_store_path', $path );
	}

	/**
	 * Create a subfolder in both Google Drive and local storage.
	 * 
	 * @param string $name Subfolder name.
	 * @param bool $switch_to Switch to the created subfolder after creation.
	 */
	public function create_subfolder( $name, $switch_to = false ) {
		$driveID = $this->create_drive_subfolder( $name );
		$this->create_local_subfolder( $name );

		if ( $switch_to ) {
			$this->drive_parent( $driveID );
			$this->local_store( $this->__get( 'local_store_path' ) . '/' . $name );
		}
	}

	/**
	 * Get the current Google Drive parent folder ID.
	 * 
	 * @return string
	 */
	public function get_drive_parent_id() {
		return $this->__get( 'drive_parent_id' );
	}

	/**
	 * Create a subfolder in local storage.
	 * 
	 * @param string $name Subfolder name.
	 * @return string Created folder path.
	 * @throws Exception if the path is not set, or if the folder exists and is not empty.
	 */
	public function create_local_subfolder( $name ) {
		if ( ! $this->__get( 'local_store_path' ) ) {
			throw new Exception( 'Error: Local Store Path not set.' );
		}

		$folderToCreate = $this->__get( 'local_store_path' ) . '/' . $name;

		if ( ! is_dir( $folderToCreate ) ) {
			if ( ! mkdir( $folderToCreate ) ) {
				throw new Exception( "Error: Failed to create '$folderToCreate'." );
			}
		} elseif ( ! $this->_is_dir_empty( $folderToCreate ) ) {
			throw new Exception( "Error: Subfolder exists and is not empty: '$folderToCreate'." );
		}

		return $folderToCreate;
	}

	/**
	 * Check if a directory is empty.
	 * 
	 * @param string $dir Directory path.
	 * @return bool|null Returns null if unreadable, true if empty, false otherwise.
	 */
	protected function _is_dir_empty( $dir ) {
		if ( ! is_readable( $dir ) ) {
			return null;
		}

		$handle = opendir( $dir );
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( $entry != '.' && $entry != '..' ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Create a subfolder in Google Drive.
	 * 
	 * @param string $name Subfolder name.
	 * @param bool $switch_to Switch to the created subfolder after creation.
	 * @return string Created Google Drive folder ID.
	 * @throws Exception if the parent folder ID is not set.
	 */
	public function create_drive_subfolder( $name, $switch_to = false ) {
		if ( ! $this->__get( 'drive_parent_id' ) ) {
			throw new Exception( 'Error: Drive Parent Folder not set.' );
		}

		$fileMetadata = new Google_Service_Drive_DriveFile(
			array(
				'name'     => $name,
				'parents'  => array( $this->__get( 'drive_parent_id' ) ),
				'mimeType' => 'application/vnd.google-apps.folder',
			)
		);

		$file = $this->__get( 'driveAgent' )->files->create( $fileMetadata, array( 'fields' => 'id' ) );

		if ( $switch_to ) {
			$this->drive_parent( $file->id );
		}

		return $file->id;
	}

	/**
	 * Store a file in both Google Drive and local storage.
	 * 
	 * @param string $filepath File path to store.
	 * @return array IDs of stored files.
	 * @throws Exception if the file does not exist.
	 */
	public function store_file( $filepath ) {
		if ( ! file_exists( $filepath ) ) {
			throw new Exception( "Error: File '$filepath' does not exist." );
		}

		$drive = $this->store_drive_file( $filepath );
		$local = $this->store_local_file( $filepath );

		return array( $drive, $local );
	}

	/**
	 * Store a file in Google Drive.
	 * 
	 * @param string $filepath File path to store.
	 * @return string Google Drive file ID.
	 * @throws Exception if the file does not exist.
	 */
	public function store_drive_file( $filepath ) {
		if ( ! file_exists( $filepath ) ) {
			throw new Exception( "Error: File '$filepath' does not exist." );
		}

		$fileMetadata = new Google_Service_Drive_DriveFile(
			array(
				'name'    => basename( $filepath ),
				'parents' => array( $this->__get( 'drive_parent_id' ) ),
			)
		);

		$file = $this->__get( 'driveAgent' )->files->create(
			$fileMetadata,
			array(
				'data'       => file_get_contents( $filepath ),
				'mimeType'   => mime_content_type( $filepath ),
				'uploadType' => 'multipart',
				'fields'     => 'id',
			)
		);

		return $file->id;
	}

	/**
	 * Store a file in local storage.
	 * 
	 * @param string $filepath File path to store.
	 * @throws Exception if the file does not exist or if copying fails.
	 */
	public function store_local_file( $filepath ) {
		if ( ! file_exists( $filepath ) ) {
			throw new Exception( "Error: File '$filepath' does not exist." );
		}

		$destination = $this->__get( 'local_store_path' ) . '/' . basename( $filepath );
		$attempts    = 0;

		do {
			copy( $filepath, $destination );
		} while ( file_get_contents( $filepath ) !== file_get_contents( $destination ) && ++$attempts < 15 );

		if ( $attempts >= 15 ) {
			throw new Exception( 'Error: Failed to store file locally after multiple attempts.' );
		}
	}
}
