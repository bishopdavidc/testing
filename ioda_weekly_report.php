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

$theorchardConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die('Could not connect to Theorchard Database');
mysqli_set_charset($theorchardConnection , 'UTF8');

$records = array();

$from_date = date("Y-m-d", strtotime('-7 day'));
$to_date   = date("Y-m-d", strtotime('-1 day'));

$sql = "SELECT 
		    batch_name, DATE_FORMAT(datetime, '%Y-%m-%d') AS process_date
		FROM
		    theorchard.album_parser
		WHERE
		    datetime >= '$from_date 00:00:00'
		        AND datetime <= '$to_date 23:59:59'
		GROUP BY batch_name";

$resource = mysqli_query($theorchardConnection, $sql);

$batches = '';
$count = 0;
$date = '';

if ( $resource->num_rows > 0 ) {
	
	while ( $row = mysqli_fetch_object( $resource ) ) {
		
		if ( $date == $row->process_date ) {
			$batches .= $row->batch_name . ', ';
			$count++;
		} else {
			
			if ( $count != 0 ) {
				$records[$date][$count] = substr( trim( $batches ), 0, -1 );
			}
			
			$date = $row->process_date;
			$batches = $row->batch_name . ', ';
			$count = 1;
		}
	}
	
	$records[$date][$count] = substr( trim( $batches ), 0, -1 );

} else {
	$records[$from_date . ' - ' . $to_date][$count] = $batches = 'No Release found.';
}

$read_script_no = fopen( 'running_script_no.txt', 'r');

if ( $read_script_no ) {
	$script_no = fgets( $read_script_no );
	$query = "SELECT 
			    foldername, script_name
			FROM
			    theorchard.running_scripts
			WHERE
			    script_no > $script_no
			        AND script_name != ''";

	$res3 = mysqli_query($theorchardConnection, $query);
	$message1 = '';
	$foldername = '';

	if ( $res3->num_rows > 0 ) {
		while ( $row = mysqli_fetch_object( $res3) ) {
			$foldername .= $row->foldername . ', ';
		}

		$message1 .= "<tr>
		<td>Note</td>
		<td>" . substr( trim( $foldername ), 0, -1 ) . "</td>
		<td>Manifest not found</td>
		</tr>";
	}
}

fclose($read_script_no);

$query = "SELECT script_no FROM theorchard.running_scripts ORDER BY script_no DESC LIMIT 1";

$record = mysqli_fetch_object( mysqli_query($theorchardConnection, $query) );

$write_script_no = fopen('running_script_no.txt', 'w');
fwrite($write_script_no, $record->script_no);

fclose($write_script_no);

//print_r($records);

mysqli_close($theorchardConnection);

$message = "<html>
<head>
<style>
table, th, td {
    border: 1px solid black;
}
.date{
	width:20%;
}
.count{
	width:10%;
	text-align: center;
}
.batches{
	width:70%;
}
</style>
</head>
<body>

<table cellpadding='10' cellspacing='10' border='5'>
  <tr>
    <th class='date'>Date</th>
    <th class='batches'>Processed Batches</th>
    <th class='count'>Count</th>
  </tr>";

foreach ( $records as $key => $record ) {
	$key2 = key($record);
	$message .= "<tr>
	<td class='date'>$key</td>
	<td class='batches'>$record[$key2]</td>
	<td class='count'>$key2</td>
	</tr>";	
}
if (isset($message1) && !empty($message1)) {
	$message .= $message1;	
}

$message .= "</table>

</body>
</html>";

mail_attachment($message );

function mail_attachment( $message)
{
	// Always set content-type when sending HTML email
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
	
	// More headers
	$headers .= 'From: <ioda_weekly_report@infobeans.com>' . "\r\n";
	//$headers .= 'Cc: myboss@example.com' . "\r\n";
	
	mail( "davidcb@libraryideas.com,meganb@libraryideas.com,libraryideas@infobeans.com,fader84@gmail.com", "The Orchard: Weekly Report ( " . date('Y-m-d', strtotime(" -7 day")) . " To " . date('Y-m-d', strtotime(" -1 day")) . " )", $message, $headers);
}

