<?php
/* Author: Z. Bornheimer (Zysys)
 * Copyright 2017 Zachary Bornheimer.
 * Version 0.0.1 alpha
 * Documentation: https://codex.zysys.org/bin/view.cgi/Main/PHPLibrary:OffsiteFileStorageSystemViaG-Suite
 * License: GPLv3 (making use of GPLv2, GPLv3, and Apache 2.0 code)
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

error_reporting(E_ALL); ini_set('display_errors', 1);

class Zysys_FileStore {
    protected function _use_gsuite() {
        # Google APIs
        require_once('vendor/autoload.php');
    }

    protected $variables = array();

    public function __set( $key, $value ) {
        $this->variables[ $key ] = $value;
    }
    public function __get( $key ) {
        return $this->variables[ $key ];
    }

    function __construct($client_secret_path, $credentials_path = null) {
        if ($credentials_path === null)
            $this->_var("credentials_path", __DIR__ . "/.credentials/" . basename($_SERVER["SCRIPT_FILENAME"], '.php') . ".json");
        else
            $this->_var("credentials_path", $credentials_path);
        $this->_var("client_secret_path", $client_secret_path);
        $this->_use_gsuite();
        $this->configure($client_secret_path, $this->__get("credentials_path"));
    }

    public function configure($client_secret_path, $credentials_path) {
        $client = new Google_Client();
        $client->setApplicationName("Zysys FileStore");
        $client->setScopes(implode(' ', array(
  Google_Service_Sheets::SPREADSHEETS, Google_Service_Drive::DRIVE)
));
        $client->setAuthConfig($client_secret_path);
        $client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        $credentialsPath = $credentials_path;
        if (file_exists($credentialsPath)) {
            $accessToken = json_decode(file_get_contents($credentialsPath), true);
        } else {
            // Request authorization from the user.

            print "This authentication procedure is now non-functional.  Please setup using the get-credentials folder.";

            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if(!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentialsPath);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
        }
        $this->_var("gsuiteAgent", $client);

        $this->_var("sheetsAgent", new Google_Service_Sheets($this->__get("gsuiteAgent")));
        $this->_var("driveAgent", new Google_Service_Drive($this->__get("gsuiteAgent")));
    }

    protected function _var($var_name, $value) {
        $this->__set($var_name, $value);
        if ($this->__get($var_name) !== $value) {
            throw Exception("Criticial error.  _var was unable to set a value. Called by: " . debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function']); 
        }
        return 1;
    }

    public function gsheet($id) {
        $this->_var("gsheet_id", $id);
    }

    public function gsheet_row($sheet_identifier, $location_identifier) {
        $this->_var("gsheet_sheet_name", $sheet_identifier);
        $this->_var("gsheet_row_identifier", $location_identifier);
    }

    public function add_row() {
        $args = func_get_args();

        $body = new Google_Service_Sheets_ValueRange(array(
            'values' => array($args)
        ));

        $params = array(
            'valueInputOption' => "RAW",
        );

        $range = sprintf("'%s'!%s", $this->__get("gsheet_sheet_name"), $this->__get("gsheet_row_identifier"));

        $result = $this->__get("sheetsAgent")->spreadsheets_values->append($this->__get("gsheet_id"), $range, $body, $params);
    }

    public function drive_parent($folderID) {
        $this->_var("drive_parent_id", $folderID);
    }

    public function local_store($path) {
        if (!is_dir($path))
            throw new Exception("local_store path $path does not exist.");

        if (!is_writeable($path))
            throw new Exception("local_store path $path is not writeable.");

        $this->_var("local_store_path", $path);
    }
    
    public function create_subfolder($name, $switch_to = null) {
        $driveID = $this->create_drive_subfolder($name);
        $this->create_local_subfolder($name);
        if ($switch_to) {
            $this->drive_parent($driveID);
            $this->local_store($this->__get('local_store_path') . '/' . $name);
        }
    }

    public function get_drive_parent_id() {
        return $this->__get("drive_parent_id");
    }

    public function create_local_subfolder($name) {
        if (!$this->__get("local_store_path"))
            throw new Exception("Error, Local Store Path not set.");
        $folderToCreate = $this->__get("local_store_path") . '/' . $name;
        if (!is_dir($folderToCreate)) {
            if (!mkdir($folderToCreate)) {
                throw new Exception("Error, failed to create " . $folderToCreate . ".");
            }
        } else {
            if (!$this->_is_dir_empty($folderToCreate)) {
                throw new Exception("Error, subfolder exists: " . $folderToCreate . ".");
            } else {
                return $folderToCreate;
            }
        }
        return $folderToCreate;
    }

    protected function _is_dir_empty($dir) {
# Taken from: http://stackoverflow.com/questions/7497733/how-can-use-php-to-check-if-a-directory-is-empty
        if (!is_readable($dir))
            return NULL; 
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle)))
            if ($entry != "." && $entry != "..")
                return false;
        return true;
    }

    public function create_drive_subfolder($name, $switch_to = null) {
        if (!$this->__get("drive_parent_id"))
            throw new Exception("Error, Drive Parent Folder not set.");
        $fileMetadata = new Google_Service_Drive_DriveFile(array('name' => $name, 'parents' => array($this->__get("drive_parent_id")), 'mimeType' => 'application/vnd.google-apps.folder'));
        $file = $this->__get("driveAgent")->files->create($fileMetadata, array( 'fields' => 'id'));
        if ($switch_to) {
            $this->drive_parent($file->id);
        }
        return $file->id;
    }

    public function store_file($filepath) {
        $drive = $this->store_drive_file($filepath);
        $local = $this->store_local_file($filepath);
        return array($drive, $local);
    }

    public function store_drive_file($filepath) {
        if (!file_exists($filepath))
            throw Exception("$filepath does not exists");
        
        //Insert a file into client specific folder new_sub_folder
        $fileMetadata = new Google_Service_Drive_DriveFile(array(
            'name' => basename($filepath),
            'parents' => array($this->__get("drive_parent_id")),
        ));

        $file = $this->__get("driveAgent")->files->create($fileMetadata, array(
            'data' => file_get_contents($filepath),
            'mimeType' => mime_content_type($filepath),
            'uploadType' => 'multipart',
            'fields' => 'id'));
        return $file->id;
    }

    public function store_local_file($filepath) {
        if (!file_exists($filepath))
            throw Exception("$filepath does not exists");

        $a = 0;
        do {
            copy($filepath, $this->__get("local_store_path") . "/" . basename($filepath));
        } while (file_get_contents($filepath) != file_get_contents($this->__get("local_store_path") . "/" . basename($filepath)) && $a++ < 15);
    }

}
