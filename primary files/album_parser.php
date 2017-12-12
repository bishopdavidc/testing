<?php

/**
 * File Name : orchard_parser.php
 * File Description :   This file is executed as a backend process (set up in crontab). 
 *                      This File is used for parsing the xml and inserting the node values into freegal and ORCHARD database.
 * @author : Library Ideas
 * */
// setting the default for parser
set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

// set timezone
date_default_timezone_set('America/New_York');

// inclusion of files which are required
include_once('library/config.php');
include_once('library/FileProcess.php');
include_once('library/FreegalDatabase.php');
include_once('library/OrchardDatabase.php');
include_once('library/Logs.php');
include_once('library/ioda_functions.php');

echo "Album Parser started. \n";

// defining patch folder directory
$processPatchDir = 'libraryideas';   //'20140104_0017';

$albums_details = array();


/**
 * reading the script name and check in running_scripts table
 * for the script is running currently or not.
 */
$self_script_name = $_SERVER['PHP_SELF'];
$album = str_replace('album_parser_', '', $self_script_name);
$album = str_replace('.php', '', $album);

$fp_obj = new FileProcess();
$patch_log = new Logs($processPatchDir, $album);
$orchard_db = new OrchardDatabase($processPatchDir, $album);
$freegal_db = new FreegalDatabase($processPatchDir, $album);
$parser_function = new IodaFunctions($processPatchDir, $album);

$patch_folder_path = ROOTPATH . $processPatchDir;

