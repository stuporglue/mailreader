<?php

namespace Mail;

use \Mail_mimeDecode;

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
    private $body;

    /**
     * @param \PDO $pdo A PDO connection to a database for saving emails 
     * @param string $save_directory A path to a directory where files will be saved
     */
    public function __construct($pdo = null, $save_directory = null)
    {
        if (empty($save_directory))
            $save_directory = $this->findDirectory();

        if (!\file_exists($save_directory))
            @\mkdir($save_directory, 0770, true);

        $this->save_directory = \rtrim($save_directory, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;
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

        // Now decode it!
        // http://pear.php.net/manual/en/package.mail.mail-mimedecode.decode.php
        $decoder = new Mail_mimeDecode($this->raw);
        $this->decoded = $decoder->decode([
            'decode_headers' => true,
            'include_bodies' => true,
            'decode_bodies' => true
        ]);

        $this->to = $this->decoded->headers['to'];
        $this->to_email = \preg_replace('/.*<(.*)>.*/', "$1", $this->to);
        $this->date = $this->decoded->headers['date'];
        // Set $this->from_email
        $this->from = $this->decoded->headers['from'];
        $this->from_email = \preg_replace('/.*<(.*)>.*/', "$1", $this->from);

        // Set the $this->subject
        $this->subject = $this->decoded->headers['subject'];

        // Find the email body, and any attachments
        // $body_part->ctype_primary and $body_part->ctype_secondary make up the mime type eg. text/plain or text/html
        if (isset($this->decoded->parts) && \is_array($this->decoded->parts)) {
            foreach ($this->decoded->parts as $idx => $body_part) {
                $this->decodePart($body_part);
            }
        }

        if (isset($this->decoded->disposition) && $this->decoded->disposition == 'inline') {
            $mimeType = "{$this->decoded->ctype_primary}/{$this->decoded->ctype_secondary}"; 

            if (isset($this->decoded->d_parameters) 
                && \array_key_exists('filename', $this->decoded->d_parameters)) {
                $filename = $this->decoded->d_parameters['filename'];
            } else {
                $filename = 'file';
            }

            $this->saveFile($filename, $this->decoded->body, $mimeType);
            $this->body = "Body was a binary";
        }

        // We might also have uuencoded files. Check for those.
        if (!isset($this->body)) {
            if (isset($this->decoded->body)) {
                $this->body = $this->decoded->body;
            } else {
                $this->body = "No plain text body found";
            }
        }

        if (\preg_match("/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us", $this->body) > 0) {
            foreach ($decoder->uudecode($this->body) as $file) {
                // file = Array('filename' => $filename, 'fileperm' => $fileperm, 'filedata' => $filedata)
                $this->saveFile($file['filename'], $file['filedata']);
            }

            // Strip out all the uuencoded attachments from the body
            while (\preg_match("/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us", $this->body) > 0) {
                $this->body = \preg_replace("/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us", "\n", $this->body);
            }
        }


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
     * Decode a single body part of an email message,
     * the body part of the email message, as parsed by Mail_mimeDecode.
     * 
     * Recursive if nested body parts are found
     *
     * This is the meat of the script.
     *
     * @param mixed $body_part (required) 
     */
    private function decodePart($body_part) 
    {
        if (\array_key_exists('name', $body_part->ctype_parameters)) { // everyone else I've tried
            $filename = $body_part->ctype_parameters['name'];
        } elseif ($body_part->ctype_parameters 
            && \array_key_exists('filename', $body_part->ctype_parameters)) { // hotmail
            $filename = $body_part->ctype_parameters['filename'];
        } else {
            $filename = "file";
        }

        $mimeType = "{$body_part->ctype_primary}/{$body_part->ctype_secondary}"; 

        if ($this->debug) {
            print "Found body part type $mimeType\n";
        }

        if ($body_part->ctype_primary == 'multipart') {
            if (\is_array($body_part->parts)) {
                foreach($body_part->parts as $ix => $sub_part) {
                    $this->decodePart($sub_part);
                }
            }
        } elseif ($mimeType == 'text/plain') {
            if (!isset($body_part->disposition)) {
                $this->body .= $body_part->body . "\n"; // Gather all plain/text which doesn't have an inline or attachment disposition
            }
        } elseif (\in_array($mimeType,$this->allowed_mime_types)) {
            $this->saveFile($filename,$body_part->body, $mimeType);
        }
    }

    /**
     * Save off a single file
     *
     * @param string $filename (required) The filename to use for this file
     * @param mixed $contents (required) The contents of the file we will save
     * @param string $mimeType (required) The mime-type of the file
     */
    private function saveFile($filename, $contents, $mimeType = 'unknown')
    {
        $filename = \preg_replace('/[^a-zA-Z0-9_-]/','_', $filename);

        $unlocked_and_unique = FALSE;
        while (!$unlocked_and_unique) {
            // Find unique
            $name = \time() . "_" . $filename;
            while (\file_exists($this->save_directory . $name)) {
                $name = \time() . "_" . $filename;
            }

            // Attempt to lock
            $outFile = \fopen($this->save_directory.$name, 'w');
            if (\flock($outFile, \LOCK_EX)) {
                $unlocked_and_unique = TRUE;
            } else {
                \flock($outFile, \LOCK_UN);
                \fclose($outFile);
            }
        }

        \fwrite($outFile, $contents);
        \fclose($outFile);

        // This is for readability for the return e-mail and in the DB
        $this->saved_files[$name] = Array(
            'size' => $this->formatBytes(\filesize($this->save_directory.$name)),
            'mime' => $mimeType
        );
    }

    /**
     * Format Bytes into human-friendly sizes
     * with the number of bytes in the largest applicable unit (eg. KB, MB, GB, TB)
     *
     * @return string 
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = \max($bytes, 0);
        $pow = \floor(($bytes ? \log($bytes) : 0) / \log(1024));
        $pow = \min($pow, \count($units) - 1);

        $bytes /= \pow(1024, $pow);

        return \round($bytes, $precision) . ' ' . $units[$pow];
    } 

    /**
     * Save the plain text, subject and sender of an email to the database
     * @throws \Exception if insert failure
     */
    private function saveToDb()
    {
        $insert = $this->pdo->prepare("INSERT INTO emails (user, toaddr, sender, fromaddr, date, subject, body) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // Replace non UTF-8 characters with their UTF-8 equivalent, or drop them
        if (!$insert->execute(Array(
            \mb_convert_encoding($this->to, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->to_email, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->from, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->from_email, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->date, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->subject, 'UTF-8', 'UTF-8'),
            \mb_convert_encoding($this->body, 'UTF-8', 'UTF-8')
        ))) {
            if ($this->debug) {
                \print_r($insert->errorInfo());
            }
            throw new \Exception("INSERT INTO emails failed!");
        }
        $email_id = $this->pdo->lastInsertId();
        unset($insert);

        foreach ($this->saved_files as $f => $data) {
            $insertFile = $this->pdo->prepare("INSERT INTO files (email, name, size, mime) VALUES (:email, :name, :size, :mime)");
            $insertFile->bindParam(':email', $email_id);
     	    $convertedFilename = \mb_convert_encoding($f, 'UTF-8', 'UTF-8');
            $insertFile->bindParam(':name', $convertedFilename);
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
        foreach ($this->saved_files as $f => $s) {
            $newmsg .= "$f -- ({$s['size']}) of type {$s['mime']}\n";
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
        print "Body : $this->body\n";
        print "Saved Files : \n";
        print_r($this->saved_files);
    }
}
