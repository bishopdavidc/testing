<?php

/**
 * Description of OrchardDatabase
 *
 * @author ratnesh.gupta
 */
set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

include_once('library/config.php');
include_once('library/FileProcess.php');

class OrchardDatabase {

    var $patch_name;
    var $log;
    var $orchard_db;

    public function __construct($patch_name, $album = null) {
        $this->orchard_db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die("Could not connect to theorchard Database");
        mysqli_set_charset($this->orchard_db, 'UTF8');

        $this->patch_name = $patch_name;
        $this->log = new Logs($this->patch_name, $album);
    }

    function executeQuery($query, $db_flag = false) {
        $resource = false;
        $query_to_run = $query;

        $count = 0;
        if ($db_flag && preg_match('/^select/i', $query_to_run)) {
            $this->log->writeMysqLogs("DB5 Query : " . $query);
            do {
                $orchard_db5 = mysqli_connect(SLAVE_DB, DB_USER, DB_PASSWORD, DB1) or die("Could not connect to theorchard Database");
                mysqli_set_charset($orchard_db5, 'UTF8');
                $resource = mysqli_query($orchard_db5, $query_to_run);
                if (mysqli_errno($orchard_db5) == 11) {
                    $this->log->writeDebugLog("error occured with this query: ".$query);
                    exit("Resource not aviable issue found");
                }
                mysqli_close($orchard_db5);
            } while ($resource == false);
        } else {
            $this->log->writeMysqLogs($query);
            do {
                $count++;
                if (mysqli_ping($this->orchard_db)) {
                    $resource = mysqli_query($this->orchard_db, $query_to_run);
                    if (mysqli_errno($this->orchard_db) == 11) {
                        $this->log->writeDebugLog("error occured with this query: ".$query);
                        exit("Resource not aviable issue found");
                    }
                } else {
                    $this->log->writeError(Logs::ERR_MYSQL, $this->patch_name, null, "MYSQL server has gone away." . " \n $query_to_run" . mysqli_error($this->orchard_db));
                    $this->orchard_db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die("Could not connect to theorchard Database");
                    mysqli_set_charset($this->orchard_db, 'UTF8');
                    $resource = mysqli_query($this->orchard_db, $query_to_run);
                    if (mysqli_errno($orchard_db5) == 11) {
                        $this->log->writeDebugLog("error occured with this query: ".$query);
                        exit("Resource not aviable issue found");
                    }
                }

                if ($count == 200) {
                    $this->log->writeError(Logs::MYSQL_LOGS, $this->patch_name, null, "MySQL server has gone away. Please check. $count" . " \n $query_to_run" . mysqli_error($this->orchard_db));
                    $count = 0;
                }
            } while ($resource == false);

            $this->closeConnection();
        }

        return $resource;
    }

    /**
     * This method is use to close all connection 
     * for the object.
     */
    function closeConnection() {
        mysqli_close($this->orchard_db);
    }

    /**
     * This method is used to check the details of 
     * the orcahrd script 
     * @param string $patch_no
     * @param string $script_name
     * @return int
     */
    function checkScriptNo($patch_no, $script_name) {
        $query = "SELECT script_no FROM running_scripts WHERE foldername='$patch_no' and script_name='$script_name'";
        $script_no = mysqli_fetch_row($this->executeQuery($query, true));
        return ($script_no[0] != null && $script_no[0] > 0 ) ? $script_no[0] : 0;
    }

    /**
     * This method is used to get the LAst used ProdID for the
     * Orchard Script from the Running script table
     * @param string $batch_no
     * @param string $script_name
     * @return int
     */
    function getStartProdIDAlbumParser($batch_no, $script_name) {
        $query = "SELECT start_prodid FROM album_parser WHERE batch_name='$batch_no' AND album_parser_name='$script_name'";
        $script_no = mysqli_fetch_row($this->executeQuery($query, true));
        return ($script_no[0] != null && $script_no[0] > 0 ) ? $script_no[0] : 0;
    }

    /**
     * This method returns the album parser name which are
     * started and yet not complete.
     * 
     * @return resource
     */
    function getJobStartedParser() {
        $query = "SELECT album_parser_name  FROM album_parser WHERE job_completed=0 AND job_started=1";
        return $this->executeQuery($query, true);
    }

    /**
     * This method returns the total no of parser running currently
     * @return int
     */
    function checkTotalAlbumParserRunning() {
        $query = "SELECT count(*) FROM album_parser WHERE job_completed=0 AND job_started=1";
        $script_no = mysqli_fetch_row($this->executeQuery($query, true));
        return $script_no[0];
    }

    /**
     * This method returns the count of the album parser running
     * for the current batch.
     * 
     * @param string $batch_name
     * @return int
     */
    function checkAlbumProcessByBatch($batch_name) {
        $query = "SELECT count(*) from album_parser where batch_name='$batch_name'";
        $count = mysqli_fetch_row($this->executeQuery($query, true));
        return ($count[0] != null && $count[0] > 0 ) ? $count[0] : 0;
    }

