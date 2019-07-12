<?php

namespace Mail\Tests;

use Mail\MailParser;
use PHPUnit\Framework\TestCase;

class MailParserTest extends TestCase
{
    public function testSubject()
    {
        $file = __DIR__ . '/emails/m0002';
        $email = new MailParser(\file_get_contents($file));

        $this->assertEquals('foobar.com download complete', $email->getSubject());
    }

    public function testSubjectForeign()
    {
        $file = __DIR__ . '/emails/m0009';
        $email = new MailParser(\file_get_contents($file));

        $this->assertEquals("これはテストです", $email->getSubject());
        $this->assertEquals("それは作品を期待", $email->getPlain());
    }

    public function testFrom()
    {
        $file = __DIR__ . '/emails/m0001';
        $email = new MailParser(\file_get_contents($file));

        $this->assertEquals('Name <name@company.com>', $email->getFrom());
        $this->assertEquals("Mail avec fichier attaché de 1ko", $email->getSubject());
    }

    public function testTo()
    {
        $file = __DIR__ . '/emails/m0003';
        $email = new MailParser(\file_get_contents($file));

        $this->assertEquals('dan@test.com', $email->getTo());
    }

    public function testCcAndBcc()
    {
        $file = __DIR__ . '/emails/m0004';
        $email = new MailParser(\file_get_contents($file));

        $this->assertNull($email->getCc());
        $this->assertNull($email->getBcc());
    }

    public function testDateSubject()
    {
        $file = __DIR__ . '/emails/m0006';
        $email = new MailParser(\file_get_contents($file));

        $expect = 'Re: Testo Del di Soggetto Che Va A Capo In UTF8 ';

        $this->assertEquals('10 May 2012 14:41:43 -0000', $email->getDate());
        $this->assertEquals($expect, $email->getSubject());
    }

    public function testFromName()
    {
        $file = __DIR__ . '/emails/m0005';
        $email = new MailParser(\file_get_contents($file));

        $this->assertEquals('Dan Occhi', $email->getFromName());
    }

    public function testFromEmail()
    {
        $file = __DIR__ . '/emails/m0004';
        $email = new MailParser(\file_get_contents($file));

        $this->assertEquals('dan@test.com', $email->getFromEmail());

    }

    public function testPlainBody()
    {
        
        $file = __DIR__ . '/emails/m0002';
        $email = new MailParser(\file_get_contents($file));
        $expect = '
Hello,

this is the automated message from foobar.com. Your files are cached already and ready for download.

Log in to your account and start download or click the link:
http://foobar.com/files?a=true&hash=123

---
support@foobar.com';
        $this->assertEquals($expect, $email->getPlain());
    }

    public function testHtmlBody()
    {
        
        $file = __DIR__ . '/emails/m0003';
        $email = new MailParser(\file_get_contents($file));
        $expect = '<html><head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8"></head><body
 style="font-family: tt; font-size: 10pt;" bgcolor="#FFFFFF" 
text="#000000">
<div style="font-size: 10pt;font-family: tt;"><span style="font-family: 
monospace;">This is a nonesense test-email to check Mail to 
Inbox feature.<br>All Polish diacritics included ;-) tag also included for 
additional tests.<br>It should look like in the picture attached.<br>Best
 regards<br>Pawel<br></span></div>
</body>
</html>';
        $this->assertEquals($expect, $email->getHtml());
    }

    public function testGetAttachments()
    {
        $file = __DIR__ . '/emails/uid-01.eml';
        $email = new MailParser(\file_get_contents($file));

        $attachment = $email->getAttachments();
        $this->assertNotNull($attachment[0]['content']);
        $this->assertEquals('image/gif', $attachment[0]['type']);
        $this->assertEquals('av-7.gif', $attachment[0]['name']);
    }
    
    public function testDecode()
    {
        $file = __DIR__ . '/emails/uid-02.eml';
        $Parser = new MailParser(file_get_contents($file));

        $attachDir = __DIR__ . '/emails/attachments/';
        $savedAttachment = $Parser->decode($attachDir);

        $attachmentFiles = \glob($attachDir.'*');

        // Clean up attachments dir
        \array_map('unlink', $attachmentFiles);
        \rmdir($attachDir);

        $this->assertEquals(1, \count($attachmentFiles));
        $this->assertEquals('av-7.gif', $savedAttachment[0]['name']);
        $this->assertEquals($attachmentFiles[0], $savedAttachment[0]['path']);
    }

    public function testGetFromError()
    {
        $file = __DIR__ . '/emails/m0007';
        $Parser = new MailParser(file_get_contents($file));
        $this->expectExceptionMessage("Couldn't find the sender of the email");
        $Parser->getFrom();
    }

    public function testGetSubjectError()
    {
        $file = __DIR__ . '/emails/m0007';
        $Parser = new MailParser(file_get_contents($file));
        $this->expectExceptionMessage("Couldn't find the subject of the email");
        $Parser->getSubject();
    }

    public function testGetToError()
    {
        $file = __DIR__ . '/emails/m0007';
        $Parser = new MailParser(file_get_contents($file));
        $this->expectExceptionMessage("Couldn't find the recipients of the email");
        $Parser->getTo();
    }
}
