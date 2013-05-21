<?php

require_once('mimeDecode.php');

/*
 * @class mailReader.php
 *
 * @brief Recieve mail and attachments with PHP
 *
 * Support: 
 * http://stuporglue.org/mailreader-php-parse-e-mail-and-save-attachments-php-version-2/
 *
 * Code:
 * https://github.com/stuporglue/mailreader
 *
 * See the README.md for the license, and other information
 */
class mailReader {
    var $saved_files = Array();
    var $send_email = FALSE; // Send confirmation e-mail back to sender?
    var $save_msg_to_db = FALSE; // Save e-mail message and file list to DB?
    var $save_directory; // A safe place for files. Malicious users could upload a php or executable file, so keep this out of your web root
    var $allowed_senders = Array(); // Allowed senders is just the email part of the sender (no name part)
    var $debug = FALSE;

    var $raw = '';
    var $decoded;
    var $from;
    var $subject;
    var $body;


    /**
     * @param $save_directory (required) A path to a directory where files will be saved
     * @param $allowed_senders (required) An array of email addresses allowed to send through this script
     * @param $pdo (optional) A PDO connection to a database for saving emails 
     */
    public function __construct($save_directory,$allowed_senders,$pdo = NULL){
        if(!preg_match('|/$|',$save_directory)){ $save_directory .= '/'; } // add trailing slash if needed
        $this->saved_directory = $save_directory;
        $this->allowed_senders = $allowed_senders;
        $this->pdo = $pdo;
    }

    /**
     * @brief Read an email message
     *
     * @param $src (optional) Which file to read the email from. Default is php://stdin for use as a pipe email handler
     *
     * @return An associative array of files saved. The key is the file name, the value is an associative array with size and mime type as keys.
     */
    public function readEmail($src = 'php://stdin'){
        // Process the e-mail from stdin
        $fd = fopen($src,'r');
        while(!feof($fd)){ $this->raw .= fread($fd,1024); }

        // Now decode it!
        // http://pear.php.net/manual/en/package.mail.mail-mimedecode.decode.php
        $decoder = new Mail_mimeDecode($this->raw);
        $this->decoded = $decoder->decode(
            Array(
                'decode_headers' => TRUE,
                'include_bodies' => TRUE,
                'decode_bodies' => TRUE,
            )
        );

        // Set $this->from_email and check if it's allowed
        $this->from = $this->decoded->headers['from'];
        $this->from_email = preg_replace('/.*<(.*)>.*/',"$1",$this->from);
        if(!in_array($this->from_email,$this->allowed_senders)){
            die("$this->from_email not an allowed sender");
        }

        // Set the $this->subject
        $this->subject = $this->decoded->headers['subject'];

        // Find the email body, and any attachments
        // $body_part->ctype_primary and $body_part->ctype_secondary make up the mime type eg. text/plain or text/html
        if(isset($this->decoded->parts) && is_array($this->decoded->parts)){
            foreach($this->decoded->parts as $idx => $body_part){
                $this->decodePart($body_part);
            }
        }

        // We might also have uuencoded files. Check for those.
        if(!isset($this->body)){
           if(isset($this->decoded->body)){
                $this->body = $this->decoded->body;
           }else{
                $this->body = "No plain text body found";
           }
        }

        foreach($decoder->uudecode($this->body) as $file){
            // file = Array('filename' => $filename, 'fileperm' => $fileperm, 'filedata' => $filedata)
            $this->saveFile($file['filename'],$file['filedata']);
        }
        

        // Put the results in the database if needed
        if($this->save_msg_to_db && !is_null($this->pdo)){
            $this->saveToDb();
        }

        // Send response e-mail if needed
        if($this->send_email && $this->from_email != ""){
            $this->sendEmail();
        }

        // Print messages
        if($this->debug){
            $this->debugMsg();
        }

        return $this->saved_files;
    }

