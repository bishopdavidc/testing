<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

include_once('library/config.php');

class Logs
{

    var $patch_name;
    var $patch_details;
    var $patch_report;
    var $patch_arcive;
    var $patch_redeliver;
    var $patch_album;

    public function __construct($patch_name, $album = null)
    {
        $this->patch_name = $patch_name;
        $this->patch_album = $album;

        $this->patch_details = PATCH_DETAILS . $this->patch_name;
        if (!file_exists($this->patch_details))
        {
            if (!mkdir($this->patch_details, 0777, TRUE))
            {
                $this->writeError(self::ERR_CUSTOM, $this->patch_name, null, 'Patch folder cannot be created at ' . $this->patch_details);
            }
        }

        $this->patch_arcive = $this->patch_details . '/' . 'archive/';
        if (!file_exists($this->patch_arcive))
        {
            if (!mkdir($this->patch_arcive, 0777, TRUE))
            {
                $this->writeError(self::ERR_CUSTOM, $this->patch_name, null, 'Patch Album Arcive folder cannot be created at ' . $this->patch_arcive);
            }
        }
    }

    CONST ERR_ROOPATH = 1;
    CONST ERR_NO_PATCH_FOLDER = 2;
    CONST ERR_MAIN_MANIFEST = 3;
    CONST ERR_MANIFEST_EMPTY = 4;
    CONST ERR_DELIVERY_FILE = 5;
    CONST ERR_DIRECTORY_NOT_CREATED = 6;
    CONST ERR_SCRIPT_PROBLEM = 7;
    CONST ERR_RUNNING_SCRIPT = 8;
    CONST ERR_DIFFERENT_DELIVERY_TYPE = 9;
    CONST ERR_RECORD_ALREADY_INSERTED = 10;
    CONST MYSQL_LOGS = 11;
    CONST ERR_ALBUM_FOLDER = 12;
    CONST ERR_MYSQL = 13;
    CONST ERR_CUSTOM = 14;
    CONST PATCH_REPORT = 15;

    function writeError($type, $patch_name = null, $album_id = null, $msg = null)
    {
        $this->import_logs_error = fopen($this->patch_details . '/' . 'error_logs.txt', "a");

        $message = "";

        switch ($type)
        {
            case self::ERR_ROOPATH :
                $message = "There is a problem with ROOTPATH. \n";
                $message .= "Please configure correct ROOTPATH in config.php \n";
                $this->sendMail($message, 'Problem with ROOTPATH');
                break;

            case self::ERR_NO_PATCH_FOLDER :
                $message = "There is a problem with Batch Folder. \n";
                $message .= "No Batch folder is found at the Root Path \n";
                $this->sendMail($message, 'Problem with Batch Folder');
                break;

            case self::ERR_MAIN_MANIFEST :
                $message = "Manifest.txt is NOT FOUND with the patch : $this->patch_name. \n";
                $message .= "Please ask for Redeliver of the Batch.";
                $this->sendMail($message, 'Batch Manifest is NOT FOUND');
                break;

            case self::ERR_MANIFEST_EMPTY :
                $message = "Manifest.txt is found EMPTY with the patch : $this->patch_name. \n";
                $message .= "Please ask for Redeliver of the Batch.";
                $this->sendMail($message, 'Manifest.txt is empty');
                break;

            case self::ERR_DELIVERY_FILE :
                $message = "Delivery.complete file is MISSING with the patch : $this->patch_name. \n";
                $message .= "Please ask for Redeliver of the Batch.";
                $this->sendMail($message, 'Delivery.complete file is MISSING');
                break;

            case self::ERR_DIRECTORY_NOT_CREATED :
                $message = "Not able to create log directory for patch : $this->patch_name";
                $message .= "at Location : " . IMPORTLOGS . "\n";
                $this->sendMail($message, "Not able to create log directory");
                break;

            case self::ERR_SCRIPT_PROBLEM :
                $message = "There is a problem with the new script generated for the patch : $this->patch_name. \n";
                $message .= "Please revist the code and verify the code. \n";
                $this->sendMail($message, "problem with the new script");
                break;

            case self::ERR_RUNNING_SCRIPT :
                $message = $msg;
                $message .= "\n Please wait till the current script injest is finished. \n";
                $this->sendMail($message, 'Previous script is still running');
                break;

            case self::ERR_DIFFERENT_DELIVERY_TYPE:
                break;

            case self::ERR_RECORD_ALREADY_INSERTED :
                $message = "Record already inserted in orchard and freegal both databases $patch_name / $album_id \n";

                $mail_message = "Hello
                                The Release already inserted with previous patch.Please check in orchard and freegal both databases.
                                Record's release patch " . $patch_name . "
                                Record's release id is " . $album_id . "
                                Please contact The Orchard for re-supply.
                                Check log: error_logs.txt

                            Thanks";
                $this->sendMail($mail_message, "Record already inserted");

                break;

            case self::MYSQL_LOGS:
                $message = $msg;
                $mail_message = "Hello , 
                                MySQL server has gone away.Please  review the connection. \n                                
                                Record's release patch " . $patch_name . "\n
                                $message \n
                            Thanks";
                //$this->sendMail($mail_message, "MYSQL Server has gone away", self::MYSQL_LOGS);
                break;


            case self::ERR_MYSQL:
                $message = $msg;
                break;

            case self::ERR_CUSTOM:
                $message = $msg;
                break;

            case self::PATCH_REPORT:
                $message = $msg;
                $this->patch_name = $patch_name;
                $this->sendMail($message, 'Report of patch ' . $patch_name);
                break;
        }

        fwrite($this->import_logs_error, date('Y-m-d h:i:s') . "  " . $message . "\n");
        fclose($this->import_logs_error);
    }

