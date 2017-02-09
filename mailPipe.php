#!/usr/bin/php -q
<?php
//  Use -q so that php doesn't print out the HTTP headers

/*
 * mailPipe.php
 *
 * This script is a sample of how to use mailReader.php as a mail pipe to save 
 * emailed attachments and emails into a directory and/or database
 *
 * Test it by running
 *
 * cat mail.raw | ./mailPipe.php
 *
 * Support: 
 * http://stuporglue.org/mailreader-php-parse-e-mail-and-save-attachments-php-version-2/
 *
 * Code:
 * https://github.com/stuporglue/mailreader
 *
 * See the README.md for the license, and other information
 */


// Set a long timeout in case we're dealing with big files
set_time_limit(600);
ini_set('max_execution_time',600);

// Anything printed to STDOUT will be sent back to the sender as an error!
// error_reporting(-1);
// ini_set("display_errors", 1);


// Require the file with the mailReader class in it
require_once('mailReader.php');

// Where should discovered files go
$save_directory = '/attachments/'; // stick them in the current directory

// Configure your MySQL database connection here
// Other PDO connections will probably work too
$db_host = '';
$db_un = '';
$db_pass = '';
$db_name = '';
$pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8",$db_un,$db_pass);


// Who can send files to through this script?

//select mentor's email addresses
$getUsers = $pdo->prepare("SELECT useremail FROM tprusers");
$getUsers->execute();
$users = $getUsers->fetchAll();

//declare array
$mentoremails = array();

//insert emails into array
foreach ($users as $user) {
	$mentoremails[] = $user['useremail'];
}

//turn email array into comma-separated string
//$emailstr = implode (",' '", $mentoremails);
//$emailstr = "'".$emailstr."'";

$allowed_senders = $mentoremails;

//$allowed_senders = $emailstr;
//$allowed_senders = Array('willchurchill89@gmail.com');

$mr = new mailReader($save_directory,$allowed_senders,$pdo);
$mr->save_msg_to_db = TRUE;
//$mr->send_email = TRUE;
// Example of how to add additional allowed mime types to the list
// $mr->allowed_mime_types[] = 'text/csv';
$mr->readEmail();
