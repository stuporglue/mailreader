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
Limited free support available in the comments on the latest blog post on [this page] [1] for this script
or via email. Contracted support available for specific projects.

  [1]: http://stuporglue.org/tag/mailreader-php/ "this page"


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

April 14, 2012
* Uses PEAR's mimeDecode.php
* Support for more mime part configurations

March 24, 2010
* Initial release
* Works for me, for Gmail.
* Homemade parser!
