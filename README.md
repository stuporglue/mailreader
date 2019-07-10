mailreader
====================================

[![Build Status](https://travis-ci.org/techno-express/mailreader.svg?branch=master)](https://travis-ci.org/techno-express/mailreader)[![codecov](https://codecov.io/gh/techno-express/mailreader/branch/master/graph/badge.svg)](https://codecov.io/gh/techno-express/mailreader)

Receive mail and attachments with PHP

This package can be used to...

- Parse, decode and read email from Postfix, and others still WIP.
- For reading messages (Filename extension: eml)
- Create webMail
- Store email information such a subject, body, attachments, and etc. into a database

Usage
-------------------------------------

Full documentation is a work in progress, see **Phpunit** [tests](#./tests/) folder for more example usage.

**MailReader.php** contains the class that works with the incoming email, and the database.

**MailParser.php** contains the class that works with any file that's in an email format.

**mailPipe.php** is a sample script using the MailReader class.

**mailPipe.php** expects to receive raw emails via **STDIN**.

You can run the script manually by using **`cat`**

```sh
cat tests/testfile | ./mailPipe.php
```

Or **`type`** On Windows

```cmd
type tests\testfile | php mailPipe.php
```

You will likely want to copy *mailPipe.php* to your own script and adjust the parameters to suite your needs.

This library also allows you to easily parse an email given its content (headers + body).

```php
require 'vendor/autoload.php';

use Mail\MailParser;

$emailPath = "/var/mail/spool/dan/new/12323344323234234234";
$emailParser = new MailParser(file_get_contents($emailPath));

// You can use some predefined methods to retrieve headers...
$to = $emailParser->getTo();
$subject = $emailParser->getSubject();
$cc = $emailParser->getCc();
$from = $emailParser->getFrom();
$fromName = $emailParser->getFromName();
$fromEmail = $emailParser->getFromEmail();
$attachments = $emailParser->getAttachments();

$actualContent = $attachments[0]['content']

// ... or you can use the 'general purpose' method getHeader()
$emailDeliveredToHeader = $emailParser->getHeader('Delivered-To');

$emailBody = $emailParser->getPlain();
```

Installation
-------------------------------------

```shell
composer require forked/mailreader
```

Will pull composer [forked/mail_mime-decode](https://packagist.org/packages/forked/mail_mime-decode) package in as dependency.

Setup
-------------------------------------

Configure your mail server to pipe emails to this script. See
<http://stuporglue.org/add-an-email-address-that-forwards-to-a-script/>
for instructions.  

Make this script *executable*, and edit the configuration options to suit your needs. Change permissions of the directories so that the user executing the script (probably the mail user) will have write permission to the file upload directory.

By default the script is configured to save pdf, zip, jpg, png and gif files. Edit the method array property `$allowed_mime_types` around line 47 to change this. Or call `->addMimeType()` to add more.

___Postfix configuration to manage email from a mail server___

Next you need to forward emails to this script above. For that I'm using [Postfix](http://www.postfix.org/) like a mail server, you need to configure /etc/postfix/master.cf

Add this line at the end of the file (specify myhook to send all emails to the script mailPipe.php)

```sh
myhook unix - n n - - pipe              flags=F user=www-data argv=php -c /etc/php5/apache2/php.ini -f /var/www/mailPipe.php ${sender} ${size} ${recipient}
```

Edit this line (register myhook)

```sh
smtp      inet  n       -       -       -       -       smtpd                   -o content_filter=myhook:dummy
```

License
-------------------------------------

Copyright 2012,
Michael Moore <stuporglue@gmail.com>
<http://stuporglue.org>

Licensed under the same terms as PHP itself and under the GPLv2 or Later.
You are free to use this script for personal or commercial projects. Use at your own risk. No guarantees or warranties.

Support
-------------------------------------

 1. If you have problems, you are encouraged to post them on the MailReader GitHub issue tracker instead of as comments on my blog.
 2. MailReader is OpenSource. You can pay (or not) anyone you want (including yourself!) to work on MailReader, the code is here.
 3. I will accept GitHub pull requests that fix bugs or add features. This sort of maintenance will be done for free.

Thanks
-------------------------------------

Many thanks to *forahobby* of www.360-hq.com for testing this script and helping me find the initial bugs and *Craig Hopson* of twitterrooms.co.uk for help tracking down an iOS email handling bug.

Versions
-------------------------------------

___July 9, 2019___

- many additions, library more **OOP** compliant.
- added methods to easily retrieved records from the database.
- added additional classes to work with any email formated file/folder.
- added PSR-4 support, can now be installed using [Composer](https://getcomposer.org).
- added phpunit tests, and email files to test against.
- removed allowed senders, any email received with script will get an reply if turned on.
- the mailPipe script now setup to auto locate the database config and create database if not initialized.
- general code clean up.

___May 21, 2013___

- UUEncoded attachment support
- It's now a class
- Uses PHP PDO connection with prepared statements instead of mysql/mysql_real_escape_string
- Support for inline content type (from mail app on mac?)

___April 14, 2012___

- Uses PEAR's mimeDecode.php
- Support for more mime part configurations

___March 24, 2010___

- Initial release
- Works for me, for gmail.com
- Homemade parser!
