<?php

set_time_limit(0);
error_reporting(1);
ini_set('mysqli.reconnect', 1);

// set timezone
date_default_timezone_set('America/New_York');

include_once('library/config.php');
include_once('library/OrchardDatabase.php');

$orchard_db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB1) or die("Could not connect to theorchard Database");
//$orchard_db = mysqli_connect('localhost', 'ratneshg', 's*V4P6rxQjvH', DB1) or die("Could not connect to Freegal Database");
$parserScriptPath = '/home/parser/theorchard/';

// Getting a list of jobs from the album_parser table that have started but are not completed.
$query = "SELECT album_parser_name , batch_name FROM album_parser WHERE job_completed=0 and job_started=1";

$running_parser = mysqli_query($orchard_db, $query);

$num_running_parsers = mysqli_num_rows($running_parser);

echo "\n" . date('Y-m-d h:i:s') . ' check_album_parser Query: ' . $query . ' Result: ' . $num_running_parsers . "\n";

if ($num_running_parsers == 0 || $num_running_parsers < TOTAL_ALBUM_PARSER) {

    // Checks for how many parser need to be started.
    $total_parser = (int) TOTAL_ALBUM_PARSER - (int) mysqli_num_rows($running_parser);

    // Gets the jobs that are ready to be started.
    $query = "SELECT album_parser_name , batch_name FROM album_parser WHERE job_completed=0 and job_started=0 and album_parser_name is not null limit $total_parser";
    $run_parser = mysqli_query($orchard_db, $query);

    while ($check_running_album_parser = mysqli_fetch_assoc($run_parser)) {

        $newFileName = $check_running_album_parser['album_parser_name'];
      
        if (!empty($newFileName) && $newFileName != 'album_parser.php') {

            // Make a copy of the existing album_parser.php script with the name from the album_parser table.
            echo exec("cd " . $parserScriptPath);
            echo shell_exec("cp -R " . $parserScriptPath . "album_parser.php " . $parserScriptPath . $newFileName);

            // Opens the new album parser script and puts the contents into a variable.
            $parserOrg = fopen($parserScriptPath . $newFileName, "r");
            $parserData = fread($parserOrg, filesize($parserScriptPath . $newFileName));
            fclose($parserOrg);

            // Changes the text in the script to be the batch name (ex 20151207_0012) and overwrite the script file.
            $newdata = str_replace('libraryideas', $check_running_album_parser['batch_name'], $parserData);
            $parserOrg = fopen($parserScriptPath . $newFileName, 'w');
            fwrite($parserOrg, $newdata);
            fclose($parserOrg);

            // Updates the album parser to show that the job is running.
            $query = "update album_parser set job_started=1 where batch_name='" . $check_running_album_parser ['batch_name'] . "' and album_parser_name='$newFileName' ";
            mysqli_query($orchard_db, $query);
            echo "\n" . date('Y-m-d h:i:s') . ' ' . $query;
            echo "\n" . date('Y-m-d h:i:s') . ' ' . $newFileName . ' (Starting 2)';
            // Starts the new album parser
            echo shell_exec("php '" . $newFileName . "' > /dev/null &");
        }
    }
}

// This loops through the results of the query that gets all jobs that have started=1 and completed=0.
while ($check_running_album_parser = mysqli_fetch_assoc($running_parser)) {

    // Check to see if a script exist on the server for the specified album parser.
    if (file_exists($parserScriptPath . $check_running_album_parser['album_parser_name'])) {

	// execute command to check to see if the script is already running. If so, there is no need to start the process again.
        $command = "ps aux | grep '" . $check_running_album_parser ['album_parser_name'] . "' | grep -v grep | awk '{print $2}'";
        echo exec($command, $output);

        if (empty($output)) {

            // Write to the cron log that we are about to execute the script
            echo "\n" . date('Y-m-d h:i:s') . ' ' . $check_running_album_parser ['album_parser_name'] . ' (Starting 3)';

            // execute the existing script
            echo shell_exec("php '" . $check_running_album_parser ['album_parser_name'] . "' > /dev/null &");

        }

        // clearing the output for the next iteration of the loop
        $output = array();
        continue;

    } else {
	
        // check to see if a parser is already running before creating a new file to start
        $command = "ps aux | grep '" . $check_running_album_parser ['album_parser_name'] . "' | grep -v grep | awk '{print $2}'";

        echo exec($command, $output);

        // if there is no script running then go ahead and created a new script and start the process
        if (empty($output)) {

            // get the name for the new script that needs to be created
            $newFileName = $check_running_album_parser['album_parser_name'];

            if (!empty($newFileName) && $newFileName != 'album_parser.php') { // checking the name for the new file

                // make a copy of the regular album parser with the newFileName
                echo exec("cd " . $parserScriptPath);
                echo shell_exec("cp -R " . $parserScriptPath . "album_parser.php " . $parserScriptPath . $newFileName);

                // open and read the new file and assign its contents to a variable
                $parserOrg = fopen($parserScriptPath . $newFileName, "r");
                $parserData = fread($parserOrg, filesize($parserScriptPath . $newFileName));
                fclose($parserOrg);

                // search the contents from the file for the string 'libraryideas' and replace the string with the name of the batch
                $newdata = str_replace('libraryideas', $check_running_album_parser['batch_name'], $parserData);

                // overwrite the contents of the file with the contents from the variable since the string has been replaced
                $parserOrg = fopen($parserScriptPath . $newFileName, 'w');
                fwrite($parserOrg, $newdata);
                fclose($parserOrg);

                // write to the cron log so that we know we are about to execute the script
                echo "\n" . date('Y-m-d h:i:s') . ' ' . $newFileName . ' (Starting 1)';

                // execute the new script
                echo shell_exec("php '" . $newFileName . "' > /dev/null &");
                continue;

            } // end if to check filename and create a new parser and start it if one is not already running

        } // end if to check if a process is running

        $output = array(); // clearing the output for the next iteration of the loop
        continue;

    }

} // end loop for all jobs that are started but not completed
