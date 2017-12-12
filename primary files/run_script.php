<?php

/**
 * File Name : orchard_parser.php
 * File Description :   This file is use to check the runnign script. and reate a new script for new patch folder.
 * 
 * @author : LibraryIdeas
 * */
set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

date_default_timezone_set('America/New_York'); //set timezone

include_once('library/config.php');
include_once('library/Logs.php');
include_once('library/FileProcess.php');
include_once('library/OrchardDatabase.php');

/**
 * 
 * Checking as Server time is above 12:00 pm
 * and There are current date batches on the Root location
 * 
 */
// this gets date for two days previous
$curr_date = date("Ymd", strtotime('-2 day'));

// this is the command that will be used to check if there are any directories with the date from two days previous in the name
$command = "ls " . ROOTPATH . " | grep '$curr_date'";

// this executes the above command, the output holds the entire response, serverResponse holds the status code
$status = exec($command, $output, $serverResponse);

// if the server response is 0 that means the command was successful, any other response means the command failed
if ($serverResponse == 0) {

    // Need to check why this has the condition of less than 2. If there is only one batch directory than it will exit.
    if (count($output) < 2) {
        exit(date('Y-m-d h:i:s') . " The Orchard has not delivered any batches yet. Exiting run_script." . "\n");
    } else {
        
        // Makes a connection to theorchard on DB5
        $orchardConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die('Could not connect to theorchard Database');
        mysqli_set_charset($orchardConnection, 'UTF8');

        // This gets all of the rows in the running_scripts table.
        $resultSet = mysqli_query($orchardConnection, 'SELECT foldername, job_completed, script_name FROM running_scripts');

        $batchFolderName = array();
        $scriptName = 'orchard_parser.php';
        $parserScriptPath = '/home/parser/theorchard/';

        $scriptRunning = 0;

        // Loops through the results from the above query as long as the running scripts is lower than the total running scripts
        while (($scriptData = mysqli_fetch_assoc($resultSet)) && ( $scriptRunning < TOTAL_SCRIPT_RUNNING )) {

            $batchFolderName[$scriptData['foldername']] = $scriptData['job_completed'];

            // If the script is not completed than we check to see if it is running. If not, we start it.
            if (!$scriptData['job_completed']) {
                
                checkRunningScript($parserScriptPath, $scriptName, $scriptData['foldername']);
                $scriptRunning++;
                
                // Breaks the loop if a script is running or one is started
                if ($scriptRunning == TOTAL_SCRIPT_RUNNING) {
                    break;
                }
            }
        }

        // If there are still no scripts running we go into the condition
        if ($scriptRunning < TOTAL_SCRIPT_RUNNING) {
           
            $handleRoot = glob(ROOTPATH . '*', GLOB_ONLYDIR);
            $today_date = date("Ymd");
            $one_day_back_date = date("Ymd", strtotime('-1 day'));

            // Loops through all of the batch directories that still exist.
            foreach ($handleRoot as $dir) {

                // Makes sure not to process batches that are from today or yesterday or test directories
            	if ($dir != "." && $dir != ".." && $dir != 'test' && strpos( $dir, $today_date ) == false && strpos( $dir, $one_day_back_date ) == false ) {
                    
                    // Removes the file path so it is just the name of the directory
                    $dir = str_replace(ROOTPATH, '', $dir);
                    
                    // Checks to see that the batch does not already exist in the running_scripts table
                    if (!key_exists($dir, $batchFolderName)) {

                        // Creates the logs object and creates the backup directories for the batch
                        $batchLog = new Logs($dir);

                        // Check if batch folder is found or not              
                        if (!file_exists(ROOTPATH . $dir)) {
                            // Error written to the logs and email sent
                            $batchLog->writeError(Logs::ERR_NO_PATCH_FOLDER);
                            continue;
                        }

                        // Check for manifest.txt is present in folder or not
                        if (!file_exists(ROOTPATH . $dir . '/' . 'manifest.txt')) {
                            // Write entry to the running script table that the manifest was not found
                            insertIntoRunningScript($dir, 'Manifest not found');
                            // Error written to the logs and email sent
                            $batchLog->writeError(Logs::ERR_MAIN_MANIFEST);
                            continue;
                        }

                        // Check if Manifest file is not of 0 size
                        if (!filesize(ROOTPATH . $dir . '/' . 'manifest.txt')) {
                            insertIntoRunningScript($dir, 'Manifest not found');
                            $batchLog->writeError(Logs::ERR_MANIFEST_EMPTY);
                            continue;
                        }

                        // Check for delivery.complete is present in folder or not
                        if (!file_exists(ROOTPATH . $dir . '/' . 'delivery.complete')) {
                            insertIntoRunningScript($dir, 'Delivery.complete not found');
                            $batchLog->writeError(Logs::ERR_DELIVERY_FILE);
                            continue;
                        }

                        

                        // Inserting into the running scripts table for statring the new script
                        $orchardDbObj = new OrchardDatabase($dir);

                        $lastScript = $orchardDbObj->getRunningScript();
                        $lastUsedProdId = (float) ( $lastScript['last_used_prodid'] + TOTAL_ALBUM_PARSER );
                        $scriptNo = (float) $lastScript['script_no'] + 1;

                        $orchardDbObj->insertCurrentScriptDetails($scriptNo, (float) ($lastScript['last_used_prodid'] + 1), $lastUsedProdId, $dir);
                        checkRunningScript($parserScriptPath, $scriptName, $dir);
                        $scriptRunning++;
                    }
                }

                if ($scriptRunning == TOTAL_SCRIPT_RUNNING) {
                    break;
                }
            }
        }


        // Deleting the previous completed batchs from the Root Path

        $completed_batch = mysqli_query($orchardConnection, 'SELECT foldername, job_completed FROM theorchard.running_scripts WHERE job_completed=1 ORDER BY script_no DESC LIMIT 20');

        while ($scriptData = mysqli_fetch_assoc($completed_batch)) {

            if ($scriptData['job_completed']) {

                $query = "SELECT 
                    count(*) as total_release,
                    sum(if(job_completed = 1 and job_started = 1,1,0)) as total_processed  
                 FROM theorchard.album_parser  
                 WHERE batch_name='" . $scriptData['foldername'] . "' ";

                $result = mysqli_fetch_object(mysqli_query($orchardConnection, $query));

                $batch_path = ROOTPATH . $scriptData['foldername'];
        
                if (is_dir($batch_path) && $result->total_release === $result->total_processed) {
                    $command = "rm -r $batch_path";
                    exec($command, $output, $serverResponse);
                }

                if (is_dir($batch_path) && $result->total_release == 0) {
                    $command = "rm -r $batch_path";
                    exec($command, $output, $serverResponse);
                }

            }
        }
        exit("\nRun script finish1.");
    }
} else {
    
    // The command failed to execute on the server. This should probably notify us of the problemm.
    if (empty($output)) {
        exit("\n $command \n No batches to be procesed on $curr_date.\n");
    } else {
        exit("\n $command fails to execute.\n");
    }
}


