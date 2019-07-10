<?php

namespace Mail\Tests;

use Mail\MailReader;
use PHPUnit\Framework\TestCase;

class MailReaderTest extends TestCase
{

    const TEST_DB_USER = 'ez_test';
    const TEST_DB_PASSWORD = 'ezTest';
    const TEST_DB_NAME = 'ez_test';
    const TEST_DB_HOST = 'localhost';
    const TEST_DB_CHARSET = 'utf8';
    const TEST_DB_PORT = '3306';

    protected $handle;

    protected function setUp()
    {
        if (!\extension_loaded('pdo_mysql')) {
            $this->markTestSkipped(
              'The pdo_mysql Lib is not available.'
            );
        }

        $this->handle = new \PDO("mysql:host=".self::TEST_DB_HOST.
            ";dbname=".self::TEST_DB_NAME.
            ";charset=".self::TEST_DB_CHARSET.
            ";port=".self::TEST_DB_PORT, 
            self::TEST_DB_USER, 
            self::TEST_DB_PASSWORD, 
            array(
                \PDO::ATTR_EMULATE_PREPARES => false, 
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            )
        );
    }

    protected function tearDown()
    {
        $this->handle->query('DROP TABLE emails;');
        $this->handle->query('DROP TABLE files;');
        $this->handle = null;
    }

    public function testMailPipe()
    {
        $this->handle->query('DROP TABLE IF EXISTS emails;');
        $this->handle->query('DROP TABLE IF EXISTS files;');

        if ('\\' == \DIRECTORY_SEPARATOR)
            $output = \shell_exec('type tests\testfile | php mailPipe.php');
        else
            $output = \shell_exec('cat tests/testfile | php mailPipe.php');

        $this->assertNull($output);

        $mr = new MailReader($this->handle, '..');
        $this->assertEquals(0, $mr->getMessages('no@email.com'));
        $this->assertEquals(0, $mr->getMessageCount('no@email.com'));
        $emails = $mr->getMessages('name@company2.com');
        $this->assertEquals(1, $mr->getMessageCount('name@company2.com'));

        $emailID = $emails[0]->getId();
        $this->assertTrue(\is_int($emailID));

        $this->assertEquals('Name <name@company2.com>', $emails[0]->getUser());
        $this->assertEquals('Name <name@company.com>', $emails[0]->getSender());
        $this->assertEquals('Sat, 30 Apr 2005 19:28:29 -0300', $emails[0]->getDate());
        $this->assertEquals('Testing MIME E-mail composing with cid', $emails[0]->getSubject());

        $this->assertFalse($mr->getMessageAttachments(0));
        $emailAttachments = $mr->getMessageAttachments($emailID);

        $saved = $mr->findDirectory('.');
        $attachDir = $saved  . \DIRECTORY_SEPARATOR;
        
        $attachmentFiles = \glob($attachDir . '*');
        $this->assertEquals(2, \count($attachmentFiles));

        $this->assertFileExists($attachDir . $emailAttachments[0]->getName());
        $this->assertTrue((\strpos($emailAttachments[0]->getName(), '_logo_jpg') !== false));
        $this->assertFileExists($attachDir . $emailAttachments[1]->getName());
        $this->assertTrue((\strpos($emailAttachments[1]->getName(), '_background_jpg') !== false));

        // Clean up attachments dir
        $attachDir = 'mailPiped' . \DIRECTORY_SEPARATOR;
        $attachmentFiles = \glob($attachDir . '*');
        \array_map('unlink', $attachmentFiles);
        \rmdir($attachDir);
        
        $this->expectExceptionMessage("getAllEmailAttachments does not exist");
        $emailAttachments[0]->getAllEmailAttachments();
    }
    
    public function testReadEmail()
    {
        $this->handle->query('DROP TABLE IF EXISTS emails;');
        $this->handle->query('DROP TABLE IF EXISTS files;');
        
        \create_mail_pipe_db($this->handle);

        $attachDir = __DIR__ . '/emails/attachments/';
        if (!\file_exists($attachDir))
            @\mkdir($attachDir, 0770, true);

        $mr = new MailReader($this->handle, $attachDir);
        $mr->saveOn();
        $mr->sendOff();
        $mr->addMimeType('text/csv');

        $file = __DIR__ . '/emails/uid-01.eml';
        $fileSaved = $mr->readEmail($file);
        $this->assertNotNull($fileSaved);

        $emails = $mr->getMessages('test@domain.com');
        $emailID = $emails[0]->getId();
        $emailAttachments = $mr->getMessageAttachments($emailID);

        $this->assertEquals('11.33 KB', $fileSaved[$emailAttachments[0]->getName()]['size']);
        $this->assertEquals('image/gif', $fileSaved[$emailAttachments[0]->getName()]['mime']);

        $attachmentFiles = \glob($attachDir.'*');

        // Clean up attachments dir
        \array_map('unlink', $attachmentFiles);
        \rmdir($attachDir);

        $this->expectExceptionMessage("getAllEmailers does not exist");
        $emails[0]->getAllEmailers();
    }
}
