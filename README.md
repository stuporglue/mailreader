mailReader.php
====================================

Recieve mail and attachments with PHP

Usage
-------------------------------------
mailReader.php contains the class that works with the incoming email. 

mailPipe.php is a sample script using the mailReader class.


mailPipe.php expects to recieve raw emails via STDIN.

You can run the script manually by using cat

    cat testfile.txt | ./mailPipe.php

You will likely want to copy mailPipe.php to your own script and adjust
the parameters to suite your needs.


Requirements
-------------------------------------
You will need mimeDecode.php from http://pear.php.net/package/Mail_mimeDecode/ 

I used version 1.5.5.

Setup
-------------------------------------
Configure your mail server to pipe emails to this script. See
http://stuporglue.org/add-an-email-address-that-forwards-to-a-script/
for instructions.  

Make this script executable, and edit the configuration options to suit your needs. Change permissions
of the directories so that the user executing the script (probably the
mail user) will have write permission to the file upload directory.

By default the script is configured to save pdf, zip, jpg, png and gif files.
Edit the switch statements around line 200 to change this.


License
-------------------------------------
Copyright 2012, 
Michael Moore <stuporglue@gmail.com>
http://stuporglue.org

Licensed under the same terms as PHP itself and under the GPLv2.

You are free to use this script for personal or commercial projects. 

Use at your own risk. No guarantees or warranties.


Support
-------------------------------------
MailReader is No Longer Being Supported For Free (But it’s Still Free)

It has been a fun ride, and many people are still interested in MailReader, but my own interests have moved elsewhere. I haven’t used MailReader for my own projects for nearly 2 years and there has never been any money in it for me or anything like that.

I will no longer be doing free support for MailReader.

If you require assistance I will be charging $75/hour. Most of the support requests I get would be solved in an hour or less. Contact me at stuporglue@gmail.com to make arrangements.

 1. You can still download and use MailReader. Its code will live on GitHub for as long as GitHub is around.
 2. If you have problems, you are encouraged to post them on the MailReader GitHub issue tracker instead of as comments on my blog.
 3. MailReader is OpenSource. You can pay (or not) anyone you want (including yourself!) to work on MailReader, the code is here.
 4. I will accept GitHub pull requests that fix bugs or add features. This sort of maintenance will be done for free.


Thanks
-------------------------------------
Many thanks to forahobby of www.360-hq.com for testing this script and helping me find
the initial bugs and Craig Hopson of twitterrooms.co.uk for help tracking down an iOS email handling bug.


Versions
-------------------------------------
May 21, 2013
* UUEncoded attachment support
* It's now a class
* Uses PHP PDO connection with prepared statements instead of mysql/mysql_real_escape_string
* Support for inline content type (from mail app on mac?)

April 14, 2012
* Uses PEAR's mimeDecode.php
* Support for more mime part configurations

March 24, 2010
* Initial release
* Works for me, for Gmail.
* Homemade parser!
