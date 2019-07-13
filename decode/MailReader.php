<?php

namespace Mail;

use \Mail_mimeDecode;
use Mail\MailParser;

/**
 * @package MailReader.php
 *
 * Receive mail and attachments with PHP
 *
 * Support: 
 * http://stuporglue.org/mailreader-php-parse-e-mail-and-save-attachments-php-version-2/
 *
 * Code:
 * https://github.com/stuporglue/mailreader
 *
 * See the README.md for the license, and other information
 */
class MailReader 
{
    /**
     * Attachments Array
     *
     * @var array
     */
    private $saved_files = [];

    /**
     * Send confirmation e-mail back to sender?
     *
     * @var boolean
     */
    private $send_email = false;

    /**
     * Save e-mail message and file list to DB?
     *
     * @var boolean
     */
    private $save_msg_to_db = false;

    /**
     * A safe place for files. 
     * Malicious users could upload a php or executable file, 
     * so keep this out of your web root
     *
     * @var string
     */
    private $save_directory;

    private $allowed_mime_types = [
        'audio/wave',
        'audio/caf',
        'application/pdf',
        'application/zip',
        'application/octet-stream',
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    private $debug = false;

    private $raw = '';
    private $decoded;
    private $to;
    private $to_email;
    private $date;
    private $from;
    private $from_email;
    private $subject;
    private $plain;
    private $html;

    /**
     * @param \PDO $pdo A PDO connection to a database for saving emails 
     * @param string $save_directory A path to a directory where files will be saved
     */
    public function __construct($pdo = null, $save_directory = null)
    {
        if (!\file_exists($save_directory))
            @\mkdir($save_directory, 0770, true);

        if (!\preg_match('|\\/$|', $save_directory))
            $save_directory .= \DIRECTORY_SEPARATOR;

        $this->save_directory = $save_directory;
        $this->pdo = $pdo;
    }

    /**
     * Add additional allowed mime types to the list.
     * 
     * @param string
     */
    public function addMimeType($mime_types = '')
    {
        if (\strpos($mime_types, '/') !== false)
            \array_push($this->allowed_mime_types, $mime_types);

        return $this;
    }

    public function debugOn()
    {
        $this->debug = true;
        return $this;
    }

    public function debugOff()
    {
        $this->debug = false;
        return $this;
    }

    /**
     * Turn on sending confirmation e-mail back to sender.
     */
    public function sendOn()
    {
        $this->send_email = true;
        return $this;
    }

    /**
     * Turn off sending confirmation e-mail back to sender.
     */
    public function sendOff()
    {
        $this->send_email = false;
        return $this;
    }

    /**
     * Turn on saving e-mail message and file list to the database.
     */
    public function saveOn()
    {
        $this->save_msg_to_db = true;
        return $this;
    }

    /**
     * Turn off saving e-mail message and file list to the database.
     */
    public function saveOff()
    {
        $this->save_msg_to_db = false;        
        return $this;
    }

    public function findDirectory($startingDirectory = null, $directoryNamed = 'mailPiped')
    {
        $home = (\getenv('USERPROFILE') !== false) ? \getenv('USERPROFILE') : \getenv('HOME');
        $info = ($home !== false) ? $home : \dirname(__FILE__);
        $findConfigPath = $info.\DIRECTORY_SEPARATOR;
        $save_directory = '';

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $info = \posix_getpwuid(\posix_getuid());
            $findConfigPath = $info['dir'].\DIRECTORY_SEPARATOR;
        }
        if (!empty($startingDirectory))
            $findConfigPath = $startingDirectory.\DIRECTORY_SEPARATOR;

        if (($directoryHandle = @\opendir($findConfigPath)) == true ) {
            while (($file = \readdir($directoryHandle)) !== false) {
                if (\is_dir($findConfigPath.$file) 
                    && (($file == '.' || $file == '..') !== true) 
                    && (\strpos($file, $directoryNamed) !== false)
                ) {
                    $save_directory = $findConfigPath.$file;
                    break;
               }
            }
            \closedir($directoryHandle);
        }

        return $save_directory;
    }

    /**
     * Read an email message
     * with the given $src file to read the email from. 
     * Default is php://stdin for use as a pipe email handler
     * 
     * Will returns an associative array of files saved. 
     * The key is the file name, the value is an associative array with size and mime type as keys.
     * 
     * @param $src (optional) 
     *
     * @return array
     */
    public function readEmail($src = 'php://stdin')
    {
        // Process the e-mail from stdin
        $fd = \fopen($src, 'r');
        while (!\feof($fd)) { 
            $this->raw .= \fread($fd, 1024); 
        }

        $this->decoded = new MailParser($this->raw);

        $this->to = $this->decoded->getTo();
        $this->to_email = $this->decoded->getToEmail();
        $this->date = $this->decoded->getDate();
        $this->from = $this->decoded->getFrom();
        $this->from_email = $this->decoded->getFromEmail();
        $this->subject = $this->decoded->getSubject();
        $this->plain = $this->decoded->getPlain();
        $this->html = $this->decoded->getHtml();
        $this->saved_files = $this->decoded->decode($this->save_directory);

        // Put the results in the database if needed
        if ($this->save_msg_to_db && !\is_null($this->pdo)) {
            $this->saveToDb();
        }

        // Send response e-mail if needed
        if ($this->send_email && $this->from_email != "") {
            $this->sendEmail();
        }

        // Print messages
        if ($this->debug) {
            $this->debugMsg();
        }

        return $this->saved_files;
    }