    function getAlbumParserByBatch($batch_name, $script_name = null) {
        $script_query = ($script_name != null) ? " and job_started=1 and album_parser_name='$script_name' " : "and job_started=0";
        $query = "SELECT * FROM album_parser WHERE batch_name='$batch_name' AND job_completed=0  $script_query ";
        return $this->executeQuery($query, true);
    }

    function getAlbumJobByBatch($batch_name, $upc) {
        $query = "SELECT * FROM album_parser WHERE batch_name='$batch_name' AND job_completed=1  and upc=$upc ";
        return $this->executeQuery($query, true);
    }

    function getAlbumParserByBatchTest($batch_name, $script_name = null) {
        $script_query = ($script_name != null) ? " and job_started=1 and album_parser_name='$script_name' " : "and job_started=0";
        echo $query = "SELECT * FROM album_parser WHERE batch_name='$batch_name' AND job_completed=1  $script_query ";
        return $this->executeQuery($query, true);
    }

    function getBatchJobCompleted($batch_name) {
        $query = "SELECT * from album_parser where batch_name='$batch_name' and job_completed=0 and job_started=1";
        $script_no = mysqli_fetch_row($this->executeQuery($query, true));
        return $script_no[0];
    }

    function updateRunningScript($patch_no) {
        $query = "update running_scripts set job_completed=1 where foldername='$patch_no'";
        $this->executeQuery($query);

        $parser_report = $this->orchardParserReport($patch_no);
        $mail_message = "Please see the import results below for Batch no : $patch_no

                        Manifest Filename:  manifest.txt
                        Number of Xml files processed for Insert(CompleteAlbum) : " . $parser_report['total_insert'] . " Albums
                        Number of Xml files processed for Update(MetadataOnlyUpdate) : " . $parser_report['total_updated'] . " Albums
                        Number of Xml files processed for Delete(Takedown) : " . $parser_report['total_down'] . " Albums

                        Thanks";
        
        if ( !empty( $parser_report ) && ( $parser_report['total_insert'] > 0 || $parser_report['total_updated'] > 0 || $parser_report['total_down'] > 0 ) ) {
        	$this->log->writeError(Logs::PATCH_REPORT, $patch_no, null, $mail_message);
        } else {
        	$this->log->writeError(Logs::ERR_CUSTOM, $patch_no, null, $mail_message);
        }
    }

    /**
     * This method is used to update the job_started status
     * for the current album parser which has been started.
     * 
     * @param string $patch_no
     * @param string $album_parser_name
     */
    function updateAlbumRunningScript($patch_no, $album_parser_name) {
        $query = "update album_parser set job_started=1 where batch_name='$patch_no' and album_parser_name='$album_parser_name' ";
        $this->executeQuery($query);
    }

    function updateJobStatus($patch_no, $album_parser_name) {
        $query = "update album_parser set job_completed=1 where batch_name='$patch_no' and album_parser_name='$album_parser_name' ";
        $this->executeQuery($query);
    }

