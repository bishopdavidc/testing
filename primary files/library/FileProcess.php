<?php

// setting the default for parser
set_time_limit(0);
error_reporting(1);

//set timezone
date_default_timezone_set('America/New_York');

//inclusion of files which are required
//include_once('library/config.php');
include_once('library/mp3_id3v11.php');
include_once('library/getid3/getid3.php');
include_once('library/getid3/write.php');
include_once('library/SimpleImage.php');

include_once('library/FreegalDatabase.php');
include_once('library/OrchardDatabase.php');
include_once('library/Logs.php');
include_once('content_invalidation.php');

class FileProcess
{

    function sshConnection()
    {
        $cdn_obj = ssh2_connect(SFTP_HOST, SFTP_PORT);
        if ($cdn_obj == false)
        {
            return false;
        }
        return $cdn_obj;
    }

    function sshAuth($cdn_obj)
    {
        $cdn_auth = ssh2_auth_password($cdn_obj, SFTP_USER, SFTP_PASS);
        if ($cdn_auth == false)
        {
            return false;
        }
        return true;
    }

    /**
     * This method checks if the album folder is present or not at
     * CDN server.
     * 
     * @param resource $sftp
     * @param string $dir_path
     * @return boolean
     */
    function checkCDNFolder($sftp, $dir_path)
    {
        if (!is_dir("ssh2.sftp://$sftp/published/$dir_path"))
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    function checkFileCDN($sftp, $file)
    {
        if (!file_exists("ssh2.sftp://$sftp/published/$file"))
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * 
     * @param resource $sftp
     * @param string $dir_path
     * @return boolean
     */
    /*function createCDNFolder($sftp, $dir_path)
    {
        if (ssh2_sftp_mkdir($sftp, $dir_path))
        {
            return true;
        }
        else
        {
            $dir_path_array = explode('/', $dir_path);
            $new_path = CDNPATH . "/";
            for ($i = 1; $i < count($dir_path_array) - 1; $i++)
            {
                $new_path .= $dir_path_array[$i] . "/";
                if (!$this->checkCDNFolder($sftp, $new_path))
                {
                    ssh2_sftp_mkdir($sftp, $new_path);
                }
            }
            return true;
        }
    }*/
    
    function createCDNFolder( $sftp, $dir_path ) {
    	
    	if( strpos( $dir_path, 'ioda' ) !== false ) {
    		
    		$flag = false;
    		$count = 0;
    		
    		while ( $count < 10 && $flag == false ) {
    		
    			if ( !$this->checkCDNFolder( $sftp, $dir_path ) ) {
    				ssh2_sftp_mkdir( $sftp, $dir_path );
    			} else {
    				$flag = true;
    				$count = 10;
    			}
    			$count ++;
    		}
    		
    		if ( !$this->checkCDNFolder( $sftp, $dir_path ) ) {
    			return false;
    		} else {
    			return true;
    		}
    	} else {
    		$dir_path_array = explode( '/', $dir_path );
    		
    		$new_path = CDNPATH . "/";
    		
    		for ( $i = 1; $i < count( $dir_path_array ); $i++ ) {
    		
    			$new_path .= trim( $dir_path_array[$i] ) . "/";
    		
    			$flag = false;
    			$count = 0;
    		
    			while ( $count < 10 && $flag == false ) {
    		
    				if ( !$this->checkCDNFolder( $sftp, $new_path ) ) {
    					ssh2_sftp_mkdir( $sftp, $new_path );
    				} else {
    					$flag = true;
    					$count = 10;
    				}
    				$count ++;
    			}
    		}
    		
    		if ( !$this->checkCDNFolder( $sftp, $new_path ) ) {
    			return false;
    		} else {
    			return true;
    		}	
    	}
    }

    /**
     * 
     * @param type $file_path
     * @param type $sftp
     * @param type $batch
     * @param type $upc
     */
    function cdnFileDelete($file_path, $sftp, $batch, $upc)
    {
        $log = new Logs($batch, $upc);
        $file = '/published/' . $file_path;
        if (ssh2_sftp_unlink($sftp, $file))
        {
            $log->writeAlbumLog("$file deleted \n", $upc);
        }
        else
        {
            $log->writeAlbumLog("$file not deleted \n", $upc);
        }
    }

    /**
     * This method is used to upload the files to 
     * CDN folder to the respective folder
     * @param type $connection
     * @param type $type
     * @param type $album_files_array
     * @param type $cdn_path
     * @param type $patch_folder
     * @param type $upc
     */
    function uploadFiles($connection, $type, $album_files_array, $cdn_path, $patch_folder, $upc = null)
    {
        $log = new Logs($patch_folder, $upc);

        switch ($type)
        {
            case 'image' :
                foreach ( $album_files_array as $id => $values ) {

                    if (file_exists($album_files_array[$id]['path'])) {
                    	
                    	$local_file  = $album_files_array[$id]['path'];
                    	$remote_file = $cdn_path . '/' . $album_files_array[$id]['file_name'];
                    	$log->writeAlbumLog($local_file . '-->' . $remote_file, $upc);

                    	$command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $local_file . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $remote_file;
                    	
                    	$flag = false;
                    	$retry = 0;

                    	while ( $retry < 30 && $flag == false ) {
                    	
                    		$status = exec( $command, $output, $serverResponse );
                    	
                    		if ( $serverResponse > 0 ) {
                    			$retry++;
                    			unset($output);
                    			unset($serverResponse);
                    			sleep(5);
                    			continue;
                    		} else {
                    			$retry = 30;
                    			$flag = true;
                    		}
                    	}

                    	if ( $flag == false ) {
                    		$patch_log = new Logs($patch_folder, $upc);
                    		$patch_log->sendMail("The Album image is not uploaded " . $local_file . ' ----->>> ' . $remote_file, "UPC : $upc Image uploading Fails", Logs::MYSQL_LOGS);
                    		exit();
                    	}
                    	unset($output);
                    }
                }
                break;

            case 'mp3':
            	foreach ( $album_files_array as $id => $files ) {
            		//mp3 file source path
            		$mp3_file_src = ROOTPATH . $patch_folder . "/" . (float) $upc . "/" . $album_files_array[$id]['new_track_name'];
            		$log->writeAlbumLog($mp3_file_src, $upc);

            		if ( file_exists( $mp3_file_src ) ) {
            			//mp3 file target path
            			$new_track_name = str_replace('//', '/', $cdn_path . '/' . $album_files_array[$id]['new_track_name']);
				invalidateContent( $new_track_name );
            			$command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $mp3_file_src . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $new_track_name;
            	
            			$flag = false;
            			$retry = 0;
            	
            			while ( $retry < 30 && $flag == false ) {

            				$status = exec( $command, $output, $serverResponse );

            				if ( $serverResponse > 0 ) {
            					$retry++;
            					unset($output);
            					unset($serverResponse);
            					sleep(5);
            					continue;
            				} else {
            					$retry = 30;
            					$flag = true;
            				}
            			}

            			$log->writeAlbumLog($command, $upc);

            			if ( $flag == false ) {
            				$log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
            				$patch_log = new Logs($patch_folder, $upc);

            				if ( !empty( $status ) && strlen( $status ) > 5 ) {
            					$patch_log->sendMail("$command was executed but we got the following error.\n Error : $status \n", 'Orcahrd Parser : ASPERA Fails');
            				} else {
            					$patch_log->sendMail("$command was executed but we got the following error.\n Error : We have tried 30 times to establish the connection with CDN but not able to connect. \n", 'Orcahrd Parser : ASPERA Fails');
            				}
            				exit();
            			}
            			unset($output);
            		}
            	}
                break;

            case 'mp4':
                foreach ( $album_files_array as $id => $files ) {
                	//mp4 file source path
                    $mp4_file_src = ROOTPATH . $patch_folder . "/" . (float) $upc . "/" . $album_files_array[$id]['mp4_track_name'];
                    $log->writeAlbumLog($mp4_file_src, $upc);

                    if ( file_exists( $mp4_file_src ) ) {
                    	//mp4 file target path
                        $mp4_track_name = str_replace('//', '/', $cdn_path . '/' . $album_files_array[$id]['mp4_track_name']);
			invalidateContent( $mp4_track_name );
                        $command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $mp4_file_src . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $mp4_track_name;

                        $flag = false;
                        $retry = 0;
                         
                        while ( $retry < 30 && $flag == false ) {
                        
                        	$status = exec( $command, $output, $serverResponse );
                        
                        	if ( $serverResponse > 0 ) {
                        		$retry++;
                        		unset($output);
                        		unset($serverResponse);
                        		sleep(5);
                        		continue;
                        	} else {
                        		$retry = 30;
                        		$flag = true;
                        	}
                        }

                        $log->writeAlbumLog($command, $upc);

                        if ( $flag == false ) {
                        	$log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
                            $patch_log = new Logs($patch_folder, $upc);
                        
                        	if ( !empty( $status ) && strlen( $status ) > 5 ) {
                        		$patch_log->sendMail("$command was executed but we got the following error.\n Error : $status \n", 'Orcahrd Parser : ASPERA Fails');
                        	} else {
                        		$patch_log->sendMail("$command was executed but we got the following error.\n Error : We have tried 30 times to establish the connection with CDN but not able to connect. \n", 'Orcahrd Parser : ASPERA Fails');
                        	}
                        	exit();
                        }
                        unset($output);
                    }
                }
                break;

            case 'clip':
                foreach ( $album_files_array as $id => $files ) {
                	//clip file source path
                    $clip_file_src = ROOTPATH . $patch_folder . "/" . (float) $upc . "/" . $album_files_array[$id]['new_clip_name'];
                    $log->writeAlbumLog($clip_file_src, $upc);

                    if ( file_exists( $clip_file_src ) ) {
                    	//clip file target path
                        $clip_file_new = str_replace('//', '/', $cdn_path . '/' . $album_files_array[$id]['new_clip_name']);
			invalidateContent( $clip_file_new );
                        $command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $clip_file_src . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $clip_file_new;

                        $flag = false;
                        $retry = 0;

                        while ( $retry < 30 && $flag == false ) {

                        	$status = exec( $command, $output, $serverResponse );

                        	if ( $serverResponse > 0 ) {
                        		$retry++;
                        		unset($output);
                        		unset($serverResponse);
                        		sleep(5);
                        		continue;
                        	} else {
                        		$retry = 30;
                        		$flag = true;
                        	}
                        }

                        $log->writeAlbumLog($command, $upc);

                        if ( $flag == false ) {
                        	$log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
                            $patch_log = new Logs($patch_folder, $upc);
                        
                        	if ( !empty( $status ) && strlen( $status ) > 5 ) {
                        		$patch_log->sendMail("$command was executed but we got the following error.\n Error : $status \n", 'Orcahrd Parser : ASPERA Fails');
                        	} else {
                        		$patch_log->sendMail("$command was executed but we got the following error.\n Error : We have tried 30 times to establish the connection with CDN but not able to connect. \n", 'Orcahrd Parser : ASPERA Fails');
                        	}
                        	exit();
                        }
                        unset($output);
                    }
                }
                break;
        }
    }

    /**
     * This method is used to check for 0 size mp3/mp4 file at
     * CDN server if found the just upload the new mp3/mp4 files.
     * 
     * @param resource $sftp
     * @param string $dir_path
     * @param array $album_details
     * @param string $patch_folder
     */
    function checkZeroSizeFile($sftp, $cdn_path, $album_files_array, $patch_folder, $upc)
    {
        $log = new Logs($patch_folder, $upc);

        foreach ($album_files_array as $id => $files)
        {
            $mp3_file_src = ROOTPATH . $patch_folder . "/" . $upc . "/" . $album_files_array[$id]['new_track_name'];
            if (file_exists($mp3_file_src))
            {
                $output = array();
                $serverResponse = '';
                $mp3_file_size = filesize('ssh2.sftp://' . $sftp . '/published/' . $cdn_path . '/' . $album_files_array[$id]['new_track_name']);
                if ($mp3_file_size < 1)
                {
                    $clip_file_new = $cdn_path . '/' . $album_files_array[$id]['new_track_name'];
                    $clip_file_src = ROOTPATH . $patch_folder . "/" . $upc . "/" . $album_files_array[$id]['new_track_name'];
                    $command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $clip_file_src . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $clip_file_new;

                    $flag = false;
                    $retry = 0;

                    while ( $retry < 30 && $flag == false ) {
                    
                    	$status = exec( $command, $output, $serverResponse );
                    
                    	if ( $serverResponse > 0 ) {
                    		$retry++;
                    		unset($output);
                    		unset($serverResponse);
                    		sleep(5);
                    		continue;
                    	} else {
                    		$retry = 30;
                    		$flag = true;
                    	}
                    }

                    $log->writeAlbumLog($command, $upc);

                    if ( $flag == false ) {
                    
                    	$log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
                        $patch_log = new Logs($patch_folder, $upc);
                    
                    	if ( !empty( $status ) && strlen( $status ) > 5 ) {
                    		$patch_log->sendMail("$command was executed but we got the following error.\n Error : $status \n", 'Orcahrd Parser : ASPERA Fails');
                    	} else {
                    		$patch_log->sendMail("$command was executed but we got the following error.\n Error : We have tried 30 times to establish the connection with CDN but not able to connect. \n", 'Orcahrd Parser : ASPERA Fails');
                    	}
                    	exit();
                    }
                    unset($output);
                }
            }

            $output = array();
            $serverResponse = '';
            $mp4_file_src = ROOTPATH . $patch_folder . "/" . $upc . "/" . $album_files_array[$id]['mp4_track_name'];
            if (file_exists($mp4_file_src))
            {
                $mp4_file_size = filesize('ssh2.sftp://' . $sftp . '/published/' . $cdn_path . '/' . $album_files_array[$id]['mp4_track_name']);
                if ($mp4_file_size < 1)
                {
                    $track_file_new = $cdn_path . '/' . $album_files_array[$id]['mp4_track_name'];
                    $track_file_src = ROOTPATH . $patch_folder . "/" . $upc . "/" . $album_files_array[$id]['mp4_track_name'];
                    $command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $track_file_src . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $track_file_new;

                    $flag = false;
                    $retry = 0;
                    
                    while ( $retry < 30 && $flag == false ) {

                    	$status = exec( $command, $output, $serverResponse );

                    	if ( $serverResponse > 0 ) {
                    		$retry++;
                    		unset($output);
                    		unset($serverResponse);
                    		sleep(5);
                    		continue;
                    	} else {
                    		$retry = 30;
                    		$flag = true;
                    	}
                    }

                    $log->writeAlbumLog($command, $upc);

                    if ( $flag == false ) {
                    
                    	$log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
                        $patch_log = new Logs($patch_folder, $upc);
                    
                    	if ( !empty( $status ) && strlen( $status ) > 5 ) {
                    		$patch_log->sendMail("$command was executed but we got the following error.\n Error : $status \n", 'Orcahrd Parser : ASPERA Fails');
                    	} else {
                    		$patch_log->sendMail("$command was executed but we got the following error.\n Error : We have tried 30 times to establish the connection with CDN but not able to connect. \n", 'Orcahrd Parser : ASPERA Fails');
                    	}
                    	exit();
                    }
                    unset($output);
                }
            }
            $output = array();
            $serverResponse = '';
            $clip_file_src = ROOTPATH . $patch_folder . "/" . $upc . "/" . $album_files_array[$id]['new_clip_name'];
            if (file_exists($clip_file_src))
            {
                $clip_file_size = filesize('ssh2.sftp://' . $sftp . '/published/' . $cdn_path . '/' . $album_files_array[$id]['new_clip_name']);
                if ($clip_file_size < 1)
                {
                    $clip_file_new = $cdn_path . '/' . $album_files_array[$id]['new_clip_name'];
                    $clip_file_src = ROOTPATH . $patch_folder . "/" . $upc . "/" . $album_files_array[$id]['new_clip_name'];
                    $command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $clip_file_src . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $clip_file_new;
                    
                    $flag = false;
                    $retry = 0;
                    
                    while ( $retry < 30 && $flag == false ) {
                    
                    	$status = exec( $command, $output, $serverResponse );
                    
                    	if ( $serverResponse > 0 ) {
                    		$retry++;
                    		unset($output);
                    		unset($serverResponse);
                    		sleep(5);
                    		continue;
                    	} else {
                    		$retry = 30;
                    		$flag = true;
                    	}
                    }
                    
                    $log->writeAlbumLog($command, $upc);

                    if ( $flag == false ) {
                    
                    	$log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
                        $patch_log = new Logs($patch_folder, $upc);
                    
                    	if ( !empty( $status ) && strlen( $status ) > 5 ) {
                    		$patch_log->sendMail("$command was executed but we got the following error.\n Error : $status \n", 'Orcahrd Parser : ASPERA Fails');
                    	} else {
                    		$patch_log->sendMail("$command was executed but we got the following error.\n Error : We have tried 30 times to establish the connection with CDN but not able to connect. \n", 'Orcahrd Parser : ASPERA Fails');
                    	}
                    	exit();
                    }
                    unset($output);
                }
            }
        }
    }

    /**
     * Check the file size on the CDN server
     * 
     * @param type $sftp_cdn
     * @param type $remote_file
     * @return boolean
     */
    function checkFileSize($sftp_cdn, $remote_file, $batch = null, $upc = null)
    {
        if ($batch != null)
        {
            $log = new Logs($batch, $upc);
            $file = '/published/' . $remote_file;
            $log->writeAlbumLog("$file " . filesize('ssh2.sftp://' . $sftp_cdn . '/published/' . $remote_file), $upc);
        }
        $file_size = filesize('ssh2.sftp://' . $sftp_cdn . '/published/' . $remote_file);
        if ($file_size > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function createServerPath($album_id)
    {
        if (!file_exists(SERVER_PATH . $this->createPathCDN($album_id)))
        {
            if (!mkdir(SERVER_PATH . $this->createPathCDN($album_id), 0777, TRUE))
            {
//TODO error log
            }
        }
        else
        {
            echo 'Folder found';
        }
    }

    function createPathCDN( $album_id ) {
    
    	$album_id  = str_split($album_id, 3);
    
    	$dirpath = implode('/', $album_id);
    	$dirpath = $dirpath . '/';
    
    	return $dirpath;
    }

    /**
     * This function is used to scan folder and return an array of files after removing the '.' , '..' from the array
     * @param string $dir_path
     * @return array
     */
    function getFolderFilesList($dir_path)
    {
        $temp_array = scandir($dir_path);
        unset($temp_array[array_search('.', $temp_array)]);
        unset($temp_array[array_search('..', $temp_array)]);

        return $temp_array;
    }

    /**
     * This method is used to search / get the count of particular type files
     * like mp3 / images / mp4 type.
     * 
     * @param string $dir_path
     * @param string $type
     * @param boolean $count
     * @return array / int
     */
    function getFolderFilesByType($dir_path, $type, $count = false)
    {
        $search = $dir_path . '/*' . $type;
        if (!$count)
        {
            return ( count(glob($search)) > 0) ? true : false;
        }
        else
        {
            return glob($search);
        }
    }

    /**
     * This method is used to return the no of
     * mp3 files present in the current patch.
     * 
     * @param string $dir_path
     * @return int
     */
    function getFilesCountFromPatch($dir_path)
    {
        $count = 0;
        $albums = scandir($dir_path);
        foreach ($albums as $album)
        {
            $tmpcount = count(($this->getFolderFilesByType($dir_path . "/" . $album, '.mp3', 'true')));
            $count = $count + (int) $tmpcount;
        }
        return $count;
    }

    function checkManifest($dir_array)
    {
        if (array_search('manifest.txt', $dir_array))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function checkBatchFolder($batch_folder)
    {
        if (file_exists($batch_folder))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function checkManifestSize($file)
    {
        if (!filesize($file))
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    function checkDelivery($dir_array)
    {
        if (array_search('delivery.complete', $dir_array))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function checkForRenamedFiles($dir_path)
    {
//for CLIP files
        $search = $dir_path . '/*_CLIP.mp3';
        $clip_tracks = glob($search);

        foreach ($clip_tracks as $clip)
        {
            $clip_title = explode('/', $clip);
            $clip_title_count = count(explode('_', $clip_title[count($clip_title) - 1]));

            if ($clip_title_count > 4)
            {
                echo $command = "rm -fr $clip";
                exec($command);
            }
        }

        $search = $dir_path . '/*.mp3';
        $mp3_tracks = glob($search);
        $mp3_tracks = array_diff($mp3_tracks, $clip_tracks);
        foreach ($mp3_tracks as $track)
        {
            $track_title = explode('/', $track);
            $track_title_count = count(explode('_', $track_title[count($track_title) - 1]));

            if ($track_title_count > 3)
            {
                echo $command = "rm -fr $track";
                exec($command);
            }
        }

        $search = $dir_path . '/*.mp4';
        $mp4_tracks = glob($search);

        foreach ($mp4_tracks as $track)
        {
            $track_title = explode('/', $track);
            $track_title_count = count(explode('_', $track_title[count($track_title) - 1]));

            if ($track_title_count > 3)
            {
                echo $command = "rm -fr $track";
                exec($command);
            }
        }
    }

    /**
     * This function is used to resixe the Album image
     * and create the new image with the specified name
     * 
     * @param type $image_src
     * @param type $image_name
     * @param type $height
     * @param type $width
     */
    function resizeAlbumImage($image_src, $image_name, $height, $width)
    {
        $image_resize = new SimpleImage();
        $image_resize->load($image_src);
        $image_resize->resize($width, $height);
        $image_resize->save($image_name);
    }

    /**
     * This method is used to create array for album images 
     * with there name , format, width , height and CDN path
     * which is used to insert/update in the Orchard DB->image table
     * 
     * @param array $album_image_array
     * @param string $patch_name
     * @return array
     */
    function albumImageArray($album_image_array, $patch_name)
    {
        $image_array = array();
        foreach ($album_image_array as $image)
        {
            //get Image size        
            $insert_image['format'] = 'jpg';
            $imageWidthHeight = getimagesize($image);
            if ($imageWidthHeight)
            {
                $insert_image['width'] = $imageWidthHeight['0'];
                $insert_image['height'] = $imageWidthHeight['1'];
            }
            $image_temp = explode('/', $image);
            $insert_image['file_name'] = $image_temp[count($image_temp) - 1];
            $insert_image['upc'] = $image_temp[count($image_temp) - 2];
            $insert_image['path'] = ROOTPATH . $patch_name . "/" . $insert_image['upc'] . "/" . $insert_image['file_name'];
            array_push($image_array, $insert_image);
        }
        return $image_array;
    }

    /**
     * This method is used to create array for album images 
     * with there name , format, width , height and CDN path
     * which is used to insert/update in the Orchard DB->image table
     * 
     * @param array $album_image_array
     * @param string $patch_name
     * @return array
     */
    function albumImageDetailArray($album_image_array, $patch_name)
    {
        $image_array = array();
        foreach ($album_image_array as $image)
        {
            //get Image size        
            $insert_image['format'] = 'jpg';
            $imageWidthHeight = getimagesize($image);
            if ($imageWidthHeight)
            {
                $insert_image['width'] = $imageWidthHeight['0'];
                $insert_image['height'] = $imageWidthHeight['1'];
            }
            $image_temp = explode('/', $image);
            $insert_image['file_name'] = $image_temp[count($image_temp) - 1];
            $insert_image['upc'] = $image_temp[count($image_temp) - 2];
            $insert_image['path'] = PATCH_DETAILS . $patch_name . "/archive/" . $insert_image['upc'] . "/" . $insert_image['file_name'];
            array_push($image_array, $insert_image);
        }
        return $image_array;
    }

    /**
     * Checking tags availibility in XML for Album
     * 
     * @return type array
     */
    function albumDetails($album_xml_path)
    {
        $album_xml_object = simplexml_load_file($album_xml_path);
        $xmlArray = json_decode(json_encode($album_xml_object), 'true');
        $insert_album = array();

        if ($xmlArray['PrimaryReleaseDate'])
            $insert_album['primary_release_date'] = $xmlArray['PrimaryReleaseDate'];
        if ($xmlArray['ProductDescription'])
            $insert_album['product_description'] = $xmlArray['ProductDescription'];
        if ($xmlArray['ProductDuration'])
            $insert_album['duration'] = $xmlArray['ProductDuration'];
        if ($xmlArray['Language'])
            $insert_album['language'] = $xmlArray['Language'];
        if ($xmlArray['ProductName'])
            $insert_album['product_name'] = $xmlArray['ProductName'];
        if ($xmlArray['PrimarySaleStartDate'])
            $insert_album['primary_sales_start_date'] = $xmlArray['PrimarySaleStartDate'];
        if ($xmlArray['CLine'])
            $insert_album['cline'] = $xmlArray['CLine'];
        if ($xmlArray['Label'])
            $insert_album['label'] = $xmlArray['Label'];
        if ($xmlArray['Genre']['PrimaryGenre'])
            $insert_album['primary_genre'] = $xmlArray['Genre']['PrimaryGenre'];
        if ($xmlArray['Genre']['SubGenres']['SubGenre'])
            $insert_album['sub_genre'] = $xmlArray['Genre']['SubGenres']['SubGenre'];
        if ($xmlArray['@attributes']['volumeCount'])
            $insert_album['volume_count'] = $xmlArray['@attributes']['volumeCount'];
        if ($xmlArray['@attributes']['trackCount'])
            $insert_album['track_count'] = $xmlArray['@attributes']['trackCount'];
        if ($xmlArray['@attributes']['dateCreated'])
            $insert_album['date_created'] = $xmlArray['@attributes']['dateCreated'];
        if ($xmlArray['Moods'])
        {
            if (is_integer(key($xmlArray['Moods']['Mood'])))
            {
                $insert_album['moods'] = implode(',', $xmlArray['Moods']['Mood']);
            }
            else
            {
                $insert_album['moods'] = $xmlArray['Moods']['Mood'];
            }
        }
        if ($xmlArray['Instruments'])
        {
            if (is_integer(key($xmlArray['Instruments']['Instrument'])))
            {
                $insert_album['instruments'] = implode(',', $xmlArray['Instruments']['Instrument']);
            }
            else
            {
                $insert_album['instruments'] = $xmlArray['Instruments']['Instrument'];
            }
        }
        if ($xmlArray['Themes'])
        {
            if (is_integer(key($xmlArray['Themes']['Theme'])))
            {
                $insert_album['themes'] = implode(',', $xmlArray['Themes']['Theme']);
            }
            else
            {
                $insert_album['themes'] = $xmlArray['Themes']['Theme'];
            }
        }
        if ($xmlArray['MarketingPriority'])
            $insert_album['marketing_priority'] = $xmlArray['MarketingPriority'];
        if ($xmlArray['MarketingBlurb'])
            $insert_album['marketing_blurb'] = $xmlArray['MarketingBlurb'];
        if ($xmlArray['DeliveryType'])
            $insert_album['delivery_type'] = $xmlArray['DeliveryType'];
        if ($xmlArray['ProductArtist']['DisplayText'])
            $insert_album['artist_display'] = $xmlArray['ProductArtist']['DisplayText'];


        $removedArtists = array('Meat Shits', 'Anal Cunts');
        $insert_album['DownloadStatus'] = (in_array($insert_album['ArtistText'], $removedArtists)) ? '0' : '1';
        $insert_album['StreamingStatus'] = (in_array($insert_album['ArtistText'], $removedArtists)) ? 0 : 1;
        $insert_album['TrackBundleCount'] = 0;

        $insert_album['webpage_url'] = '';

        if ($xmlArray['ProductArtist']['WebpageURL']) {
            $insert_album['webpage_url'] = $xmlArray['ProductArtist']['WebpageURL'];
        }

        $insert_album['PublicationStatus'] = $xmlArray['DeliveryType'];
        $insert_album['LastUpdated'] = date('Y-m-d H:i:s');
        $insert_album['StatusNotes'] = '';
        $insert_album['PublicationDate'] = $xmlArray['PrimaryReleaseDate'];
        return $insert_album;
    }

    function albumXMLDetails($album_xml_object)
    {
        if ($album_xml_object->EAN)
        {
            $insert_album['upc'] = (string) $album_xml_object->EAN;
            $insert_album['UPC'] = (string) $album_xml_object->EAN;
        }
        else
        {
            $insert_album['upc'] = (string) $album_xml_object->UPC;
            $insert_album['UPC'] = (string) $album_xml_object->UPC;
        }
        if ($album_xml_object->PrimaryReleaseDate)
        {
            $insert_album['primary_release_date'] = (string) $album_xml_object->PrimaryReleaseDate;
            $insert_album['PublicationDate'] = (string) $album_xml_object->PrimaryReleaseDate;
        }
        if ($album_xml_object->ProductDescription)
        {
            $insert_album['product_description'] = (string) $album_xml_object->ProductDescription;
        }
        if ($album_xml_object->ProductDuration)
        {
            $insert_album['duration'] = (string) $album_xml_object->ProductDuration;
        }
        if ($album_xml_object->Language)
        {
            $insert_album['language'] = (string) $album_xml_object->Language;
        }
        if ($album_xml_object->ProductName)
        {
            $insert_album['product_name'] = (string) $album_xml_object->ProductName;
            $insert_album['AlbumTitle'] = (string) $album_xml_object->ProductName;
            $insert_album['Title'] = (string) $album_xml_object->ProductName;
        }
        if ($album_xml_object->PrimarySaleStartDate)
        {
            $insert_album['primary_sales_start_date'] = (string) $album_xml_object->PrimarySaleStartDate;
        }
        if ($album_xml_object->CLine)
        {
            $insert_album['cline'] = (string) $album_xml_object->CLine;
        }
        if ($album_xml_object->Label)
        {
            $insert_album['label'] = (string) $album_xml_object->Label;
            $insert_album['Label'] = (string) $album_xml_object->Label;
        }
        if ($album_xml_object->Genre->PrimaryGenre)
        {
            $insert_album['primary_genre'] = (string) $album_xml_object->Genre->PrimaryGenre;
        }
        if ($album_xml_object->Genre->SubGenres->SubGenre)
        {
            $insert_album['sub_genre'] = (string) $album_xml_object->Genre->SubGenres->SubGenre;
        }
        if ($album_xml_object->attributes()->volumeCount)
        {
            $insert_album['volume_count'] = (string) $album_xml_object->attributes()->volumeCount;
        }
        if ($album_xml_object->attributes()->trackCount)
        {
            $insert_album['track_count'] = (string) $album_xml_object->attributes()->trackCount;
        }
        if ($album_xml_object->attributes()->dateCreated)
        {
            $insert_album['date_created'] = (string) $album_xml_object->attributes()->dateCreated;
        }
        if ($album_xml_object->MarketingPriority)
        {
            $insert_album['marketing_priority'] = (string) $album_xml_object->MarketingPriority;
        }
        if ($album_xml_object->MarketingBlurb)
        {
            $insert_album['marketing_blurb'] = (string) $album_xml_object->MarketingBlurb;
        }
        if ($album_xml_object->DeliveryType)
        {
            $insert_album['delivery_type'] = (string) $album_xml_object->DeliveryType;
            $insert_album['PublicationStatus'] = (string) $album_xml_object->DeliveryType;
        }
        if ($album_xml_object->ProductArtist->DisplayText)
        {
            $insert_album['artist_display'] = (string) $album_xml_object->ProductArtist->DisplayText;
            $insert_album['ArtistText'] = (string) $album_xml_object->ProductArtist->DisplayText;
            $insert_album['Artist'] = (string) $album_xml_object->ProductArtist->DisplayText;
        }
        if ($album_xml_object->ProductArtist->WebpageURL)
        {
            $insert_album['artist_webpage_url'] = $album_xml_object->ProductArtist->WebpageURL;
        }

        if ($album_xml_object->Moods)
        {
            $insert_album['moods'] = (string) $this->getObjectToCommaSeprated($album_xml_object->Moods);
        }
        if ($album_xml_object->Instruments)
        {
            $insert_album['instruments'] = (string) $this->getObjectToCommaSeprated($album_xml_object->Instruments);
        }
        if ($album_xml_object->Instruments)
        {
            $insert_album['instruments'] = (string) $this->getObjectToCommaSeprated($album_xml_object->Instruments);
        }

        $removedArtists = array('Meat Shits', 'Anal Cunts');
        $insert_album['DownloadStatus'] = (in_array($insert_album['ArtistText'], $removedArtists)) ? '0' : '1';
        $insert_album['StreamingStatus'] = (in_array($insert_album['ArtistText'], $removedArtists)) ? 0 : 1;
        $insert_album['TrackBundleCount'] = 0;


        $insert_album['LastUpdated'] = date('Y-m-d H:i:s');
        $insert_album['StatusNotes'] = '';

        return $insert_album;
    }

    function getObjectToArray($object)
    {
        return json_decode(json_encode($object), 'true');
    }

    function getObjectToCommaSeprated($object)
    {
        $temp = json_decode(json_encode($object), 'true');
        return implode(',', $temp);
    }

    /**
     * This method fetch the details from the Album XML
     * and makes an array for the track.
     * 
     * @param array $album_xml_array
     * @param int $album_id
     * @return array
     */
    function getTracksDetails($album_xml_path, $album_id) {
        
        $album_xml_object = simplexml_load_file($album_xml_path);
        $album_xml_array = json_decode(json_encode($album_xml_object), 'true');

        $total_volune_count = $album_xml_array['@attributes']['volumeCount'];
        $total_track_count = $album_xml_array['@attributes']['trackCount'];
        $tracks_array = array();

        if ($total_volune_count > 1) {

            $count = 0;

            //multi volums in album xml
            for ($seq = 0; $seq < $total_volune_count; $seq++) {

                $album_tracks_array = $album_xml_array['Volumes']['Volume'][$seq]['Tracks']['Track'];

                $volume_track_count = $album_xml_array['Volumes']['Volume'][$seq]['@attributes']['trackCount'];

                $volume = $seq + 1;

                if ($volume_track_count > 1) {
                    
                    for ($i = 0; $i < count($album_tracks_array); $i++) {

                        $original_clip_name = $album_id . "_" . $volume . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '_CLIP.mp3';
                        $original_track_name = $album_id . "_" . $volume . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '.mp3';

                        $tracks_array[$count]['track_restricted_from'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedFrom']['Country']);
                        $tracks_array[$count]['track_restricted_to'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedTo']['Country']);
                        $tracks_array[$count]['volume_count'] = $volume;

                        $tracks_array[$count]['title'] = $album_tracks_array[$i]['TrackTitle'];
                        $tracks_array[$count]['isrc'] = $album_tracks_array[$i]['ISRC'];
                        $tracks_array[$count]['track_time'] = $this->trackTime( $album_tracks_array[$i]['TrackTime'] );
                        $tracks_array[$count]['sequence'] = $album_tracks_array[$i]['@attributes']['sequence'];

                        $tracks_array[$count]['pline'] = $album_tracks_array[$i]['Copyright']['PLine'];
                        
                        $tracks_array[$count]['rights_granted'] = $album_tracks_array[$i]['RightsGranted'];

                        // these three lines added by Ralph on 16/06/10
                        $songAvailability = $this->songAvailability($album_tracks_array[$i]['RightsGranted']);
                        $tracks_array[$count]['DownloadStatus'] = $songAvailability['DownloadStatus'];
                        $tracks_array[$count]['StreamingStatus'] = $songAvailability['StreamingStatus'];

                        $tracks_array[$count]['explicit_lyrics'] = $album_tracks_array[$i]['ExplicitLyrics'];

                        $tracks_array[$count]['original_clip_name'] = $original_clip_name;
                        $tracks_array[$count]['original_track_name'] = $original_track_name;
                        $tracks_array[$count]['upc'] = $album_id;

                        if ($album_tracks_array[$i]['Participants']['Participant']) {
                            $tracks_array[$count]['Artist'] = $this->checkParticipants($album_xml_path, $album_tracks_array[$i]['Participants']['Participant'], $album_tracks_array[$i]['ISRC'], $album_id);
                            $tracks_array[$count]['ArtistText'] = $tracks_array[$count]['Artist'];
                        }
                        $count++;
                    }

                } else {

                    $original_clip_name = $album_id . "_" . $volume . "_" . $album_tracks_array['@attributes']['sequence'] . '_CLIP.mp3';
                    $original_track_name = $album_id . "_" . $volume . "_" . $album_tracks_array['@attributes']['sequence'] . '.mp3';

                    $tracks_array[$count]['track_restricted_from'] = implode(',', (array) $album_tracks_array['TrackRestrictedFrom']['Country']);
                    $tracks_array[$count]['track_restricted_to'] = implode(',', (array) $album_tracks_array['TrackRestrictedTo']['Country']);
                    $tracks_array[$count]['volume_count'] = $volume;

                    $tracks_array[$count]['title'] = $album_tracks_array['TrackTitle'];
                    $tracks_array[$count]['isrc'] = $album_tracks_array['ISRC'];
                    $tracks_array[$count]['track_time'] = $this->trackTime($album_tracks_array['TrackTime'] );
                    $tracks_array[$count]['sequence'] = $album_tracks_array['@attributes']['sequence'];

                    $tracks_array[$count]['pline'] = $album_tracks_array['Copyright']['PLine'];
                    
                    $tracks_array[$count]['rights_granted'] = $album_tracks_array['RightsGranted'];

                    // these three lines added by Ralph on 16/06/10
                    $songAvailability = $this->songAvailability($album_tracks_array['RightsGranted']);
                    $tracks_array[$count]['DownloadStatus'] = $songAvailability['DownloadStatus'];
                    $tracks_array[$count]['StreamingStatus'] = $songAvailability['StreamingStatus'];

                    $tracks_array[$count]['explicit_lyrics'] = $album_tracks_array['ExplicitLyrics'];

                    $tracks_array[$count]['original_clip_name'] = $original_clip_name;
                    $tracks_array[$count]['original_track_name'] = $original_track_name;
                    $tracks_array[$count]['upc'] = $album_id;

                    if ($album_tracks_array['Participants']['Participant']) {
                        $tracks_array[$count]['Artist'] = $this->checkParticipants($album_xml_path, $album_tracks_array['Participants']['Participant'], $album_tracks_array['ISRC'], $album_id);
                        $tracks_array[$count]['ArtistText'] = $tracks_array[$count]['Artist'];
                    }
                    $count++;

                }

                    
            }

        } elseif ($total_track_count > 1) {

            //single volume but multi track
            $album_tracks_array = $album_xml_array['Volumes']['Volume']['Tracks']['Track'];

            for ($i = 0; $i < count($album_tracks_array); $i++) {

                $tracks_array[$i]['track_restricted_from'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedFrom']['Country']);
                $tracks_array[$i]['track_restricted_to'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedTo']['Country']);
                $tracks_array[$i]['volume_count'] = $album_xml_array['Volumes']['Volume']['@attributes']['sequence'];
                $tracks_array[$i]['title'] = $album_tracks_array[$i]['TrackTitle'];
                $tracks_array[$i]['isrc'] = $album_tracks_array[$i]['ISRC'];
                $tracks_array[$i]['track_time'] = $this->trackTime( $album_tracks_array[$i]['TrackTime'] );
                $tracks_array[$i]['sequence'] = $album_tracks_array[$i]['@attributes']['sequence'];

                $tracks_array[$i]['pline'] = $album_tracks_array[$i]['Copyright']['PLine'];

                $tracks_array[$i]['rights_granted'] = $album_tracks_array[$i]['RightsGranted'];

                // these three lines added by Ralph on 16/06/10
                $songAvailability = $this->songAvailability($album_tracks_array[$i]['RightsGranted']);
                $tracks_array[$i]['DownloadStatus'] = $songAvailability['DownloadStatus'];
                $tracks_array[$i]['StreamingStatus'] = $songAvailability['StreamingStatus'];

                $tracks_array[$i]['explicit_lyrics'] = $album_tracks_array[$i]['ExplicitLyrics'];

                $original_clip_name = $album_id . "_" . $tracks_array[$i]['volume_count'] . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '_CLIP.mp3';
                $original_track_name = $album_id . "_" . $tracks_array[$i]['volume_count'] . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '.mp3';
                $tracks_array[$i]['original_clip_name'] = $original_clip_name;
                $tracks_array[$i]['original_track_name'] = $original_track_name;
                $tracks_array[$i]['upc'] = $album_id;

                if ($album_tracks_array[$i]['Participants']['Participant']) {
                    $tracks_array[$i]['Artist'] = $this->checkParticipants($album_xml_path, $album_tracks_array[$i]['Participants']['Participant'], $album_tracks_array[$i]['ISRC'], $album_id);
                    $tracks_array[$i]['ArtistText'] = $tracks_array[$i]['Artist'];
                }
            }

        } else {
            
            //single track
            $album_tracks_array = $album_xml_array['Volumes']['Volume']['Tracks']['Track'];

            $tracks_array[0]['track_restricted_from'] = implode(',', (array) $album_tracks_array['TrackRestrictedFrom']['Country']);
            $tracks_array[0]['track_restricted_to'] = implode(',', (array) $album_tracks_array['TrackRestrictedTo']['Country']);
            $tracks_array[0]['volume_count'] = $album_xml_array['Volumes']['Volume']['@attributes']['sequence'];
            $tracks_array[0]['title'] = $album_tracks_array['TrackTitle'];
            $tracks_array[0]['isrc'] = $album_tracks_array['ISRC'];
            $tracks_array[0]['track_time'] = $this->trackTime( $album_tracks_array['TrackTime'] );
            $tracks_array[0]['sequence'] = $album_tracks_array['@attributes']['sequence'];

            $tracks_array[0]['pline'] = htmlentities($album_tracks_array['Copyright']['PLine'], 'ENT_COMPAT', 'UTF-8');

            $tracks_array[0]['rights_granted'] = $album_tracks_array['RightsGranted'];

            // these three lines added by Ralph on 16/06/10
            $songAvailability = $this->songAvailability($album_tracks_array['RightsGranted']);
            $tracks_array[0]['DownloadStatus'] = $songAvailability['DownloadStatus'];
            $tracks_array[0]['StreamingStatus'] = $songAvailability['StreamingStatus'];

            $tracks_array[0]['explicit_lyrics'] = $album_tracks_array['ExplicitLyrics'];

            $original_clip_name = $album_id . "_" . $tracks_array[0]['volume_count'] . "_" . $album_tracks_array['@attributes']['sequence'] . '_CLIP.mp3';
            $original_track_name = $album_id . "_" . $tracks_array[0]['volume_count'] . "_" . $album_tracks_array['@attributes']['sequence'] . '.mp3';
            $tracks_array[0]['original_clip_name'] = $original_clip_name;
            $tracks_array[0]['original_track_name'] = $original_track_name;
            $tracks_array[0]['upc'] = $album_id;

            if ($album_tracks_array['Participants']['Participant']) {
                $tracks_array[0]['Artist'] = $this->checkParticipants($album_xml_path, $album_tracks_array['Participants']['Participant'], $album_tracks_array['ISRC'], $album_id);
                $tracks_array[0]['ArtistText'] = $tracks_array[0]['Artist'];
            }
        }
        return $tracks_array;
    }
    /*function getTracksDetails($album_xml_path, $album_id) {
        
        $album_xml_object = simplexml_load_file($album_xml_path);
        $album_xml_array = json_decode(json_encode($album_xml_object), 'true');

        $total_volune_count = $album_xml_array['@attributes']['volumeCount'];
        $total_track_count = $album_xml_array['@attributes']['trackCount'];
        $tracks_array = array();

        if ($total_volune_count > 1) {

            $count = 0;

            //multi volums in album xml
            for ($seq = 0; $seq < $total_volune_count; $seq++) {

                $album_tracks_array = $album_xml_array['Volumes']['Volume'][$seq]['Tracks']['Track'];

                for ($i = 0; $i < count($album_tracks_array); $i++) {

                    $volume = $seq + 1;
                    $original_clip_name = $album_id . "_" . $volume . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '_CLIP.mp3';
                    $original_track_name = $album_id . "_" . $volume . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '.mp3';

                    $tracks_array[$count]['track_restricted_from'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedFrom']['Country']);
                    $tracks_array[$count]['track_restricted_to'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedTo']['Country']);
                    $tracks_array[$count]['volume_count'] = $volume;

                    $tracks_array[$count]['title'] = $album_tracks_array[$i]['TrackTitle'];
                    $tracks_array[$count]['isrc'] = $album_tracks_array[$i]['ISRC'];
                    $tracks_array[$count]['track_time'] = $this->trackTime( $album_tracks_array[$i]['TrackTime'] );
                    $tracks_array[$count]['sequence'] = $album_tracks_array[$i]['@attributes']['sequence'];

                    $tracks_array[$count]['pline'] = $album_tracks_array[$i]['Copyright']['PLine'];
                    
                    $tracks_array[$count]['rights_granted'] = $album_tracks_array[$i]['RightsGranted'];

                    // these three lines added by Ralph on 16/06/10
                    $songAvailability = $this->songAvailability($album_tracks_array[$i]['RightsGranted']);
                    $tracks_array[$count]['DownloadStatus'] = $songAvailability['DownloadStatus'];
                    $tracks_array[$count]['StreamingStatus'] = $songAvailability['StreamingStatus'];

                    $tracks_array[$count]['explicit_lyrics'] = $album_tracks_array[$i]['ExplicitLyrics'];

                    $tracks_array[$count]['original_clip_name'] = $original_clip_name;
                    $tracks_array[$count]['original_track_name'] = $original_track_name;
                    $tracks_array[$count]['upc'] = $album_id;

                    if ($album_tracks_array[$i]['Participants']['Participant']) {
                        $tracks_array[$count]['Artist'] = $this->checkParticipants($album_xml_path, $album_tracks_array[$i]['Participants']['Participant'], $album_tracks_array[$i]['ISRC'], $album_id);
                        $tracks_array[$count]['ArtistText'] = $tracks_array[$i]['Artist'];
                    }
                    $count++;
                }
            }

        } elseif ($total_track_count > 1) {

            //single volume but multi track
            $album_tracks_array = $album_xml_array['Volumes']['Volume']['Tracks']['Track'];

            for ($i = 0; $i < count($album_tracks_array); $i++) {

                $tracks_array[$i]['track_restricted_from'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedFrom']['Country']);
                $tracks_array[$i]['track_restricted_to'] = implode(',', (array) $album_tracks_array[$i]['TrackRestrictedTo']['Country']);
                $tracks_array[$i]['volume_count'] = $album_xml_array['Volumes']['Volume']['@attributes']['sequence'];
                $tracks_array[$i]['title'] = $album_tracks_array[$i]['TrackTitle'];
                $tracks_array[$i]['isrc'] = $album_tracks_array[$i]['ISRC'];
                $tracks_array[$i]['track_time'] = $this->trackTime( $album_tracks_array[$i]['TrackTime'] );
                $tracks_array[$i]['sequence'] = $album_tracks_array[$i]['@attributes']['sequence'];

                $tracks_array[$i]['pline'] = $album_tracks_array[$i]['Copyright']['PLine'];

                $tracks_array[$i]['rights_granted'] = $album_tracks_array[$i]['RightsGranted'];

                // these three lines added by Ralph on 16/06/10
                $songAvailability = $this->songAvailability($album_tracks_array[$i]['RightsGranted']);
                $tracks_array[$i]['DownloadStatus'] = $songAvailability['DownloadStatus'];
                $tracks_array[$i]['StreamingStatus'] = $songAvailability['StreamingStatus'];

                $tracks_array[$i]['explicit_lyrics'] = $album_tracks_array[$i]['ExplicitLyrics'];

                $original_clip_name = $album_id . "_" . $tracks_array[$i]['volume_count'] . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '_CLIP.mp3';
                $original_track_name = $album_id . "_" . $tracks_array[$i]['volume_count'] . "_" . $album_tracks_array[$i]['@attributes']['sequence'] . '.mp3';
                $tracks_array[$i]['original_clip_name'] = $original_clip_name;
                $tracks_array[$i]['original_track_name'] = $original_track_name;
                $tracks_array[$i]['upc'] = $album_id;

                if ($album_tracks_array[$i]['Participants']['Participant']) {
                    $tracks_array[$i]['Artist'] = $this->checkParticipants($album_xml_path, $album_tracks_array[$i]['Participants']['Participant'], $album_tracks_array[$i]['ISRC'], $album_id);
                    $tracks_array[$i]['ArtistText'] = $tracks_array[$i]['Artist'];
                }
            }

        } else {
            
            //single track
            $album_tracks_array = $album_xml_array['Volumes']['Volume']['Tracks']['Track'];

            $tracks_array[0]['track_restricted_from'] = implode(',', (array) $album_tracks_array['TrackRestrictedFrom']['Country']);
            $tracks_array[0]['track_restricted_to'] = implode(',', (array) $album_tracks_array['TrackRestrictedTo']['Country']);
            $tracks_array[0]['volume_count'] = $album_xml_array['Volumes']['Volume']['@attributes']['sequence'];
            $tracks_array[0]['title'] = $album_tracks_array['TrackTitle'];
            $tracks_array[0]['isrc'] = $album_tracks_array['ISRC'];
            $tracks_array[0]['track_time'] = $this->trackTime( $album_tracks_array['TrackTime'] );
            $tracks_array[0]['sequence'] = $album_tracks_array['@attributes']['sequence'];

            $tracks_array[0]['pline'] = htmlentities($album_tracks_array['Copyright']['PLine'], 'ENT_COMPAT', 'UTF-8');

            $tracks_array[0]['rights_granted'] = $album_tracks_array['RightsGranted'];

            // these three lines added by Ralph on 16/06/10
            $songAvailability = $this->songAvailability($album_tracks_array['RightsGranted']);
            $tracks_array[0]['DownloadStatus'] = $songAvailability['DownloadStatus'];
            $tracks_array[0]['StreamingStatus'] = $songAvailability['StreamingStatus'];

            $tracks_array[0]['explicit_lyrics'] = $album_tracks_array['ExplicitLyrics'];

            $original_clip_name = $album_id . "_" . $tracks_array[0]['volume_count'] . "_" . $album_tracks_array['@attributes']['sequence'] . '_CLIP.mp3';
            $original_track_name = $album_id . "_" . $tracks_array[0]['volume_count'] . "_" . $album_tracks_array['@attributes']['sequence'] . '.mp3';
            $tracks_array[0]['original_clip_name'] = $original_clip_name;
            $tracks_array[0]['original_track_name'] = $original_track_name;
            $tracks_array[0]['upc'] = $album_id;

            if ($album_tracks_array['Participants']['Participant']) {
                $tracks_array[0]['Artist'] = $this->checkParticipants($album_xml_path, $album_tracks_array['Participants']['Participant'], $album_tracks_array['ISRC'], $album_id);
                $tracks_array[0]['ArtistText'] = $tracks_array[0]['Artist'];
            }
        }
        return $tracks_array;
    }*/

    // this function added by Ralph on 16/06/10 to return the correct download and streaming status based on the RightsGranted
    function songAvailability($rights_granted) {
        $status = array(
            'DownloadStatus' => 0,
            'StreamingStatus' => 0
        );
        switch ($rights_granted) {
            case 'ALL':
                $status['DownloadStatus'] = 1;
                $status['StreamingStatus'] = 1;
                break;
            case 'Full Album Permanent Only':
                $status['DownloadStatus'] = 0;
                $status['StreamingStatus'] = 0;
                break;
            case 'Track Permanent Only':
                $status['DownloadStatus'] = 1;
                $status['StreamingStatus'] = 0;
                break;
            case 'Track Subscription/Stream Only':
                $status['DownloadStatus'] = 0;
                $status['StreamingStatus'] = 1;
                break;
            case 'Full Album Permanent AND Track Permanent Only':
                $status['DownloadStatus'] = 1;
                $status['StreamingStatus'] = 0;
                break;
            case 'Track Permanent AND Track Subscription/Stream Only':
                $status['DownloadStatus'] = 1;
                $status['StreamingStatus'] = 1;
                break;
            case 'Full Album Permanent AND Track Subscription/Stream Only':
                $status['DownloadStatus'] = 0;
                $status['StreamingStatus'] = 1;
                break;
            case 'None':
                $status['DownloadStatus'] = 0;
                $status['StreamingStatus'] = 0;
                break;
            default:
                $status['DownloadStatus'] = 0;
                $status['StreamingStatus'] = 0;
                break;
        }
        return $status;
    }

    function trackTime( $trackTime ) {

    	$time = explode( ':', $trackTime );
    	 
    	if ( count( $time ) == 3 ) {
    	
    		$hour   = (int) trim( $time[0] );
    		$minute = $hour * 60 + (int) trim( $time[1] );
    	
    		if ( strlen( $minute ) < 2 ) {
    			$minute = "0" . $minute;
    		}

    		$track_time = $minute . ':' . trim( $time[2] );
    	} else {
    		$track_time = trim( $trackTime );
    	}
    	
    	return $track_time;
    }

    function stripped($var)
    {
        $var_array = explode(' ', $var);
        $var = '';
        foreach ($var_array as $ele)
        {
            $var .= ucfirst(ereg_replace("[^A-Za-z0-9]", "", $ele));
        }
        return $var;
    }

    /**
     * 
     * This function is used to rename and convert all Mp3 -> mp4 
     */
    function writeTagToTrack($album_path, $album_detail, $track_detail, $patch_name)
    {

        $track_src = $album_path . '/' . $album_detail['upc'] . "_" . $track_detail['volume_count'] . "_" . $track_detail['sequence'] . '.mp3';
        //writing TAGs to MP3 files 
        $track_year = (isset($album_detail['primary_release_date']) && ($album_detail['primary_release_date'] != '')) ? date('Y', strtotime($album_detail['primary_release_date'])) : '';

        $tag1 = new mp3_id3v11();
        $tag1->load_file($track_src);
        $tag1->set_tag($track_detail['title'], $track_detail['ArtistText'], $album_detail['product_name'], $track_year, '', $track_detail['sequence'], $album_detail['primary_genre']);
        $tag1->write_file();

        $tag_format = 'UTF-8';
        // Initialize getID3 engine
        $getID3 = new getID3;
        $getID3->setOption(array('encoding' => $tag_format));

        // Initialize getID3 tag-writing module
        $tagwriter = new getid3_writetags;
        $tagwriter->filename = $track_src;
        $tagwriter->tagformats = array('id3v2.3');
        $tagwriter->overwrite_tags = true;
        $tagwriter->tag_encoding = $tag_format;
        $tagwriter->remove_other_tags = true;

        $tag_data = array(
            'title' => array($track_detail['title']),
            'artist' => array($track_detail['ArtistText']),
            'album' => array($album_detail['product_name']),
            'year' => array($track_year),
            'genre' => array($album_detail['primary_genre']),
            'track' => array($track_detail['sequence']),
        );

        $image_name = ROOTPATH . $patch_name . "/" . (float) $album_detail['upc'] . "/" . $album_detail['upc'] . '.jpg';
        ob_start();
        $fd = fopen($image_name, 'rb');
        if ($fd)
        {
            ob_end_clean();
            $APICdata = fread($fd, filesize($image_name));
            fclose($fd);

            list($APIC_width, $APIC_height, $APIC_imageTypeID) = GetImageSize($image_name);
            $imagetypes = array(1 => 'gif', 2 => 'jpeg', 3 => 'png');
            if (isset($imagetypes[$APIC_imageTypeID]))
            {
                $tag_data['attached_picture'][0]['data'] = $APICdata;
                $tag_data['attached_picture'][0]['picturetypeid'] = '3';
                $tag_data['attached_picture'][0]['description'] = "cover";
                $tag_data['attached_picture'][0]['mime'] = 'image/' . $imagetypes[$APIC_imageTypeID];
            }
        }
        else
        {
            ob_end_clean();
        }

	$log = new Logs($patch_name, $album_detail['upc'] );

	$log->writeAlbumLog('MP3 File: ' .    $track_src, $album_detail['upc'] );
        $log->writeAlbumLog('Track Title: ' . $track_detail['title'], $album_detail['upc'] );
        $log->writeAlbumLog('Artist: ' .      $track_detail['ArtistText'], $album_detail['upc'] );
        $log->writeAlbumLog('Album Title: ' . $album_detail['product_name'], $album_detail['upc'] );
        $log->writeAlbumLog('Year: ' .        $track_year, $album_detail['upc'] );
        $log->writeAlbumLog('Genre: ' .       $album_detail['primary_genre'], $album_detail['upc'] );
        $log->writeAlbumLog('Sequence: ' .    $track_detail['sequence'], $album_detail['upc'] );
        $log->writeAlbumLog('Image File: ' .  $image_name, $album_detail['upc'] );

        $tagwriter->tag_data = $tag_data;
        $tagwriter->WriteTags();
    }

    /**
     * This method is used to rename mp3 file in the format as given below
     * < ArtistName_SongTitle_UPC_Verison_Sequnace >
     * 
     * @param array $track_details
     * @param array $album_details
     * 
     * @return null
     */
    function renameFile($album_path, $original_clip_name, $new_clip_name, $original_track_name, $new_track_name, $album_id, $patch_name)
    {
        $log = new Logs($patch_name, $album_id);

        //rename all clip files with new name
        $src_track_clip = $album_path . "/" . $original_clip_name;
        $new_track_clip = $album_path . "/" . $new_clip_name;

        $command = 'cp ' . $src_track_clip . ' ' . $new_track_clip;
        $log->writeAlbumLog($command, $album_id);
        echo exec($command);

        $src_track = $album_path . "/" . $original_track_name;
        $new_track = $album_path . "/" . $new_track_name;

        $command = 'cp ' . $src_track . ' ' . $new_track;
        $log->writeAlbumLog($command, $album_id);
        echo exec($command);
    }

    /**
     * This function use to convert MP3 file to MP4 format and 
     * save it to current location of the album
     * 
     * @param array $track_details
     * @param array $album_details
     * 
     * @return null 
     */
    function convertToMp4($mp3_file_path, $mp4_file_path, $album_id, $patch_name)
    {
        if (!file_exists($mp4_file_path))
        {
            $log = new Logs($patch_name, $album_id);
            $command = '/usr/local/bin/ffmpeg -y -i ' . $mp3_file_path . ' -vn -strict -2 -b:a 96k ' . $mp4_file_path . ' &';
            $log->writeAlbumLog($command, $album_id);

            $status = exec($command, $output, $serverResponse);
            if ($serverResponse > 0)
            {
                $log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
            }
            unset($output);
        }
    }

    function return_track_artist_freegal($primary_artists)
    {
        if (is_numeric(key($primary_artists)))
        {
            $pa_ids = '';
            foreach ($primary_artists as $primary_artist)
            {

                if ($pa_ids == '')
                {
                    $pa_ids = $primary_artist['artist_name'];
                }
                else
                {
                    $pa_ids .= "," . $primary_artist['artist_name'];
                }
            }
            return $pa_ids;
        }
        else
        {
//return artist_do_insert($primary_artists['artist'] , $type);
            return $primary_artists['DisplayText'];
        }
    }

    function creatingClientXML($album_id, $patch_name, $cdn_path)
    {
        $album_xml_path = ROOTPATH . $patch_name . '/' . (float) $album_id . '/' . (float) $album_id . '.xml';
        $album_xml = simplexml_load_file($album_xml_path);
        $xml_array = json_decode(json_encode($album_xml), 'true');


        $content = file_get_contents($album_xml_path);
        $content = preg_replace("/<ProductMetadata[^>]+\>/i", "<Catalog>", $content);
        $content = str_replace("</ProductMetadata>", "</Action></Catalog>", $content);

        if ($xml_array['DeliveryType'] == 'CompleteAlbum')
        {
            $content = str_replace("<DeliveryType>", "<Action Type='Insert'>", $content);
            $content = str_replace("CompleteAlbum</DeliveryType>", "", $content);
            $XmlFor = 'I';
        }
        if ($xml_array['DeliveryType'] == 'MetadataOnlyUpdate')
        {
            $content = str_replace("<DeliveryType>", "<Action Type='Update'>", $content);
            $content = str_replace("MetadataOnlyUpdate</DeliveryType>", "", $content);
            $XmlFor = 'U';
        }
        if ($xml_array['DeliveryType'] == 'Takedown')
        {
            $content = str_replace("<DeliveryType>", "<Action Type='Delete'>", $content);
            $content = str_replace("Takedown</DeliveryType>", "", $content);
            $XmlFor = 'D';
        }
        if (isset($xml_array['UPC']))
        {
            $ImageName = $album_id . '.jpg';
            $imgPath = $cdn_path;
        }
        if (isset($xml_array['EAN']))
        {
            $ImageName = $album_id . '.jpg';
            $imgPath = $cdn_path;
        }

        $productImg = "<ProductImage><Filename>$ImageName</Filename><Filepath>$imgPath</Filepath></ProductImage>";
        $content = str_replace("</ProductName>", "</ProductName>$productImg", $content);

        $artistText = $this->return_track_artist_freegal($xml_array['ProductArtist'], 1);
        $tracksArray = $xml_array['Volumes']['Volume'];

        if (is_integer(key($tracksArray)))
        {
            $counter = 0;
            foreach ($tracksArray as $trackArray)
            {
                $finalTrackArray = $trackArray['Tracks']['Track'];
                $volume = $trackArray['@attributes']['sequence'];
                if (is_integer(key($finalTrackArray)))
                {
                    foreach ($finalTrackArray as $trackValues)
                    {
                        $sequence = $trackValues['@attributes']['sequence'];
                        $editTrackInfo = $this->stripped(substr($artistText, 0, 100)) . "_" . $this->stripped(substr($trackValues['TrackTitle'], 0, 100)) . "_" . $album_id . "_" . $volume . "_" . $sequence . ".mp3";
                        $oldISRC = '<ISRC>' . $trackValues['ISRC'] . '</ISRC>';
                        $content = str_replace("$oldISRC", "<Filename>$editTrackInfo</Filename><Filepath>$imgPath</Filepath>$oldISRC", $content);
                    }
                }
                else
                {
                    $sequence = $finalTrackArray['@attributes']['sequence'];
                    $editTrackInfo = $this->stripped(substr($artistText, 0, 100)) . "_" . $this->stripped(substr($finalTrackArray['TrackTitle'], 0, 100)) . "_" . $album_id . "_" . $volume . "_" . $sequence . ".mp3";
                    $oldISRC = '<ISRC>' . $finalTrackArray['ISRC'] . '</ISRC>';
                    $content = str_replace("$oldISRC", "<Filename>$editTrackInfo</Filename><Filepath>$imgPath</Filepath>$oldISRC", $content);
                }
                $counter++;
            }
        }
        else
        {
            $finalTrackArray = $tracksArray['Tracks']['Track'];
            $volume = $xml_array['Volumes']['Volume']['@attributes']['sequence'];

            if (is_integer(key($finalTrackArray)))
            {
                foreach ($finalTrackArray as $trackValues)
                {
                    $sequence = $trackValues['@attributes']['sequence'];
                    $editTrackInfo = $this->stripped(substr($artistText, 0, 100)) . "_" . $this->stripped(substr($trackValues['TrackTitle'], 0, 100)) . "_" . $album_id . "_" . $volume . "_" . $sequence . ".mp3";
                    $oldISRC = '<ISRC>' . $trackValues['ISRC'] . '</ISRC>';
                    $content = str_replace("$oldISRC", "<FileInfo><Filename>$editTrackInfo</Filename><Filepath>$imgPath</Filepath></FileInfo>$oldISRC", $content);
                }
            }
            else
            {
                $sequence = $finalTrackArray['@attributes']['sequence'];
                $editTrackInfo = $this->stripped(substr($artistText, 0, 100)) . "_" . $this->stripped(substr($finalTrackArray['TrackTitle'], 0, 100)) . "_" . $album_id . "_" . $volume . "_" . $sequence . ".mp3";
                $oldISRC = '<ISRC>' . $finalTrackArray['ISRC'] . '</ISRC>';
                $content = str_replace("$oldISRC", "<FileInfo><Filename>$editTrackInfo</Filename><Filepath>$imgPath</Filepath></FileInfo>$oldISRC", $content);
            }
        }

        if (isset($xml_array['UPC']))
        {
            $newFileName = $album_id . '.xml';
        }
        if (isset($xml_array['EAN']))
        {
            $newFileName = $album_id . '.xml';
        }


        $orchar_db = new OrchardDatabase($patch_name, $album_id);
        $album_version = $orchar_db->checkVersionOfAlbum($album_id, $XmlFor);
        if (!empty($album_version))
        {
            $new_version_no = $album_version['version_no'] + 1;
            $newFileName = str_replace('.xml', '_' . $XmlFor . '_' . $new_version_no . '.xml', $newFileName);
        }
        else
        {
            $new_version_no = 1;
            $newFileName = str_replace('.xml', '_' . $XmlFor . '_1.xml', $newFileName);
        }

        if (!file_exists(LOCAL_SERVER_PATH . $patch_name . '/' . $album_id))
        {
            if (!mkdir(LOCAL_SERVER_PATH . $patch_name . '/' . $album_id, 0777, TRUE))
            {
                echo ("Not able to create log directory");
            }
        }

        /*if (file_exists(LOCAL_SERVER_PATH . $patch_name . '/' . $album_id . "/$newFileName"))
        {
            echo exec("rm " . LOCAL_SERVER_PATH . $patch_name . '/' . $album_id . "/$newFileName");
        }*/

        echo $newFileObj = fopen(LOCAL_SERVER_PATH . $patch_name . '/' . $album_id . "/$newFileName", 'w') or die("Can't open file");
        chmod(LOCAL_SERVER_PATH . $patch_name . '/' . $album_id . "/$newFileName", '0775');
        fwrite($newFileObj, $content) or die("Can't write file");
//orchar_db->closeConnection();
        return $newFileName;
    }

    /**
     * Checking tags availibility in XML for Artist
     * 
     * @return type array
     */
    function checkArtistInfo($xmlArray)
    {
        if ($xmlArray['ArtistID'])
            $insert_product_artist['artist_id'] = $xmlArray['ArtistID'];
        if ($xmlArray['ArtistCountry'])
            $insert_product_artist['artist_country'] = $xmlArray['ArtistCountry'];
        if ($xmlArray['DisplayText'])
            $insert_product_artist['artist_display_text'] = $xmlArray['DisplayText'];
        if ($xmlArray['WebpageURL'])
            $insert_product_artist['artist_webpage_url'] = $xmlArray['WebpageURL'];
        if ($xmlArray['Description'])
            $insert_product_artist['description'] = $xmlArray['Description'];
        if ($xmlArray['ArtistBio'])
            $insert_product_artist['artist_bio'] = $xmlArray['ArtistBio'];

        return $insert_product_artist;
    }

    function checkParticipants($album_xml_path, $participants, $isrc, $upc)
    {
        $album_xml_path = str_replace(ROOTPATH, '', $album_xml_path);
        $temp_array = explode('/', $album_xml_path);

        $orchard_db = new OrchardDatabase($temp_array[0], $upc);

        if (is_integer(key($participants)))
        {
            $i = 0;
            foreach ($participants as $participant)
            {
                $insert_participant['participant_id'] = '';
                $insert_participant['name'] = $participant['ParticipantName'];
                $insert_participant['role'] = $participant['@attributes']['role'];
                $insert_participant['isrc'] = $isrc;
                $insert_participant['upc'] = $upc;

                if ($i == 0)
                {
                    $whereCondition = " where role='" . $insert_participant['role'] . "' and isrc='" . $isrc . "' and upc=$upc";
                    $orchard_db->deleteTableQuery('participants', $whereCondition);
                }

                $orchard_db->insertTableQuery($insert_participant, 'participants');
                $i++;
            }
        }
        else
        {
            $insert_participant['participant_id'] = '';
            $insert_participant['name'] = $participants['ParticipantName'];
            $insert_participant['role'] = $participants['@attributes']['role'];
            $insert_participant['isrc'] = $isrc;
            $insert_participant['upc'] = $upc;
            $whereCondition = " where role='" . $insert_participant['role'] . "' and isrc='" . $isrc . "' and upc=$upc";
            $orchard_db->deleteTableQuery('participants', $whereCondition);

            if ($upc > 0)
            {
                $orchard_db->insertTableQuery($insert_participant, 'participants');
            }
        }

        $artist = $orchard_db->getParticipantsOrchardDB($isrc, $upc);
        $orchard_db->closeConnection();

        return $artist['artist'];
    }

    function aspSFTPMakeConnection()
    {
        $asp_sftp_obj = ssh2_connect(ASP_SFTP_HOST, ASP_SFTP_PORT);
        if ($asp_sftp_obj == false)
        {
            return false;
        }

        return $asp_sftp_obj;
    }

    function aspSFTPAuthentication($sftp_obj)
    {
        $cdn_auth = ssh2_auth_password($sftp_obj, ASP_SFTP_USER, ASP_SFTP_PASS);
        if ($cdn_auth == false)
        {
            return false;
        }
        return true;
    }

    /**
     * This method is called for uploading files to CDN server
     * using ASPERA command 
     * 
     * @param type $local_file
     * @param type $remote_file
     * @param type $patch_folder
     * @param type $upc
     * 
     */
    function uploadFileAspera($local_file, $remote_file, $patch_folder, $upc)
    {
        $log = new Logs($patch_folder, $upc);
        $command = "export ASPERA_SCP_PASS=" . SFTP_PASS . ";" . "ascp -Q -l 100m " . $local_file . " " . SFTP_USER . "@" . SFTP_HOST . ":" . $remote_file;
        
        $flag = false;
        $retry = 0;
        
        while ( $retry < 30 && $flag == false ) {
        
        	$status = exec( $command, $output, $serverResponse );
        
        	if ( $serverResponse > 0 ) {
        		$retry++;
        		unset($output);
        		unset($serverResponse);
        		sleep(5);
        		continue;
        	} else {
        		$retry = 30;
        		$flag = true;
        	}
        }

        $log->writeAlbumLog($command, $upc);

        if ( $flag == false ) {
        
        	$log->writeAlbumLog("Error : $serverResponse & error : $status & output : " . serialize($output), $upc);
            $patch_log = new Logs($patch_folder, $upc);
        
        	if ( !empty( $status ) && strlen( $status ) > 5 ) {
        		$patch_log->sendMail("$command was executed but we got the following error.\n Error : $status \n", 'Orcahrd Parser : ASPERA Fails');
        	} else {
        		$patch_log->sendMail("$command was executed but we got the following error.\n Error : We have tried 30 times to establish the connection with CDN but not able to connect. \n", 'Orcahrd Parser : ASPERA Fails');
        	}
        	exit();
        }
    }
}
