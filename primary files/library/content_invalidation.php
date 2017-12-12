<?php
//below file is included for invalidate the content of CDN server files
require_once 'Crypt/HMAC2.php';

//$serverMp4 = "000/000/000/000/308/078/90/MariahCarey_Infinity_Infinity_1.mp4";
//invalidateContent( $serverMp4 );

function invalidateContent( $filePath ) {

	$keyId = 279816074;
	$secretKey = "T9tQK3CZCSMAYybBYENq";
	$verb = "POST";
	$ct = "text/xml";
	$resource = "";
	if(strpos($filePath,'.mp3'){
	$resource = "/origininvalidations/v1.0/2835/BBCK89831/libraryideas.origin.cdn.level3.net";
	}else{
	$resource = "/invalidations/v1.0/2835/BBLB86908/libraryideas";
	}
	$env = "";
	$queryString = "force=1";

	$postcontent = "<paths><path>/$filePath</path></paths>";

	$requestDate = gmdate( "D, d M Y H:i:s T" );

	$str = "$requestDate\n$env$resource\n$ct\n$verb\n";

	$hasher =& new Crypt_HMAC2( $secretKey, "sha1" );

	$authString = "MPA " . $keyId . ":" . hexToBase64( $hasher->hash( $str ) ) ;

	$url = "https://ws.level3.com" . $env . $resource;

	$ch = curl_init();

	if ( $verb == 'POST' ) {
		curl_setopt( $ch, CURLOPT_POST, true );
		if ( $postcontent != '' ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $postcontent );
		}
	}

	if ( $queryString != '' ) {
		$url = $url . "?" . $queryString;
	}

	curl_setopt( $ch, CURLOPT_URL, $url );

	$header_array = array(  "Date: " . $requestDate, "Authorization: " . $authString, "Content-Type: " . $ct );

	curl_setopt( $ch, CURLOPT_HTTPHEADER, $header_array );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );

	$output = curl_exec( $ch );
	//$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
}

function hexToBase64( $str ) {
	$raw = '';
	for ( $i=0; $i < strlen( $str ); $i+=2 ) {
		$raw .= chr( hexdec( substr( $str, $i, 2 ) ) );
	}
	return base64_encode( $raw );
}
