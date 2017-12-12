<?php

// setting the default for parser
set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

//set timezone
date_default_timezone_set('America/New_York');

//inclusion of files which are required
include_once('library/config.php');
include_once('library/FileProcess.php');

echo "=================================\n";
//echo $today = '2014-09-30';
echo $today = date('Y-m-d');echo "\n";
echo "=================================\n";
$root_folder_name = '/data/ftp/libraryideas/upload/' . date('ymd', strtotime($today)) . "/";    //for folder name on ASP server
$yesterday = date('Y-m-d', strtotime($today . "-1 day"));

$orchard = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die("Could not connect to the Orchard Database at Orchard");

$xml_having_problem = array();

//Get list of UPC's and Batch Name which were ingested previous day
$release_list_resource = getBatchUPCProcessedList($yesterday, $orchard);

if ($release_list_resource->num_rows > 0)
{
    $fp_obj = new FileProcess();
    $sftp_obj = $fp_obj->aspSFTPMakeConnection();                       //creating the SFTP connection
    if ($sftp_obj == false)
    {
        echo "SFTP connection cannot be made.";
	$xml_having_problem[] =  "SFTP connection cannot be made.";
        sendMail($xml_having_problem);
        exit();
    }
    else
    {
        if ($fp_obj->aspSFTPAuthentication($sftp_obj))                  //Authenticating the SFTP connection
        {
            $sftp_cdn = ssh2_sftp($sftp_obj);
            while ($release = mysqli_fetch_object($release_list_resource))
            {
                //Getting the new XML File Name(s) for the release
                $release_xml_name = getReleaseXMLFileName($release->upc, $yesterday, $orchard);

                if ($release_xml_name->num_rows > 1)
                {
                    while ($release_multi_file = mysqli_fetch_object($release_xml_name))
                    {
                        $client_xml = CLIENT_SERVER_PATH . $release->batch_name . '/' . createPathCDN($release->upc) . $release_multi_file->generate_xml_name;

                        if (file_exists("$client_xml"))
                        {
                            $xml_object = simplexml_load_file($client_xml);
                            if (!is_object($xml_object))
                            {
                                $xml_having_problem[] = "problem in xml $client_xml.";
                            }
                            else
                            {
                                $remote_path = $root_folder_name . str_replace(CLIENT_SERVER_PATH, '', $client_xml);

                                if (createRemoteFolder($remote_path, $sftp_cdn))
                                {
                                    if (uploadXML($sftp_obj, $client_xml, $remote_path))
                                    {
                                        $xml_having_problem[] = "$client_xml uploaded to $remote_path";
                                    }
                                    else
                                    {
                                        $xml_having_problem[] = "$client_xml not uploaded to $remote_path";
                                    }
                                }
                                else
                                {
                                    $xml_having_problem[] = "$remote_path cannot be created";
                                }
                            }
                        }
                        else
                        {
                            $xml_having_problem[] = "$client_xml file not found.";
                        }
                    }
                }
                else
                {
                    $xml_file_object = mysqli_fetch_object($release_xml_name);

                    $client_xml = CLIENT_SERVER_PATH . $release->batch_name . '/' . createPathCDN($release->upc) . $xml_file_object->generate_xml_name;

                    if (file_exists("$client_xml"))
                    {
                        $xml_object = simplexml_load_file($client_xml);
                        if (!is_object($xml_object))
                        {
                            $xml_having_problem[] = "problem in xml $client_xml.";
                        }
                        else
                        {
                            $remote_path = $root_folder_name . str_replace(CLIENT_SERVER_PATH, '', $client_xml);

                            if (createRemoteFolder($remote_path, $sftp_cdn))
                            {
                                if (uploadXML($sftp_obj, $client_xml, $remote_path))
                                {
                                    $xml_having_problem[] = "$client_xml uploaded to $remote_path";
                                }
                                else
                                {
                                    $xml_having_problem[] = "$client_xml not uploaded to $remote_path";
                                }
                            }
                            else
                            {
                                $xml_having_problem[] = "$remote_path cannot be created";
                            }
                        }
                    }
                    else
                    {
                        $xml_having_problem[] = "$client_xml file not found.";
                    }
                }
            }
        }
    }

    ssh2_exec($sftp_obj, 'exit');
}
else
{
    $xml_having_problem[] =  "No Release details found for the date : $yesterday";
}

 sendMail($xml_having_problem);



mysqli_close($orchard);
exit();

function getBatchUPCProcessedList($for_date, $db)
{
    echo $query = "select 
                    batch_name, upc
                from
                    theorchard.album_parser
                where
                    datetime between '$for_date 00:00:00' and '$for_date 23:59:59'";
    return mysqli_query($db, $query);
}

function getReleaseXMLFileName($release, $date, $db)
{
    $query = "SELECT 
                    id, generate_xml_name
                FROM
                    theorchard.new_generated_xml
                where
                    upc = $release
                        and date(generated_date) = '$date'
                order by id desc";
    return mysqli_query($db, $query);
}

function createPathCDN($album_id)
{
    $length = strlen($album_id);
    $less_lenght = 10 - $length;
    $cdn_string = $album_id;
    for ($i = 0; $i < $less_lenght; $i++)
    {
        $cdn_string = "0" . $cdn_string;
    }

    $dirpath = "";
    for ($i = 0; $i < $length; $i = $i + 3)
    {

        $dir = substr($cdn_string, $i, 3);
        if (strlen($dir) == 3)
        {
            $dirpath .= $dir . "/";
        }
        else
        {
            $d = substr($cdn_string, $i, 2);
            $dirpath .= $d . "/";
        }
    }
    return $dirpath;
}

function getFileName($file_path)
{
    $folder_array = explode('/', $file_path);
    return trim($folder_array[count($folder_array) - 1]);
}

function createRemoteFolder($remote, $sftp_cdn)
{
    $remote_folder = str_replace(getFileName($remote), '', $remote);

    if (!is_dir("ssh2.sftp://$sftp_cdn" . $remote_folder))
    {
        $dir_path_array = explode('/', $remote_folder);
        $new_path = "/";
        for ($i = 1; $i < count($dir_path_array) - 1; $i++)
        {
            $new_path .= $dir_path_array[$i] . "/";
            if (!is_dir("ssh2.sftp://$sftp_cdn" . $new_path))
            {
                ssh2_sftp_mkdir($sftp_cdn, $new_path);
            }
        }
        return $remote_folder;
    }
    else
    {
        return $remote_folder;
    }
}

function uploadXML($sftp_obj, $local, $remote)
{
    if (ssh2_scp_send($sftp_obj, $local, $remote))
    {
        return true;
    }
    else
    {
        return false;
    }
}

function sendMail($xml_having_problem)
{
    $headers = 'From: Knox' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
    $title = "Daily ASP XML report";
    $to = "ghanshyam.agrawal@infobeans.com,narendra.nagesh@infobeans.com";

    $body = "Hello Rob, \n \n";
    foreach ($xml_having_problem as $xml)
    {
        $body .= $xml . PHP_EOL;
    }

    $body .="\n Thanks";

    mail("$to", $title, "$body", "$headers");
}