    function checkUPCByParser($batch_no, $upc) {
        $query = "select parser_id from album_parser where batch_name='$batch_no' and upc=$upc ";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    function getProdIDStartted($script_no) {
        $query = "SELECT prod_id_started_from FROM running_scripts WHERE script_no='$script_no' ";
        $script_no = mysqli_fetch_row($this->executeQuery($query, true));
        return ($script_no[0] != null && $script_no[0] > 0 ) ? $script_no[0] : 0;
    }

    function getRunningScript() {
        $query = "SELECT * FROM running_scripts ORDER BY script_no DESC LIMIT 0,1";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    function checkRunningScriptCount() {
        $query = "SELECT count(*) FROM running_scripts WHERE job_completed is null order by script_no desc";
        $last_used_prodid = mysqli_fetch_row($this->executeQuery($query, true));
        return ($last_used_prodid[0] != null && $last_used_prodid[0] > 0 ) ? $last_used_prodid[0] : 0;
    }

    function getStartProdID($foldername, $script_name = null) {
        $script = ($script_name == null) ? '' : "and script_name='$script_name'";
        $query = "SELECT last_used_prodid FROM running_scripts WHERE foldername='$foldername' $script";
        $last_used_prodid = mysqli_fetch_row($this->executeQuery($query, true));
        return ($last_used_prodid[0] != null && $last_used_prodid[0] > 0 ) ? $last_used_prodid[0] : 0;
    }

    function getLastUsedProdID() {
        $query = "SELECT max(end_prodid) FROM theorchard.album_parser  order by parser_id desc limit 0,1";
        $last_used_prodid = mysqli_fetch_row($this->executeQuery($query, true));
        return ($last_used_prodid[0] != null && $last_used_prodid[0] > 0 ) ? $last_used_prodid[0] : 0;
    }

    function getLastScriptNo() {
        $query = "SELECT  script_no FROM running_scripts order by script_no desc limit 0,1 ";
        $last_script_no = mysqli_fetch_row($this->executeQuery($query, true));
        return ($last_script_no[0] != null && $last_script_no[0] > 0 ) ? $last_script_no[0] : 0;
    }

    function insertCurrentScriptDetails($current_script_no, $prod_id_started_from, $last_used_prodid, $patch_folder = null, $self_script_name = null, $update = false) {
        $query = "";
        if (!$update) {
            $query = "INSERT INTO running_scripts (script_no,   last_used_prodid,   foldername,   prod_id_started_from,   script_name) 
                 VALUES('$current_script_no','$last_used_prodid','$patch_folder','$prod_id_started_from', '$self_script_name')";
        } else {
            $query = "UPDATE running_scripts SET last_used_prodid='$prod_id_started_from' WHERE script_no='$current_script_no' ";
        }

        $this->executeQuery($query);
        return false;
    }

    /**
     * This method is used to update the last_used_prodid
     * for the batch parser running.
     * 
     * @param int $last_used_prodid
     * @param string $foldername
     * @param string $script_name
     */
    function updateLastUsedProdId($last_used_prodid, $foldername, $script_name = null) {
        $script_condition = ( $script_name != null ) ? " and script_name='$script_name'" : '';
        $query = "UPDATE running_scripts SET last_used_prodid='$last_used_prodid' WHERE foldername='$foldername'  $script_condition";
        $this->executeQuery($query);
    }

    function updateLastProdID($last_used_prodid, $script_no) {
        $query = "UPDATE running_scripts SET last_used_prodid='$last_used_prodid' WHERE script_no='$script_no' ";
        $this->executeQuery($query);
    }

    /**
     * This method is used the Album details from the Orchard
     * Album table
     * @param int $album_id
     * @return array
     */
    function getAlbum($upc) {
        $query = "SELECT album_id,  product_name,  ioda_release_id , is_ioda FROM album where upc = $upc ";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    /**
     * This method is used to insert the Album detials 
     * in 'album' table
     * 
     * @param array $album_array
     * @param string $where_condition
     */
    function orchardAlbumInsert($album_array) {
        //insert the data else
        $insert_album['primary_release_date'] = $album_array['primary_release_date'];
        $insert_album['duration'] = $album_array['duration'];
        $insert_album['language'] = $album_array['language'];

        $insert_album['product_name'] = $album_array['product_name'];
        $insert_album['primary_sales_start_date'] = $album_array['primary_sales_start_date'];
        $insert_album['cline'] = $album_array['cline'];

        $insert_album['label'] = $album_array['label'];
        $insert_album['primary_genre'] = $album_array['primary_genre'];
        $insert_album['volume_count'] = $album_array['volume_count'];
        $insert_album['track_count'] = $album_array['track_count'];

        $insert_album['date_created'] = $album_array['date_created'];
        $insert_album['delivery_type'] = $album_array['delivery_type'];

        $insert_album['created_at'] = date('Y-m-d h:i:s');
        $insert_album['updated_at'] = date('Y-m-d h:i:s');
        $insert_album['patch_name'] = $album_array['patch_name'];
        $insert_album['upc'] = $album_array['upc'];
        $this->insertTableQuery($insert_album, 'album');
    }

    function orchardAlbumUpdate($album_array) {
        $insert_album['primary_release_date'] = $album_array['primary_release_date'];
        $insert_album['duration'] = $album_array['duration'];
        $insert_album['language'] = $album_array['language'];

        $insert_album['product_name'] = $album_array['product_name'];
        $insert_album['primary_sales_start_date'] = $album_array['primary_sales_start_date'];
        $insert_album['cline'] = $album_array['cline'];

        $insert_album['label'] = $album_array['label'];
        $insert_album['primary_genre'] = $album_array['primary_genre'];
        $insert_album['volume_count'] = $album_array['volume_count'];
        $insert_album['track_count'] = $album_array['track_count'];

        $insert_album['date_created'] = $album_array['date_created'];
        $insert_album['delivery_type'] = $album_array['delivery_type'];

        $insert_album['updated_at'] = date('Y-m-d h:i:s');
        $insert_album['patch_name'] = $album_array['patch_name'];

        $where_condition = " WHERE upc=" . trim($album_array['upc']) . "";
        $this->updateTableQuery($insert_album, 'album', $where_condition);
    }

    /**
     * This method return the Artist text for album from the Artist table
     * @param int $upc
     * @param int $artist_id
     * @return array
     */
    function checkArtist($upc, $artist_id) {
        $query = "SELECT artist_display_text FROM artist WHERE upc=" . $upc . " and artist_id=" . $artist_id;
        return mysqli_fetch_row($this->executeQuery($query));
    }

    /**
     * This method is used to check whether the track is 
     * present in the countries table
     * @param int $isrc
     * @param int $upc
     * @return array
     */
    function checkCountryForTrack($isrc, $upc) {
        $query = "SELECT * FROM countries WHERE isrc='$isrc' and upc=$upc";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    /**
     * This method is used to check the Track details 
     * in the tracks table 
     * 
     * @param int $isrc
     * @param int $upc
     * @return array
     */
    function checkTrack($isrc, $upc) {
        $query = "SELECT * FROM tracks WHERE isrc='$isrc' and upc=$upc";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    function checkTrackFile($isrc, $upc = null) {
        $sub_text = ($upc != null && $upc > 0) ? " and upc=$upc" : "";
        $query = "SELECT * FROM files WHERE isrc='$isrc' $sub_text";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    /**
     * This method is used to the track count from the tracks
     * table for the UPC
     * @param int $upc
     * @return array
     */
    function getTrackCountFromTracks($upc) {
        $query = "SELECT count(*) FROM tracks where upc=" . trim($upc) . "";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    /**
     * This method is used to get tracks count 
     * from the Tracks table and the Album table with
     * Join from Album table
     * 
     * @param int $upc
     * @return array
     */
    function getTrackCountFromAlbumAndTracks($upc) {
        $query = "SELECT if(a.track_count is null , 0 ,a.track_count) as album_track_count ,    count(*) as tracks_track_count from tracks AS t 
                JOIN album AS a ON t.upc=a.upc   where a.upc =$upc ";
        return mysqli_fetch_assoc($this->executeQuery($query));
    }

    /**
     * This method get the detials fo the Track with there ISRC and the Album ID
     * 
     * @param int $isrc
     * @param int $upc
     * @return array
     */
    function getTracksDetail($isrc, $upc) {
        $query = "SELECT * FROM tracks where ISRC='$isrc' and upc=$upc";
        return mysqli_fetch_assoc($this->executeQuery($query));
    }

    /**
     * This method return the patch no 
     * for the album
     * @param int $upc
     * @return array
     */
    function getPatchName($upc) {
        $query = "select patch_name from album where upc=$upc";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    /**
     * 
     * @param int $upc
     * @param string $isrc
     * @param string $country_code
     * @return array
     */
    function checkCountryForFreegal($upc, $isrc, $country_code) {
        $query = "SELECT country_id FROM countries WHERE upc=$upc and isrc='$isrc' and track_restricted_to like '%$country_code%'";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    function getSubGenre($upc) {
        $query = "SELECT sub_genre FROM sub_genre WHERE upc=" . $upc . "";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    function updateTableQuery($details_array, $table_name, $whereCondition) {
        $setValues = '';
        foreach ($details_array as $key => $value) {
            $setValues .= $key . "='" . addslashes(trim($value)) . "',";
        }

        $setValues = substr($setValues, 0, -1);
        $query = "UPDATE $table_name set $setValues $whereCondition" . "\n";
        $this->executeQuery($query);
    }

    function updateTableQueryTest($details_array, $table_name, $whereCondition) {
        $setValues = '';
        foreach ($details_array as $key => $value) {
            $setValues .= $key . "='" . addslashes(trim($value)) . "',";
        }

        $setValues = substr($setValues, 0, -1);
        echo $query = "UPDATE $table_name set $setValues $whereCondition" . "\n";
        //$this->executeQuery($query);
    }

    /**
     * This method is used to Create Insert Query for the 
     * table and executes the query.
     * 
     * @param array $track_array
     * @param string $table
     */
    function insertTableQuery($track_array, $table) {
        foreach ($track_array as $key => $value) {
            $track_array[$key] = addslashes(trim($value));
        }

        $fields = implode(',', array_keys($track_array));
        $values = implode("','", array_values($track_array));
        $query = "INSERT INTO " . $table . " ($fields) VALUES('" . $values . "')";
        $this->executeQuery($query);
    }

    /**
     * This method is used to delete the record from the table
     * @param type $table_name
     * @param type $whereCondition
     */
    function deleteTableQuery($table_name, $whereCondition) {
        $query = "Delete from $table_name $whereCondition" . "\n";
        $this->executeQuery($query);
    }

    /**
     * This Method is use to update / insert new record in the
     * Image table for the Album.
     * 
     * @param array $image_array
     * @param boolean $update
     * @param string $where_condition
     */
    function orchardImageInsert($image_array) {
        $insert_image['upc'] = $image_array['upc'];
        $insert_image['format'] = $image_array['format'];
        $insert_image['file_name'] = $image_array['file_name'];
        $insert_image['width'] = $image_array['width'];
        $insert_image['height'] = $image_array['height'];
        $insert_image['cdn_filepath'] = CDNPATH . '/' . $this->createPathCDN($image_array['upc']);
        $insert_image['cdn_filename'] = $image_array['file_name'];

        $this->insertTableQuery($insert_image, 'images');
    }

    function orchardImageUpdate($image_array) {
        $insert_image['format'] = $image_array['format'];
        $insert_image['file_name'] = $image_array['file_name'];
        $insert_image['width'] = isset($image_array['width']) ? $image_array['width'] : '250';
        $insert_image['height'] = isset($image_array['height']) ? $image_array['height'] : '250';
        $insert_image['cdn_filepath'] = $image_array['cdn_filepath'];

        $insert_image['cdn_filename'] = $image_array['file_name'];
        $where_condition = " WHERE upc=" . $image_array['upc'] . " and image_id='" . $image_array['image_id'] . "'";
        $this->updateTableQuery($insert_image, 'images', $where_condition);
    }

    function getImageDetails($upc) {
        $query = "SELECT * FROM images where upc =$upc ";
        return mysqli_fetch_row($this->executeQuery($query));
    }

    /**
     * This method check is Image for the album is inserted in the
     * image table of the Orchard db.
     * 
     * @param string $upc
     * @return array
     */
    function checkAlbumImage($upc) {
        $query = "select * from images where upc=$upc";
        return mysqli_fetch_assoc($this->executeQuery($query));
    }

    /**
     * This method is used to insert / update the Release Artist table
     * for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardReleaseArtistTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ProductArtist']['ReleaseArtists']['ReleaseArtist']) {
            if ($update) {
                $query = "DELETE FROM release_artist WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc='" . $album_id . "'";
                $this->executeQuery($query);
            }


            if (is_integer(key($xmlArray['ProductArtist']['ReleaseArtists']['ReleaseArtist']))) {
                foreach ($xmlArray['ProductArtist']['ReleaseArtists']['ReleaseArtist'] as $releaseArtistName) {
                    $insert_release_artist['release_artist_text'] = $releaseArtistName;
                    if ($xmlArray['ProductArtist']['ArtistID']) {
                        $insert_release_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    }
                    $this->insertTableQuery($insert_release_artist, 'release_artist');
                }
            } else {
                $insert_release_artist['release_artist_text'] = $xmlArray['ProductArtist']['ReleaseArtists']['ReleaseArtist'];
                if ($xmlArray['ProductArtist']['ArtistID']) {
                    $insert_release_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                }
                $this->insertTableQuery($insert_release_artist, 'release_artist');
            }
        }
    }

    /**
     * This method  is used to insert / update the Sub genre table
     * for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardSubGenreTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['Genre']['SubGenres']['SubGenre']) {
            if ($update) {
                $query = "DELETE FROM sub_genre WHERE upc='$album_id' ";
                $this->executeQuery($query);
            }
            if (is_integer(key($xmlArray['Genre']['SubGenres']['SubGenre']))) {
                foreach ($xmlArray['Genre']['SubGenres']['SubGenre'] as $subGenreName) {
                    $insert_sub_genre['sub_genre_id'] = '';
                    $insert_sub_genre['upc'] = $album_id;
                    $insert_sub_genre['sub_genre'] = $subGenreName;
                    $this->insertTableQuery($insert_sub_genre, 'sub_genre');
                }
            } else {
                $insert_sub_genre['sub_genre_id'] = '';
                $insert_sub_genre['upc'] = $album_id;
                $insert_sub_genre['sub_genre'] = $xmlArray['Genre']['SubGenres']['SubGenre'];
                $this->insertTableQuery($insert_sub_genre, 'sub_genre');
            }
        }
    }

    /**
     * This method is to insert / update the Similar Artist table
     * for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardSimilarArtistTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');

        if ($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist']) {
            if ($update) {
                $query = "DELETE FROM similar_artists WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "'";

                $this->executeQuery($query);
            }

            if (is_integer(key($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist']))) {
                foreach ($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist'] as $similarArtistName) {
                    $insert_similar_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_similar_artist['upc'] = $album_id;
                    $insert_similar_artist['similar_artist_name'] = $similarArtistName;
                    $this->insertTableQuery($insert_similar_artist, 'similar_artists');
                }
            } else {
                $insert_similar_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_similar_artist['upc'] = $album_id;
                $insert_similar_artist['similar_artist_name'] = $xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist'];
                $this->insertTableQuery($insert_similar_artist, 'similar_artists');
            }
        }
    }

    /**
     * This method is to insert / update the Album Release Date
     * table for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardAlbumReleaseDateTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ReleaseDates']['ReleaseDate']) {
            if ($update) {

                $query = "DELETE FROM album_release_dates WHERE upc=$album_id";

                $this->executeQuery($query);
            }
            if (is_integer(key($xmlArray['ReleaseDates']['ReleaseDate']))) {
                foreach ($xmlArray['ReleaseDates']['ReleaseDate'] as $releaseDate) {
                    $release_date['upc'] = $album_id;
                    $release_date['release_date'] = $releaseDate;
                    $this->insertTableQuery($release_date, 'album_release_dates');
                }
            } else {
                $release_date['upc'] = $album_id;
                $release_date['release_date'] = $xmlArray['ReleaseDates']['ReleaseDate'];
                $this->insertTableQuery($release_date, 'album_release_dates');
            }
        }
    }

    /**
     * This method is to insert / update the Album Sales Date
     * table for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardAlbumSaleStartDate($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');

        if ($xmlArray['SaleStartDates']['SaleStartDate']) {
            if ($update) {
                $query = "DELETE FROM album_sales_start_dates WHERE upc=$album_id";
                $this->executeQuery($query);
            }
            if (is_integer(key($xmlArray['SaleStartDates']['SaleStartDate']))) {
                foreach ($xmlArray['SaleStartDates']['SaleStartDate'] as $saleStartDates) {
                    $sale_start_date['upc'] = $album_id;
                    $sale_start_date['sales_start_date'] = $saleStartDates;
                    $this->insertTableQuery($album_id, 'album_sales_start_dates');
                }
            } else {
                $sale_start_date['upc'] = $album_id;
                $sale_start_date['sales_start_date'] = $xmlArray['SaleStartDates']['SaleStartDate'];
                $this->insertTableQuery($album_id, 'album_sales_start_dates');
            }
        }
    }

    /**
     * This method is to insert / update the Album Artist Influences
     * table for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardArtistInflunaceTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');

        if ($xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence']) {
            if ($update) {
                $query = "DELETE FROM artist_influences WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "'";
                $this->executeQuery($query);
            }
            if (is_integer(key($xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence']))) {
                foreach ($xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence'] as $artistInfluenceName) {
                    $insert_artist_influences['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_artist_influences['upc'] = $album_id;
                    $insert_artist_influences['artist_influences'] = $artistInfluenceName;
                    $this->insertTableQuery($insert_artist_influences, 'artist_influences');
                }
            } else {
                $insert_artist_influences['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_artist_influences['upc'] = $album_id;
                $insert_artist_influences['artist_influences'] = $xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence'];
                $this->insertTableQuery($insert_artist_influences, 'artist_influences');
            }
        }
    }

    /**
     * This method is to insert / update the Album Artist Contempories
     * table for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardArtistContemporariesTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');

        if ($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary']) {
            if ($update) {
                $query = "DELETE FROM artist_contemporaries WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "'";
                $this->executeQuery($query);
            }
            if (is_integer(key($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary']))) {
                foreach ($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary'] as $artistContemporary) {
                    $insert_artist_contemporaries['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_artist_contemporaries['upc'] = $album_id;
                    $insert_artist_contemporaries['artist_contemporaries'] = $artistContemporary;
                    $this->insertTableQuery($insert_artist_contemporaries, 'artist_contemporaries');
                }
            } else {
                $insert_artist_contemporaries['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_artist_contemporaries['upc'] = $album_id;
                $insert_artist_contemporaries['artist_contemporaries'] = $xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary'];
                $this->insertTableQuery($insert_artist_contemporaries, 'artist_contemporaries');
            }
        }
    }

    /**
     * This method is to insert / update the Album Artist Followers
     * table for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardArtistFollowersTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');

        if ($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower']) {
            if ($update) {
                $query = "DELETE FROM artist_followers WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "'";
                $this->executeQuery($query);
            }
            if (is_integer(key($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower']))) {
                foreach ($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower'] as $artistFollower) {
                    $insert_artist_followers['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_artist_followers['upc'] = $album_id;
                    $insert_artist_followers['artist_followers'] = $artistFollower;
                    $this->insertTableQuery($insert_artist_followers, 'artist_followers');
                }
            } else {
                $insert_artist_followers['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_artist_followers['upc'] = $album_id;
                $insert_artist_followers['artist_followers'] = $xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower'];
                $this->insertTableQuery($insert_artist_followers, 'artist_followers');
            }
        }
    }

    /**
     * This method is to insert / update the Album Artist 
     * table for the Album.
     * 
     * @param int $album_id
     * @param boolean $update
     * @param int $patch_name
     */
    function orchardArtistTable($album_id, $update = false, $patch_name = null) {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');

        $fp_obj = new FileProcess();
        if ($update) {
            $insert_product_artist = $fp_obj->checkArtistInfo($xmlArray['ProductArtist']);
            $where_condition = " WHERE upc=" . $album_id . " and artist_id=" . $insert_product_artist['artist_id'];
            $this->updateTableQuery($insert_product_artist, 'artist', $where_condition);
        } else {
            $insert_product_artist = $fp_obj->checkArtistInfo($xmlArray['ProductArtist']);
            $insert_product_artist['upc'] = $album_id;
            $this->insertTableQuery($insert_product_artist, 'artist');
        }
    }

    /**
     * This method is used to insert /update the
     * Countries table for the Track(s)
     * 
     * @param array $track_array    Array of the Tracks
     * @param int $upc              Album id / UPC from the XML
     * @param int $ioda_track_id    ProdID of the Album from the Freegal db
     */
    function orchardCountriesUpdate($track_array, $upc) {
        //update
        $insert_country['ioda_track_id'] = $track_array['ioda_track_id'];
        $insert_country['isrc'] = $track_array['isrc'];
        $insert_country['track_restricted_to'] = $track_array['track_restricted_to'];
        $insert_country['track_restricted_from'] = $track_array['track_restricted_from'];
        $where_condition = "WHERE upc=" . $upc . " and isrc='" . $track_array['isrc'] . "' ";
        $this->updateTableQuery($insert_country, 'countries', $where_condition);
    }

    function orchardCountriesInsert($track_array, $upc) {
        $check_in_countries = $this->orchardCountriesCheckSong($track_array['isrc'], $upc);
        if (empty($check_in_countries)) {
            $insert_country['ioda_track_id'] = $track_array['ioda_track_id'];
            $insert_country['isrc'] = $track_array['isrc'];
            $insert_country['track_restricted_to'] = $track_array['track_restricted_to'];
            $insert_country['track_restricted_from'] = $track_array['track_restricted_from'];
            $insert_country['upc'] = $upc;
            $this->insertTableQuery($insert_country, 'countries');
        } else {
            $this->orchardCountriesUpdate($track_array, $upc);
        }
    }

    function orchardCountriesCheckSong($isrc, $upc) {
        $query = "select * from theorchard.countries where isrc='$isrc' and upc=$upc ";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    /**
     * This method is used to insert /update the tracks details 
     * in the Tracks table.
     * 
     * @param array     $track_array            Track details array for making insert array
     * @param int       $upc                    Album id 
     * @param array     $album_details          Array of the Album details 
     * @param int       $custom_prodid          Custom ProdID which is used in Freegal Songs table for the Songs
     * 
     */
    function orchardTracksUpdate($track_array, $prod_id = null) {
        $insert_track['pline'] = $track_array['pline'];
        $insert_track['rights_granted'] = $track_array['rights_granted'];
        $insert_track['title'] = $track_array['title'];
        $insert_track['track_time'] = $track_array['track_time'];
        $insert_track['explicit_lyrics'] = $track_array['explicit_lyrics'];
        $insert_track['sequence'] = $track_array['sequence'];
        $insert_track['volume'] = $track_array['volume_count'];
        $insert_track['cdn_filepath'] = $track_array['CdnPath'];
        
        if (isset($track_array['new_track_name']) && $track_array['new_track_name'] != '') {
            $insert_track['cdn_filename'] = $track_array['new_track_name'];
        }

        if (isset($track_array['new_clip_name']) && $track_array['new_clip_name'] != '') {
            $insert_track['cdn_samplename'] = $track_array['new_clip_name'];
        }

        // $insert_track['cdn_filename'] = $track_array['new_track_name'];
        // $insert_track['cdn_samplename'] = $track_array['new_clip_name'];

        $insert_track['ioda_track_id'] = $prod_id;

        $where_condition = " WHERE isrc='" . $track_array['isrc'] . "' and upc=" . $track_array['upc'] . "";
        $this->updateTableQuery($insert_track, 'tracks', $where_condition);
    }

    function orchardTracksInsert($track_array, $prod_id = null) {
        $insert_track['track_id'] = '';
        $insert_track['isrc'] = $track_array['isrc'];
        $insert_track['upc'] = $track_array['upc'];
        $insert_track['pline'] = $track_array['pline'];
        $insert_track['rights_granted'] = $track_array['rights_granted'];
        $insert_track['title'] = $track_array['title'];
        $insert_track['track_time'] = $track_array['track_time'];
        $insert_track['explicit_lyrics'] = $track_array['explicit_lyrics'];
        $insert_track['sequence'] = $track_array['sequence'];
        $insert_track['volume'] = $track_array['volume_count'];
        $insert_track['cdn_filepath'] = $track_array['CdnPath'];

        if (isset($track_array['new_track_name']) && $track_array['new_track_name'] != '') {
	    $insert_track['cdn_filename'] = $track_array['new_track_name'];
        }

        if (isset($track_array['new_clip_name']) && $track_array['new_clip_name'] != '') {
	    $insert_track['cdn_samplename'] = $track_array['new_clip_name'];
       	}

        // $insert_track['cdn_filename'] = $track_array['new_track_name'];
        // $insert_track['cdn_samplename'] = $track_array['new_clip_name'];

        $insert_track['ioda_track_id'] = $prod_id;

        $this->insertTableQuery($insert_track, 'tracks');
    }

   function createPathCDN( $album_id ) {
    
    	$album_id  = str_split($album_id, 3);
    
    	$dirpath = implode('/', $album_id);
    	$dirpath = $dirpath . '/';
    
    	return $dirpath;
    }

    function checkVersionOfAlbum($upc, $xml_for) {
        $query = "SELECT version_no,insert_update_delete FROM new_generated_xml WHERE upc=" . $upc . " and insert_update_delete='$xml_for' order by version_no desc";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    function checkClientVersion($client_xml_name) {
        $query = "SELECT version_no, id FROM new_generated_xml where generate_xml_name='$client_xml_name' and is_uploaded_on_client_server is null";
        return mysqli_fetch_assoc($this->executeQuery($query));
    }

    function clientXMLTable($xml_array, $update = false) {
        if ($update) {
            $whereCondition = " where id='" . $xml_array['id'] . "' ";
            $this->updateTableQuery($xml_array, 'new_generated_xml', $whereCondition);
        } else {
            $this->insertTableQuery($xml_array, 'new_generated_xml');
        }
    }

    function orchardFilesTable($track_array, $upc, $prod_id, $update = false) {
        $insert_track = array();
        if ($update) {
            $insert_track['file_name'] = $track_array['original_clip_name'];
            $insert_track['file_format'] = 'mp3';
            $insert_track['isrc'] = $track_array['isrc'];
            $insert_track['cdn_filepath'] = 'theorchard/' . $this->createPathCDN($upc);
            $insert_track['cdn_filename'] = $track_array['new_clip_name'];
            $whereCondition = " Where isrc=" . $track_array['isrc'];
            $this->updateTableQuery($insert_track, 'files', $whereCondition);

            $insert_track['file_name'] = $track_array['original_track_name'];
            $insert_track['file_format'] = 'mp3';
            $insert_track['fk_ioda_track_id'] = $prod_id;
            $insert_track['isrc'] = $track_array['isrc'];
            $insert_track['cdn_filepath'] = 'theorchard/' . $this->createPathCDN($upc);
            $insert_track['cdn_filename'] = $track_array['new_track_name'];
            $this->insertTableQuery($insert_track, 'files');
        } else {
            $insert_track['file_name'] = $track_array['original_clip_name'];
            $insert_track['file_format'] = 'mp3';
            $insert_track['fk_ioda_track_id'] = $prod_id;
            $insert_track['isrc'] = $track_array['isrc'];
            $insert_track['cdn_filepath'] = 'theorchard/' . $this->createPathCDN($upc);
            $insert_track['cdn_filename'] = $track_array['new_clip_name'];
            $this->insertTableQuery($insert_track, 'files');

            $insert_track['file_name'] = $track_array['original_track_name'];
            $insert_track['file_format'] = 'mp3';
            $insert_track['fk_ioda_track_id'] = $prod_id;
            $insert_track['isrc'] = $track_array['isrc'];
            $insert_track['cdn_filepath'] = 'theorchard/' . $this->createPathCDN($upc);
            $insert_track['cdn_filename'] = $track_array['new_track_name'];
            $this->insertTableQuery($insert_track, 'files');
        }
    }

    /**
     * This method use to udpate  the status of redeliverd album 
     * update the status of the UPC if it has been redelivered from
     * the Orchard.
     * 
     * @param int $upc
     */
    function orchardRedeliverTable( $upc, $redeliver_flag ) {
        $is_found = mysqli_fetch_row($this->executeQuery("SELECT id FROM album_redeliver WHERE upc = $upc AND status = 1"));
        if (!empty($is_found)) {
        	
        	if ( $redeliver_flag ==  true ) {
        		$query = "Update album_redeliver SET status = 0 , comment = 'received only meta data' , modified='" . date('Y-m-d h:i:s') . "' WHERE upc = $upc AND id = '" . $is_found[0] . "'";
        		$this->executeQuery($query);
        		return false;
        	} else {
        		$query = "Update album_redeliver SET status = 2 , modified='" . date('Y-m-d h:i:s') . "' WHERE upc = $upc AND id = '" . $is_found[0] . "'";
        		$this->executeQuery($query);
        		return true;
        	}
        }  else {
        	return true;
        }
    }

    /**
     * This method is used to insert the detials of 
     * UPC which are to be redelivered for the patch.
     * it insert the details in the table.
     * 
     * @param string $batch_no
     * @param int $upc
     * @param string $remark
     */
    function orchardRedeliverInsert($batch_no, $upc, $remark) {
        $insert_redeliver['batch_no'] = $batch_no;
        $insert_redeliver['upc'] = $upc;
        $insert_redeliver['remarks'] = 'Error : ' . $remark;
        $insert_redeliver['created'] = date('Y-m-d h:i:s');
        $insert_redeliver['modified'] = date('Y-m-d h:i:s');

        $this->insertTableQuery($insert_redeliver, 'album_redeliver');
    }

    function orchardParserReport($batch_no) {
        $query = "select 
                    sum(if( type_flag='I',1,0)) as total_insert,
                    sum(if( type_flag='U',1,0)) as total_updated,
                    sum(if( type_flag='D',1,0)) as total_down  
                 from album_parser where batch_name='$batch_no'";

        return mysqli_fetch_assoc($this->executeQuery($query));
    }

    /**
     * This method is everytime when album parser complete 
     * one task like back up of data , CDN upload
     * @param int $parser_id    Parser id for Job 
     * @param int $status       Current Job Status
     */
    function updateParserJobStatus($parser_id, $status) {
        $whereCondition = " where parser_id=$parser_id";
        $parser_job_status['job_status'] = $status;
        $this->updateTableQueryTest($parser_job_status, 'album_parser', $whereCondition);
    }

    function getParticipantsOrchardDB($isrc, $upc) {
        $query = "select group_concat(distinct name) as artist from theorchard.participants where isrc = '$isrc' and upc = $upc ";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    function updateReleaseJobStatus($status, $parser_id) {
        $query = "update album_parser set job_status=$status where parser_id=$parser_id ";
        $this->executeQuery($query);
    }

    function setJobStatusComplete($batch, $upc) {
        $query = "update album_parser set job_completed=1 where batch_name=$batch and upc=$upc";
        $this->executeQuery($query);
    }

    function getReleaseArtwork($upc) {
        $query = "SELECT cdn_filename , cdn_filepath FROM theorchard.images WHERE cdn_filename like '%.jpg' and  upc=$upc";
        return mysqli_fetch_object($this->executeQuery($query));
    }

    function orchardSongCountriesCheck($isrc, $upc) {
        $query = "select track_restricted_to from theorchard.countries where isrc='$isrc' and upc=$upc ";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }
}
