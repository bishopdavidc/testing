<?php

set_time_limit(0);
error_reporting(1);

ini_set('mysqli.reconnect', 1);

//set timezone
date_default_timezone_set('America/New_York');

//inclusion of files which are required
include_once('library/config.php');

$today = date('D');

if ($today === 'Fri') {

    $orchard_db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die("Could not connect to theorchard Database");

    $file_name = 'theorchard_upc_redeliver_list_' . date('Y-m-d') . ".txt";
    $log = fopen( LOG_TEMP_PATH . 'upc_redeliver/' . $file_name, "a");

    $redeliver_query = "SELECT upc, id FROM theorchard.album_redeliver WHERE status = 0 AND remarks NOT LIKE '%takedown%' ORDER BY id";
    $redeliver_upc = mysqli_query($orchard_db, $redeliver_query);

    if ( $redeliver_upc->num_rows > 0 ) {
    	while ( $row = mysqli_fetch_assoc( $redeliver_upc ) ) {
    		if ( mysqli_query($orchard_db, "UPDATE theorchard.album_redeliver SET status = 1 WHERE id = " . $row['id'] . " AND upc = '" . $row['upc'] . "'") ) {
    			fwrite($log, $row['upc'] . PHP_EOL);
    		}
    	}
    }

    fclose($log);

    mysqli_close($orchard_db);

    $to = "ghanshyam.agrawal@infobeans.com,tech@libraryideas.com,narendra.nagesh@infobeans.com";
    //$to = "ghanshyam.agrawal@infobeans.com";

$message="Hi Rob,

  We need to send re-delivery request of attached UPCs list to The Orchard.

 Thanks";

    mail_attachment($file_name, LOG_TEMP_PATH . 'upc_redeliver/', $to , 'From: The Orchard XML Import' . "\r\n" . 'X-Mailer: PHP/' . phpversion(), 'The Orchard Redeliver Request List', '', 'The Orchard Redeliver Request List ', $message);
}
else
{
    echo "Today is $today. On Friday list will be generated";
}


exit;

function mail_attachment($filename, $path, $mailto, $from_mail, $from_name, $replyto, $subject, $message)
{
    $file = $path . $filename;
    $file_size = filesize($file);
    $handle = fopen($file, "r");
    $content = fread($handle, $file_size);
    fclose($handle);
    $content = chunk_split(base64_encode($content));
    $uid = md5(uniqid(time()));
    $name = basename($file);
    $header = "From: " . $from_name . " <" . $from_mail . ">\r\n";
    $header .= "Reply-To: " . $replyto . "\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: multipart/mixed; boundary=\"" . $uid . "\"\r\n\r\n";
    $header .= "This is a multi-part message in MIME format.\r\n";
    $header .= "--" . $uid . "\r\n";
    $header .= "Content-type:text/plain; charset=iso-8859-1\r\n";
    $header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $header .= $message . "\r\n\r\n";
    $header .= "--" . $uid . "\r\n";
    $header .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"\r\n"; // use different content types here
    $header .= "Content-Transfer-Encoding: base64\r\n";
    $header .= "Content-Disposition: attachment; filename=\"" . $filename . "\"\r\n\r\n";
    $header .= $content . "\r\n\r\n";
    $header .= "--" . $uid . "--";
    if (mail($mailto, $subject, "", $header))
    {
        echo "mail send ... OK"; // or use booleans here
    }
    else
    {
        echo "mail send ... ERROR!";
    }
}