    public function getMessageCount(string $email)
    {
        $dbc = $this->pdo;
		if ($dbc instanceof \PDO) {
            $messages = $dbc->prepare("SELECT count(*) FROM emails WHERE toaddr=?");
            if (!is_bool($messages)) {
                $messages->execute([$email]);
                $count = $messages->fetchColumn();
                unset($messages);
                return $count;
            }
        }

        return false;
    }

    public function getMessages(string $email, int $offset = 0, int $max = 20)
    {
        $dbc = $this->pdo;
		if ($dbc instanceof \PDO) {
            $emails = $dbc->prepare("SELECT * FROM emails WHERE toaddr=:mail LIMIT :offset, :max");
            if (!is_bool($emails)) {
                $emails->bindValue(':mail', $email);
                $emails->bindValue(':offset', $offset, \PDO::PARAM_INT);
                $emails->bindValue(':max', $max, \PDO::PARAM_INT);
                $emails->execute();

                $records = [];
                while ($record = $emails->fetchObject('Mail\Messages')) {
                    $records[] = $record;
                }

                unset($emails);
                if (\count($records) > 0) 
                    return $records;
                
                return 0;
            }
        }

        return false;
    }

    public function getMessageAttachments(int $message_id)
    {
        $dbc = $this->pdo;
		if ($dbc instanceof \PDO) {
            $files = $dbc->prepare("SELECT * FROM files WHERE email=:id");
            if (!is_bool($files)) {
                $files->bindValue(':id', $message_id, \PDO::PARAM_INT);
                $files->execute();

                $records = [];
                while ($record = $files->fetchObject('Mail\MessageAttachments')) {
                    $records[] = $record;
                }

                unset($files);
                if (\count($records) > 0) 
                    return $records;
            }
        }

        return false;
    }

    /**
     * Save the html and plain text, receiver, subject and sender of an email to the database
     * @throws \Exception if insert failure
     */
    private function saveToDb()
    {
        $insert = $this->pdo->prepare("INSERT INTO emails (user, toaddr, sender, fromaddr, date, subject, plain, html) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        // Replace non UTF-8 characters with their UTF-8 equivalent, or drop them
        if (!$insert->execute(Array(
            \mb_convert_encoding($this->to, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->to_email, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->from, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->from_email, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->date, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->subject, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->plain, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->html, 'UTF-8', 'UTF-8')
        ))) {
            if ($this->debug) {
                \print_r($insert->errorInfo());
            }
            throw new \Exception("INSERT INTO emails failed!");
        }
        $email_id = $this->pdo->lastInsertId();
        unset($insert);

        foreach ($this->saved_files as $data) {
            $insertFile = $this->pdo->prepare("INSERT INTO files (email, name, path, size, mime) VALUES (:email, :name, :path, :size, :mime)");
            $insertFile->bindParam(':email', $email_id);
     	    $convertedFilename = \mb_convert_encoding($data['name'], 'UTF-8', 'UTF-8');
            $insertFile->bindParam(':name', $convertedFilename);
            $insertFile->bindParam(':path', $data['path']);
            $insertFile->bindParam(':size', $data['size']);
            $insertFile->bindParam(':mime', $data['mime']);
            if (!$insertFile->execute()) {
                if ($this->debug) {
                    \print_r($insertFile->errorInfo());
                }
                throw new \Exception("Insert file info failed!");
            }
        }
        unset($insertFile);
    }

    /**
     * Send the sender a response email with a summary of what was saved
     */
    private function sendEmail()
    {
        $newmsg = "Thanks! We just received the following ";
        $newmsg .= "files for storage:\n\n";
        $newmsg .= "Filename -- Size\n";
        foreach ($this->saved_files as $s) {
            $newmsg .= "{$s['name']} -- ({$s['size']}) of type {$s['mime']}\n";
        }

        $newmsg .= "\nHope everything looks right. If not,";
        $newmsg .=  "please send us an e-mail!\n";

        \mail($this->from_email, $this->subject, $newmsg);
    }

    /**
     * Print a summary of the most recent email read
     */
    private function debugMsg()
    {
        print "From : $this->from_email\n";
        print "Subject : $this->subject\n";
        print "PlainBody : $this->plain\n";
        print "HtmlBody : $this->html\n";
        print "Saved Files : \n";
        print_r($this->saved_files);
    }
}
