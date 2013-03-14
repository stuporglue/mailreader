#!/usr/bin/php -q
<?php
//  Use -q so that php doesn't print out the HTTP headers

/*
 * mailReader.php
 *
 * Recieve mail and attachments with PHP
 *
 * Support: 
 * http://stuporglue.org/mailreader-php-parse-e-mail-and-save-attachments-php-version-2/
 *
 * Code:
 * https://github.com/stuporglue/mailreader
 *
 * See the README.md for the license, and other information
 */

global $save_directory,$saved_files,$debug,$body;

/*
 *
 *      Configuration Options
 *
 */

// What's the max # of seconds to try to process an email?
$max_time_limit = 600; 

// A safe place for files WITH TRAILING SLASH
// Malicious users could upload a php or executable file,
// so keep this out of your web root
$save_directory = "/a/safe/save/directory/";

// Allowed senders is now just the email part of the sender (no name part)
$allowed_senders = Array(
    'myemail@example.com',
    'whatever@example.com',
); 

// Send confirmation e-mail back to sender?
$send_email = FALSE; 

// Save e-mail message and file list to DB?
$save_msg_to_db = FALSE; 

// Configure your MySQL database connection here
$db_host = 'localhost';
$db_un = 'db_un';
$db_pass = 'db_pass';
$db_name = 'db_name';

$debug = FALSE;

/*
 *
 *      End of Configuration Options
 *
 */

//Anything printed to STDOUT will be sent back to the sender as an error!
//error_reporting(-1);
//ini_set("display_errors", 1);

// Initialize the other global, set PHP options, load email library
$saved_files = Array();
set_time_limit($max_time_limit);
ini_set('max_execution_time',$max_time_limit);
require_once('mimeDecode.php');

// Some functions we'll use
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
} 

// Find a happy place! Find a happy place!
function saveFile($filename,$contents,$mimeType){
    global $save_directory,$saved_files,$debug;
    $filename = preg_replace('/[^a-zA-Z0-9_-]/','_',$filename);

    $unlocked_and_unique = FALSE;
    while(!$unlocked_and_unique){
        // Find unique
        $name = time() . "_" . $filename;
        while(file_exists($save_directory . $name)) {
            $name = time() . "_" . $filename;
        }

        // Attempt to lock
        $outfile = fopen($save_directory.$name,'w');
        if(flock($outfile,LOCK_EX)){
            $unlocked_and_unique = TRUE;
        }else{
            flock($outfile,LOCK_UN);
            fclose($outfile);
        }
    }

    fwrite($outfile,$contents);
    fclose($outfile);

    // This is for readability for the return e-mail and in the DB
    $saved_files[$name] = Array(
        'size' => formatBytes(filesize($save_directory.$name)),
        'mime' => $mimeType
    );
}

function decodePart($body_part){
    global $body,$debug;
    if(array_key_exists('name',$body_part->ctype_parameters)){ // everyone else I've tried
        $filename = $body_part->ctype_parameters['name'];
    }else if($body_part->ctype_parameters && array_key_exists('filename',$body_part->ctype_parameters)){ // hotmail
        $filename = $body_part->ctype_parameters['filename'];
    }else{
        $filename = "file";
    }

    if($debug){
        print "Found body part type {$body_part->ctype_primary}/{$body_part->ctype_secondary}\n";
    }

    $mimeType = "{$body_part->ctype_primary}/{$body_part->ctype_secondary}"; 

    switch($body_part->ctype_primary){
    case 'text':
        switch($body_part->ctype_secondary){
        case 'plain':
            $body = $body_part->body; // If there are multiple text/plain parts, we will only get the last one.
            break;
        }
        break;
        case 'application':
            switch ($body_part->ctype_secondary){
            case 'pdf': // save these file types
            case 'zip':
            case 'octet-stream':
                saveFile($filename,$body_part->body,$mimeType);
                break;
            default:
                // anything else (exe, rar, etc.) will faill into this hole and die
                break;
            }
            break;
            case 'image':
                switch($body_part->ctype_secondary){
                case 'jpeg': // Save these image types
                case 'png':
                case 'gif':
                    saveFile($filename,$body_part->body,$mimeType);
                    break;
                default:
                    break;
                }
                break;
                case 'multipart':
                    if(is_array($body_part->parts)){
                        foreach($body_part->parts as $ix => $sub_part){
                            decodePart($sub_part);
                        }
                    }
                    break;
                default:
                    // anything else isn't handled
                    break;
    }
}

//
// Actual email handling starts here!
// 

// Process the e-mail from stdin
$fd = fopen('php://stdin','r');
$raw = '';
while(!feof($fd)){ $raw .= fread($fd,1024); }

// Uncomment this for debugging.
// Then you can do
// cat /my/saved/file.raw | ./mailReader.php
// for testing
//file_put_contents("$save_directory/" . time() . "_email.raw",$raw);

// Now decode it!
// http://pear.php.net/manual/en/package.mail.mail-mimedecode.decode.php
$decoder = new Mail_mimeDecode($raw);
$decoded = $decoder->decode(
    Array(
        'decode_headers' => TRUE,
        'include_bodies' => TRUE,
        'decode_bodies' => TRUE,
    )
);

// Set $from_email and check if it's allowed
$from = $decoded->headers['from'];
$from_email = preg_replace('/.*<(.*)>.*/',"$1",$from);
if(!in_array($from_email,$allowed_senders)){
    die("$from_email not an allowed sender");
}

// Set the $subject
$subject = $decoded->headers['subject'];

// Find the email body, and any attachments
// $body_part->ctype_primary and $body_part->ctype_secondary make up the mime type eg. text/plain or text/html
if(is_array($decoded->parts)){
    foreach($decoded->parts as $idx => $body_part){
        decodePart($body_part);
    }
}

// $from_email, $subject and $body should be set now. $saved_files should have
// the files we captured

// Put the results in the database if needed
if($save_msg_to_db){
    mysql_connect($db_host,$db_un,$db_pass);
    mysql_select_db($db_name);

    $q = "INSERT INTO `emails` (`from`,`subject`,`body`) VALUES ('" .
        mysql_real_escape_string($from_email) . "','" .
        mysql_real_escape_string($subject) . "','" .
        mysql_real_escape_string($body) . "')";

    mysql_query($q) or die(mysql_error());

    if(count($saved_files) > 0){
        $id = mysql_insert_id();
        $q = "INSERT INTO `files` (`email_id`,`filename`,`size`,`mime`) VALUES ";
        $filesar = Array();
        foreach($saved_files as $f => $data){
            $filesar[] = "('$id','" .
                mysql_real_escape_string($f) . "','" .
                mysql_real_escape_string($data['size']) . "','" .
                mysql_real_escape_string($data['mime']) . "')";
        }
        $q .= implode(', ',$filesar);
        mysql_query($q) or die(mysql_error());
    }
}

// Send response e-mail if needed
if($send_email && $from_email != ""){
    $to = $from_email;
    $newmsg = "Thanks! I just uploaded the following ";
    $newmsg .= "files to your storage:\n\n";
    $newmsg .= "Filename -- Size\n";
    foreach($saved_files as $f => $s){
        $newmsg .= "$f -- $s\n";
    }
    $newmsg .= "\nI hope everything looks right. If not,";
    $newmsg .=  "please send me an e-mail!\n";

    mail($to,$subject,$newmsg);
}

if($debug){
    print "From : $from_email\n";
    print "Subject : $subject\n";
    print "Body : $body\n";
    print "Saved Files : \n";
    print_r($saved_files);
}
