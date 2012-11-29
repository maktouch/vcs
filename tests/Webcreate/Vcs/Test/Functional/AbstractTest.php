<?php

/*
 * @author Jeroen Fiege <jeroen@webcreate.nl>
 * @copyright Webcreate (http://webcreate.nl)
 */

namespace Webcreate\Vcs\Test\Functional;

use Webcreate\Vcs\Common\Pointer;
use Webcreate\Vcs\Common\FileInfo;
use Webcreate\Vcs\Common\Status;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Webcreate\Vcs\VcsInterface
     */
    protected $client;

    public function setUp()
    {
        $this->client = $this->getClient();

        $this->checkoutDir = sys_get_temp_dir() . '/' . uniqid('wbcrte-1');
        $this->exportDir = sys_get_temp_dir() . '/'. uniqid('wbcrte-2');
    }

    public function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->checkoutDir);
        $filesystem->remove($this->exportDir);
    }

    abstract public function getClient();
    abstract public function existingPathProvider();
    abstract public function existingSubfolderProvider();

    public function testLs()
    {
        $result = $this->client->ls('');

        $this->assertInternalType('array', $result);
        $this->assertContainsOnlyInstancesOf('Webcreate\\Vcs\\Common\\FileInfo', $result);
    }

    /**
     * @expectedException Webcreate\Vcs\Exception\NotFoundException
     */
    public function testLsForNonExistingPathThrowsException()
    {
        $result = $this->client->ls('/non/existing/path');
    }

    /**
     * @dataProvider existingSubfolderProvider
     */
    public function testLsForSubfolder($subfolder)
    {
        $result = $this->client->ls($subfolder);

        $this->assertInternalType('array', $result);
        $this->assertContainsOnlyInstancesOf('Webcreate\\Vcs\\Common\\FileInfo', $result);
    }


    /**
     * @dataProvider existingPathProvider
     */
    public function testLog($path)
    {
        $result = $this->client->log($path);
        $this->assertContainsOnlyInstancesOf('Webcreate\\Vcs\\Common\\Commit', $result);
    }

    /**
     * @dataProvider existingPathProvider
     */
    public function testCat($path)
    {
        $result = $this->client->cat($path);
        $this->assertNotEmpty($result);
    }

    /**
     * @expectedException Webcreate\Vcs\Exception\NotFoundException
     */
    public function testCatForNonExistingPathThrowsException()
    {
        $result = $this->client->cat('/non/existing');
    }

    public function testCheckout()
    {
        $result = $this->client->checkout($this->checkoutDir);

        foreach($this->existingPathProvider() as $data) {
            list($filename) = $data;
            $this->assertFileExists($this->checkoutDir . '/' . $filename);
        }
    }

    public function testStatusUnversionedFile()
    {
        // first we need a checkout
        $result = $this->client->checkout($this->checkoutDir);

        // next add a unversioned file
        $tmpfile = tempnam($this->checkoutDir, 'statustest');

        $result = $this->client->status();

        $expected = array(
                new FileInfo(basename($tmpfile), FileInfo::FILE, null, Status::UNVERSIONED)
        );

        $this->assertContainsOnlyInstancesOf('Webcreate\\Vcs\\Common\\FileInfo', $result);
        $this->assertEquals($expected, $result);
    }

    public function testStatusAddedFile()
    {
        // first we need a checkout
        $result = $this->client->checkout($this->checkoutDir);

        // next add a unversioned file
        $tmpfile = tempnam($this->checkoutDir, 'statustest');

        $this->client->add(basename($tmpfile));

        $result = $this->client->status();

        $expected = array(
                new FileInfo(basename($tmpfile), FileInfo::FILE, null, Status::ADDED)
        );

        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider existingPathProvider
     */
    public function testStatusModifiedFile($filename)
    {
        // first we need a checkout
        $result = $this->client->checkout($this->checkoutDir);

        // next modify a file
        $tmpfile = $this->checkoutDir . '/' . $filename;
        file_put_contents($tmpfile, uniqid(null, true));

        $this->client->add($filename); // stage the change (needed for git)

        $result = $this->client->status();

        $expected = array(
                new FileInfo(basename($tmpfile), FileInfo::FILE, null, Status::MODIFIED)
        );

        $this->assertEquals($expected, $result);
    }

    public function testExportRootPath()
    {
        $this->client->export('', $this->exportDir);

        $provider = $this->existingPathProvider();
        foreach($provider as $entry) {
            list($filename) = $entry;
            $this->assertFileExists($this->exportDir . '/' . $filename);
        }
    }

    /**
     * @dataProvider existingPathProvider
     */
    public function testExportSingleFile($filename)
    {
        // we need to make sure the destination exists
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->exportDir);

        $this->client->export($filename, $this->exportDir);

        $this->assertFileExists($this->exportDir . '/' . $filename);
    }

    /**
     * @dataProvider existingPathProvider
     */
    public function testDiff($filename)
    {
        // first we need a checkout
        $result = $this->client->checkout($this->checkoutDir);

         // next modify a file
        $tmpfile = $this->checkoutDir . '/' . $filename;
        file_put_contents($tmpfile, uniqid(null, true));

        // added to the staged file list
        $this->client->add($filename);

        // now let's commit it
        $this->client->commit("changed file contents");

        // get the log
        $log = $this->client->log($filename);
        $firstLog = end($log);
        $firstRevision = $firstLog->getRevision();

        $diff = $this->client->diff($filename, $filename, $firstRevision);

        $expected = array(
                new FileInfo(
                        $filename,
                        FileInfo::FILE,
                        null,
                        Status::MODIFIED
                ),
        );

        $this->assertEquals($expected, $diff);
    }

    public function testBranches()
    {
        $branches = $this->client->branches();

        $this->assertInternalType('array', $branches);
        $this->assertContains('feature1', $branches);
    }

    public function testTags()
    {
        $tags = $this->client->tags();

        $this->assertInternalType('array', $tags);
    }

    public function testSwitchingToDifferentBranch()
    {
        $result = $this->client->setPointer(new Pointer('feature1'));
    }
}