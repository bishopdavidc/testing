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

$read = fopen( LOG_TEMP_PATH . '46525724917149/ioda_artist_name.txt', 'r');

$up	  = fopen( LOG_TEMP_PATH . '46525724917149/up_mp3_records.txt', 'a');
//$down	  = fopen( LOG_TEMP_PATH . '46525724917149/missing_records.txt', 'a');

$territories = array('au', 'bm', 'ca', 'de', 'gb', 'ie', 'it', 'nz', 'us');

if ( $read ) {

	while ( ( $line = fgets( $read ) ) !== false ) {

		$arr 	  = explode('|', $line);
		//$referenceID   = trim($line);
		$prodID = trim($arr[0]);
		$artistName = addslashes( trim( $arr[1] ) );

		//$songs = "SELECT ProdID FROM Songs WHERE ReferenceID = $referenceID AND provider_type = 'ioda'";
		$songs = "SELECT ProdID FROM Songs WHERE ArtistText = '$artistName' AND provider_type = 'sony'";
//		echo $songs . PHP_EOL;
		$resource1 = mysqli_query($freegalConnection, $songs);

		$songProdIds = array();
		if ( $resource1->num_rows > 0 ) {
			while ( $row = mysqli_fetch_object($resource1 ) ) {
					$songProdIds[] = $row->ProdID;
			}

			$flag = false;

			foreach ($territories as $territory ) {
				
				//$selectTerritory = "SELECT * FROM {$territory}_countries WHERE ( StreamingStatus = 1 OR DownloadStatus = 1 ) AND ProdID IN (" . implode( ',', $songProdIds ) . ") AND provider_type = 'ioda'";
				
				$selectTerritory = "SELECT * FROM {$territory}_countries WHERE DownloadStatus = 1  AND ProdID IN (" . implode( ',', $songProdIds ) . ") AND provider_type = 'sony'";
				
//				echo $selectTerritory . PHP_EOL;
				$resource = mysqli_query($freegalConnection, $selectTerritory);

				if ( $resource->num_rows > 0 ) {
					$flag = true;
					break;
				}
			}

			if ( $flag === true ) {
				fwrite($up, $prodID . ' | Yes | 1 '  . PHP_EOL);
			} else {
				fwrite($up, $prodID . ' | Yes | 0 '  . PHP_EOL);
			}			
		} else {
			fwrite($up,  $prodID . ' | No | 0 ' . PHP_EOL);
		}
	}
}

mysqli_close($freegalConnection);

fclose($read);
fclose($up);
//fclose($down);
?>
