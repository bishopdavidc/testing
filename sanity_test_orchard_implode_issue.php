<?php
set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

date_default_timezone_set('America/New_York'); //set timezone

include_once('library/config.php');

$freegalConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB2) or die('Could not connect to Freegal Database');
$orchardConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die('Could not connect to Theorchard Database');

mysqli_set_charset($freegalConnection, 'UTF8');
mysqli_set_charset($orchardConnection, 'UTF8');

$territories = array('us', 'au', 'gb', 'ie', 'ca', 'it', 'nz', 'bm', 'de');

$presentID = fopen( LOG_TEMP_PATH . 'orchard_implode_issue_presentId.txt', 'a' );
$missingID = fopen( LOG_TEMP_PATH . 'orchard_implode_issue_missingId.txt', 'a' );

$start = 0;
$offset = 1000;

do {
	$sqlCountries = "SELECT
						countries.country_id,
					    countries.ioda_track_id,
					    countries.isrc,
					    countries.track_restricted_from,
					    countries.upc
					FROM
					    theorchard.countries
					WHERE
					    countries.upc IS NOT NULL
					        AND countries.upc != ''
					        AND countries.isrc IS NOT NULL
					        AND countries.isrc != ''
					        AND countries.ioda_track_id IS NOT NULL
					        AND countries.ioda_track_id != ''
					        AND countries.track_restricted_from IS NOT NULL
					        AND countries.track_restricted_from != ''
					        AND (countries.track_restricted_to IS NULL
					        OR countries.track_restricted_to = '')
					ORDER BY countries.ioda_track_id
					LIMIT $start, $offset";

	$start = $start + $offset;
	
	$countriesResource = mysqli_query($orchardConnection, $sqlCountries);

	if ( $countriesResource->num_rows > 0 ) {
		while ( $countriesRow = mysqli_fetch_object( $countriesResource ) ) {
			
			$territoriesArray = explode( ',', strtolower( $countriesRow->track_restricted_from ) );
			
			$missingRecord = '';
			$presentRecord = '';

			foreach ( $territories as $territory ) {
				if ( !( in_array( $territory, $territoriesArray ) ) ) {
					$terriotryResource = mysqli_query($freegalConnection, "SELECT * FROM freegal.{$territory}_countries WHERE provider_type = 'ioda' AND ProdID = " . $countriesRow->ioda_track_id );

					if ( ! ( $terriotryResource ->num_rows > 0 ) ) {
						$missingRecord .= $territory . ' | ';
					} else {
						$presentRecord .= $territory . ' | ';
					}
				}
			}
			
			if ( !empty( $missingRecord )) {
				$missingRecord .= $countriesRow->ioda_track_id . ' | ' . $countriesRow->isrc . ' | ' . $countriesRow->upc . ' | ' . $countriesRow->country_id;
				fwrite($missingID, $missingRecord . PHP_EOL);
			}
			
			if (!empty($presentRecord)) {
				$presentRecord .= $countriesRow->ioda_track_id . ' | ' . $countriesRow->isrc . ' | ' . $countriesRow->upc . ' | ' . $countriesRow->country_id;
				fwrite($presentID, $presentRecord . PHP_EOL);
			}
		}
	}

} while( $countriesResource->num_rows > 0 );

mysqli_close($freegalConnection);
mysqli_close($freegalConnection);

fclose($presentID);
fclose($missingID);