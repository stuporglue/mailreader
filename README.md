mailReader.php
====================================

Recieve mail and attachments with PHP

Usage
-------------------------------------
This script expects to recieve raw emails via STDIN.

You can run the script manually by using cat

    cat testfile.txt | ./mailReader.php


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
Limited free support available in the comments on [this page] [1] for this script
or via email. Contracted support available for specific projects.

  [1]: http://stuporglue.org/mailreader-php-parse-e-mail-and-save-attachments-php-version-2/ "this page"


Thanks
-------------------------------------
Many thanks to forahobby of www.360-hq.com for testing this script and helping me find
the initial bugs and Craig Hopson of twitterrooms.co.uk for help tracking down an iOS email handling bug.



