<?php

namespace Mail;

use \Mail_mimeDecode;

class MailParser
{
    protected $allowedMimeTypes = [
        'audio/wave',
        'audio/caf',
        'application/pdf',
        'application/zip',
        'application/octet-stream',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    protected $disallowedMimeTypes = [];
    protected $charset = 'UTF-8';
    protected $debug = false;

    /**
     * raw mail split up by header field
     * 
     * @var array
     */
    protected $rawFields;

    /**
     * Body as array of strings, each element is a line
     * 
     * @var array
     */
    protected $rawBodyLines;

    protected $raw = null;
    protected $decoded = null;
    protected $body = "";
    protected $html = "";

    /**
     * Attachments Array
     *
     * @var array
     */
    protected $attachments = [];
    
    /**
     * A safe place for files.
     *
     * @var string
     */
    protected $attachment_directory;

    public function __construct($raw = null)
    {
        if ( !empty($raw) ) {
            $this->parse($raw);
        }
    }

    public function parse($raw)
    {
        if ( $raw !== null ) {
            $this->raw = $raw;
        }

        if ( $this->raw !== null ) {
            $this->extractHeadersAndBody();
            return $this;
        }
    }

    public function decode($attachment_directory = null, $saving = true)
    {
        if ( $this->raw !== null ) {
            if (!empty($attachment_directory))
                $this->attachment_directory = $attachment_directory;
            elseif (empty($this->attachment_directory))
                $this->attachment_directory = \sys_get_temp_dir();

            if (!\file_exists($this->attachment_directory))
                @\mkdir($this->attachment_directory, 0770, true);

            // add trailing slash if needed
            if (!\preg_match('|\\/$|', $this->attachment_directory)) { 
                $this->attachment_directory .= \DIRECTORY_SEPARATOR; 
            }

            // http://pear.php.net/manual/en/package.mail.mail-mimedecode.decode.php
            $decoder = new Mail_mimeDecode($this->raw);

            $this->decoded = $decoder->decode([
                'decode_headers' => true,
                'include_bodies' => true,
                'decode_bodies'  => true
            ]);

            if ( isset($this->decoded->parts) && \is_array($this->decoded->parts) ) {
                foreach ( $this->decoded->parts as $idx => $body_part ) {
                    $this->decodePart($body_part, $saving);
                }
            }

            if ( isset($this->decoded->disposition) && $this->decoded->disposition == 'inline' ) {
                $mime_type = "{$this->decoded->ctype_primary}/{$this->decoded->ctype_secondary}";

                if ( isset($this->decoded->d_parameters) && \array_key_exists('filename', $this->decoded->d_parameters) ) {
                    $filename = $this->decoded->d_parameters['filename'];
                } else {
                    $filename = 'file';
                }

                if ( $this->isValidAttachment($mime_type) ) {
                    $this->saveAttachment($filename, $this->decoded->body, $mime_type, $saving);
                }

                $this->body = "";
            }

            // We might also have uuencoded files. Check for those.
            if ( empty($this->body) ) {
                $this->body = isset($this->decoded->body) ? $this->decoded->body : "";
            }

            if ( \preg_match("/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us", $this->body) > 0 ) {
                foreach ( $decoder->uudecode($this->body) as $file ) {
                    $this->saveAttachment($file['filename'], $file['filedata'], 'unknown', $saving);
                }
                // Strip out all the uuencoded attachments from the body
                while ( \preg_match("/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us", $this->body) > 0 ) {
                    $this->body = \preg_replace("/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us", "\n", $this->body);
                }
            }

            $this->body = \mb_convert_encoding($this->body, $this->charset, $this->charset);

            return $this->attachments;
        }
    }
		
	/**
	 * Detect and return an array of attachments and their data. 
     * ```
     * Array([0] => 
     *  Array(
     *      [type] => audio/caf 
	 *      [name] => example_vmr_09102012182307.3gp 
	 *      [content] => Y2FmZgA
     *  ), Array(...)
     * )
     * ```
     * JSON format. 
     * ```
	 *  [{
     *      "type": "text/plain",
     *      "name": "text.txt",
     *      "content": "dGVTVGluZyA="
     *  }, {}]
     * ```
     * @param bool $json return data in JSON format
	 * @return array|bool
	 */
	public function getAttachments($json = false)
	{
		$decoded = $this->decode(null, false);
		return ($json) ? \json_encode($decoded) : $decoded;
    }

    /**
     *
     * @param string $line
     * @return boolean
     */
    private function isNewLine($line)
    {
        $line = \str_replace("\r", '', $line);
        $line = \str_replace("\n", '', $line);

        return (\strlen($line) === 0);
    }

    private function extractHeadersAndBody()
    {
        $lines = \preg_split("/(\r?\n|\r)/", $this->raw);

        $currentHeader = '';

        $i = 0;
        foreach ($lines as $line) {
            if ($this->isNewLine($line)) {
                // end of headers
                $this->rawBodyLines = \array_slice($lines, $i);
                break;
            }
            // check is line starting With printable character
            if (\preg_match('/^[A-Za-z]/', $line)) {
                // start of new header
                \preg_match('/([^:]+): ?(.*)$/', $line, $matches);
                $newHeader = \strtolower($matches[1]);
                $value = $matches[2];

                if (isset($this->rawFields[$newHeader])) {
                    if (\is_array($this->rawFields[$newHeader]))
                        $this->rawFields[$newHeader][] = $value;
                    else
                        $this->rawFields[$newHeader] = array($this->rawFields[$newHeader], $value);
                } else
                    $this->rawFields[$newHeader] = $value;


                $currentHeader = $newHeader;
            } else {
                // more lines related to the current header
                if ($currentHeader) { 
                    // to prevent notice from empty lines
        			if (\is_array($this->rawFields[$currentHeader])) {
        				$this->rawFields[$currentHeader][\count($this->rawFields[$currentHeader]) - 1] .= \substr($line, 1);
        			} else {
                        $this->rawFields[$currentHeader] .= \substr($line, 1);
        			}
                }
            }
            $i++;
        }
    }

    /**
     * Add additional allowed mime types to the list.
     * 
     * @param string
     */
    public function addMimeType($mime_types = '')
    {
        if (\strpos($mime_types, '/') !== false)
            \array_push($this->allowedMimeTypes, $mime_types);

        return $this;
    }

    /**
     * Add additional disallowed mime types to the list.
     * 
     * @param string
     */
    public function removeMimeType($mime_types = '')
    {
        if (\strpos($mime_types, '/') !== false)
            \array_push($this->disallowedMimeTypes, $mime_types);

        return $this;
    }

    /**
     * @return string - UTF8 encoded
     * 
     * Example of an email body
     * 
     *   --0016e65b5ec22721580487cb20fd
     *   Content-Type: text/plain; charset=ISO-8859-1
     *   Hi all. I am new to Android development.
     *   Please help me.
     * 
     *   --
     *   My signature
     *  email: myemail@gmail.com
     *  web: http://www.example.com

     *  --0016e65b5ec22721580487cb20fd
     *  Content-Type: text/html; charset=ISO-8859-1
     */
    public function getBody(string $returnType = null)
    {
        $body = '';
        $detectedContentType = false;
        $contentTransferEncoding = null;
        $charset = 'ASCII';
        $waitingForContentStart = true;

        if ($returnType == 'HTML')
            $contentTypeRegex = '/^Content-Type: ?text\/html/i';
        else
            $contentTypeRegex = '/^Content-Type: ?text\/plain/i';
        
        // there could be more than one boundary. This also skips the quotes if they are included.
        \preg_match_all('/boundary=(?:|")([a-zA-Z0-9_=\.\(\)_\/+-]+)(?:|")(?:$|;)/mi', $this->raw, $matches);
        $boundaries = $matches[1];
        // sometimes boundaries are delimited by quotes - we want to remove them
        foreach ($boundaries as $i => $v) {
            $boundaries[$i] = \trim(\str_replace(array("'", '"'), '', $v));
        }
        
        foreach ($this->rawBodyLines as $line) {
            if (!$detectedContentType) {
                
                if (\preg_match($contentTypeRegex, $line, $matches)) {
                    $detectedContentType = true;
                }
                
                if(\preg_match('/charset=(.*)/i', $line, $matches)) {
                    $charset = \strtoupper(\trim($matches[1], '"')); 
                }       
                
            } elseif ($detectedContentType && $waitingForContentStart) {
                
                if (\preg_match('/charset=(.*)/i', $line, $matches)) {
                    $charset = \strtoupper(\trim($matches[1], '"')); 
                }                 
                
                if ($contentTransferEncoding == null && \preg_match('/^Content-Transfer-Encoding: ?(.*)/i', $line, $matches)) {
                    $contentTransferEncoding = $matches[1];
                }                
                
                if ($this->isNewLine($line)) {
                    $waitingForContentStart = false;
                }
            } else {  // ($detectedContentType && !$waitingForContentStart)
                // collecting the actual content until we find the delimiter
                
                // if the delimited is AAAAA, the line will be --AAAAA  - that's why we use substr
                if (\is_array($boundaries)) {
                    if (\in_array(\substr($line, 2), $boundaries)) {  // found the delimiter
                        break;
                    }
                }
                $body .= $line . "\n";
            }
        }

        if (!$detectedContentType)
        {
            // if here, we missed the text/plain content-type (probably it was
            // in the header), thus we assume the whole body is what we are after
            $body = \implode("\n", $this->rawBodyLines);
        }

        // removing trailing new lines
        $body = \preg_replace('/((\r?\n)*)$/', '', $body);

        if ($contentTransferEncoding == 'base64')
            $body = \base64_decode($body);
        else if ($contentTransferEncoding == 'quoted-printable')
            $body = \quoted_printable_decode($body);        
        
        if($charset != 'UTF-8') {
            // FORMAT=FLOWED, despite being popular in emails, it is not
            // supported by iconv
            $charset = \str_replace("FORMAT=FLOWED", "", $charset);
           
	        $bodyCopy = $body; 
            $body = \iconv($charset, 'UTF-8//TRANSLIT', $body);

             // iconv returns FALSE on failure
            if ($body === false) {
                $body = \utf8_encode($bodyCopy);
            }
        }

        return $body;
    }

    /**
     * @return string - UTF8 encoded
     */
    public function getPlain()
    {
        return $this->getBody('PLAIN');
    }

    /**
     * @return string - UTF8 encoded
     */
    public function getHtml()
    {
        $body = $this->getBody('HTML');
        $tmp = explode("</html>", $body);
        return $tmp[0]."</html>"; // omit any attachments.
    }

    /**
     *
     * @return string
     * @throws \Exception if a to header is not found or if there are no recipient
     */
    public function getTo()
    {
        if (!isset($this->rawFields['to'])) {
            throw new \Exception("Couldn't find the recipients of the email");
        }
        
        // @see https://www.php.net/manual/en/function.imap-utf8.php#102081
        return \iconv_mime_decode($this->rawFields['to'], 0, "UTF-8");
    }

    /**
     *
     * @return string (in UTF-8 format)
     * @throws \Exception if a subject header is not found
     */
    public function getSubject()
    {
        if (!isset($this->rawFields['subject'])) {
            throw new \Exception("Couldn't find the subject of the email");
        }
        
        // @see https://www.php.net/manual/en/function.imap-utf8.php#102081
        return \iconv_mime_decode($this->rawFields['subject'], 0, "UTF-8");
    }

    /**
     * N.B.: if the header doesn't exist an empty string is returned
     *
     * @param string $headerName - the header we want to retrieve
     * @return string - the value of the header
     */
    public function getHeader(string $headerName)
    {
        $headerName = \strtolower($headerName);

        if (isset($this->rawFields[$headerName])) {
            return $this->rawFields[$headerName];
        }

        return '';
    }

    /**
     * The parsed headers as associative array
     * 
     * @return array 
     */
    public function getHeaders()
    {
        return $this->rawFields;
    }

    /**
     *
     * @return array
     */
    public function getCc()
    {
        if (!isset($this->rawFields['cc'])) {
            return;
        }

        return \explode(',', $this->rawFields['cc']);
    }

    /**
     *
     * @return array
     */
    public function getBcc()
    {
        if (!isset($this->rawFields['bcc'])) {
            return;
        }

        return \explode(',', $this->rawFields['bcc']);
    }

    /**
     * Returns the receive date
     * 
     * @return string $date
     */
    public function getDate()
    {
        $date = $this->getHeader("date");
        return $date;
    }

    /**
     * Returns full sender name and email
     *
     * @return string (in UTF-8 format)
     * @throws \Exception if a subject header is not found
     */
    public function getFrom()
    {
        if (!isset($this->rawFields['from'])) {
            throw new \Exception("Couldn't find the sender of the email");
        }

        // @see https://www.php.net/manual/en/function.imap-utf8.php#102081
        return \iconv_mime_decode($this->rawFields['from'], 0, "UTF-8");
    }

    /**
     * Returns the name of To or From
     * 
     * @return string
     * @throws \Exception if To or From is not found
     */
    private function getToFromName($toFrom = null)
    {
        if (empty($toFrom)) {
            throw new \Exception("Couldn't find To or From of the email");
        }

        // the returned string is like "John Smith <john.smith@example.com>"
        $strName = $this->getHeader($toFrom);

        //if returned string == "<john.smith@example.com>" there's not a name. So < and > chars are removed the result is checked
        $trans = array("<" => "", ">" => "");
        $possibleEmail = \strtr($strName, $trans);

        // if it's a valid email, there's no name
        if (\filter_var($possibleEmail, \FILTER_VALIDATE_EMAIL)) {
            return "";
        }

        // else, it's only returned what's before the < char
        return \substr($strName, 0, \strpos($strName, '<') - 1);
    }

    public function getFromName()
    {
        return $this->getToFromName("from");
    }

    public function getToName()
    {
        return $this->getToFromName("to");
    }

    /**
     * Returns the email of To or From
     * 
     * @return string
     */
    private function getToFromEmail($toFrom = null)
    {
        if (empty($toFrom)) {
            throw new \Exception("Couldn't find To or From of the email");
        }

        // the returned string is like "John Smith <john.smith@example.com>"
        $strEmail = $this->getHeader($toFrom);

        //if returned string == "<john.smith@example.com>" there's not a name. So < and > chars are removed the result is checked
        $trans = array("<" => "", ">" => "");
        $possibleEmail = \strtr($strEmail, $trans);

        // if it's a valid email, it's returned immediately
        if (\filter_var($possibleEmail, \FILTER_VALIDATE_EMAIL)) {
            return $possibleEmail;
        }

        // else it's only returned what's between the < > chars
        return \substr($strEmail, \strpos($strEmail, '<') + 1, \strlen($strEmail) - \strpos($strEmail, '<') - 2);
    }

    public function getFromEmail()
    {
        return $this->getToFromEmail('from');
    }

    public function getToEmail()
    {
        return $this->getToFromEmail('to');
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
    private function decodePart($body_part, $saving = true)
    {
        if ( isset($body_part->ctype_parameters) && \is_array($body_part->ctype_parameters) ) {
            if ( \array_key_exists('name', $body_part->ctype_parameters) ) {
                $filename = $body_part->ctype_parameters['name'];
            } elseif ( \array_key_exists('filename', $body_part->ctype_parameters) ) {
                $filename = $body_part->ctype_parameters['filename'];
            }
        } elseif ( isset($body_part->d_parameters) && \is_array($body_part->d_parameters) ) {
            if ( \array_key_exists('filename', $body_part->d_parameters) ) {
                $filename = $body_part->d_parameters['filename'];
            }
        }

        if ( !isset($filename) ) {
            $filename = "file";
        }

        $mime_type = "{$body_part->ctype_primary}/{$body_part->ctype_secondary}";

        if ( $this->debug ) {
            print "Found body part type $mime_type\n";
        }

        if ( $body_part->ctype_primary == 'multipart' ) {
            if ( \is_array($body_part->parts) ) {
                foreach ( $body_part->parts as $ix => $sub_part ) {
                    $this->decodePart($sub_part, $saving);
                }
            }
        } elseif ( !isset($body_part->disposition) || $body_part->disposition == 'inline' ) {
            switch ( $mime_type ) {
                case 'text/plain':
                    $this->body .= \mb_convert_encoding($body_part->body, $this->charset, $this->charset) . "\n";
                    break;
                case 'text/html':
                    $this->html .= \mb_convert_encoding($body_part->body, $this->charset, $this->charset) . "\n";
                    break;
                default:
                    if ( $this->isValidAttachment($mime_type) ) {
                        $this->saveAttachment($filename, $body_part->body, $mime_type, $saving);
                    }
            }
        } else {
            if ( $this->isValidAttachment($mime_type) ) {
                $this->saveAttachment($filename, $body_part->body, $mime_type, $saving);
            }
        }
    }

    private function isValidAttachment($mime_type)
    {
        if (\in_array($mime_type, $this->allowedMimeTypes) && !\in_array($mime_type, $this->disallowedMimeTypes)) {            
            return true;
        }

        return false;
    }

    /**
     * Save off a single file
     *
     * @param string $filename (required) The filename to use for this file
     * @param mixed $contents (required) The contents of the file we will save
     * @param string $mimeType (required) The mime-type of the file
     * @param bool $saving should we actual save to file system
     */
    private function saveAttachment($filename, $contents, $mime_type = 'unknown', $saving = true)
    {
        $filename = \mb_convert_encoding($filename, $this->charset, $this->charset);
        $dot_ext = '.'.$this->getFileExtension($filename);
        $unlocked_and_unique = false;
        $i = 0;

        if ($saving) {
            while ( !$unlocked_and_unique && $i++ < 10 ) {
                $name = \uniqid('attachment_');
                $path = $this->attachment_directory . $name . $dot_ext;
                // Attempt to lock
                $outFile = \fopen($path, 'wb');

                if ( \flock($outFile, \LOCK_EX) ) {
                    $unlocked_and_unique = true;
                } else {
                    \flock($outFile, \LOCK_UN);
                    \fclose($outFile);
                }
            }

            if ( isset($outFile) && $outFile !== false ) {
                \fwrite($outFile, $contents);
                \fclose($outFile);
            }

            if ( isset($name, $path) ) {
                $this->attachments[] = [
                    'name' => $filename,
                    'path' => $path,
                    'size' => $this->formatBytes(\filesize($path)),
                    'mime' => $mime_type
                ];
            }
        } else {
            $this->attachments[] = [
                'name' => $filename,
                'content' => $contents,
                'type' => $mime_type
            ];
        }
    }

    /**
     * Format Bytes into human-friendly sizes
     * with the number of bytes in the largest applicable unit (eg. KB, MB, GB, TB)
     *
     * @return string 
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

        $bytes = \max($bytes, 0);
        $pow = \floor(($bytes ? \log($bytes) : 0) / \log(1024));
        $pow = \min($pow, \count($units) - 1);

        $bytes /= \pow(1024, $pow);

        return \round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getFileExtension($filename)
    {
        if ( \substr($filename, 0, 1) == '.' ) {
            return \substr($filename, 1);
        }
        $pieces = \explode('.', $filename);
        if ( \count($pieces) > 1 ) {
            return \strtolower(\array_pop($pieces));
        }
    }
}
