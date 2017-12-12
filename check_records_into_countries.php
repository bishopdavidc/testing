<?php

// setting the default for parser
set_time_limit(0);
error_reporting(1);
ini_set('memory_limit', '-1');

ini_set('mysqli.reconnect', 1);

//set timezone
date_default_timezone_set('America/New_York');

//inclusion of files which are required
include_once('library/config.php');

$freegalConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB2) or die('Could not connect to Freegal Database');
mysqli_set_charset($freegalConnection, 'UTF8');

$readIodaLog = fopen( LOG_TEMP_PATH . '40252999698798/sony_album_id.txt', 'r');
$takeDown    = fopen( LOG_TEMP_PATH . '40252999698798/sony_log_file.txt', 'a');

$territories = array('us', 'au', 'gb', 'ie', 'ca', 'it', 'nz', 'bm', 'de');

if ( $readIodaLog ) {

	while ( ( $line = fgets( $readIodaLog ) ) !== false ) {

		$prodId = trim($line);
		
		if ( isset($prodId) && !empty($prodId)) {
			$country = '';
			foreach ( $territories as $territory ) {

				$sql = "SELECT * FROM {$territory}_countries WHERE ProdID = $prodId AND provider_type = 'sony' AND ( StreamingSTatus = 1 OR DownloadStatus = 1)";
				$res = mysqli_query( $freegalConnection, $sql );
				if ( $res->num_rows > 0 ) {
					$country .= $territory . ' | ';  
				}
			}
			
			if ( $country != '' ) {
					
				fwrite($takeDown, $prodId . ' | ' . $country . PHP_EOL );
			}
		}
	}
}

mysqli_close($freegalConnection);

fclose($readIodaLog);
fclose($takeDown);

exit;