// Checking to see if the batch foler exists
if (file_exists($patch_folder_path)) {

    $album_detail_data = $orchard_db->getAlbumParserByBatch($processPatchDir, $self_script_name);
    $album_detail = mysqli_fetch_assoc($album_detail_data);

    // This logic looks to be backwards. Need to check!
    if (!empty($album_detail)) {
        
        // check if xml file exits for UPC or not
        // This does not check if the xml exists. It only creates the path. We need to check if it exists.
        $album_path = $patch_folder_path . "/" . $album_detail['upc'];
        $album_xml = $patch_folder_path . "/" . $album_detail['upc'] . "/" . $album_detail['upc'] . ".xml";

        // album xml is found and now we read the Xml file for current album
        $album_xml_object = simplexml_load_file($album_xml);

        // XML obejct to Album array
        $album_xml_array = json_decode(json_encode($album_xml_object), 'true');

	// This statement does the same thing in both conditions. Need to check why.
        if ($album_xml_array['UPC'] == $album_detail['upc']) {
            $upc = $album_detail['upc'];
        } else {
            $upc = $album_detail['upc'];
        }

        // getting Album details from XML
        $albums_details['album_detail'] = $fp_obj->albumDetails($album_xml);
        $albums_details['album_detail']['upc'] = $upc;

        $cdn_path = $freegal_db->getAlbumCDNPath($upc);

        if ( empty( $cdn_path ) ) {
            $albums_details['album_detail']['CdnPath'] = substr(CDNPATH . '/' . $fp_obj->createPathCDN($upc), 0, -1);
            
        } else {
            if ( isset( $cdn_path['CdnPath'] ) && !empty( $cdn_path['CdnPath'] ) ) {
            	$albums_details['album_detail']['CdnPath'] = $cdn_path['CdnPath'];
            } else {
            	$albums_details['album_detail']['CdnPath'] = substr(CDNPATH . '/' . $fp_obj->createPathCDN($upc), 0, -1);
            }
        }

        $albums_details['album_detail']['Image_SaveAsName'] = $upc . ".jpg";

        $IUDFlag = '';
        if ($album_xml_array['DeliveryType'] === "CompleteAlbum") {
            $albums_details['album_detail']['flag'] = $IUDFlag = 'I';
        } elseif ($album_xml_array['DeliveryType'] === "MetadataOnlyUpdate") {
            $albums_details['album_detail']['flag'] = $IUDFlag = 'U';
        } elseif ($album_xml_array['DeliveryType'] === "Takedown") {
            $albums_details['album_detail']['flag'] = $IUDFlag = 'D';
        }

        $album_in_orchard = $orchard_db->getAlbum($upc);
        $albums_details['track_info'] = $fp_obj->getTracksDetails($album_xml, $upc);

        if ($IUDFlag == 'I') {
            /**
             * this part of code will add the details to array 
             * if image is found and it will also
             */
            $patch_log->writeAlbumLog("$upc Image processing", $upc);
            $image_src = $album_path . "/" . $upc . ".jpg";
            
            if (file_exists($image_src)) {
                
                $resize_85 = $album_path . "/" . $upc . "_85" . ".jpg";
                $resize_100 = $album_path . "/" . $upc . "_100" . ".jpg";

                $albums_details['album_image'][0] = $image_src;
                $fp_obj->resizeAlbumImage($image_src, $resize_85, 85, 85);
                $albums_details['album_image'][1] = (file_exists($resize_85)) ? $resize_85 : 'FALSE';
                $fp_obj->resizeAlbumImage($image_src, $resize_100, 100, 100);
                $albums_details['album_image'][2] = (file_exists($resize_100)) ? $resize_100 : 'FALSE';
                $fp_obj->resizeAlbumImage($image_src, $image_src, 250, 250);
                $albums_details['album_image'] = $fp_obj->albumImageArray($albums_details['album_image'], $processPatchDir);
            }

            $patch_log->writeAlbumLog("$upc Image processing done", $upc);

            // Tracks info from Album XML
            $patch_log->writeAlbumLog("$upc tracks processing", $upc);
            // write tags to tracks
            foreach ($albums_details['track_info'] as $seq => $value) {

                $original_clip_name = $albums_details['track_info'][$seq]['original_clip_name'];
                $original_track_name = $albums_details['track_info'][$seq]['original_track_name'];

                $artist = explode(',', $albums_details['album_detail']['artist_display']);

                if (count($artist) > 1) {
                    $participant_name = $artist[0];
                } else {
                    $participant_name = $albums_details['album_detail']['artist_display'];
                }

                $new_clip_name = $fp_obj->stripped(substr($participant_name, 0, 100)) . "_" . $fp_obj->stripped(substr($albums_details['track_info'][$seq]['title'], 0, 100)) . "_" . $original_clip_name;
                $new_track_name = $fp_obj->stripped(substr($participant_name, 0, 100)) . "_" . $fp_obj->stripped(substr($albums_details['track_info'][$seq]['title'], 0, 100)) . "_" . $original_track_name;

                if (file_exists(ROOTPATH . $processPatchDir . '/' . $upc . '/' . $original_track_name)) {

                    $patch_log->writeAlbumLog("writing tag -> renaming file -> conver to mp4 for $original_track_name", $upc);

		    $fp_obj->writeTagToTrack($album_path, $albums_details['album_detail'], $albums_details['track_info'][$seq], $processPatchDir);

                    $fp_obj->renameFile($album_path, $original_clip_name, $new_clip_name, $original_track_name, $new_track_name, $upc, $processPatchDir);

                    $fp_obj->convertToMp4($album_path . "/" . $new_track_name, str_ireplace('.mp3', '.mp4', $album_path . "/" . $new_track_name), $upc, $processPatchDir);

                    $patch_log->writeAlbumLog("done writing tag -> renaming file -> conver to mp4 for $original_track_name", $upc);
                }

                $albums_details['track_info'][$seq]['new_clip_name'] = $new_clip_name;
                $albums_details['track_info'][$seq]['new_track_name'] = $new_track_name;
                $albums_details['track_info'][$seq]['mp4_track_name'] = str_replace('.mp3', '.mp4', $new_track_name);
            }
            $patch_log->writeAlbumLog("$upc tracks processing done", $upc);

            $patch_log->writeAlbumLog("$upc CDN uploading processing", $upc);
            //CDN uploading of files
            $retry = 0;
            $is_connected = false;
            while ($retry < 100) {

                if (!($cdn_obj = ssh2_connect(SFTP_HOST, SFTP_PORT))) {
                    $retry++;
                    if ($retry == 100) {

                        $patch_log->sendMail("We have tried 100 times to connect to CDN but not able to connect. The Batch $processPatchDir is still in progress.\n", 'CDN Not able to connect', Logs::MYSQL_LOGS);
                        exit;
                    }
                } else {
                    $is_connected = true;
                    break;
                }
            }

            if (ssh2_auth_password($cdn_obj, SFTP_USER, SFTP_PASS)) {

                $sftp_cdn = ssh2_sftp($cdn_obj);

                $album_cdn_path = $albums_details['album_detail']['CdnPath'];
                
                if( strpos( $album_cdn_path, 'ioda' ) !== false ) {
                
                	if ( !$fp_obj->checkCDNFolder( $sftp_cdn, $album_cdn_path ) ) {
                		$album_prodid = $freegal_db->getAlbumCDNPath($upc);
                		$albums_details['album_detail']['CdnPath'] = 'ioda/' . $album_prodid['ProdID'];
                		$album_cdn_path = $albums_details['album_detail']['CdnPath'];
                	}
                }

                if (!$fp_obj->createCDNFolder($sftp_cdn, $album_cdn_path)) {
                    echo "album Folder cannot be created";
                    exit();
                }

                // uploading the images
                $fp_obj->uploadFiles($cdn_obj, 'image', $albums_details['album_image'], $album_cdn_path, $processPatchDir, $upc);
                // uploading the mp3
                $fp_obj->uploadFiles($cdn_obj, 'mp3', $albums_details['track_info'], $album_cdn_path, $processPatchDir, $upc);
                // uploading the mp4
                $fp_obj->uploadFiles($cdn_obj, 'mp4', $albums_details['track_info'], $album_cdn_path, $processPatchDir, $upc);
                // upload clip files
                $fp_obj->uploadFiles($cdn_obj, 'clip', $albums_details['track_info'], $album_cdn_path, $processPatchDir, $upc);

                $fp_obj->checkZeroSizeFile($sftp_cdn, $album_cdn_path, $albums_details['track_info'], $processPatchDir, $upc);
$patch_log->writeAlbumLog("CDN File Creation values: $sftp_cdn, $album_cdn_path", $upc);
                ssh2_exec($cdn_obj, 'exit');
            } else {
                $patch_log->writeAlbumLog("$upc Not able to authenticate to CDN", $upc);
                $patch_log->sendMail("We are not able to authenticate to CDN. The Batch $processPatchDir is still in progress.\n", 'CDN Authentication Error', Logs::MYSQL_LOGS);
                exit;
            }
            $patch_log->writeAlbumLog("$upc CDN uploading processing done", $upc);
        }



        $insert_product['ProdID'] = $upc;
        $freegal_db->freegalProductTable($insert_product);

        $parser_function->orchard_album_processing($albums_details);
        $parser_function->freegal_album_processing($albums_details);


        // album image detail is inserted
        $parser_function->orchard_image_processing($albums_details);
        $parser_function->orchard_sub_genre_processing($albums_details, $processPatchDir);
        $parser_function->orchard_similar_artist_processing($albums_details, $processPatchDir);
        $parser_function->orchard_album_release_date_processing($albums_details, $processPatchDir);
        $parser_function->orchard_album_sale_start_date_processing($albums_details, $processPatchDir);
        $parser_function->orchard_artist_influnace_processing($albums_details, $processPatchDir);
        $parser_function->orchard_artist_contemporaries_processing($albums_details, $processPatchDir);
        $parser_function->orchard_artist_followers_processing($albums_details, $processPatchDir);
        $parser_function->orchard_artist_processing($album_xml, $upc);

        // tracks table and countries table update for Orchard and Freegal
        $start_prodid = $orchard_db->getStartProdIDAlbumParser($processPatchDir, $self_script_name);


        foreach ($albums_details['track_info'] as $key => $val) {

            $new = false;
            $track_in_freegal = $freegal_db->getTrack($albums_details['track_info'][$key]['isrc'], $upc);
            $albums_details['track_info'][$key]['CdnPath'] = $albums_details['album_detail']['CdnPath'];

            if (!isset($track_in_freegal[0]) && empty($track_in_freegal)) { // This is for if a song does not exist in the Songs table
                $new = true;
                $ProdID = $start_prodid;
                $start_prodid++;
            } else {
                $new = false;
                //$ProdID = $track_in_freegal[0];
            }
            if($new == true){
                //orchard file table            
                $parser_function->orchard_file_processing($albums_details['track_info'][$key], $album_path, $ProdID, $new);

                //orcahrd contries table update
                $parser_function->orchard_countries_processing($albums_details['track_info'][$key], $upc, $ProdID, $new, $albums_details['album_detail']['flag']);

                //update Songs details  
                $parser_function->freegal_tracks_processing($albums_details['track_info'][$key], $albums_details['album_detail'], $ProdID, $new);

                //insert in Freegal Genre tbale
                $parser_function->freegal_genre_processing($albums_details['album_detail'], $ProdID, $new);

                $parser_function->freegal_territories_processing($albums_details['track_info'][$key], $albums_details['album_detail'], $ProdID, $new);
                //orchard tracks table
                $parser_function->orchard_tracks_processing($albums_details['track_info'][$key], $ProdID, $new, $albums_details['album_detail']['flag']); 
            }
            else{
                for($ids = 0; $ids < count($track_in_freegal); $ids++){
                    $ProdID = $track_in_freegal[$ids];
                    // if ($IUDFlag == 'I') {
                    //     $albums_details['track_info'][$key]['CdnPath'] = $albums_details['album_detail']['CdnPath'];
                    // }

                    //orchard file table            
                    $parser_function->orchard_file_processing($albums_details['track_info'][$key], $album_path, $ProdID, $new);

                    //orcahrd contries table update
                    $parser_function->orchard_countries_processing($albums_details['track_info'][$key], $upc, $ProdID, $new, $albums_details['album_detail']['flag']);

                    //update Songs details  
                    $parser_function->freegal_tracks_processing($albums_details['track_info'][$key], $albums_details['album_detail'], $ProdID, $new);

                    //insert in Freegal Genre tbale
                    $parser_function->freegal_genre_processing($albums_details['album_detail'], $ProdID, $new);

                    $parser_function->freegal_territories_processing($albums_details['track_info'][$key], $albums_details['album_detail'], $ProdID, $new);

                    $parser_function->orchard_tracks_processing($albums_details['track_info'][$key], $ProdID, $new, $albums_details['album_detail']['flag']);
                }
            }
        }

        //Client XML  created
        $client_xml_file_name = $fp_obj->creatingClientXML($upc, $processPatchDir, $albums_details['album_detail']['CdnPath']);

        // Check if the client XML was successfully created.
        if ($client_xml_file_name) {

            $client_xml_details = $orchard_db->checkClientVersion($client_xml_file_name);

            if (!file_exists(CLIENT_SERVER_PATH . $processPatchDir . '/' . $fp_obj->createPathCDN($upc))) {

                $new_path = "/";
                $dir_path = CLIENT_SERVER_PATH . $processPatchDir . '/' . $fp_obj->createPathCDN($upc);
                $dir_path = str_replace('//', '/', $dir_path);

                $dir_path_array = explode('/', $dir_path);

                for ($i = 1; $i < count($dir_path_array) - 1; $i++) {

                    $new_path .= $dir_path_array[$i] . "/";

                    if (!file_exists($new_path)) {
                        mkdir($new_path, 0777, TRUE);
                    }
                }
            }

            $clientPath = $processPatchDir . '/' . $fp_obj->createPathCDN($upc) . $client_xml_file_name;

            echo exec('cp -R ' . LOCAL_SERVER_PATH . $processPatchDir . '/' . $upc . "/$client_xml_file_name  " . CLIENT_SERVER_PATH . "$clientPath", $output, $serverResponse);

            if ($client_xml_details['version_no'] > 0) {

                $client_xml_array['id'] = $client_xml_details['id'];
                $client_xml_array['generate_xml_name'] = $client_xml_file_name;
                $client_xml_array['version_no'] = (int) $client_xml_details['version_no'] + 1;
                $client_xml_array['generated_date'] = date('Y-m-d h:i:s');
                $client_xml_array['insert_update_delete'] = $IUDFlag;
                $client_xml_array['is_uploaded_on_client_server'] = 1;
                $client_xml_array['upc'] = $upc;

                $orchard_db->clientXMLTable($client_xml_array, true);
            } else {

                $client_xml_array['generate_xml_name'] = $client_xml_file_name;
                $client_xml_array['version_no'] = 1;
                $client_xml_array['generated_date'] = date('Y-m-d h:i:s');
                $client_xml_array['insert_update_delete'] = $IUDFlag;
                $client_xml_array['is_uploaded_on_client_server'] = 1;
                $client_xml_array['upc'] = $upc;

                $orchard_db->clientXMLTable($client_xml_array);
            }
        }

        $orchard_db->updateJobStatus($processPatchDir, $self_script_name);

        $orchard_db->closeConnection();
        $freegal_db->closeConnection();
    } else {

        echo serialize($album_detail);
        $patch_log->sendMail(serialize($album_detail), 'Release folder already complete', Logs::MYSQL_LOGS);
        $status = exec($command, $output, $serverResponse);
        if ($serverResponse > 0) {
            $log = new Logs($processPatchDir, $upc);
            $log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
        }
        exit();
    }
} else {
    echo "Patch folder not found at $patch_folder_path";
    $patch_log->sendMail("Patch folder not found at $patch_folder_path", 'Patch Folder not found at Backup Path', Logs::MYSQL_LOGS);
    exit();
}

unset($status);
unset($output);

if (file_exists($self_script_name)) {

	$status = exec(" rm  " . $self_script_name, $output, $serverResponse);

	if ($serverResponse > 0) {

	    $patch_log->sendMail("Hi Rob, \n We have got error while deleting the Album Parser file from $self_script_name "
            . "Error : $serverResponse & error : $status & output : " . serialize($output)
            . ".\n The command was $command", "UPC $upc sanity report", Logs::MYSQL_LOGS);
	}
}

exit("Album Parser finished");
