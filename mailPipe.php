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
\set_time_limit(600);
\ini_set('max_execution_time', 600);

// Anything printed to STDOUT will be sent back to the sender as an error!
// error_reporting(-1);
// ini_set("display_errors", 1);

// Where should discovered files go
//$save_directory = __DIR__; // stick them in the current directory
$info = \dirname(__FILE__);
$findConfigPath = $info.\DIRECTORY_SEPARATOR;
$save_directory = $findConfigPath.'mailPiped' ; // stick in the script's directory
if (('\\' !== \DIRECTORY_SEPARATOR) && \is_dir($findConfigPath.'public_html')) {
    $info = \posix_getpwuid(\posix_getuid());
    $findConfigPath = $info['dir'].\DIRECTORY_SEPARATOR.'public_html'.\DIRECTORY_SEPARATOR;
    $save_directory = $info['dir'].\DIRECTORY_SEPARATOR.'mailPiped' ; // stick in the process user directory
}

$config_path = null;
// locate database config file
if (($directoryHandle = @\opendir($findConfigPath)) == true ) {
    while (($file = \readdir($directoryHandle)) !== false) {
        // Make sure we're not dealing with a file or a link to the parent directory
        if ((\is_dir($findConfigPath.$file) && (($file == '.' || $file == '..') !== true) ) 
            && (\is_file($findConfigPath.$file.\DIRECTORY_SEPARATOR.'mailConfig.php'))
        ) {
			$config_path = $findConfigPath.$file.\DIRECTORY_SEPARATOR;
			break;
	   }
    }
	\closedir($directoryHandle);
}

// Require the file with the MailReader class in it
require_once($config_path.'..'.\DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR.'autoload.php');

use Mail\MailReader;

require($config_path.'mailConfig.php');
$pdo = new \PDO("mysql:host=$db_host;dbname=$db_name;charset=$db_charset;port=$db_port", 
    $db_user, 
    $db_pass, 
    array(
        \PDO::ATTR_EMULATE_PREPARES => false, 
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    )
);
\create_mail_pipe_db($pdo);

$mr = new MailReader($pdo, $save_directory);
$mr->saveOn();
$mr->sendOff();
// Example of how to add additional allowed mime types to the list
$mr->addMimeType('text/csv');
$mr->readEmail();
$pdo = null;
