<?php
require_once __DIR__ . '/../vendor/autoload.php';

session_start();

$client = new Google\Client();
$client->setAuthConfigFile( 'client-secrets.json' );
$client->setRedirectUri( 'http://' . $_SERVER['HTTP_HOST'] . '/oauth2callback.php' );
$client->addScope( Google\Service\Drive::DRIVE );
$client->addScope( 'https://www.googleapis.com/auth/spreadsheets' );
$client->setAccessType( 'offline' );

	$credentialsPath = './credentials.json';

if ( file_exists( $credentialsPath ) ) {
	print './credentials.json already exists.  Please rename it and move it appropriately before continuing.';
	exit;
} elseif ( ! isset( $_GET['code'] ) ) {

		$auth_url = $client->createAuthUrl();
		header( 'Location: ' . filter_var( $auth_url, FILTER_SANITIZE_URL ) );
} else {
	$client->authenticate( $_GET['code'] );
	$_SESSION['access_token']  = $client->getAccessToken();
	$_SESSION['refresh_token'] = $client->getRefreshToken();
		
	file_put_contents( $credentialsPath, json_encode( $_SESSION ) );
	print './credentials.json has been created.  Please rename it and move it appropriately.';
	exit;
	#$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/';
	#header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));

}
