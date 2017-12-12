<?php

/**
 * Description of FreegalDatabase
 *
 * @author ratnesh.gupta
 */
set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

include_once('library/config.php');
include_once('library/FileProcess.php');

class FreegalDatabase {

    var $freegal_db;
    var $territories_list = array('US', 'AU', 'CA', 'IT', 'NZ', 'GB', 'IE', 'BM', 'DE');
    var $patch_name;
    var $log;

    CONST COMPLETE_ALBUM = 1;
    CONST METADATA_UPDATE = 2;
    CONST TAKE_DOWN = 3;

    public function __construct($patch_name, $album_id) {
        $this->freegal_db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB2) or die("Could not connect to Freegal Database");
        mysqli_set_charset($this->freegal_db, 'UTF8');

        $this->patch_name = $patch_name;
        $this->log = new Logs($this->patch_name, $album_id);
    }

    function executeQuery($query, $db_flag = false) {
        $resource = false;
        $query_to_run = $query;

        $count = 0;
        if ($db_flag && preg_match('/^select/i', $query_to_run)) {

            $this->log->writeMysqLogs("DB5 Query : " . $query);

            do {
                $freegal_db5 = mysqli_connect(SLAVE_DB, DB_USER, DB_PASSWORD, DB2) or die("Could not connect to Freegal Database");
                mysqli_set_charset($freegal_db5, 'UTF8');
                $resource = mysqli_query($freegal_db5, $query_to_run);

                if (mysqli_errno($freegal_db5) == 11) {
                     $this->log->writeDebugLog("error occured with this query: ".$query);
                     exit("Resource not aviable issue found");
                }
                mysqli_close($freegal_db5);
            } while ($resource == false);
        } else {

            $this->log->writeMysqLogs($query);

            do {
                $count++;
                if (mysqli_ping($this->freegal_db)) {
                    $resource = mysqli_query($this->freegal_db, $query_to_run);
                    if (mysqli_errno($this->freegal_db) == 11) {
                        $this->log->writeDebugLog("error occured with this query: ".$query);
                        exit("Resource not aviable issue found");
                    }
                } else {

                    $error_message = "";
                    if (mysqli_connect_errno()){
                        $error_message .= "\nConnection Error: " . mysqli_connect_error();
                    }

                    $this->log->writeError(Logs::ERR_MYSQL, $this->patch_name, null, "MYSQL server has gone away." . $error_message . "\n$query_to_run" . mysqli_error($this->freegal_db));

                    //  $this->log->writeError(Logs::ERR_MYSQL, $this->patch_name, null, "MYSQL server has gone away." . " \n $query_to_run" . mysqli_error($this->freegal_db));
                    $this->freegal_db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB2) or die("Could not connect to Freegal Database");
                    mysqli_set_charset($this->freegal_db, 'UTF8');
                    $resource = mysqli_query($this->freegal_db, $query_to_run);

                    if (mysqli_errno($this->freegal_db) == 11) {
                        $this->log->writeDebugLog("error occured with this query: ".$query);
                        exit("Resource not aviable issue found");
                    }
                }

                if ($count == 200) {
                    // Added error number to the log message so that we can determine on what condition to break out of this loop.
                    $this->log->writeError(Logs::MYSQL_LOGS, $this->patch_name, null, "MySQL server has gone away. Please check. $count" . " \n $query_to_run \nError: " . mysqli_error($this->freegal_db) . "\nError Num: " . mysqli_errno($this->freegal_db));
                    $count = 0;
                }

            } while ($resource == false);

            $this->closeConnection();
        }
        return $resource;
    }

    function closeConnection() {
        mysqli_close($this->freegal_db);
    }

    /**
     * Check if ablis present in Product table by album release id
     * @param type $ioda_release_id
     * @return type
     */
    function checkAlbumByIodaReleaseID($ioda_release_id) {
        $query = "SELECT ProdID FROM PRODUCT where ProdID ='$ioda_release_id' and provider_type='ioda' ";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    /**
     * Check album in Product table by using ProdID
     * 
     * @param type $prod_id
     * @return type
     */
    function checkAlbumByProdID($prod_id) {
        $query = "SELECT ProdID FROM Albums where ProdID ='$prod_id' and provider_type='ioda' ";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    /**
     * This method is used to return ProdId of Album 
     * @param int $upc
     * @return row
     */
    function checkAlbumByUPC($upc) {
        $query = "SELECT ProdID FROM Albums where UPC =$upc and provider_type='ioda' ";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    /**
     * This method is used to return ProdId of Album 
     * @param int $upc
     * @return row
     */
    function checkReleaseByUPC($upc) {
        $query = "SELECT ProdID , ProductID FROM Albums where UPC =$upc and provider_type='ioda' ";
        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    /**
     * This method is used to update the teritories table 
     * for the song is retricted or not.
     * 
     * @param array $track_array
     * @param int $ioda_track_id
     * @param date $album_sales_date
     * @param int $type
     */
    function updateCountries($ioda_track_id = null, $album_sales_date = null, $type = self::COMPLETE_ALBUM) {
        switch ($type) {
            case self::TAKE_DOWN:

                foreach ($this->territories_list as $territory) {
                    $update_country['Territory'] = $territory;
                    $update_country['provider_type'] = 'ioda';
                    $update_country['StreamingStatus'] = '0';
                    $update_country['DownloadStatus'] = 0;
                    $update_country['SalesDate'] = '';
                    $update_country['StreamingSalesDate'] = $album_sales_date;
                    $update_country['UpdateOn'] = date('Y-m-d h:i:s');

                    $territory_prefix = strtolower($territory) . "_";

                    $query = "SELECT * FROM freegal.{$territory_prefix}countries WHERE ProdID='$ioda_track_id' AND provider_type='ioda' ";
                    $in_territory = mysqli_fetch_row($this->executeQuery($query, true));

                    if (!empty($in_territory)) {
                        $whereCondition = " WHERE ProdID='$ioda_track_id' AND provider_type='ioda' ";
                        $this->updateTableQuery($update_country, $territory_prefix . 'countries', $whereCondition);
                    } else {
                        $update_country['ProdID'] = $ioda_track_id;
                        $this->insertTableQuery($update_country, $territory_prefix . 'countries');
                    }
                }
                break;
        }
    }

    function checkSongProdID($prod_id) {
        $query = "SELECT * FROM Songs where ProdID ='$prod_id' and provider_type='ioda' ";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    function updateAlbums($upc, $type = self::COMPLETE_ALBUM, $album_array = null) {
        switch ($type) {
            case self::METADATA_UPDATE:
                $update_Album = "PublicationStatus= 'MetadataOnlyUpdate' , LastUpdated = '" . date('Y-m-d h:i:s') . "' ";
                $query = "UPDATE Albums SET " . $update_Album . " WHERE upc='$upc' and provider_type='ioda'";
                $this->executeQuery($query);
                break;


            case self::TAKE_DOWN:
                $update_Album = "PublicationStatus= 'Takedown' , LastUpdated = '" . date('Y-m-d h:i:s') . "' ";
                $query = "UPDATE Albums SET " . $update_Album . " WHERE upc=$upc and provider_type='ioda'";
                $this->executeQuery($query);
                break;
        }
    }

    function freegalAlbumsTable($album_array, $update = false, $where_condition = null) {
        $insert_album = array();
        if ($update) {
            $this->updateTableQuery($insert_album, 'Albums', $where_condition);
        } else {
            $this->insertTableQuery($insert_album, 'Albums');
        }
    }

    function getTrackCountFromUPC($album_id) {
        $query = "SELECT count(*) FROM Songs WHERE ProductID=$album_id and provider_type='ioda' ";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    function getHighestProdId() {
        $query = "select ProdId from Songs order by ProdId desc limit 0,1";
        $result = mysqli_fetch_row($this->executeQuery($query, true));
        return $result[0];
    }

    function getTrack($isrc, $upc) {
        $query = "select 
                        Songs.ProdID
                    from
                        freegal.Songs
                            join
                        freegal.Albums ON (Songs.ReferenceID = Albums.ProdID
                            and Songs.provider_type = Albums.provider_type)
                    where
                        Songs.ISRC = '$isrc'
                            and Songs.provider_type = 'ioda'
                            and Albums.UPC = $upc";
        
        //return mysqli_fetch_assoc($this->executeQuery($query, true));
        if($result = $this->executeQuery($query,true)){
            $ids = array();
            $vals = 0;
            while ($row = $result->fetch_assoc()) {
                $ids[$vals] = $row["ProdID"];
                $vals++;
            }
            return $ids;
        }
    }

    function checkTrack($isrc, $upc) {
        $query = "select 
                        Songs.ProdID
                    from
                        freegal.Songs
                            join
                        freegal.Albums ON (Songs.ReferenceID = Albums.ProdID
                            and Songs.provider_type = Albums.provider_type)
                    where
                        Songs.ISRC = '$isrc'
                            and Songs.provider_type = 'ioda'
                            and Albums.UPC = $upc";

        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function getTrackCDNDetails($isrc, $upc) {
        $query = "select * from freegal.Songs
                    join freegal.Albums on (Songs.ReferenceID = Albums.ProdID and Songs.provider_type = Albums.provider_type)
                    where Albums.UPC=$upc and Songs.ISRC='$isrc'";
        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function getTrackCountFromProductID($ioda_release_id) {
        $query = "SELECT count(*) FROM Songs WHERE ProductID='$ioda_release_id' and provider_type='ioda' ";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    function updateTable($track_array, $table, $cond = null, $update = false) {
        if ($update) {
            foreach ($track_array as $key => $val) {
                $set_statement .= "$key = $val ,";
            }

            $set_statement = substr($set_statement, 0, -1);

            $query = "UPDATE " . $table . " SET $set_statement WHERE $cond ";
            $this->executeQuery($query);
        }

        foreach ($track_array as &$ele) {
            $ele = "'" . mysql_real_escape_string($ele) . "'";
        }

        $fields = implode(',', array_keys($track_array));
        $values = implode(',', array_values($track_array));
        $query = "INSERT INTO " . $table . " ($fields) VALUES( " . $values . ")";
        $this->executeQuery($query);
    }

    /**
     * This function search for the Album in Albums table
     * @param type $upc
     */
    function checkAlbum($upc) {
        $query = "SELECT * from Albums where UPC=$upc and provider_type='ioda'";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    function getSongsProdIDFromAlbumID($upc) {
        $query = "select ProdID , ISRC FROM freegal.Songs where ReferenceID=$upc and provider_type='ioda'";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    function checkCountry($country_table_name, $ProdID, $album_release_date) {
        $query = "SELECT ProdID FROM $country_table_name WHERE ProdID='$ProdID' and provider_type='ioda' and SalesDate='$album_release_date'";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

    function checkGenre($prod_id) {
        $query = "SELECT Genre FROM Genre WHERE ProdID='$prod_id' and provider_type='ioda'";
        return mysqli_fetch_row($this->executeQuery($query, true));
    }

   function createPathCDN( $album_id ) {
    
    	$album_id  = str_split($album_id, 3);
    
    	$dirpath = implode('/', $album_id);
    	$dirpath = $dirpath . '/';
    
    	return $dirpath;
    }

    /**
     * This method is used to create Insert query to the Table
     * an dexecite the query.
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
     * This method is used to create the Update query for
     * the table whose name is pass in parameters and 
     * execute the query.
     * 
     * @param array $details_array
     * @param string $table_name
     * @param string $whereCondition
     */
    function updateTableQuery($details_array, $table_name, $whereCondition) {
        $setValues = '';
        foreach ($details_array as $key => $value) {
            $setValues .= $key . "='" . addslashes(trim($value)) . "',";
        }

        $setValues = substr($setValues, 0, -1);
        $query = "UPDATE freegal.$table_name set $setValues $whereCondition";
        $this->executeQuery($query);
    }

    /**
     * this method is used to execute the delete query 
     * on the Freegal db
     * 
     * @param type $table_name
     * @param type $whereCondition
     */
    function deleteTableQuery($table_name, $whereCondition) {
        $query = "Delete from $table_name  $whereCondition" . "\n";
        $this->executeQuery($query);
    }

    /**
     * This method is used to insert / update
     * Product table for the Album
     * 
     * @param array $product_array
     * @param boolean $update
     */
    function freegalProductTable($product_array, $update = false) {
        if ($update) {
            //update album
            $product_array['provider_type'] = 'ioda';
            $where_condition = " where ProdID='" . $product_array['ProdID'] . "'";
            $this->updateTableQuery($product_array, 'PRODUCT', $where_condition);
        } else {
            $product_array['provider_type'] = 'ioda';
            $this->insertTableQuery($product_array, 'PRODUCT');
        }
    }

    /**
     * This method is used to Insert / update
     * the Album table. it also insert the Image table data for the
     * Album, as Image id used to map in the Album table.
     * 
     * @param array $album_array
     * @param boolean $update
     * @param array $image_array
     */
    function freegalAlbumInsert($album_array) {
        $insert_album['ProdID'] = $album_array['upc'];
        $insert_album['ProductID'] = $album_array['upc'];
        $insert_album['AlbumTitle'] = $album_array['product_name'];

        $insert_album['ArtistText'] = $album_array['artist_display'];
        $insert_album['ArtistURL'] = $album_array['webpage_url'];

        $insert_album['Label'] = $album_array['label'];
        $insert_album['Copyright'] = $album_array['cline'];
        $insert_album['Advisory'] = '';

        $insert_album['DownloadStatus'] = $album_array['DownloadStatus'];
        $insert_album['TrackBundleCount'] = $album_array['track_count'];
        $insert_album['UPC'] = $album_array['upc'];

        $insert_album['CdnPath'] = ( substr($album_array['CdnPath'], -1) != '/' ) ? $album_array['CdnPath'] : substr($album_array['CdnPath'], 0, -1);
        $insert_album['Image_SaveAsName'] = $album_array['Image_SaveAsName'];

        $insert_album['PublicationStatus'] = $album_array['PublicationStatus'];
        $insert_album['LastUpdated'] = $album_array['LastUpdated'];
        $insert_album['StatusNotes'] = $album_array['StatusNotes'];
        $insert_album['PublicationDate'] = $album_array['PublicationDate'];
        $insert_album['provider_type'] = 'ioda';

        $this->insertTableQuery($insert_album, 'Albums');
    }

    function freegalAlbumUpdate($album_array) {
        $insert_album['AlbumTitle'] = $album_array['product_name'];

        $insert_album['ArtistText'] = $album_array['artist_display'];
        $insert_album['ArtistURL'] = $album_array['webpage_url'];

        $insert_album['Label'] = $album_array['label'];
        $insert_album['Copyright'] = $album_array['cline'];
        $insert_album['Advisory'] = '';

        $insert_album['DownloadStatus'] = $album_array['DownloadStatus'];
        $insert_album['TrackBundleCount'] = $album_array['track_count'];
        $insert_album['UPC'] = $album_array['upc'];

        if (isset($album_array['CdnPath'])) {
            $insert_album['CdnPath'] = $album_array['CdnPath'];
            $insert_album['Image_SaveAsName'] = $album_array['Image_SaveAsName'];
        } else {
            $file_update = $this->getAlbumCDNPath($album_array['upc']);
            $insert_album['CdnPath'] = ( substr($file_update['CdnPath'], -1) != '/' ) ? $file_update['CdnPath'] : substr($file_update['CdnPath'], 0, -1);
            $insert_album['Image_SaveAsName'] = $file_update['SourceURL'];
        }

        $insert_album['PublicationStatus'] = $album_array['PublicationStatus'];
        $insert_album['LastUpdated'] = date('Y-m-d H:i:s');
        $insert_album['StatusNotes'] = $album_array['StatusNotes'];
        $insert_album['PublicationDate'] = $album_array['PublicationDate'];

        $prod_id = (isset($album_array['ProdID'])) ? $album_array['ProdID'] : $album_array['upc'];
        $where_condition = " where ProdID='$prod_id' and provider_type= 'ioda' ";
        $this->updateTableQuery($insert_album, 'Albums', $where_condition);
    }

    /**
     * This method is used to insert /update the 
     * Tracks details in the Songs table. This method fetch
     * the album details and update with tracks.
     * 
     * @param array $track_array        Track array which is having info of the track
     * @param array $albums_detail      Album detial array which is used for updating the array
     * @param int $prod_id              Custom ProdID for the Track which is  used for the Songs
     * @param boolean $update           flag which is used to check if it is for update or new insert
     */
    function freegalSongsUpdate($track_array, $albums_detail, $prod_id = null) {
        $album_details = $this->checkAlbum($albums_detail['upc']);

        $insert_track['ReferenceID'] = $album_details[0];
        $insert_track['ProductID'] = $album_details[1];
        $insert_track['Title'] = $album_details[2];
        $insert_track['SongTitle'] = $track_array['title'];
        $insert_track['ISRC'] = $track_array['isrc'];
        $insert_track['Composer'] = '';
        $insert_track['UpdateOn'] = date('Y-m-d H:i:s');
        $insert_track['Sample_Duration'] = '00:30';
        $insert_track['FullLength_Duration'] = $track_array['track_time'];
        $insert_track['sequence_number'] = $track_array['sequence'];

        $artist = $this->getParticipantsOrchardDB($insert_track['ISRC'], $albums_detail['upc']);
        $insert_track['ArtistText'] = $artist['artist'];

        $insert_track['Genre'] = $albums_detail['primary_genre'];
        $insert_track['Advisory'] = ($track_array['explicit_lyrics'] != 'false') ? 'T' : 'F';

        if ( isset( $track_array['new_clip_name'] ) && !empty( $track_array['new_clip_name'] ) ) {
            $insert_track['Sample_SaveAsName'] = $track_array['new_clip_name'];
        }

        if ( isset( $track_array['new_track_name'] ) && !empty( $track_array['new_track_name'] ) ) {
            $insert_track['FullLength_SaveAsName'] = $track_array['new_track_name'];
        }

        if (isset($albums_detail['CdnPath'])) {
            $insert_track['CdnPath'] = $albums_detail['CdnPath'];
        } else {
            $file_update = $this->getAlbumCDNPath($albums_detail['upc']);
            $insert_track['CdnPath'] = ( substr($file_update['CdnPath'], -1) != '/' ) ? $file_update['CdnPath'] : substr($file_update['CdnPath'], 0, -1);
        }

        $where_condition = " Where ProdID='$prod_id' and provider_type='ioda'";
        $this->updateTableQuery($insert_track, 'Songs', $where_condition);
    }

    function freegalSongsInsert($track_array, $albums_detail, $prod_id) {
        $album_details = $this->checkAlbum($albums_detail['upc']);
        $track_in_freegal = $this->getTrack($track_array['isrc'], $albums_detail['upc']);

        if (empty($track_in_freegal)) {
            $insert_track['ProdID'] = $prod_id;
            $insert_track['ReferenceID'] = $album_details[0];
            $insert_track['ProductID'] = $album_details[1];
            $insert_track['Title'] = $album_details[2];
            $insert_track['SongTitle'] = $track_array['title'];
            $insert_track['ISRC'] = $track_array['isrc'];
            $insert_track['Composer'] = '';
            $insert_track['provider_type'] = 'ioda';
            $insert_track['CreatedOn'] = date('Y-m-d H:i:s');
            $insert_track['UpdateOn'] = date('Y-m-d H:i:s');
            $insert_track['Sample_Duration'] = '00:30';
            $insert_track['FullLength_Duration'] = $track_array['track_time'];
            $insert_track['sequence_number'] = $track_array['sequence'];

            $artist = $this->getParticipantsOrchardDB($insert_track['ISRC'], $albums_detail['upc']);
            $insert_track['ArtistText'] = $artist['artist'];

            $insert_track['Genre'] = $albums_detail['primary_genre'];
            $insert_track['Advisory'] = ($track_array['explicit_lyrics'] != 'false') ? 'T' : 'F';

            $insert_track['Sample_SaveAsName'] = $track_array['new_clip_name'];
            $insert_track['FullLength_SaveAsName'] = $track_array['new_track_name'];


            if (isset($albums_detail['CdnPath'])) {
                $insert_track['CdnPath'] = $albums_detail['CdnPath'];
            } else {
                $file_update = $this->getAlbumCDNPath($albums_detail['upc']);
                $insert_track['CdnPath'] = ( substr($file_update['CdnPath'], -1) != '/' ) ? $file_update['CdnPath'] : substr($file_update['CdnPath'], 0, -1);
            }

            $this->insertTableQuery($insert_track, 'Songs');

            $product_song['ProdID'] = $insert_track['ProdID'];
            $product_song['provider_type'] = 'ioda';
            $this->insertTableQuery($product_song, 'PRODUCT');
        } else {
            $this->freegalSongsUpdate($track_array, $albums_detail, $track_in_freegal[0]);
        }
    }

    /**
     * This method is used to insert the tracks details
     * @param array $audio_array
     * @param int $prod_id
     */
    function freegalAudioTable($audio_array, $prod_id, $type = 'clip') {
        if ($type == 'clip') {
            $audio_insert['AudioType'] = 'Sample MPEG Layer-3 File';
            $audio_insert['IsClip'] = 'y';
            $audio_insert['Bitrate'] = 128;
            $audio_insert['Duration'] = '0:30';
            $audio_insert['ClipOffsetStart'] = ':';
            $audio_insert['ClipOffsetEnd'] = '0:30';
            $audio_insert['provider_type'] = 'ioda';
            $audio_insert['CODEC'] = strtoupper(pathinfo($audio_array['new_clip_name']));
            $audio_insert['TrkID'] = $prod_id;
        } elseif ($type == 'song') {
            $audio_insert['AudioType'] = 'Full Length MPEG-1 Layer 3';
            $audio_insert['IsClip'] = 'n';
            $audio_insert['Bitrate'] = 256;
            $audio_insert['provider_type'] = 'ioda';
            $audio_insert['Duration'] = substr($audio_array['track_time'], 3, strlen($audio_array['track_time']));
            $audio_insert['CODEC'] = strtoupper(pathinfo($audio_array['new_track_name']));
            $audio_insert['TrkID'] = $prod_id;
        }
    }

    function freegalCombineGenreCheck($genre, $return = false) {
        $genre = str_replace('"', '', $genre);
        $query = "SELECT * FROM freegal.combine_genres where genre='" . mysql_real_escape_string($genre) . "'";
        $is_present = mysqli_fetch_assoc($this->executeQuery($query, true));
        if (empty($is_present)) {
            $combine_genre['genre'] = $genre;
            $combine_genre['expected_genre'] = $genre;

            $this->insertTableQuery($combine_genre, 'combine_genres');
        }

        if ($return) {
            return $is_present;
        }
    }

    function freegalGenreInsert($album_detail, $prod_id = null) {
        $ProdID = ($prod_id == null ) ? $album_detail['upc'] : $prod_id;
        $query = "SELECT * FROM freegal.Genre where ProdID=" . $ProdID . " and provider_type='ioda' ";
        $is_present = mysqli_fetch_assoc($this->executeQuery($query, true));

        if (($is_present)) {
            //delete it
            $whereCondition = " where ProdID=" . $ProdID . " and provider_type='ioda'";
            $this->deleteTableQuery('Genre', $whereCondition);
        }

        $genre_insert['ProdID'] = $ProdID;
            $genre = str_replace('"', '', $album_detail['primary_genre']);
            if (substr($genre, -1) == ',') {
                $genre = str_replace(',', '', $album_detail['primary_genre']);
            }
            $genre_insert['Genre'] = $genre;
            $genre_insert['SubGenre'] = $album_detail['sub_genre'];
            $temp = $this->freegalCombineGenreCheck($genre_insert['Genre'], true);
            $genre_insert['expected_genre'] = $temp['expected_genre'];
            $genre_insert['provider_type'] = 'ioda';
            $this->insertTableQuery($genre_insert, 'Genre');
    }

    function freegalGenreUpdate($album_detail, $prod_id) {
        $this->freegalGenreInsert($album_detail, $prod_id);
    }

    function freegalTerritoriesUpdate($track_array, $album_array, $prod_id) {
        
        $country_array = array();

        foreach ($this->territories_list as $territory) {
            
            $country_array['Territory'] = $territory;
            $country_array['provider_type'] = 'ioda';
            $country_array['UpdateOn'] = date('Y-m-d h:i:s');
            $table = strtolower($territory) . "_" . 'countries';

            $check_in_terr = $this->freegalTerritoriesCheck($territory, $prod_id);
            
            if (in_array($territory, explode(',', $track_array['track_restricted_to']))) {
                
                $country_array['SalesDate'] = $album_array['primary_sales_start_date'];
                $country_array['StreamingSalesDate'] = $album_array['primary_sales_start_date'];
                $country_array['DownloadStatus'] = $track_array['DownloadStatus'];
                $country_array['StreamingStatus'] = $track_array['StreamingStatus']; 
        
                if (empty($check_in_terr)) {
                    $country_array['ProdID'] = $prod_id;
                    $this->insertTableQuery($country_array, $table);
                } else {
                    $where_condition = " WHERE ProdId = '$prod_id' and provider_type='ioda'";
                    $this->updateTableQuery($country_array, $table, $where_condition);
                } 
            
            } else {
                
                $country_array['SalesDate'] = '';
                $country_array['StreamingSalesDate'] = '';
                $country_array['DownloadStatus'] = 0;
                $country_array['StreamingStatus'] = 0;
            
                if (!empty($check_in_terr))  {
                    $where_condition = " WHERE ProdId = '$prod_id' and provider_type='ioda'";
                    $this->updateTableQuery($country_array, $table, $where_condition);
                } 
            }
        }
    }

    /*function freegalTerritoriesUpdate($track_array, $album_array, $prod_id) {
        $country_array = array();

        foreach ($this->territories_list as $territory) {
            if (in_array($territory, explode(',', $track_array['track_restricted_to']))) {
                
                $country_array['Territory'] = $territory;
                $country_array['SalesDate'] = $album_array['primary_release_date'];
                $country_array['provider_type'] = 'ioda';
                $country_array['StreamingStatus'] = $album_array['StreamingStatus'];
                $country_array['DownloadStatus'] = $album_array['DownloadStatus'];
                $country_array['StreamingSalesDate'] = $album_array['primary_release_date'];
                $country_array['UpdateOn'] = date('Y-m-d h:i:s');

                $table = strtolower($territory) . "_" . 'countries';
            
                $check_in_terr = $this->freegalTerritoriesCheck($territory, $prod_id);

                if (empty($check_in_terr)) {
                	$country_array['ProdID'] = $prod_id;
                	$this->insertTableQuery($country_array, $table);
                } else {
                	$where_condition = " WHERE ProdId = '$prod_id' and provider_type='ioda'";
                	$this->updateTableQuery($country_array, $table, $where_condition);
                } 
            }
        }
    }*/

    function freegalTerritoriesCheck($territory, $prod_id) {
        $query = "SELECT * FROM freegal." . strtolower($territory) . "_countries where provider_type='ioda'  and ProdID=$prod_id ";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    function getParticipantsOrchardDB($isrc, $upc) {
        $query = "SELECT group_concat(distinct name) as artist FROM theorchard.participants WHERE isrc = '$isrc' AND upc = $upc ";
        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    function getAlbumCDNPath($upc) {

    	$query = "SELECT 
				    Albums.CdnPath,
				    Albums.Image_SaveAsName AS SourceURL,
				    Albums.ProdID,
				    Albums.ProductID,
				    Albums.UPC
				FROM
				    freegal.Albums
				WHERE
				    Albums.UPC = $upc
				        AND Albums.provider_type = 'ioda'";

        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    function getAlbumArtworkCDNPath($upc) {

    	$query = "SELECT 
				    Albums.CdnPath,
				    Albums.Image_SaveAsName AS SourceURL,
				    Albums.ProdID,
				    Albums.ProductID,
				    Albums.UPC
				FROM
				    freegal.Albums
				WHERE
				    Albums.UPC = $upc
				        AND Albums.provider_type = 'ioda'";

        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function getFileDetials($prod_id) {

    	$query = "SELECT 
				    Songs.FullLength_SaveAsName AS SaveAsName, Songs.CdnPath
				FROM
				    freegal.Songs
				WHERE
				    Songs.ProdID = $prod_id
				        AND Songs.provider_type = 'ioda'";

        return mysqli_fetch_assoc($this->executeQuery($query, true));
    }

    function songsTakeDown($upc) {
        $query = "select 
                        group_concat(Songs.ProdID) as prod_ids
                    from
                        freegal.Songs
                            join
                        freegal.Albums ON (Songs.ReferenceID = Albums.ProdID
                            and Songs.provider_type = Albums.provider_type)
                    where
                        Albums.UPC =$upc";
        $resource = mysqli_fetch_assoc($this->executeQuery($query, true));

        $territories_list = array('US', 'AU', 'CA', 'IT', 'NZ', 'GB', 'IE', 'BM', 'DE');
        foreach ($territories_list as $territory) {
            $ter = strtolower($territory);
            echo $update_query = "UPDATE {$ter}_countries set DownloadStatus=0 , StreamingStatus=0  where provider_type='ioda' and ProdID in (" . $resource['prod_ids'] . ")";
            echo "\n";
            $this->executeQuery($update_query);
        }
    }

    function checkProduct($prod_id) {
        $query = "SELECT pid FROM freegal.PRODUCT WHERE  ProdID=$prod_id and provider_type='ioda'";
        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function checkProdIDGenre($ProdID) {
        $query = "SELECT * FROM freegal.Genre where ProdID=" . $ProdID . " and provider_type='ioda' ";
        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function getSongsCDNInfoByISRCandUPC($isrc, $upc) {

    	$query = "SELECT 
				    Songs.FullLength_SaveAsName AS SaveAsName, Songs.CdnPath
				FROM
				    freegal.Songs
				        JOIN
				    freegal.Albums ON (Songs.ReferenceID = Albums.ProdID
				        AND Songs.provider_type = Albums.provider_type)
				WHERE
				    Songs.provider_type = 'ioda'
				        AND Songs.ISRC = '$isrc'
				        AND Albums.UPC = $upc";

        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function getSongsSampleCDNInfoByISRCandUPC($isrc, $upc) {

    	$query = "SELECT 
				    Songs.Sample_SaveAsName AS SaveAsName, Songs.CdnPath
				FROM
				    freegal.Songs
				        JOIN
				    freegal.Albums ON (Songs.ReferenceID = Albums.ProdID
				        AND Songs.provider_type = Albums.provider_type)
				WHERE
				    Songs.provider_type = 'ioda'
				        AND Songs.ISRC = '$isrc'
				        AND Albums.UPC = $upc";

        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function getSongsMP4CDNInfoByISRCandUPC($isrc, $upc) {
        $query = "SELECT
                        Songs.CdnPath, REPLACE(FullLength_SaveAsName, '.mp3', '.mp4') AS SaveAsName
                    FROM
                        freegal.Songs
                            JOIN
                        freegal.Albums ON (Songs.ReferenceID = Albums.ProdID
                            AND Songs.provider_type = Albums.provider_type)
                    WHERE
                        Songs.provider_type = 'ioda'
                            AND Songs.ISRC = '$isrc'
                            AND Albums.UPC = $upc";
        return mysqli_fetch_object($this->executeQuery($query, true));
    }

    function freegalUpdateAlbum($upc) {
        $query = "
                update freegal.Albums,
                    theorchard.album 
                set 
                    Albums.AlbumTitle = album.product_name,
                    Albums.Title = album.product_name,
                    Albums.Label = album.label,
                    Albums.Copyright = album.cline,
                    Albums.TrackBundleCount = album.track_count,
                    Albums.DownloadStatus = if(album.delivery_type = 'TakeDown','0','1'),
                    Albums.Advisory = '',
                    Albums.StatusNotes = '',
                    Albums.PublicationDate = album.primary_release_date
                where
                    Albums.UPC = album.upc
                        and Albums.provider_type = 'ioda'
                        and Albums.UPC = $upc";
        $this->executeQuery($query);
    }

    function freegalInsertAlbum($upc) {
        $insert_album['ProdID'] = $upc;
        $insert_album['ProductID'] = $upc;
        $insert_album['UPC'] = $upc;
        $insert_album['provider_type'] = 'ioda';

        $this->insertTableQuery($insert_album, 'Albums');

        $this->freegalUpdateAlbum($upc);
    }

    function freegalUpdateAlbumArtist($upc) {
        $query = "
                update freegal.Albums,
                    theorchard.artist 
                set 
                    Albums.ArtistText = artist.artist_display_text,
                    Albums.Artist = artist.artist_display_text,
                    Albums.ArtistURL = artist.artist_webpage_url
                where
                    Albums.UPC = artist.upc
                        and Albums.provider_type = 'ioda'
                        and Albums.UPC = $upc";
        $this->executeQuery($query);
    }


}