    /**
     * @brief Decode a single body part of an email message
     *
     * @note Recursive if nested body parts are found
     *
     * @note This is the meat of the script.
     *
     * @param $body_part (required) The body part of the email message, as parsed by Mail_mimeDecode
     */
    private function decodePart($body_part){
        if(array_key_exists('name',$body_part->ctype_parameters)){ // everyone else I've tried
            $filename = $body_part->ctype_parameters['name'];
        }else if($body_part->ctype_parameters && array_key_exists('filename',$body_part->ctype_parameters)){ // hotmail
            $filename = $body_part->ctype_parameters['filename'];
        }else{
            $filename = "file";
        }

        if($this->debug){
            print "Found body part type {$body_part->ctype_primary}/{$body_part->ctype_secondary}\n";
        }

        $mimeType = "{$body_part->ctype_primary}/{$body_part->ctype_secondary}"; 

        switch($body_part->ctype_primary){
        case 'text':
            switch($body_part->ctype_secondary){
            case 'plain':
                $this->body = $body_part->body; // If there are multiple text/plain parts, we will only get the last one.
                break;
            }
            break;
            case 'application':
                switch ($body_part->ctype_secondary){
                case 'pdf': // save these file types
                case 'zip':
                case 'octet-stream':
                    $this->saveFile($filename,$body_part->body,$mimeType);
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
                    $this->saveFile($filename,$body_part->body,$mimeType);
                    break;
                default:
                    break;
                }
            break;
            case 'multipart':
                if(is_array($body_part->parts)){
                    foreach($body_part->parts as $ix => $sub_part){
                        $this->decodePart($sub_part);
                    }
                }
                break;
            default:
                // anything else isn't handled
                break;
        }
    }

    /**
     * @brief Save off a single file
     *
     * @param $filename (required) The filename to use for this file
     * @param $contents (required) The contents of the file we will save
     * @param $mimeType (required) The mime-type of the file
     */
    private function saveFile($filename,$contents,$mimeType = 'unknown'){
        $filename = preg_replace('/[^a-zA-Z0-9_-]/','_',$filename);

        $unlocked_and_unique = FALSE;
        while(!$unlocked_and_unique){
            // Find unique
            $name = time() . "_" . $filename;
            while(file_exists($this->save_directory . $name)) {
                $name = time() . "_" . $filename;
            }

            // Attempt to lock
            $outfile = fopen($this->save_directory.$name,'w');
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
        $this->saved_files[$name] = Array(
            'size' => $this->formatBytes(filesize($this->save_directory.$name)),
            'mime' => $mimeType
        );
    }

    /**
     * @brief Format Bytes into human-friendly sizes
     *
     * @return A string with the number of bytes in the largest applicable unit (eg. KB, MB, GB, TB)
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    } 

    /**
     * @brief Save the plain text, subject and sender of an email to the database
     */
    private function saveToDb(){
        $insert = $this->pdo->prepare("INSERT INTO emails (from,subject,body) VALUES (?,?,?)");
        if(!$insert->execute(Array($this->from_email,$this->subject,$this->body))){
            die("INSERT INTO emails failed!");
        }
        $email_id = array_pop(array_values($insert->fetch(PDO::FETCH_ASSOC)));


        $insertFile = $this->pdo->prepare("INSERT INTO files (email_id,filename,size,mime) VALUES (?,?,?,?)");
        foreach($this->saved_files as $f => $data){
            $insertFile->bindParam($email_id,$f,$data['size'],$data['mime']);
        }
        $insertFile->execute();
    }

    /**
     * @brief Send the sender a response email with a summary of what was saved
     */
    private function sendEmail(){
        $newmsg = "Thanks! I just uploaded the following ";
        $newmsg .= "files to your storage:\n\n";
        $newmsg .= "Filename -- Size\n";
        foreach($this->saved_files as $f => $s){
            $newmsg .= "$f -- $s\n";
        }
        $newmsg .= "\nI hope everything looks right. If not,";
        $newmsg .=  "please send me an e-mail!\n";

        mail($this->from_email,$this->subject,$newmsg);
    }

    /**
     * @brief Print a summary of the most recent email read
     */
    private function debugMsg(){
        print "From : $this->from_email\n";
        print "Subject : $this->subject\n";
        print "Body : $this->body\n";
        print "Saved Files : \n";
        print_r($this->saved_files);
    }
}
