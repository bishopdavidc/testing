<?php

/**
 * File Name : orchard_parser.php
 * File Description :   This file is executed as a backend process (set up in crontab). 
 *                      This File is used for parsing the xml and inserting the node values floato freegal and ORCHARD database.
 * @author : m68 Interactive
 * */
// setting the default for parser
set_time_limit(0);
error_reporting(1);
ini_set('memory_limit', '-1');
ini_set('mysqli.reconnect', 1);

//set timezone
date_default_timezone_set('America/New_York');

//inclusion of files which are required
include_once('library/config.php');
include_once('library/FileProcess.php');

include_once('library/FreegalDatabase.php');
include_once('library/OrchardDatabase.php');
include_once('library/Logs.php');
include_once('library/ioda_functions.php');

echo "Parser started. \n";

if (!isset($argv[1]) || empty($argv[1])) {
    echo 'You have passed empty arguments.' . PHP_EOL;
    exit;
}
//defining patch folder directory
$processPatchDir = $argv[1];   //'20130101_0001';
//$processPatchDir = '20130101_0001';

$fp_obj = new FileProcess();
$patch_log = new Logs($processPatchDir);
$orchard_db = new OrchardDatabase($processPatchDir);
$freegal_db = new FreegalDatabase($processPatchDir);
$parser_function = new IodaFunctions($processPatchDir);
$album_not_processed = array();

//check if the Batch folder folder value is set in $processPatchDir variable
if (strstr($processPatchDir, 'libraryideas')) {
    $patch_log->writeError(Logs::ERR_SCRIPT_PROBLEM);
    exit;
}

//setting the patch folder path
$patch_folder_path = ROOTPATH . $processPatchDir;

/**
 * This part of code is going to run the multiple Album
 * parsers. It reads the UPC from the Manifest file.
 * First is validates the Album. This section is run in do - while loop
 * till all the album folder is removed from the batch folder 
 * whether they are valid or not.
 * 
 * 1. Album folder is present or not.
 * 2. Album XML is present or not 
 * 3. Album XML is well formatted
 * 4. Album Image is present or not
 * 5. Tracks present as per XML and in folder
 * 6. Tracks which are having 0 in size  
 * 
 * If album fails during validation then it is moved to 
 * The Redeliver folder and if the validation is passed then
 * it create the album_parser_<UPC>.php
 */
//Todo : uncomment
$start_prodid = $orchard_db->getStartProdID($processPatchDir);
//$start_prodid = 10075738;

$batch_album_processing = $orchard_db->checkAlbumProcessByBatch($processPatchDir);