    function sendMail($message, $title, $type = null)
    {
        $headers = 'From: The Orchard XML Import' . "\r\n" . 'X-Mailer: PHP/' . phpversion();

        if ($type == self::MYSQL_LOGS and $type != null)
        {
            $to = "ghanshyam.agrawal@infobeans.com,libraryideasuser@gmail.com,narendra.nagesh@infobeans.com";
          
        }
        else
        {
            $to = "ghanshyam.agrawal@infobeans.com,tech@libraryideas.com,narendra.nagesh@infobeans.com";

        }
        $body = "Hello Rob, \n \n";
        $body .= "Batch report : $this->patch_name \n \n";
        $body .= $message;
        $body .="\n Thanks";
        mail("$to", $title, "$body", "$headers");
    }

    function writeRedeliverLog($redeliver_array)
    {
        $redeliver_logs = fopen($this->patch_redeliver . $this->patch_name . "_redeliver_of_upc.txt", "w");
        $redeliver_logs_detail = fopen($this->patch_redeliver . $this->patch_name . "_redeliver_of_upc_detailed.txt", "w");
        foreach ($redeliver_array as $upc => $value)
        {
            fwrite($redeliver_logs, $upc . "\n");
            fwrite($redeliver_logs_detail, $upc . " : " . $value['error'] . " \n ");
        }
        fclose($redeliver_logs);
        fclose($redeliver_logs_detail);
    }

    function writeMysqLogs($query)
    {
        if (MYSQLLOGS_FLAG == 1)
        {
            if ($this->patch_album != NULL)
            {
                if (!file_exists($this->patch_arcive . '/' . $this->patch_album))
                {
                    if (!mkdir($this->patch_arcive . '/' . $this->patch_album, 0777, TRUE))
                    {
                        echo 'Patch folder cannot be created at ' . $this->patch_details;
                    }
                }

                $mysql_logs = fopen($this->patch_arcive . '/' . $this->patch_album . '/' . $this->patch_album . '_mysql_log.txt', "a");
                fwrite($mysql_logs, date('Y-m-d h:i:s') . "  " . $query . "\n");
                fclose($mysql_logs);
            }
            else
            {
                $mysql_logs = fopen($this->patch_details . '/' . $this->patch_name . '_mysql_log.txt', "a");
                fwrite($mysql_logs, date('Y-m-d h:i:s') . "  " . $query . "\n");
                fclose($mysql_logs);
            }
        }
    }

    /**
     * This method is use to write logs for the Releases.
     * 
     * @param type $message
     * @param type $album_id
     */
    function writeAlbumLog($message, $album_id)
    {
        if ($this->patch_album != NULL)
        {
            if (!file_exists($this->patch_arcive . '/' . $this->patch_album))
            {
                if (!mkdir($this->patch_arcive . '/' . $this->patch_album, 0777, TRUE))
                {
                    echo 'Patch folder cannot be created at ' . $this->patch_details;
                }
            }

            $album_log = fopen($this->patch_arcive . '/' . $this->patch_album . '/' . "output_" . $this->patch_name . '-' . $album_id . '.txt', "a");
            fwrite($album_log, date('Y-m-d h:i:s') . "  " . $message . "\n");
            fclose($album_log);
        }
        else
        {
            $album_log = fopen($this->patch_details . $this->patch_name . "/" . "output_" . $this->patch_name . '-' . $album_id . '.txt', "a");
            fwrite($album_log, date('Y-m-d h:i:s') . "  " . $message . "\n");
            fclose($album_log);
        }
    }

}
