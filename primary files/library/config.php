<?php

error_reporting(1);
/*
//LUTHER CONFIG
define('DB_HOST', 'localhost');
define('DB_USER', 'alokb');
define('DB_PASSWORD', '67J3HAWT9u');
define('DB1', 'orchard');
define('DB2', 'ib_test_freegal');

define('ROOTPATH','/mnt/libraryideas/theorchard/test/');  
define('IMPORTLOGS','/mnt/libraryideas/test/theorchard_test/theorchard_reports/logs/');
define('REPORT_LOGS','/mnt/libraryideas/test/theorchard_test/theorchard_reports/reports/');
define('SERVER_PATH','/mnt/libraryideas/test/theorchard_test/');
*/

define('TOTAL_SCRIPT_RUNNING' , 1);

define('TOTAL_ALBUM_PARSER', 15);

define('PATCH_DETAILS', '/mnt/libraryideas/theorchard_music/');
define('ALBUMTEST','/home/parser/theorchard/test/');  

//PROD CONFIG
define('DB_HOST','192.168.100.52'); //DB5
//define('DB_HOST', '192.168.100.114'); //DB1
define('DB_USER', 'ioda_parser');
define('DB_PASSWORD', '64:m46aGiF+D');
define('DB1', 'theorchard');
define('DB2', 'freegal');

//SLAVE DB CONFIG
define('SLAVE_DB','192.168.100.52');        //DB5
//define('SLAVE_DB','192.168.100.53');        //DB6
//define('SLAVE_DB','192.168.100.115');       //DB2
//define('SLAVE_DB','192.168.100.114');       //DB1



define('ROOTPATH','/mnt/libraryideas/theorchard/production/');  
define('ROOTPATHTEST','/mnt/libraryideas/theorchard/test/');
define('SCRIPTPATH' , '/home/parser/theorchard/');

define('IMPORTLOGS','/home/parser/theorchard/theorchard_logs/');
define('MYSQLLOGS','/home/parser/theorchard/mysql_logs/');
define('MYSQLLOGS_FLAG','1');
define('REPORT_LOGS','/home/parser/theorchard/report/');
define('SERVER_PATH','/mnt/libraryideas/production/theorchard/');

define('CLIENT_SERVER_PATH','/home/parser/theorchard/ASP/');
define('LOCAL_SERVER_PATH','/home/parser/theorchard/new_env/client_xml/');

define('CDNPATH','theorchard');
//define('CDNPATH','theorchard_test');
define('HOST_URL','http://music.libraryideas.com');
define('LOG_TEMP_PATH','/home/parser/theorchard/temp/');
define('SFTP_HOST','libraryideas.ingest.cdn.level3.net');
define('SFTP_PORT',22);

define('SFTP_HOST_TEST','libraryideas.ingest.cdn.level3');
define('SFTP_PORT_TEST',22);

define('SFTP_USER','libraryideas');
define('SFTP_PASS','t837dgkZU6xCMnc');
define('TO','tech@libraryideas.com');
define('FROM','no-reply@freegalmusic.com');

define('SUBJECT','FreegalMusic.com: The Orchard File Processing Information');
define('HEADERS','From:'. FROM);
define('SLEEP_TIME',6); //set to 1200 on server for 20 min sleep
define('MAILSERVER','localhost');


define('ASP_SFTP_HOST','ftp.alexanderstreet.com');
define('ASP_SFTP_PORT',2222);
define('ASP_SFTP_USER','libraryideas');
define('ASP_SFTP_PASS','4uPrAn#W');








$orchard = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD ,true) or die("Could not connect to theorchard Database");
$freegal = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD,true) or die("Could not connect to Freegal Database");

if(!mysql_select_db(DB1, $orchard))
	die("Could not connect to orchard Database");
	
if(!mysql_select_db(DB2, $freegal))
	die("Could not connect to freegal Database");

mysql_set_charset('UTF8',$freegal);
mysql_set_charset('UTF8',$orchard);

?>