if (!$batch_album_processing) {

    //first insert the confirm Album folders in album_parser table with details
    $manifest_file_path = $patch_folder_path . '/manifest.txt';
    $handle = fopen($manifest_file_path, "r");

    while (!feof($handle)) {

        $insert_album_parser = array();

        $line_upc = fgets($handle);
        $line = trim($line_upc);

        if (!empty($line)) {

        	//check if xml file exits for UPC or not
        	$album_path = $patch_folder_path . "/" . $line;
        	$album_xml = $patch_folder_path . "/" . $line . "/" . $line . ".xml";
        	
        	$xml_object = simplexml_load_file($album_xml);
        	$xml_array  = json_decode(json_encode($album_xml_object), 'true');
        	
        	$redeliver_flag = false;
        	if ( $xml_array['DeliveryType'] === "MetadataOnlyUpdate" ) {
        		$redeliver_flag = true;
        	}

        	//check if the album is redelivered
        	$booleanVal = $orchard_db->orchardRedeliverTable( $line, $redeliver_flag );
        	
        	if ( $booleanVal == false ) {
        		$patch_log = new Logs($patch_folder_path, $line);
        		$patch_log->sendMail("We have requested UPC: $line for redelivery but we got meta data update so we have not processed them. \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
        		continue;
        	}

            $insert_album_parser['upc'] = $line;
            $insert_album_parser['batch_name'] = $processPatchDir;
            $insert_album_parser['album_parser_name'] = $album_parser_name = "album_parser_$line.php";
            $insert_album_parser['datetime'] = date('Y-m-d H:i:s');
            $insert_album_parser['start_prodid'] = $start_prodid;

            //validating the album
            if (!file_exists($album_path)) {

                $album_not_processed[$line]['error'] = "Album Folder not found in batch no : $processPatchDir";
                echo $command = "rm -fr $album_path";
                exec($command);
                continue;
            }

            //check if Album xml is present for album
            if (!file_exists($album_xml)) {

                $album_not_processed[$line]['error'] = "Album XML not found in batch no : $processPatchDir";
                echo $command = "rm -fr $album_path";
                $status = exec($command, $output, $serverResponse);

                if ($serverResponse > 0) {
                    $patch_log = new Logs($patch_folder_path, $line);
                    $patch_log->sendMail("$command was executed but got some error.\n  Album XML not found.\n Error : $status \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
                    exit();
                }
                continue;
            }

            //album xml is found and now we read the Xml file for current album
            $album_xml_object = simplexml_load_file($album_xml);
            if (!is_object($album_xml_object)) {

                $album_not_processed[$line]['error'] = "Album XML not well formmated in batch no : $processPatchDir";
                echo $command = "rm -fr $album_path";
                $status = exec($command, $output, $serverResponse);

                if ($serverResponse > 0) {
                    $patch_log = new Logs($patch_folder_path, $line);
                    $patch_log->sendMail("$command was executed but got some error.\n Album XML not well formmated.\n Error : $status \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
                    exit();
                }
                continue;
            }

            //XML obejct to Album array
            $album_xml_array = json_decode(json_encode($album_xml_object), 'true');

            if (!in_array($album_xml_array['DeliveryType'], array("CompleteAlbum", "MetadataOnlyUpdate", "Takedown"))) {

                $album_not_processed[$line]['error'] = 'Album Delivery type is different. Having the delivery type as : ' . $album_xml_array['DeliveryType'];
                echo $command = "rm -fr $album_path";
                $status = exec($command, $output, $serverResponse);

                if ($serverResponse > 0) {
                    $patch_log = new Logs($patch_folder_path, $line);
                    $patch_log->sendMail("$command was executed but got some error.\n Album Delivery type is different.\n Error : $status \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
                    exit();
                }
                continue;
            }

            $fp_obj->checkForRenamedFiles($album_path);

            //Tracks detail from XML
            //if it is for complete Album then MP# should be present in the album folder
            $albums_tracks_details = $fp_obj->getTracksDetails($album_xml, $line);
            $album_clip_tracks_array = $fp_obj->getFolderFilesByType($patch_folder_path . "/" . $line, '_CLIP.mp3', 'true');
            $album_mp3_tracks_array = $fp_obj->getFolderFilesByType($patch_folder_path . "/" . $line, '.mp3', 'true');
            $folder_track_count = count(array_diff($album_mp3_tracks_array, $album_clip_tracks_array));


            //code for 0size mp3 file check added
            if ($album_xml_array['DeliveryType'] == "CompleteAlbum") {

                //validate if album is having image or not
                $album_image = $patch_folder_path . "/" . (float) $line . "/" . $line . ".jpg";

                if (!file_exists($album_image)) {

                    $album_not_processed[$line]['error'] = "Album image not found in batch no : $processPatchDir";
                    echo $command = "rm -fr $album_path";
                    $status = exec($command, $output, $serverResponse);

                    if ($serverResponse > 0) {
                        $patch_log = new Logs( $patch_folder_path, $line);
                        $patch_log->sendMail("$command was executed but got some error.\n Album image not found.\n Error : $status \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
                        exit();
                    }
                    continue;
                }

                if ($folder_track_count != count($albums_tracks_details)) {

                    $album_not_processed[$line]['error'] = 'Track count are diffrent from the XML and present in Album folder';
                    echo $command = "rm -fr $album_path";
                    $status = exec($command, $output, $serverResponse);

                    if ($serverResponse > 0) {
                        $patch_log = new Logs($patch_folder_path, $line);
                        $patch_log->sendMail("$command was executed but got some error.\n Track count are diffrent.\n Error : $status \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
                        exit();
                    }
                    continue;
                }

                $folder_track = array_diff($album_mp3_tracks_array, $album_clip_tracks_array);
                foreach ($folder_track as $track) {

                    if (!filesize($track)) {

                        $album_not_processed[$line]['error'] = 'Track with 0 size found';
                        echo $command = "rm -fr $album_path";
                        $status = exec($command, $output, $serverResponse);

                        if ($serverResponse > 0) {
                            $patch_log = new Logs($patch_folder_path, $line);
                            $patch_log->sendMail("$command was executed but got some error.\n Track with 0 size found.\n Error : $status \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
                            exit();
                        }
                        continue;
                    }
                }
            }

            if ($album_xml_array['DeliveryType'] === "CompleteAlbum") {

                $insert_album_parser['type_flag'] = 'I';

            } elseif ($album_xml_array['DeliveryType'] === "MetadataOnlyUpdate") {

                $is_already_orchard = $orchard_db->getAlbum($line);
                $album_freegal = $freegal_db->checkAlbumByUPC($line);

                if (!empty($is_already_orchard) && !empty($album_freegal)) {

                    $insert_album_parser['type_flag'] = 'U';

                } else {

                    $album_not_processed[$line]['error'] = 'Album came for update but record not found in DB\'s : ';
                    echo $command = "rm -fr $album_path";
                    exec($command);
                    continue;

                }

            } elseif ($album_xml_array['DeliveryType'] === "Takedown") {

                $is_already_orchard = $orchard_db->getAlbum($line);
                $album_freegal = $freegal_db->checkAlbumByUPC($line);

                if (!empty($is_already_orchard) && !empty($album_freegal)) {

                    $insert_album_parser['type_flag'] = 'D';

                } else {

                    $album_not_processed[$line]['error'] = 'Album came for takedown but record not found in DB\'s : ';
                    echo $command = "rm -fr $album_path";
                    exec($command);
                    continue;

                }
            }
            
            $missing_track_count = 0;
            $tracks = $fp_obj->getTracksDetails($album_xml, $line);

            if ($album_xml_array['DeliveryType'] === "MetadataOnlyUpdate") {

            	foreach ($tracks as $key => $val) {

            		$track_in_freegal = $freegal_db->getTrack($tracks[$key]['isrc'], $line);
            		if (empty($track_in_freegal)) {
            			$missing_track_count++;
            		}
            	}
            	
            	if ($missing_track_count > 0) {

            		$album_not_processed[$line]['error'] = "$missing_track_count tracks are missing in our DB as per updated XML.";
            		echo $command = "rm -fr $album_path";
            		$status = exec($command, $output, $serverResponse);
            		
            		if ($serverResponse > 0) {
            			$patch_log = new Logs($patch_folder_path, $line);
            			$patch_log->sendMail("$command was executed but got some error.\n Track count are diffrent.\n $missing_track_count tracks are missing in our DB as per updated XML.\n Error : $status \n", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
            			exit();
            		}
            		continue;
            	}

            } else if ( $album_xml_array['DeliveryType'] === "Takedown" ) {
            	foreach ( $tracks as $key => $val ) {
            	
            		$track_in_freegal = $freegal_db->getTrack($tracks[$key]['isrc'], $line);
            		if ( empty( $track_in_freegal) ) {
            			$missing_track_count++;
            		}
            	}
            }

            $insert_album_parser['end_prodid'] = (int) $start_prodid + (int) $folder_track_count + (int) $missing_track_count + (int) TOTAL_ALBUM_PARSER;
            $start_prodid = $insert_album_parser['end_prodid'];

            $is_inserted = $orchard_db->checkUPCByParser($processPatchDir, $line);

            if (empty($is_inserted)) {
                $orchard_db->insertTableQuery($insert_album_parser, 'album_parser');
            }

            //backup the orignial album
            $src_album_path = $patch_folder_path . "/" . $line;
            $backup_path = PATCH_DETAILS . $processPatchDir . '/' . 'archive' . '/';

            //creating the backup folder
            if (!file_exists($backup_path . $line)) {

                if (!mkdir($backup_path . $line, 0777, TRUE)) {
                    $patch_log->writeError(Logs::ERR_CUSTOM, $processPatchDir, null, "Cannot create Backup Album folder $processPatchDir.");
                }
            }

            $album_file_list = $fp_obj->getFolderFilesList($src_album_path);

            foreach ($album_file_list as $file) {

                if (strpos($file, ".mp3") || strpos($file, ".jpg")) {
                    continue;
                } else {
                    echo shell_exec('cp -R  ' . $album_path . '/' . $file . '  ' . $backup_path . $line . '/');
                }
	    }
        }
    }

    fclose($handle);

    // delete the album folder which are not present in the 
    // manifest file
    $folder_array = glob($patch_folder_path . '/' . '*', GLOB_ONLYDIR);

    foreach ($folder_array as $folder) {

        $dir = str_replace($patch_folder_path . '/', '', $folder);
        $is_in_manifest = $orchard_db->checkUPCByParser($processPatchDir, $dir);

        if (empty($is_in_manifest)) {

            // echo shell_exec("rm - " . $folder);
            $album_not_processed[$dir]['error'] = "$dir is not found in Manifest.txt";
            continue;
        }
    }

    if (!empty($album_not_processed)) {

        foreach ($album_not_processed as $upc => $value) {

            if (is_integer($upc)) {
                $orchard_db->orchardRedeliverInsert($processPatchDir, $upc, $value['error']);
            }
        }
    }

    $start_prodid = (int) $start_prodid + (int) TOTAL_ALBUM_PARSER + 1000;
    $orchard_db->updateLastUsedProdId($start_prodid, $processPatchDir);
}

$batch_album_processing = $orchard_db->checkAlbumProcessByBatch($processPatchDir);
$parserScriptPath = '/home/parser/theorchard/';
do
{
    //check total no of parser running in album_parser
    $total_album_parser_running = $orchard_db->checkTotalAlbumParserRunning();

    while ($total_album_parser_running < TOTAL_ALBUM_PARSER)
    {
        //get list of album parser
        $album_parser_data = $orchard_db->getAlbumParserByBatch($processPatchDir);
        $album_parser_count = mysqli_num_rows($album_parser_data);

        if (!($album_parser_count > 0)) {
            $orchard_db->updateRunningScript($processPatchDir);
            exit;
        }


        while ($album_parser_detail = mysqli_fetch_assoc($album_parser_data))
        {
            // This creates a copy of the album_parser.php with the UPC appended to the end of the file name
            $newFileName = $album_parser_detail['album_parser_name'];
            echo exec("cd " . $parserScriptPath);
            echo shell_exec("cp -R " . $parserScriptPath . "album_parser.php " . $parserScriptPath . $newFileName);

            // This reads the new file and assigns the contents to a variable
            $parserOrg = fopen($parserScriptPath . $newFileName, "r");
            $parserData = fread($parserOrg, filesize($parserScriptPath . $newFileName));
            fclose($parserOrg);

            // This replaces the text 'libraryideas' with the patch directory and replaces the contents of the file with the new contents.
            $newdata = str_replace('libraryideas', $processPatchDir, $parserData);
            $parserOrg = fopen($parserScriptPath . $newFileName, 'w');
            fwrite($parserOrg, $newdata);
            fclose($parserOrg);

            $orchard_db->updateAlbumRunningScript($processPatchDir, $newFileName);
            //end changing patch folder name in file

            $total_album_parser_running++;
            if ($total_album_parser_running == TOTAL_ALBUM_PARSER) {
                break;
            }
        }
    }

    sleep(15); // this was 10 seconds, changed it to 15 seconds for testing

    // Get all of the parsers from the batch that have already been started.
    $query = "SELECT batch_name , upc , album_parser_name FROM theorchard.album_parser where batch_name='$processPatchDir' and job_started=1";
    $album_parser_details = $orchard_db->executeQuery($query);

    // Loop through all of the parsers from the batch and delete those that have already been started
    while ($album_parsers = mysqli_fetch_assoc($album_parser_details)) {

        // Change to the directory that holds the parsers
        echo exec("cd " . $parserScriptPather);

        // Check if the parser file still exist
        if (file_exists($album_parsers['album_parser_name'])) {

            // Execute command to delete the parser script
            $status = exec(" rm " . $album_parsers['album_parser_name'], $output, $serverResponse);

            // If there is a problem deleting the file send an email
            if ($serverResponse > 0) {

                $patch_log->sendMail("Hi Rob, \n We have got error while deleting the " . $album_parsers['album_parser_name'] . " file from orchard_parser.php"
                        . "Error : $serverResponse & error : $status & output : " . serialize($output)
                        . ".\n The command was $command", "UPC $upc sanity report", Logs::MYSQL_LOGS);
            }
        }
    }

    // The number of parsers from the batch that have been started.
    $completed = mysqli_num_rows($album_parser_details);
}
while ($completed != $batch_album_processing);

sleep(3);
$orchard_db->updateRunningScript($processPatchDir);
$orchard_db->closeConnection();
$freegal_db->closeConnection();

exit;