exit("\nRun script finish2.");


function insertIntoRunningScript($dir, $msg) {

    $orchardDbObj = new OrchardDatabase($dir);

    // Get row from running_scripts ordery by script_no desc limit 0,1
    $lastScript = $orchardDbObj->getRunningScript();

    $lastUsedProdId = (float) ( $lastScript['last_used_prodid'] + TOTAL_ALBUM_PARSER );
    $scriptNo = (float) ( $lastScript['script_no'] + 1 );

    $insertArray = array(
        'script_no' => $scriptNo,
        'last_used_prodid' => (float) ( $lastScript['last_used_prodid'] + 1 ),
        'foldername' => $dir,
        'prod_id_started_from' => $lastUsedProdId,
        'script_name' => $msg,
        'job_completed' => 1
    );

    $orchardDbObj->insertTableQuery($insertArray, 'running_scripts');
}

/**
 * This method is use to check the Prcoess id for the running Batch.
 * If process id is found then the script exits, else if no process id is found then it 
 * goes to script path and execute the command to start processign the batch.
 * 
 * @param type $parserScriptPath
 * @param type $scriptName
 * @param type $folderName
 */
function checkRunningScript($parserScriptPath, $scriptName, $folderName) {
    // checks is the Orchard_parser.php file is at the script path
    if (file_exists($parserScriptPath . $scriptName)) {
        // makes the commad to get the process id 
        $command = "ps aux | grep '" . $scriptName . " " . $folderName . "' | grep -v grep | awk '{print $2}'";
        exec($command, $output, $serverResponse);

        // if the command succesfully executed then it goes inside the if block else
        // error is thrown and log is written
        if ($serverResponse == 0) {
            // if No process id is found then the $output array is empty
            if (empty($output)) {
                // goes to the script path
                exec("cd " . $parserScriptPath, $output, $serverResponse);
                if ($serverResponse == 0) {
                    //execute the script for the batch
                    exec("php '" . $scriptName . "' '" . $folderName . "' > /dev/null &", $output, $serverResponse);
                }
            }
        }

        if ($serverResponse == 1) {
            $msg = $command . "\n command faild for " . $folderName;
            writeLog($msg, $folderName, false );
            exit();
        }
    } else {
        $msg = $scriptName . ' file is missing.';
        writeLog($msg, $scriptName, true);
        exit();
    }
}

function writeLog($msg, $dir, $send_mail = false) {
    $batchLog = new Logs($dir);
    $batchLog->writeError(Logs::ERR_CUSTOM, null, null, $msg);
    if ($send_mail) {
        //echo exec("cd /home/parser/theorchard/");
        //echo shell_exec("cp /home/parser/theorchard/orchard_parser_original.php  /home/parser/theorchard/orchard_parser.php");
        
    	$patch_log = new Logs( $dir, null );
    	$patch_log->sendMail( "We do not find orchard_parser.php file it might be deleted.", 'Orcahrd Parser ', Logs::MYSQL_LOGS);
    	exit();
    }
}
