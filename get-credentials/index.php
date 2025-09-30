<?php
require_once __DIR__ . '/../vendor/autoload.php';

session_start();

$client = new Google\Client();
$client->setAuthConfig( 'client-secrets.json' );
$client->addScope( Google\Service\Drive::DRIVE );
$client->addScope( 'https://www.googleapis.com/auth/spreadsheets' );
$client->setAccessType( 'offline' );

if ( file_exists( $credentialsPath ) ) {
	print './credentials.json already exists.  Please rename it and move it appropriately before continuing.';
	exit;
} else {
	$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/oauth2callback.php';
	header( 'Location: ' . filter_var( $redirect_uri, FILTER_SANITIZE_URL ) );
}
