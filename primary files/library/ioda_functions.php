<?php

set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

//inclusion of files which are required
include_once('library/config.php');
include_once('library/FileProcess.php');

include_once('library/FreegalDatabase.php');
include_once('library/OrchardDatabase.php');
include_once('library/Logs.php');

class IodaFunctions {

    var $batch_name;
    var $log;
    var $upc;

    public function __construct($batch_name, $upc = null) {
        $this->batch_name = $batch_name;
        $this->upc = $upc;

        $this->log = new Logs($this->patch_name, $upc);
    }

    /**
     * 
     * @param array $albums_details
     */
    function orchard_tracks_processing($track_array, $prod_id, $new, $flag) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        switch ($flag) {
            case 'I':
                if ($new) {
                    $orchard_db->orchardTracksInsert($track_array, $prod_id);
                } else {
                    $orchard_db->orchardTracksUpdate($track_array, $prod_id);
                }
                break;


            case 'U':
                if ($new) {
                    $orchard_db->orchardTracksInsert($track_array, $prod_id);
                } else {
                    $orchard_db->orchardTracksUpdate($track_array, $prod_id);
                }
                break;

            case 'D':
                break;
        }
    }

    function freegal_tracks_processing($track_array, $albums_details, $prod_id, $new) {
        $freegal_db = new FreegalDatabase($this->batch_name, $this->upc);
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);

        switch ($albums_details['flag']) {
            case 'I':
                if ($new) {
                    $is_prodid_used = $freegal_db->checkSongProdID($prod_id);
                    if (empty($is_prodid_used)) {
                        $freegal_db->freegalSongsInsert($track_array, $albums_details, $prod_id);
                    } else {
                        $last_used_prodid = $orchard_db->getLastUsedProdID();
                        $new_prodid = (float) $last_used_prodid + (float) 1;
                        while (1) {
                            $new_prodid = (float) $new_prodid + (float) 1;
                            $is_prodid_used = $freegal_db->checkSongProdID($new_prodid);
                            if (empty($is_prodid_used)) {
                                $last_script_no = $orchard_db->getLastScriptNo();
                                $orchard_db->updateLastProdID($new_prodid, $last_script_no);
                                $freegal_db->freegalSongsInsert($track_array, $albums_details, $new_prodid);
                                break;
                            }
                        }
                    }
                } else {
                    $freegal_db->freegalSongsUpdate($track_array, $albums_details, $prod_id);
                }
                break;

            case 'U':
                if ($new) {
                    $is_prodid_used = $freegal_db->checkSongProdID($prod_id);
                    if (empty($is_prodid_used)) {
                        $freegal_db->freegalSongsInsert($track_array, $albums_details, $prod_id);
                    } else {
                        $last_used_prodid = $orchard_db->getLastUsedProdID();
                        $new_prodid = (int) $last_used_prodid + (int) 1;
                        while (1) {
                            $new_prodid = (int) $new_prodid + (int) 1;
                            $is_prodid_used = $freegal_db->checkSongProdID($new_prodid);
                            if (empty($is_prodid_used)) {
                                $last_script_no = $orchard_db->getLastScriptNo();
                                $orchard_db->updateLastProdID($new_prodid, $last_script_no);
                                $freegal_db->freegalSongsInsert($track_array, $albums_details, $new_prodid);
                                break;
                            }
                        }
                    }
                } else {
                    $freegal_db->freegalSongsUpdate($track_array, $albums_details, $prod_id);
                }
                break;

            case 'D':

                if ($new) {
                    $is_prodid_used = $freegal_db->checkSongProdID($prod_id);
                    if (empty($is_prodid_used)) {
                        $freegal_db->freegalSongsInsert($track_array, $albums_details, $prod_id);
                    } else {
                        $last_used_prodid = $orchard_db->getLastUsedProdID();
                        $new_prodid = (int) $last_used_prodid + (int) 1;
                        while (1) {
                            $new_prodid = (int) $new_prodid + (int) 1;
                            $is_prodid_used = $freegal_db->checkSongProdID($new_prodid);
                            if (empty($is_prodid_used)) {
                                $last_script_no = $orchard_db->getLastScriptNo();
                                $orchard_db->updateLastProdID($new_prodid, $last_script_no);
                                $freegal_db->freegalSongsInsert($track_array, $albums_details, $new_prodid);
                                break;
                            }
                        }
                    }
                } else {
                    $insert_track['UpdateOn'] = date('Y-m-d H:i:s');
                    $whereCondition = " where ProdID='$prod_id' and provider_type='ioda' ";
                    $freegal_db->updateTableQuery($insert_track, "Songs", $whereCondition);
                }
                break;
        }
        $freegal_db->closeConnection();
        $orchard_db->closeConnection();
    }

    function orchard_album_processing($albums_details) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $is_already_orchard = $orchard_db->getAlbum($albums_details['album_detail']['upc']);

        switch ($albums_details['album_detail']['flag']) {
            case 'I':
            case 'U':
            case 'D':

                if (is_array($is_already_orchard)) {
                    $albums_details['album_detail']['patch_name'] = $this->batch_name;
                    $orchard_db->orchardAlbumUpdate($albums_details['album_detail']);
                } else {
                    $albums_details['album_detail']['patch_name'] = $this->batch_name;
                    $orchard_db->orchardAlbumInsert($albums_details['album_detail']);
                }
                break;
        }
    }

    function freegal_album_processing($albums_details) {
        $freegal_db = new FreegalDatabase($this->batch_name, $this->upc);

        switch ($albums_details['album_detail']['flag']) {
            case 'I':
                $album_freegal = $freegal_db->checkAlbumByUPC($albums_details['album_detail']['upc']);

                if (empty($album_freegal)) {
                    $freegal_db->freegalAlbumInsert($albums_details['album_detail']);
                    $freegal_db->freegalGenreInsert($albums_details['album_detail']);
                } else {
                    $albums_details['album_detail']['ProdID'] = $album_freegal[0];
                    $freegal_db->freegalAlbumUpdate($albums_details['album_detail']);
                    $freegal_db->freegalGenreInsert($albums_details['album_detail'], $albums_details['album_detail']['ProdID']);
                }

                break;

            case 'U':
                $album_by_prodID = $freegal_db->checkAlbumByProdID($albums_details['album_detail']['upc']);
                $album_freegal = $freegal_db->checkAlbumByUPC($albums_details['album_detail']['upc']);

                if (empty($album_by_prodID) && empty($album_freegal)) {
                    $freegal_db->freegalAlbumInsert($albums_details['album_detail']);
                    $freegal_db->freegalGenreInsert($albums_details['album_detail']);//ADDED to update the genre
                } elseif (!empty($album_by_prodID) && empty($album_freegal)) {
                    $albums_details['album_detail']['ProdID'] = $albums_details['album_detail']['upc'];
                    $freegal_db->freegalAlbumUpdate($albums_details['album_detail']);
                    $freegal_db->freegalGenreInsert($albums_details['album_detail'], $albums_details['album_detail']['ProdID']);//ADDED to update the genre
                } elseif (empty($album_by_prodID) && !empty($album_freegal)) {
                    $albums_details['album_detail']['ProdID'] = $album_freegal[0];
                    $freegal_db->freegalAlbumUpdate($albums_details['album_detail']);
                    $freegal_db->freegalGenreInsert($albums_details['album_detail'], $albums_details['album_detail']['ProdID']);//ADDED to update the genre
                }  elseif (!empty($album_by_prodID) && !empty($album_freegal)) {
                    $albums_details['album_detail']['ProdID'] = $album_freegal[0];
                    $freegal_db->freegalAlbumUpdate($albums_details['album_detail']);
                    $freegal_db->freegalGenreInsert($albums_details['album_detail'], $albums_details['album_detail']['ProdID']);//ADDED to update the genre
                }
                break;

            case 'D':
                $album_by_prodID = $freegal_db->checkAlbumByProdID($albums_details['album_detail']['upc']);
                $album_freegal = $freegal_db->checkAlbumByUPC($albums_details['album_detail']['upc']);
                if (empty($album_freegal) && empty($album_by_prodID)) {
                    $freegal_db->freegalAlbumInsert($albums_details['album_detail']);
                }
                break;
        }

    }

    function orchard_image_processing($albums_details) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        switch ($albums_details['album_detail']['flag']) {
            case 'I':
                $image_details = $orchard_db->getImageDetails($albums_details['album_detail']['upc']);
                if (!is_array($image_details) && isset($albums_details['album_image'][1])) {
                    $orchard_db->orchardImageInsert($albums_details['album_image'][0]);
                }
                break;

            case 'U':
            case 'D': break;
        }
    }

    function orchardImageProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $image_details = $orchard_db->getImageDetails($albums_detail['upc']);

        if (empty($image_details)) {
            $albums_detail['album_image'][0]['cdn_filepath'] = $albums_detail['CdnPath'];
            $orchard_db->orchardImageInsert($albums_detail['album_image'][0]);
        } else {
            $albums_detail['album_image'][0]['image_id'] = $image_details[0];
            $albums_detail['album_image'][0]['format'] = $image_details[1];
            $albums_detail['album_image'][0]['file_name'] = $image_details[2];
            $albums_detail['album_image'][0]['cdn_filename'] = $image_details[11];
            $albums_detail['album_image'][0]['cdn_filepath'] = $albums_detail['CdnPath'];
            $albums_detail['album_image'][0]['upc'] = $albums_detail['upc'];
            $orchard_db->orchardImageUpdate($albums_detail['album_image'][0]);
        }
    }

    function orchard_similar_artist_processing($albums_details, $patch_name) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $album_xml_path = ROOTPATHTEST . $patch_name . '/' . (float) $albums_details['album_detail']['upc'] . '/' . (float) $albums_details['album_detail']['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist']) {
            switch ($albums_details['album_detail']['flag']) {
                case 'U':
                    $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "'";
                    $orchard_db->deleteTableQuery('similar_artists', $whereCondition);

                case 'I':
                    if (is_integer(key($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist']))) {
                        foreach ($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist'] as $similarArtistName) {
                            $insert_similar_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                            $insert_similar_artist['upc'] = $albums_details['album_detail']['upc'];
                            $insert_similar_artist['similar_artist_name'] = $similarArtistName;
                            $orchard_db->insertTableQuery($insert_similar_artist, 'similar_artists');
                        }
                    } else {
                        $insert_similar_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                        $insert_similar_artist['upc'] = $albums_details['album_detail']['upc'];
                        $insert_similar_artist['similar_artist_name'] = $xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist'];
                        $orchard_db->insertTableQuery($insert_similar_artist, 'similar_artists');
                    }
                    break;
            }
        }
    }

    function orchard_sub_genre_processing($albums_details, $patch_name) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $albums_details['album_detail']['upc'] . '/' . (float) $albums_details['album_detail']['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['Genre']['SubGenres']['SubGenre']) {
            switch ($albums_details['album_detail']['flag']) {
                case 'U':
                case 'I':

                    $whereCondition = " WHERE upc='" . $albums_details['album_detail']['upc'] . "' ";
                    $orchard_db->deleteTableQuery('sub_genre', $whereCondition);

                    if (is_integer(key($xmlArray['Genre']['SubGenres']['SubGenre']))) {
                        foreach ($xmlArray['Genre']['SubGenres']['SubGenre'] as $subGenreName) {
                            $insert_sub_genre['sub_genre_id'] = '';
                            $insert_sub_genre['upc'] = $albums_details['album_detail']['upc'];
                            $insert_sub_genre['sub_genre'] = $subGenreName;
                            $orchard_db->insertTableQuery($insert_sub_genre, 'sub_genre');
                        }
                    } else {
                        $insert_sub_genre['sub_genre_id'] = '';
                        $insert_sub_genre['upc'] = $albums_details['album_detail']['upc'];
                        $insert_sub_genre['sub_genre'] = $xmlArray['Genre']['SubGenres']['SubGenre'];
                        $orchard_db->insertTableQuery($insert_sub_genre, 'sub_genre');
                    }
                    break;
            }
        }
    }

    function orchardSubGenreProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['Genre']['SubGenres']['SubGenre']) {
            $whereCondition = " WHERE upc='" . $albums_detail['upc'] . "' ";
            $orchard_db->deleteTableQuery('sub_genre', $whereCondition);

            if (is_integer(key($xmlArray['Genre']['SubGenres']['SubGenre']))) {
                foreach ($xmlArray['Genre']['SubGenres']['SubGenre'] as $subGenreName) {
                    $insert_sub_genre['sub_genre_id'] = '';
                    $insert_sub_genre['upc'] = $album['upc'];
                    $insert_sub_genre['sub_genre'] = $subGenreName;
                    $orchard_db->insertTableQuery($insert_sub_genre, 'sub_genre');
                }
            } else {
                $insert_sub_genre['sub_genre_id'] = '';
                $insert_sub_genre['upc'] = $albums_detail['upc'];
                $insert_sub_genre['sub_genre'] = $xmlArray['Genre']['SubGenres']['SubGenre'];
                $orchard_db->insertTableQuery($insert_sub_genre, 'sub_genre');
            }
        }
    }

    function orchardSimilarArtistProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist']) {

            $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc=" . $albums_detail['upc'];
            $orchard_db->deleteTableQuery('similar_artists', $whereCondition);

            if (is_integer(key($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist']))) {
                foreach ($xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist'] as $similarArtistName) {
                    $insert_similar_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_similar_artist['upc'] = $albums_detail['upc'];
                    $insert_similar_artist['similar_artist_name'] = $similarArtistName;
                    $orchard_db->insertTableQuery($insert_similar_artist, 'similar_artists');
                }
            } else {
                $insert_similar_artist['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_similar_artist['upc'] = $albums_detail['upc'];
                $insert_similar_artist['similar_artist_name'] = $xmlArray['ProductArtist']['SimilarArtists']['SimilatArtist'];
                $orchard_db->insertTableQuery($insert_similar_artist, 'similar_artists');
            }
        }
    }

    function orchardAlbumReleaseDateProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ReleaseDates']['ReleaseDate']) {
            $whereCondition = " WHERE upc='" . $albums_detail['upc'] . "'";
            $orchard_db->deleteTableQuery('album_release_dates', $whereCondition);

            if (is_integer(key($xmlArray['ReleaseDates']['ReleaseDate']))) {
                foreach ($xmlArray['ReleaseDates']['ReleaseDate'] as $releaseDate) {
                    $release_date['upc'] = $albums_detail['upc'];
                    $release_date['release_date'] = $releaseDate;
                    $orchard_db->insertTableQuery($release_date, 'album_release_dates');
                }
            } else {
                $release_date['upc'] = $albums_detail['upc'];
                $release_date['release_date'] = $xmlArray['ReleaseDates']['ReleaseDate'];
                $orchard_db->insertTableQuery($release_date, 'album_release_dates');
            }
        }
    }

    function orchardAlbumSaleStartDateProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['SaleStartDates']['SaleStartDate']) {
            $whereCondition = " WHERE upc='" . $albums_detail['upc'] . "'";
            $orchard_db->deleteTableQuery('album_sales_start_dates', $whereCondition);

            if (is_integer(key($xmlArray['SaleStartDates']['SaleStartDate']))) {
                foreach ($xmlArray['SaleStartDates']['SaleStartDate'] as $saleStartDates) {
                    $sale_start_date['upc'] = $albums_detail['upc'];
                    $sale_start_date['sales_start_date'] = $saleStartDates;
                    $orchard_db->insertTableQuery($sale_start_date, 'album_sales_start_dates');
                }
            } else {
                $sale_start_date['upc'] = $albums_detail['upc'];
                $sale_start_date['sales_start_date'] = $xmlArray['SaleStartDates']['SaleStartDate'];
                $orchard_db->insertTableQuery($sale_start_date, 'album_sales_start_dates');
            }
        }
    }

    function orchardArtistInflunaceProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['SaleStartDates']['SaleStartDate']) {
            $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc='" . $albums_detail['upc'] . "'";
            $orchard_db->deleteTableQuery('artist_influences', $whereCondition);

            if (is_integer(key($xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence']))) {
                foreach ($xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence'] as $artistInfluenceName) {
                    $insert_artist_influences['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_artist_influences['upc'] = $albums_detail['upc'];
                    $insert_artist_influences['artist_influences'] = $artistInfluenceName;
                    $this->insertTableQuery($insert_artist_influences, 'artist_influences');
                }
            } else {
                $insert_artist_influences['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_artist_influences['upc'] = $albums_detail['upc'];
                $insert_artist_influences['artist_influences'] = $xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence'];
                $this->insertTableQuery($insert_artist_influences, 'artist_influences');
            }
        }
    }

    function orchardArtistContemporariesProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary']) {
            $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc='" . $albums_detail['upc'] . "'";
            $orchard_db->deleteTableQuery('artist_contemporaries', $whereCondition);

            if (is_integer(key($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary']))) {
                foreach ($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary'] as $artistContemporary) {
                    $insert_artist_contemporaries['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_artist_contemporaries['upc'] = $albums_detail['upc'];
                    $insert_artist_contemporaries['artist_contemporaries'] = $artistContemporary;
                    $this->insertTableQuery($insert_artist_contemporaries, 'artist_contemporaries');
                }
            } else {
                $insert_artist_contemporaries['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_artist_contemporaries['upc'] = $albums_detail['upc'];
                $insert_artist_contemporaries['artist_contemporaries'] = $xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary'];
                $this->insertTableQuery($insert_artist_contemporaries, 'artist_contemporaries');
            }
        }
    }

    function orchardArtistFollowersProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower']) {
            $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc='" . $albums_details['album_detail']['upc'] . "'";
            $orchard_db->deleteTableQuery('artist_followers', $whereCondition);

            if (is_integer(key($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower']))) {
                foreach ($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower'] as $artistFollower) {
                    $insert_artist_followers['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                    $insert_artist_followers['upc'] = $albums_detail['upc'];
                    $insert_artist_followers['artist_followers'] = $artistFollower;
                    $this->insertTableQuery($insert_artist_followers, 'artist_followers');
                }
            } else {
                $insert_artist_followers['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                $insert_artist_followers['upc'] = $albums_detail['upc'];
                $insert_artist_followers['artist_followers'] = $xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower'];
                $this->insertTableQuery($insert_artist_followers, 'artist_followers');
            }
        }
    }

    function orchardArtistProcessing($albums_detail) {
        $orchard_db = new OrchardDatabase($albums_detail['patch_name'], $albums_detail['upc']);
        $album_xml_path = PATCH_DETAILS . $albums_detail['patch_name'] . '/archive/' . (float) $albums_detail['upc'] . '/' . (float) $albums_detail['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        $fp_obj = new FileProcess();
        $insert_artist = $fp_obj->checkArtistInfo($xmlArray['ProductArtist']);
        $insert_artist['upc'] = $albums_detail['upc'];
        if (isset($insert_artist['artist_id']) && $insert_artist['artist_id'] != null) {
            $whereCondition = " where upc =" . $albums_detail['upc'];
            $orchard_db->deleteTableQuery('artist', $whereCondition);
            $orchard_db->insertTableQuery($insert_artist, 'artist');
        }
    }

    function orchard_album_release_date_processing($albums_details, $patch_name) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $album_xml_path = ROOTPATHTEST . $patch_name . '/' . (float) $albums_details['album_detail']['upc'] . '/' . (float) $albums_details['album_detail']['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ReleaseDates']['ReleaseDate']) {
            switch ($albums_details['album_detail']['flag']) {
                case 'U':
                    $whereCondition = " WHERE upc='" . $albums_details['album_detail']['upc'] . "'";
                    $orchard_db->deleteTableQuery('album_release_dates', $whereCondition);

                case 'I':
                    if (is_integer(key($xmlArray['ReleaseDates']['ReleaseDate']))) {
                        foreach ($xmlArray['ReleaseDates']['ReleaseDate'] as $releaseDate) {
                            $release_date['upc'] = $albums_details['album_detail']['upc'];
                            $release_date['release_date'] = $releaseDate;
                            $orchard_db->insertTableQuery($release_date, 'album_release_dates');
                        }
                    } else {
                        $release_date['upc'] = $albums_details['album_detail']['upc'];
                        $release_date['release_date'] = $xmlArray['ReleaseDates']['ReleaseDate'];
                        $orchard_db->insertTableQuery($release_date, 'album_release_dates');
                    }
                    break;
            }
        }
//$orchard_db->closeConnection();
    }

    function orchard_album_sale_start_date_processing($albums_details, $patch_name) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $album_xml_path = ROOTPATHTEST . $patch_name . '/' . (float) $albums_details['album_detail']['upc'] . '/' . (float) $albums_details['album_detail']['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['SaleStartDates']['SaleStartDate']) {
            switch ($albums_details['album_detail']['flag']) {
                case 'U':
                    $whereCondition = " WHERE upc='" . $albums_details['album_detail']['upc'] . "'";
                    $orchard_db->deleteTableQuery('album_sales_start_dates', $whereCondition);

                case 'I':
                    if (is_integer(key($xmlArray['SaleStartDates']['SaleStartDate']))) {
                        foreach ($xmlArray['SaleStartDates']['SaleStartDate'] as $saleStartDates) {
                            $sale_start_date['upc'] = $albums_details['album_detail']['upc'];
                            $sale_start_date['sales_start_date'] = $saleStartDates;
                            $orchard_db->insertTableQuery($sale_start_date, 'album_sales_start_dates');
                        }
                    } else {
                        $sale_start_date['upc'] = $albums_details['album_detail']['upc'];
                        $sale_start_date['sales_start_date'] = $xmlArray['SaleStartDates']['SaleStartDate'];
                        $orchard_db->insertTableQuery($sale_start_date, 'album_sales_start_dates');
                    }
                    break;
            }
        }
    }

    function orchard_artist_influnace_processing($albums_details, $patch_name) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $album_xml_path = ROOTPATHTEST . $patch_name . '/' . (float) $albums_details['album_detail']['upc'] . '/' . (float) $albums_details['album_detail']['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['SaleStartDates']['SaleStartDate']) {
            switch ($albums_details['album_detail']['flag']) {
                case 'U':
                    $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc='" . $albums_details['album_detail']['upc'] . "'";
                    $orchard_db->deleteTableQuery('artist_influences', $whereCondition);

                case 'I':
                    if (is_integer(key($xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence']))) {
                        foreach ($xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence'] as $artistInfluenceName) {
                            $insert_artist_influences['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                            $insert_artist_influences['upc'] = $albums_details['album_detail']['upc'];
                            $insert_artist_influences['artist_influences'] = $artistInfluenceName;
                            $this->insertTableQuery($insert_artist_influences, 'artist_influences');
                        }
                    } else {
                        $insert_artist_influences['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                        $insert_artist_influences['upc'] = $albums_details['album_detail']['upc'];
                        $insert_artist_influences['artist_influences'] = $xmlArray['ProductArtist']['ArtistInfluences']['ArtistInfluence'];
                        $this->insertTableQuery($insert_artist_influences, 'artist_influences');
                    }
                    break;
            }
        }
    }

    function orchard_artist_contemporaries_processing($albums_details, $patch_name) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $album_xml_path = ROOTPATHTEST . $patch_name . '/' . (float) $albums_details['album_detail']['upc'] . '/' . (float) $albums_details['album_detail']['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary']) {
            switch ($albums_details['album_detail']['flag']) {
                case 'U':
                    $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc='" . $albums_details['album_detail']['upc'] . "'";
                    $orchard_db->deleteTableQuery('artist_contemporaries', $whereCondition);

                case 'I':
                    if (is_integer(key($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary']))) {
                        foreach ($xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary'] as $artistContemporary) {
                            $insert_artist_contemporaries['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                            $insert_artist_contemporaries['upc'] = $albums_details['album_detail']['upc'];
                            $insert_artist_contemporaries['artist_contemporaries'] = $artistContemporary;
                            $this->insertTableQuery($insert_artist_contemporaries, 'artist_contemporaries');
                        }
                    } else {
                        $insert_artist_contemporaries['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                        $insert_artist_contemporaries['upc'] = $albums_details['album_detail']['upc'];
                        $insert_artist_contemporaries['artist_contemporaries'] = $xmlArray['ProductArtist']['ArtistContemporaries']['ArtistContemporary'];
                        $this->insertTableQuery($insert_artist_contemporaries, 'artist_contemporaries');
                    }
                    break;
            }
        }
    }

    function orchard_artist_followers_processing($albums_details, $patch_name) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        $album_xml_path = ROOTPATHTEST . $patch_name . '/' . (float) $albums_details['album_detail']['upc'] . '/' . (float) $albums_details['album_detail']['upc'] . '.xml';
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        if ($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower']) {
            switch ($albums_details['album_detail']['flag']) {
                case 'U':
                    $whereCondition = " WHERE artist_id='" . $xmlArray['ProductArtist']['ArtistID'] . "' and upc='" . $albums_details['album_detail']['upc'] . "'";
                    $orchard_db->deleteTableQuery('artist_followers', $whereCondition);

                case 'I':
                    if (is_integer(key($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower']))) {
                        foreach ($xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower'] as $artistFollower) {
                            $insert_artist_followers['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                            $insert_artist_followers['upc'] = $albums_details['album_detail']['upc'];
                            $insert_artist_followers['artist_followers'] = $artistFollower;
                            $this->insertTableQuery($insert_artist_followers, 'artist_followers');
                        }
                    } else {
                        $insert_artist_followers['artist_id'] = $xmlArray['ProductArtist']['ArtistID'];
                        $insert_artist_followers['upc'] = $albums_details['album_detail']['upc'];
                        $insert_artist_followers['artist_followers'] = $xmlArray['ProductArtist']['ArtistFollowers']['ArtistFollower'];
                        $this->insertTableQuery($insert_artist_followers, 'artist_followers');
                    }
                    break;
            }
        }
    }

    function freegal_audio_processing($audio_array, $prod_id, $new, $type) {
        if ($new) {
            $freegal_db = new FreegalDatabase($this->batch_name, $this->upc);
            if ($type === 'clip') {
                $freegal_db->freegalAudioTable($audio_array, $prod_id, 'clip');
            } else {
                $freegal_db->freegalAudioTable($audio_array, $prod_id, 'song');
            }
        }
    }

    function orchard_countries_processing($track_array, $upc, $ProdID, $new, $came_for) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
        switch ($came_for) {
            case 'I':
                $track_array['ioda_track_id'] = $ProdID;
                $orchard_db->orchardCountriesInsert($track_array, $upc);
                break;


            case 'U':
                $track_array['ioda_track_id'] = $ProdID;
                $orchard_db->orchardCountriesUpdate($track_array, $upc);
                break;


            case 'D':
                $track_array['ioda_track_id'] = $ProdID;
                $orchard_db->orchardCountriesUpdate($track_array, $upc);
                break;
        }
    }

    function freegal_genre_processing($albums_details, $prod_id, $new) {
        $freegal_db = new FreegalDatabase($this->batch_name, $this->upc);
        $freegal_db->freegalCombineGenreCheck($albums_details['primary_genre']);

        switch ($albums_details['flag']) {
            case 'I':
                if ($prod_id > 0) {
                    $freegal_db->freegalGenreInsert($albums_details, $prod_id);
                }
                break;


            case 'U':
                if ($new) {
                    if ($prod_id > 0) {
                        $freegal_db->freegalGenreInsert($albums_details, $prod_id);
                    }
                } else {
                    $freegal_db->freegalGenreUpdate($albums_details, $prod_id);
                }
                break;
            case 'D':
                break;
        }
    }

    function freegal_territories_processing($track_array, $albums_details, $prod_id, $new) {
        $freegal_db = new FreegalDatabase($this->batch_name, $this->upc);
        switch ($albums_details['flag']) {
            case 'I':
            case 'U':
                $freegal_db->freegalTerritoriesUpdate($track_array, $albums_details, $prod_id);
                break;

            case 'D':
                $freegal_db->updateCountries($prod_id, $albums_details['album_detail']['primary_release_date'], FreegalDatabase::TAKE_DOWN);
                break;
        }
    }

    function orchard_artist_processing($album_xml_path, $upc) {
        $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);

        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');

        $fp_obj = new FileProcess();

        $insert_artist = $fp_obj->checkArtistInfo($xmlArray['ProductArtist']);
        $insert_artist['upc'] = $upc;



        if (isset($insert_artist['artist_id']) && $insert_artist['artist_id'] != null) {
            $whereCondition = " where upc =$upc";
            $orchard_db->deleteTableQuery('artist', $whereCondition);
            $orchard_db->insertTableQuery($insert_artist, 'artist');
        }
    }

    function orchard_parser_report($batch_name) {
        $orchard_db = new OrchardDatabase($batch_name);
        $parser_report = $orchard_db->orchardParserReport($batch_name);
        $mail_message = "   Hi Rob,
                
                            Please see the import results below for Batch no : $batch_name

                            Manifest Filename:  manifest.txt
                            Number of Xml files processed for Insert(CompleteAlbum) : " . $parser_report['total_insert'] . "
                            Number of Xml files processed for Update(MetadataOnlyUpdate) : " . $parser_report['total_updated'] . "
                            Number of Xml files processed for Delete(Takedown) : " . $parser_report['total_down'] . " 
                                
                            Thanks";

        $this->log->writeError(Logs::PATCH_REPORT, $batch_name, null, $mail_message);

    }

    function orchard_file_processing($album_array, $album_path, $prod_id, $new) {
        if ($new) {
            $orchard_db = new OrchardDatabase($this->batch_name, $this->upc);
            $insert_file_array = array();
            $insert_file_array['file_name'] = $album_array['original_clip_name'];
            $insert_file_array['file_size'] = filesize($album_path . '/' . $album_array['original_clip_name']);
            $insert_file_array['file_format'] = 'mp3';
            $insert_file_array['fk_ioda_track_id'] = $prod_id;
            $insert_file_array['isrc'] = $album_array['isrc'];
            $insert_file_array['cdn_filepath'] = $album_array['CdnPath'];
            $insert_file_array['cdn_filename'] = $album_array['new_clip_name'];
            $insert_file_array['upc'] = $album_array['upc'];
            $orchard_db->insertTableQuery($insert_file_array, 'files');

            $insert_file_array['file_name'] = $album_array['original_track_name'];
            $insert_file_array['file_size'] = filesize($album_path . '/' . $album_array['original_track_name']);
            $insert_file_array['file_format'] = 'mp3';
            $insert_file_array['fk_ioda_track_id'] = $prod_id;
            $insert_file_array['isrc'] = $album_array['isrc'];
            $insert_file_array['cdn_filepath'] = $album_array['CdnPath'];
            $insert_file_array['cdn_filename'] = $album_array['new_track_name'];
            $insert_file_array['upc'] = $album_array['upc'];
            $orchard_db->insertTableQuery($insert_file_array, 'files');
            $orchard_db->closeConnection();
        }
    }

    function orchardFileProcessing($track, $batch) {
        $orchard_db = new OrchardDatabase($batch, $track['upc']);
        $track_check = $orchard_db->checkTrackFile($track['isrc'], $track['upc']);
        if (empty($track_check)) {
            $insert_file_array = array();
            $insert_file_array['file_name'] = $track['original_clip_name'];
            $insert_file_array['file_size'] = $track['clip_file_size'];
            $insert_file_array['file_format'] = 'mp3';
            $insert_file_array['fk_ioda_track_id'] = 0;
            $insert_file_array['isrc'] = $track['isrc'];
            $insert_file_array['cdn_filepath'] = $track['CdnPath'];
            $insert_file_array['cdn_filename'] = $track['new_clip_name'];
            $insert_file_array['upc'] = $track['upc'];
            $orchard_db->insertTableQuery($insert_file_array, 'files');

            unset($insert_file_array);
            $insert_file_array = array();
            $insert_file_array['file_name'] = $track['original_track_name'];
            $insert_file_array['file_size'] = $track['track_file_size'];
            $insert_file_array['file_format'] = 'mp3';
            $insert_file_array['fk_ioda_track_id'] = 0;
            $insert_file_array['isrc'] = $track['isrc'];
            $insert_file_array['cdn_filepath'] = $track['CdnPath'];
            $insert_file_array['cdn_filename'] = $track['new_track_name'];
            $insert_file_array['upc'] = $track['upc'];
            $orchard_db->insertTableQuery($insert_file_array, 'files');
        }

        $orchard_db->closeConnection();
    }

    function orchardCountriesProcessing($track, $batch) {
        $orchard_db = new OrchardDatabase($batch, $track['upc']);
        $check_in_countries = $orchard_db->orchardCountriesCheckSong($track['isrc'], $track['upc']);
        if (empty($check_in_countries)) {
            $orchard_db->orchardCountriesInsert($track, $track['upc']);
        } else {
            $orchard_db->orchardCountriesUpdate($track, $track['upc']);
        }
        $orchard_db->closeConnection();
    }

    function orchardTracksProcessing($track, $batch) {
        $orchard_db = new OrchardDatabase($batch, $track['upc']);
        $check_in_track = $orchard_db->checkTrack($track['isrc'], $track['upc']);
        if (empty($check_in_track)) {
            $orchard_db->orchardTracksInsert($track);
        } else {
            $orchard_db->orchardTracksUpdate($track);
        }
        $orchard_db->closeConnection();
    }

    function createPathCDN( $album_id ) {
    
    	$album_id  = str_split($album_id, 3);
    
    	$dirpath = implode('/', $album_id);
    	$dirpath = $dirpath . '/';
    
    	return $dirpath;
    }

    function freegalProductTable($batch, $upc, $album_prod_id) {
        $freegal_db = new FreegalDatabase($batch, $upc);
        $in_product = $freegal_db->checkProduct($album_prod_id);
        if (empty($in_product)) {
            $insert_product['ProdID'] = $album_prod_id;
            $insert_product['provider_type'] = 'ioda';
            $freegal_db->insertTableQuery($insert_product, 'PRODUCT');
        }
        $freegal_db->closeConnection();
    }

    function freegalAlbumGenreTable($batch, $upc, $albums_detail) {
        $freegal_db = new FreegalDatabase($batch, $upc);
        $freegal_db->freegalCombineGenreCheck($albums_detail['primary_genre']);
        $in_genre = $freegal_db->checkProdIDGenre($albums_detail['ProdID']);
        if (empty($in_genre)) {
            $genre_insert['ProdID'] = $albums_detail['ProdID'];
            $genre_insert['Genre'] = str_replace('"', '', $albums_detail['primary_genre']);
            $genre_insert['SubGenre'] = $albums_detail['sub_genre'];
            $temp = $freegal_db->freegalCombineGenreCheck($genre_insert['Genre'], true);
            $genre_insert['expected_genre'] = $temp['expected_genre'];
            $genre_insert['provider_type'] = 'ioda';
            $freegal_db->insertTableQuery($genre_insert, 'Genre');
        } else {
            $genre_insert['Genre'] = str_replace('"', '', $albums_detail['primary_genre']);
            $genre_insert['SubGenre'] = $albums_detail['sub_genre'];
            $temp = $freegal_db->freegalCombineGenreCheck($genre_insert['Genre'], true);
            $genre_insert['expected_genre'] = $temp['expected_genre'];

            $where_condition = " where ProdID =" . $albums_detail['ProdID'] . " and provider_type='ioda' ";
            $freegal_db->updateTableQuery($genre_insert, 'Genre', $where_condition);
        }
        $freegal_db->closeConnection();
    }

    function freegalSongsProcessing($track_array, $album_detail, $insert = false) {
        $freegal_db = new FreegalDatabase($album_detail['patch_name'], $album_detail['upc']);

        $insert_track['ISRC'] = $track_array['isrc'];
        $insert_track['ReferenceID'] = $album_detail['ProdID'];
        $insert_track['ProductID'] = $album_detail['ProductID'];
        $insert_track['Title'] = $album_detail['AlbumTitle'];
        $insert_track['SongTitle'] = $track_array['title'];
        $insert_track['ISRC'] = $track_array['isrc'];
        $insert_track['Composer'] = '';
        $insert_track['DownloadStatus'] = $album_detail['DownloadStatus'];
        $insert_track['StreamingStatus'] = $album_detail['StreamingStatus'];
        $insert_track['UpdateOn'] = date('Y-m-d H:i:s');
        $insert_track['Sample_Duration'] = '00:30';
        $insert_track['FullLength_Duration'] = $track_array['track_time'];
        $insert_track['sequence_number'] = $track_array['sequence'];
        $insert_track['ArtistText'] = $track_array['ParticipantName'];
        $insert_track['Artist'] = $track_array['ParticipantName'];
        $insert_track['Genre'] = $album_detail['primary_genre'];
        $insert_track['Advisory'] = ($track_array['explicit_lyrics'] != 'false') ? 'T' : 'F';


        if ($insert) {
            $insert_track['Sample_SaveAsName'] = $track_array['new_clip_name'];
            $insert_track['FullLength_SaveAsName'] = $track_array['new_track_name'];
            $insert_track['CdnPath'] = $track_array['CdnPath'];
            //insert song details
            $product_song['ProdID'] = $insert_track['ProdID'];
            $product_song['provider_type'] = 'ioda';
            $this->insertTableQuery($product_song, 'PRODUCT');
        } else {
            $where_condition = " Where ProdID='" . $track_array['ProdID'] . "' and provider_type='ioda'";
            $freegal_db->updateTableQuery($insert_track, 'Songs', $where_condition);
        }
    }

}
