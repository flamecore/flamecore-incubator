<?php
/*
 * FlameCore Filesystem
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Filesystem\Tests;

use FlameCore\Filesystem\Filesystem;
use FlameCore\Filesystem\Exception\IOException;

/**
 * Test class for Filesystem.
 */
class FilesystemTest extends FilesystemTestCase
{
    public function testCopyCreatesNewFile()
    {
        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        Filesystem::copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertStringEqualsFile($targetFilePath, 'SOURCE FILE');
    }

    public function testCopyFails()
    {
        $this->expectException(IOException::class);
        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        Filesystem::copy($sourceFilePath, $targetFilePath);
    }

    public function testCopyUnreadableFileFails()
    {
        $this->expectException(IOException::class);

        // skip test on Windows; PHP can't easily set file as unreadable on Windows
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        if (!getenv('USER') || getenv('USER') === 'root') {
            $this->markTestSkipped('This test will fail if run as superuser');
        }

        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        // make sure target cannot be read
        Filesystem::chmod($sourceFilePath, 0222);

        Filesystem::copy($sourceFilePath, $targetFilePath);
    }

    public function testCopyOverridesExistingFileIfModified()
    {
        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');
        touch($targetFilePath, time() - 1000);

        Filesystem::copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertStringEqualsFile($targetFilePath, 'SOURCE FILE');
    }

    public function testCopyDoesNotOverrideExistingFileByDefault()
    {
        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        Filesystem::copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertStringEqualsFile($targetFilePath, 'TARGET FILE');
    }

    public function testCopyOverridesExistingFileIfForced()
    {
        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        Filesystem::copy($sourceFilePath, $targetFilePath, true);

        $this->assertFileExists($targetFilePath);
        $this->assertStringEqualsFile($targetFilePath, 'SOURCE FILE');
    }

    public function testCopyWithOverrideWithReadOnlyTargetFails()
    {
        $this->expectException(IOException::class);
        // skip test on Windows; PHP can't easily set file as unwritable on Windows
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        if (!getenv('USER') || getenv('USER') === 'root') {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        // make sure target is read-only
        Filesystem::chmod($targetFilePath, 0444);

        Filesystem::copy($sourceFilePath, $targetFilePath, true);
    }

    public function testCopyCreatesTargetDirectoryIfItDoesNotExist()
    {
        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFileDirectory = $this->workspace . \DIRECTORY_SEPARATOR . 'directory';
        $targetFilePath = $targetFileDirectory . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        Filesystem::copy($sourceFilePath, $targetFilePath);

        $this->assertDirectoryExists($targetFileDirectory);
        $this->assertFileExists($targetFilePath);
        $this->assertStringEqualsFile($targetFilePath, 'SOURCE FILE');
    }

    /**
     * @group network
     */
    public function testCopyForOriginUrlsAndExistingLocalFileDefaultsToCopy()
    {
        if (!\in_array('https', stream_get_wrappers())) {
            $this->markTestSkipped('"https" stream wrapper is not enabled.');
        }
        $sourceFilePath = 'https://symfony.com/images/common/logo/logo_symfony_header.png';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($targetFilePath, 'TARGET FILE');

        Filesystem::copy($sourceFilePath, $targetFilePath, false);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals(file_get_contents($sourceFilePath), file_get_contents($targetFilePath));
    }

    public function testCreateDirCreatesDirectoriesRecursively()
    {
        $directory = $this->workspace
            . \DIRECTORY_SEPARATOR . 'directory'
            . \DIRECTORY_SEPARATOR . 'sub_directory';

        Filesystem::createDir($directory);

        $this->assertDirectoryExists($directory);
    }

    public function testCreateDirCreatesDirectoriesFromArray()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;
        $directories = [
            $basePath . '1',
            $basePath . '2',
            $basePath . '3',
        ];

        Filesystem::createDir($directories);

        $this->assertDirectoryExists($basePath . '1');
        $this->assertDirectoryExists($basePath . '2');
        $this->assertDirectoryExists($basePath . '3');
    }

    public function testCreateDirCreatesDirectoriesFromTraversableObject()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;
        $directories = new \ArrayObject([
            $basePath . '1',
            $basePath . '2',
            $basePath . '3',
        ]);

        Filesystem::createDir($directories);

        $this->assertDirectoryExists($basePath . '1');
        $this->assertDirectoryExists($basePath . '2');
        $this->assertDirectoryExists($basePath . '3');
    }

    public function testCreateDirCreatesDirectoriesFails()
    {
        $this->expectException(IOException::class);
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;
        $dir = $basePath . '2';

        file_put_contents($dir, '');

        Filesystem::createDir($dir);
    }

    public function testTouchCreatesEmptyFile()
    {
        $file = $this->workspace . \DIRECTORY_SEPARATOR . '1';

        Filesystem::touch($file);

        $this->assertFileExists($file);
    }

    public function testTouchFails()
    {
        $this->expectException(IOException::class);
        $file = $this->workspace . \DIRECTORY_SEPARATOR . '1' . \DIRECTORY_SEPARATOR . '2';

        Filesystem::touch($file);
    }

    public function testTouchCreatesEmptyFilesFromArray()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;
        $files = [
            $basePath . '1',
            $basePath . '2',
            $basePath . '3',
        ];

        Filesystem::touch($files);

        $this->assertFileExists($basePath . '1');
        $this->assertFileExists($basePath . '2');
        $this->assertFileExists($basePath . '3');
    }

    public function testTouchCreatesEmptyFilesFromTraversableObject()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;
        $files = new \ArrayObject([
            $basePath . '1',
            $basePath . '2',
            $basePath . '3',
        ]);

        Filesystem::touch($files);

        $this->assertFileExists($basePath . '1');
        $this->assertFileExists($basePath . '2');
        $this->assertFileExists($basePath . '3');
    }

    public function testRemoveCleansFilesAndDirectoriesIteratively()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR . 'directory' . \DIRECTORY_SEPARATOR;

        mkdir($basePath);
        mkdir($basePath . 'dir');
        touch($basePath . 'file');

        Filesystem::remove($basePath);

        $this->assertFileDoesNotExist($basePath);
    }

    public function testRemoveCleansArrayOfFilesAndDirectories()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;

        mkdir($basePath . 'dir');
        touch($basePath . 'file');

        $files = [
            $basePath . 'dir',
            $basePath . 'file',
        ];

        Filesystem::remove($files);

        $this->assertFileDoesNotExist($basePath . 'dir');
        $this->assertFileDoesNotExist($basePath . 'file');
    }

    public function testRemoveCleansTraversableObjectOfFilesAndDirectories()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;

        mkdir($basePath . 'dir');
        touch($basePath . 'file');

        $files = new \ArrayObject([
            $basePath . 'dir',
            $basePath . 'file',
        ]);

        Filesystem::remove($files);

        $this->assertFileDoesNotExist($basePath . 'dir');
        $this->assertFileDoesNotExist($basePath . 'file');
    }

    public function testRemoveIgnoresNonExistingFiles()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;

        mkdir($basePath . 'dir');

        $files = [
            $basePath . 'dir',
            $basePath . 'file',
        ];

        Filesystem::remove($files);

        $this->assertFileDoesNotExist($basePath . 'dir');
    }

    public function testRemoveThrowsExceptionOnPermissionDenied()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $basePath = $this->workspace . \DIRECTORY_SEPARATOR . 'dir_permissions';
        mkdir($basePath);
        $file = $basePath . \DIRECTORY_SEPARATOR . 'file';
        touch($file);
        chmod($basePath, 0400);

        try {
            Filesystem::remove($file);
            $this->fail('Filesystem::remove() should throw an exception');
        } catch (IOException $e) {
            $this->assertStringContainsString('Failed to remove file "' . $file . '"', $e->getMessage());
            $this->assertStringContainsString('Permission denied', $e->getMessage());
        } finally {
            // Make sure we can clean up this file
            chmod($basePath, 0777);
        }
    }

    public function testRemoveCleansInvalidLinks()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $basePath = $this->workspace . \DIRECTORY_SEPARATOR . 'directory' . \DIRECTORY_SEPARATOR;

        mkdir($basePath);
        mkdir($basePath . 'dir');

        // create symlink to nonexistent file
        @symlink($basePath . 'file', $basePath . 'file-link');

        // create symlink to dir using trailing forward slash
        Filesystem::symlink($basePath . 'dir/', $basePath . 'dir-link');
        $this->assertDirectoryExists($basePath . 'dir-link');

        // create symlink to nonexistent dir
        rmdir($basePath . 'dir');
        $this->assertFalse('\\' === \DIRECTORY_SEPARATOR ? @readlink($basePath . 'dir-link') : is_dir($basePath . 'dir-link'));

        Filesystem::remove($basePath);

        $this->assertFileDoesNotExist($basePath);
    }

    public function testFilesExists()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR . 'directory' . \DIRECTORY_SEPARATOR;

        mkdir($basePath);
        touch($basePath . 'file1');
        mkdir($basePath . 'folder');

        $this->assertTrue(Filesystem::exists($basePath . 'file1'));
        $this->assertTrue(Filesystem::exists($basePath . 'folder'));
    }

    public function testFilesExistsFails()
    {
        $this->expectException(IOException::class);
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Long file names are an issue on Windows');
        }
        $basePath = $this->workspace . '\\directory\\';
        $maxPathLength = \PHP_MAXPATHLEN - 2;

        $oldPath = getcwd();
        mkdir($basePath);
        chdir($basePath);
        $file = str_repeat('T', $maxPathLength - \strlen($basePath) + 1);
        $path = $basePath . $file;
        exec('TYPE NUL >>' . $file); // equivalent of touch, we can not use the php touch() here because it suffers from the same limitation
        $this->longPathNamesWindows[] = $path; // save this so we can clean up later
        chdir($oldPath);
        Filesystem::exists($path);
    }

    public function testFilesExistsTraversableObjectOfFilesAndDirectories()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;

        mkdir($basePath . 'dir');
        touch($basePath . 'file');

        $files = new \ArrayObject([
            $basePath . 'dir',
            $basePath . 'file',
        ]);

        $this->assertTrue(Filesystem::exists($files));
    }

    public function testFilesNotExistsTraversableObjectOfFilesAndDirectories()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR;

        mkdir($basePath . 'dir');
        touch($basePath . 'file');
        touch($basePath . 'file2');

        $files = new \ArrayObject([
            $basePath . 'dir',
            $basePath . 'file',
            $basePath . 'file2',
        ]);

        unlink($basePath . 'file');

        $this->assertFalse(Filesystem::exists($files));
    }

    public function testInvalidFileNotExists()
    {
        $basePath = $this->workspace . \DIRECTORY_SEPARATOR . 'directory' . \DIRECTORY_SEPARATOR;

        $this->assertFalse(Filesystem::exists($basePath . time()));
    }

    public function testChmodChangesFileMode()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);
        $file = $dir . \DIRECTORY_SEPARATOR . 'file';
        touch($file);

        Filesystem::chmod($file, 0400);
        Filesystem::chmod($dir, 0753);

        $this->assertFilePermissions(753, $dir);
        $this->assertFilePermissions(400, $file);
    }

    public function testChmodRecursive()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);
        $file = $dir . \DIRECTORY_SEPARATOR . 'file';
        touch($file);

        Filesystem::chmod($file, 0400, 0000, true);
        Filesystem::chmod($dir, 0753, 0000, true);

        $this->assertFilePermissions(753, $dir);
        $this->assertFilePermissions(753, $file);
    }

    public function testChmodAppliesUmask()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        touch($file);

        Filesystem::chmod($file, 0770, 0022);
        $this->assertFilePermissions(750, $file);
    }

    public function testChmodChangesModeOfArrayOfFiles()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $directory = $this->workspace . \DIRECTORY_SEPARATOR . 'directory';
        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $files = [$directory, $file];

        mkdir($directory);
        touch($file);

        Filesystem::chmod($files, 0753);

        $this->assertFilePermissions(753, $file);
        $this->assertFilePermissions(753, $directory);
    }

    public function testChmodChangesModeOfTraversableFileObject()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $directory = $this->workspace . \DIRECTORY_SEPARATOR . 'directory';
        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $files = new \ArrayObject([$directory, $file]);

        mkdir($directory);
        touch($file);

        Filesystem::chmod($files, 0753);

        $this->assertFilePermissions(753, $file);
        $this->assertFilePermissions(753, $directory);
    }

    public function testChmodChangesZeroModeOnSubdirectoriesOnRecursive()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $directory = $this->workspace . \DIRECTORY_SEPARATOR . 'directory';
        $subdirectory = $directory . \DIRECTORY_SEPARATOR . 'subdirectory';

        mkdir($directory);
        mkdir($subdirectory);
        chmod($subdirectory, 0000);

        Filesystem::chmod($directory, 0753, 0000, true);

        $this->assertFilePermissions(753, $subdirectory);
    }

    public function testChownByName()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);

        $owner = $this->getFileOwner($dir);
        Filesystem::chown($dir, $owner);

        $this->assertSame($owner, $this->getFileOwner($dir));
    }

    public function testChownById()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);

        $ownerId = $this->getFileOwnerId($dir);
        Filesystem::chown($dir, $ownerId);

        $this->assertSame($ownerId, $this->getFileOwnerId($dir));
    }

    public function testChownRecursiveByName()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);
        $file = $dir . \DIRECTORY_SEPARATOR . 'file';
        touch($file);

        $owner = $this->getFileOwner($dir);
        Filesystem::chown($dir, $owner, true);

        $this->assertSame($owner, $this->getFileOwner($file));
    }

    public function testChownRecursiveById()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);
        $file = $dir . \DIRECTORY_SEPARATOR . 'file';
        touch($file);

        $ownerId = $this->getFileOwnerId($dir);
        Filesystem::chown($dir, $ownerId, true);

        $this->assertSame($ownerId, $this->getFileOwnerId($file));
    }

    public function testChownSymlink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::symlink($file, $link);

        $owner = $this->getFileOwner($link);
        Filesystem::chown($link, $owner);

        $this->assertSame($owner, $this->getFileOwner($link));
    }

    public function testChownLink()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::hardlink($file, $link);

        $owner = $this->getFileOwner($link);
        Filesystem::chown($link, $owner);

        $this->assertSame($owner, $this->getFileOwner($link));
    }

    public function testChownSymlinkFails()
    {
        $this->expectException(IOException::class);
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::symlink($file, $link);

        Filesystem::chown($link, 'user' . time() . mt_rand(1000, 9999));
    }

    public function testChownLinkFails()
    {
        $this->expectException(IOException::class);
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::hardlink($file, $link);

        Filesystem::chown($link, 'user' . time() . mt_rand(1000, 9999));
    }

    public function testChownFail()
    {
        $this->expectException(IOException::class);
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);

        Filesystem::chown($dir, 'user' . time() . mt_rand(1000, 9999));
    }

    public function testChgrpByName()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);

        $group = $this->getFileGroup($dir);
        Filesystem::chgrp($dir, $group);

        $this->assertSame($group, $this->getFileGroup($dir));
    }

    public function testChgrpById()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);

        $groupId = $this->getFileGroupId($dir);
        Filesystem::chgrp($dir, $groupId);

        $this->assertSame($groupId, $this->getFileGroupId($dir));
    }

    public function testChgrpRecursive()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);
        $file = $dir . \DIRECTORY_SEPARATOR . 'file';
        touch($file);

        $group = $this->getFileGroup($dir);
        Filesystem::chgrp($dir, $group, true);

        $this->assertSame($group, $this->getFileGroup($file));
    }

    public function testChgrpSymlinkByName()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::symlink($file, $link);

        $group = $this->getFileGroup($link);
        Filesystem::chgrp($link, $group);

        $this->assertSame($group, $this->getFileGroup($link));
    }

    public function testChgrpSymlinkById()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::symlink($file, $link);

        $groupId = $this->getFileGroupId($link);
        Filesystem::chgrp($link, $groupId);

        $this->assertSame($groupId, $this->getFileGroupId($link));
    }

    public function testChgrpLink()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::hardlink($file, $link);

        $group = $this->getFileGroup($link);
        Filesystem::chgrp($link, $group);

        $this->assertSame($group, $this->getFileGroup($link));
    }

    public function testChgrpSymlinkFails()
    {
        $this->expectException(IOException::class);
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::symlink($file, $link);

        Filesystem::chgrp($link, 'user' . time() . mt_rand(1000, 9999));
    }

    public function testChgrpLinkFails()
    {
        $this->expectException(IOException::class);
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::hardlink($file, $link);

        Filesystem::chgrp($link, 'user' . time() . mt_rand(1000, 9999));
    }

    public function testChgrpFail()
    {
        $this->expectException(IOException::class);
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace . \DIRECTORY_SEPARATOR . 'dir';
        mkdir($dir);

        Filesystem::chgrp($dir, 'user' . time() . mt_rand(1000, 9999));
    }

    public function testRename()
    {
        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $newPath = $this->workspace . \DIRECTORY_SEPARATOR . 'new_file';
        touch($file);

        Filesystem::rename($file, $newPath);

        $this->assertFileDoesNotExist($file);
        $this->assertFileExists($newPath);
    }

    public function testRenameThrowsExceptionIfTargetAlreadyExists()
    {
        $this->expectException(IOException::class);
        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $newPath = $this->workspace . \DIRECTORY_SEPARATOR . 'new_file';

        touch($file);
        touch($newPath);

        Filesystem::rename($file, $newPath);
    }

    public function testRenameOverwritesTheTargetIfItAlreadyExists()
    {
        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $newPath = $this->workspace . \DIRECTORY_SEPARATOR . 'new_file';

        touch($file);
        touch($newPath);

        Filesystem::rename($file, $newPath, true);

        $this->assertFileDoesNotExist($file);
        $this->assertFileExists($newPath);
    }

    public function testRenameThrowsExceptionOnError()
    {
        $this->expectException(IOException::class);
        $file = $this->workspace . \DIRECTORY_SEPARATOR . uniqid('fs_test_', true);
        $newPath = $this->workspace . \DIRECTORY_SEPARATOR . 'new_file';

        Filesystem::rename($file, $newPath);
    }

    public function testSymlink()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support creating "broken" symlinks');
        }

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        // $file does not exist right now: creating "broken" links is a wanted feature
        Filesystem::symlink($file, $link);

        $this->assertTrue(is_link($link));

        // Create the linked file AFTER creating the link
        touch($file);

        $this->assertEquals($file, readlink($link));
    }

    /**
     * @depends testSymlink
     */
    public function testRemoveSymlink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        Filesystem::remove($link);

        $this->assertFalse(is_link($link));
        $this->assertFalse(is_file($link));
        $this->assertDirectoryDoesNotExist($link);
    }

    public function testSymlinkIsOverwrittenIfPointsToDifferentTarget()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);
        symlink($this->workspace, $link);

        Filesystem::symlink($file, $link);

        $this->assertTrue(is_link($link));
        $this->assertEquals($file, readlink($link));
    }

    public function testSymlinkIsNotOverwrittenIfAlreadyCreated()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);
        symlink($file, $link);

        Filesystem::symlink($file, $link);

        $this->assertTrue(is_link($link));
        $this->assertEquals($file, readlink($link));
    }

    public function testSymlinkCreatesTargetDirectoryIfItDoesNotExist()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link1 = $this->workspace . \DIRECTORY_SEPARATOR . 'dir' . \DIRECTORY_SEPARATOR . 'link';
        $link2 = $this->workspace . \DIRECTORY_SEPARATOR . 'dir' . \DIRECTORY_SEPARATOR . 'subdir' . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        Filesystem::symlink($file, $link1);
        Filesystem::symlink($file, $link2);

        $this->assertTrue(is_link($link1));
        $this->assertEquals($file, readlink($link1));
        $this->assertTrue(is_link($link2));
        $this->assertEquals($file, readlink($link2));
    }

    public function testLink()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);
        Filesystem::hardlink($file, $link);

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    /**
     * @depends testLink
     */
    public function testRemoveLink()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        Filesystem::remove($link);

        $this->assertTrue(!is_file($link));
    }

    public function testLinkIsOverwrittenIfPointsToDifferentTarget()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $file2 = $this->workspace . \DIRECTORY_SEPARATOR . 'file2';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);
        touch($file2);
        link($file2, $link);

        Filesystem::hardlink($file, $link);

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    public function testLinkIsNotOverwrittenIfAlreadyCreated()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);
        link($file, $link);

        Filesystem::hardlink($file, $link);

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    public function testLinkWithSeveralTargets()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link1 = $this->workspace . \DIRECTORY_SEPARATOR . 'link';
        $link2 = $this->workspace . \DIRECTORY_SEPARATOR . 'link2';

        touch($file);

        Filesystem::hardlink($file, [$link1, $link2]);

        $this->assertTrue(is_file($link1));
        $this->assertEquals(fileinode($file), fileinode($link1));
        $this->assertTrue(is_file($link2));
        $this->assertEquals(fileinode($file), fileinode($link2));
    }

    public function testLinkWithSameTarget()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace . \DIRECTORY_SEPARATOR . 'file';
        $link = $this->workspace . \DIRECTORY_SEPARATOR . 'link';

        touch($file);

        // practically same as testLinkIsNotOverwrittenIfAlreadyCreated
        Filesystem::hardlink($file, [$link, $link]);

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    public function testReadRelativeLink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Relative symbolic links are not supported on Windows');
        }

        $file = $this->workspace . '/file';
        $link1 = $this->workspace . '/dir/link';
        $link2 = $this->workspace . '/dir/link2';
        touch($file);

        Filesystem::symlink('../file', $link1);
        Filesystem::symlink('link', $link2);

        $this->assertEquals($this->normalize('../file'), Filesystem::readlink($link1));
        $this->assertEquals('link', Filesystem::readlink($link2));
        $this->assertEquals($file, Filesystem::readlink($link1, true));
        $this->assertEquals($file, Filesystem::readlink($link2, true));
        $this->assertEquals($file, Filesystem::readlink($file, true));
    }

    public function testReadAbsoluteLink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->normalize($this->workspace . '/file');
        $link1 = $this->normalize($this->workspace . '/dir/link');
        $link2 = $this->normalize($this->workspace . '/dir/link2');
        touch($file);

        Filesystem::symlink($file, $link1);
        Filesystem::symlink($link1, $link2);

        $this->assertEquals($file, Filesystem::readlink($link1));
        $this->assertEquals($link1, Filesystem::readlink($link2));
        $this->assertEquals($file, Filesystem::readlink($link1, true));
        $this->assertEquals($file, Filesystem::readlink($link2, true));
        $this->assertEquals($file, Filesystem::readlink($file, true));
    }

    public function testReadBrokenLink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support creating "broken" symlinks');
        }

        $file = $this->workspace . '/file';
        $link = $this->workspace . '/link';

        Filesystem::symlink($file, $link);

        $this->assertEquals($file, Filesystem::readlink($link));
        $this->assertNull(Filesystem::readlink($link, true));

        touch($file);
        $this->assertEquals($file, Filesystem::readlink($link, true));
    }

    public function testReadLinkDefaultPathDoesNotExist()
    {
        $this->assertNull(Filesystem::readlink($this->normalize($this->workspace . '/invalid')));
    }

    public function testReadLinkDefaultPathNotLink()
    {
        $file = $this->normalize($this->workspace . '/file');
        touch($file);

        $this->assertNull(Filesystem::readlink($file));
    }

    public function testReadLinkCanonicalizePath()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->normalize($this->workspace . '/file');
        mkdir($this->normalize($this->workspace . '/dir'));
        touch($file);

        $this->assertEquals($file, Filesystem::readlink($this->normalize($this->workspace . '/dir/../file'), true));
    }

    public function testReadLinkCanonicalizedPathDoesNotExist()
    {
        $this->assertNull(Filesystem::readlink($this->normalize($this->workspace . 'invalid'), true));
    }

    public function testMirrorCopiesFilesAndDirectoriesRecursively()
    {
        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;
        $directory = $sourcePath . 'directory' . \DIRECTORY_SEPARATOR;
        $file1 = $directory . 'file1';
        $file2 = $sourcePath . 'file2';

        mkdir($sourcePath);
        mkdir($directory);
        file_put_contents($file1, 'FILE1');
        file_put_contents($file2, 'FILE2');

        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'target' . \DIRECTORY_SEPARATOR;

        Filesystem::mirror($sourcePath, $targetPath);

        $this->assertDirectoryExists($targetPath);
        $this->assertDirectoryExists($targetPath . 'directory');
        $this->assertFileEquals($file1, $targetPath . 'directory' . \DIRECTORY_SEPARATOR . 'file1');
        $this->assertFileEquals($file2, $targetPath . 'file2');

        Filesystem::remove($file1);

        Filesystem::mirror($sourcePath, $targetPath, null, ['delete' => false]);
        $this->assertTrue(Filesystem::exists($targetPath . 'directory' . \DIRECTORY_SEPARATOR . 'file1'));

        Filesystem::mirror($sourcePath, $targetPath, null, ['delete' => true]);
        $this->assertFalse(Filesystem::exists($targetPath . 'directory' . \DIRECTORY_SEPARATOR . 'file1'));

        file_put_contents($file1, 'FILE1');

        Filesystem::mirror($sourcePath, $targetPath, null, ['delete' => true]);
        $this->assertTrue(Filesystem::exists($targetPath . 'directory' . \DIRECTORY_SEPARATOR . 'file1'));

        Filesystem::remove($directory);
        Filesystem::mirror($sourcePath, $targetPath, null, ['delete' => true]);
        $this->assertFalse(Filesystem::exists($targetPath . 'directory'));
        $this->assertFalse(Filesystem::exists($targetPath . 'directory' . \DIRECTORY_SEPARATOR . 'file1'));
    }

    public function testMirrorCreatesEmptyDirectory()
    {
        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;

        mkdir($sourcePath);

        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'target' . \DIRECTORY_SEPARATOR;

        Filesystem::mirror($sourcePath, $targetPath);

        $this->assertDirectoryExists($targetPath);

        Filesystem::remove($sourcePath);
    }

    public function testMirrorCopiesLinks()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;

        mkdir($sourcePath);
        file_put_contents($sourcePath . 'file1', 'FILE1');
        symlink($sourcePath . 'file1', $sourcePath . 'link1');

        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'target' . \DIRECTORY_SEPARATOR;

        Filesystem::mirror($sourcePath, $targetPath);

        $this->assertDirectoryExists($targetPath);
        $this->assertFileEquals($sourcePath . 'file1', $targetPath . 'link1');
        $this->assertTrue(is_link($targetPath . \DIRECTORY_SEPARATOR . 'link1'));
    }

    public function testMirrorCopiesLinkedDirectoryContents()
    {
        $this->markAsSkippedIfSymlinkIsMissing(true);

        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;

        mkdir($sourcePath . 'nested/', 0777, true);
        file_put_contents($sourcePath . '/nested/file1.txt', 'FILE1');
        // Note: We symlink directory, not file
        symlink($sourcePath . 'nested', $sourcePath . 'link1');

        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'target' . \DIRECTORY_SEPARATOR;

        Filesystem::mirror($sourcePath, $targetPath);

        $this->assertDirectoryExists($targetPath);
        $this->assertFileEquals($sourcePath . '/nested/file1.txt', $targetPath . 'link1/file1.txt');
        $this->assertTrue(is_link($targetPath . \DIRECTORY_SEPARATOR . 'link1'));
    }

    public function testMirrorCopiesRelativeLinkedContents()
    {
        $this->markAsSkippedIfSymlinkIsMissing(true);

        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;
        $oldPath = getcwd();

        mkdir($sourcePath . 'nested/', 0777, true);
        file_put_contents($sourcePath . '/nested/file1.txt', 'FILE1');
        // Note: Create relative symlink
        chdir($sourcePath);
        symlink('nested', 'link1');

        chdir($oldPath);

        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'target' . \DIRECTORY_SEPARATOR;

        Filesystem::mirror($sourcePath, $targetPath);

        $this->assertDirectoryExists($targetPath);
        $this->assertFileEquals($sourcePath . '/nested/file1.txt', $targetPath . 'link1/file1.txt');
        $this->assertTrue(is_link($targetPath . \DIRECTORY_SEPARATOR . 'link1'));
        $this->assertEquals('\\' === \DIRECTORY_SEPARATOR ? realpath($sourcePath . '\nested') : 'nested', readlink($targetPath . \DIRECTORY_SEPARATOR . 'link1'));
    }

    public function testMirrorContentsWithSameNameAsSourceOrTargetWithoutDeleteOption()
    {
        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;

        mkdir($sourcePath);
        touch($sourcePath . 'source');
        touch($sourcePath . 'target');

        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'target' . \DIRECTORY_SEPARATOR;

        $oldPath = getcwd();
        chdir($this->workspace);

        Filesystem::mirror('source', $targetPath);

        chdir($oldPath);

        $this->assertDirectoryExists($targetPath);
        $this->assertFileExists($targetPath . 'source');
        $this->assertFileExists($targetPath . 'target');
    }

    public function testMirrorContentsWithSameNameAsSourceOrTargetWithDeleteOption()
    {
        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;

        mkdir($sourcePath);
        touch($sourcePath . 'source');

        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'target' . \DIRECTORY_SEPARATOR;

        mkdir($targetPath);
        touch($targetPath . 'source');
        touch($targetPath . 'target');

        $oldPath = getcwd();
        chdir($this->workspace);

        Filesystem::mirror('source', 'target', null, ['delete' => true]);

        chdir($oldPath);

        $this->assertDirectoryExists($targetPath);
        $this->assertFileExists($targetPath . 'source');
        $this->assertFileDoesNotExist($targetPath . 'target');
    }

    public function testMirrorAvoidCopyingTargetDirectoryIfInSourceDirectory()
    {
        $sourcePath = $this->workspace . \DIRECTORY_SEPARATOR . 'source' . \DIRECTORY_SEPARATOR;
        $directory = $sourcePath . 'directory' . \DIRECTORY_SEPARATOR;
        $file1 = $directory . 'file1';
        $file2 = $sourcePath . 'file2';

        mkdir($sourcePath);
        mkdir($directory);
        file_put_contents($file1, 'FILE1');
        file_put_contents($file2, 'FILE2');

        $targetPath = $sourcePath . 'target' . \DIRECTORY_SEPARATOR;

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            Filesystem::symlink($targetPath, $sourcePath . 'target_simlink');
        }

        Filesystem::mirror($sourcePath, $targetPath, null, ['delete' => true]);

        $this->assertTrue(Filesystem::exists($targetPath));
        $this->assertTrue(Filesystem::exists($targetPath . 'directory'));

        $this->assertFileEquals($file1, $targetPath . 'directory' . \DIRECTORY_SEPARATOR . 'file1');
        $this->assertFileEquals($file2, $targetPath . 'file2');

        $this->assertFalse(Filesystem::exists($targetPath . 'target_simlink'));
        $this->assertFalse(Filesystem::exists($targetPath . 'target'));
    }

    public function testMirrorFromSubdirectoryInToParentDirectory()
    {
        $targetPath = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR;
        $sourcePath = $targetPath . 'bar' . \DIRECTORY_SEPARATOR;
        $file1 = $sourcePath . 'file1';
        $file2 = $sourcePath . 'file2';

        Filesystem::createDir($sourcePath);
        file_put_contents($file1, 'FILE1');
        file_put_contents($file2, 'FILE2');

        Filesystem::mirror($sourcePath, $targetPath);

        $this->assertFileEquals($file1, $targetPath . 'file1');
    }

    public function testTempnam()
    {
        $dirname = $this->workspace;

        $filename = Filesystem::tempnam($dirname, 'foo');

        $this->assertFileExists($filename);
    }

    public function testTempnamWithFileScheme()
    {
        $scheme = 'file://';
        $dirname = $scheme . $this->workspace;

        $filename = Filesystem::tempnam($dirname, 'foo');

        $this->assertStringStartsWith($scheme, $filename);
        $this->assertFileExists($filename);
    }

    public function testTempnamWithMockScheme()
    {
        stream_wrapper_register('mock', 'FlameCore\Filesystem\Tests\Fixtures\MockStream\MockStream');

        $scheme = 'mock://';
        $dirname = $scheme . $this->workspace;

        $filename = Filesystem::tempnam($dirname, 'foo');

        $this->assertStringStartsWith($scheme, $filename);
        $this->assertFileExists($filename);
    }

    public function testTempnamWithZlibSchemeFails()
    {
        $this->expectException(IOException::class);
        $scheme = 'compress.zlib://';
        $dirname = $scheme . $this->workspace;

        // The compress.zlib:// stream does not support mode x: creates the file, errors "failed to open stream: operation failed" and returns false
        Filesystem::tempnam($dirname, 'bar');
    }

    public function testTempnamWithPHPTempSchemeFails()
    {
        $scheme = 'php://temp';
        $dirname = $scheme;

        $filename = Filesystem::tempnam($dirname, 'bar');

        $this->assertStringStartsWith($scheme, $filename);

        // The php://temp stream deletes the file after close
        $this->assertFileDoesNotExist($filename);
    }

    public function testTempnamWithPharSchemeFails()
    {
        $this->expectException(IOException::class);
        // Skip test if Phar disabled phar.readonly must be 0 in php.ini
        if (!\Phar::canWrite()) {
            $this->markTestSkipped('This test cannot run when phar.readonly is 1.');
        }

        $scheme = 'phar://';
        $dirname = $scheme . $this->workspace;
        $pharname = 'foo.phar';

        new \Phar($this->workspace . '/' . $pharname, 0, $pharname);
        // The phar:// stream does not support mode x: fails to create file, errors "failed to open stream: phar error: "$filename" is not a file in phar "$pharname"" and returns false
        Filesystem::tempnam($dirname, $pharname . '/bar');
    }

    public function testTempnamWithHTTPSchemeFails()
    {
        $this->expectException(IOException::class);
        $scheme = 'http://';
        $dirname = $scheme . $this->workspace;

        // The http:// scheme is read-only
        Filesystem::tempnam($dirname, 'bar');
    }

    public function testTempnamOnUnwritableFallsBackToSysTmp()
    {
        $scheme = 'file://';
        $dirname = $scheme . $this->workspace . \DIRECTORY_SEPARATOR . 'does_not_exist';

        $filename = Filesystem::tempnam($dirname, 'bar');
        $realTempDir = realpath(sys_get_temp_dir());
        $this->assertStringStartsWith(rtrim($scheme . $realTempDir, \DIRECTORY_SEPARATOR), $filename);
        $this->assertFileExists($filename);

        // Tear down
        @unlink($filename);
    }

    public function testTempnamWithSuffix()
    {
        $dirname = $this->workspace;
        $filename = Filesystem::tempnam($dirname, 'foo', '.bar');
        $this->assertStringEndsWith('.bar', $filename);
        $this->assertFileExists($filename);
    }

    public function testTempnamWithSuffix0()
    {
        $dirname = $this->workspace;
        $filename = Filesystem::tempnam($dirname, 'foo', '0');
        $this->assertStringEndsWith('0', $filename);
        $this->assertFileExists($filename);
    }

    public function testWriteFile()
    {
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'baz.txt';

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $oldMask = umask(0002);
        }

        Filesystem::writeFile($filename, 'bar');
        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'bar');

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->assertFilePermissions(664, $filename);
            umask($oldMask);
        }
    }

    public function testWriteFileWithResource()
    {
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'baz.txt';

        $resource = fopen('php://memory', 'rw');
        fwrite($resource, 'bar');
        fseek($resource, 0);

        Filesystem::writeFile($filename, $resource);

        fclose($resource);
        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'bar');
    }

    public function testWriteFileOverwritesAnExistingFile()
    {
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo.txt';
        file_put_contents($filename, 'FOO BAR');

        Filesystem::writeFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'bar');
    }

    public function testWriteFileWithFileScheme()
    {
        $scheme = 'file://';
        $filename = $scheme . $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'baz.txt';

        Filesystem::writeFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'bar');
    }

    public function testWriteFileWithZlibScheme()
    {
        $scheme = 'compress.zlib://';
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'baz.txt';

        Filesystem::writeFile($filename, 'bar');

        // Zlib stat uses file:// wrapper so remove scheme
        $this->assertFileExists(str_replace($scheme, '', $filename));
        $this->assertStringEqualsFile($filename, 'bar');
    }

    public function testAppendToFile()
    {
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt';

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $oldMask = umask(0002);
        }

        Filesystem::writeFile($filename, 'foo');

        Filesystem::appendToFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'foobar');

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->assertFilePermissions(664, $filename);
            umask($oldMask);
        }
    }

    public function testAppendToFileWithResource()
    {
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt';

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $oldMask = umask(0002);
        }

        Filesystem::writeFile($filename, 'foo');

        $resource = fopen('php://memory', 'rw');
        fwrite($resource, 'bar');
        fseek($resource, 0);

        Filesystem::appendToFile($filename, $resource);

        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'foobar');

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->assertFilePermissions(664, $filename);
            umask($oldMask);
        }
    }

    public function testAppendToFileWithScheme()
    {
        $scheme = 'file://';
        $filename = $scheme . $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'baz.txt';
        Filesystem::writeFile($filename, 'foo');

        Filesystem::appendToFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'foobar');
    }

    public function testAppendToFileWithZlibScheme()
    {
        $scheme = 'compress.zlib://';
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'baz.txt';
        Filesystem::writeFile($filename, 'foo');

        // Zlib stat uses file:// wrapper so remove it
        $this->assertStringEqualsFile(str_replace($scheme, '', $filename), 'foo');

        Filesystem::appendToFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'foobar');
    }

    public function testAppendToFileCreateTheFileIfNotExists()
    {
        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo' . \DIRECTORY_SEPARATOR . 'bar.txt';

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $oldMask = umask(0002);
        }

        Filesystem::appendToFile($filename, 'bar');

        // skip mode check on Windows
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->assertFilePermissions(664, $filename);
            umask($oldMask);
        }

        $this->assertFileExists($filename);
        $this->assertStringEqualsFile($filename, 'bar');
    }

    public function testWriteFileRemovesTmpFilesOnFailure()
    {
        $expected = scandir(__DIR__, \SCANDIR_SORT_ASCENDING);

        try {
            Filesystem::writeFile(__DIR__ . '/Fixtures', 'bar');
            $this->fail('IOException expected.');
        } catch (IOException $e) {
            $this->assertSame($expected, scandir(__DIR__, \SCANDIR_SORT_ASCENDING));
        }
    }

    public function testWriteFileKeepsExistingPermissionsWhenOverwritingAnExistingFile()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $filename = $this->workspace . \DIRECTORY_SEPARATOR . 'foo.txt';
        file_put_contents($filename, 'FOO BAR');
        chmod($filename, 0745);

        Filesystem::writeFile($filename, 'bar', null);

        $this->assertFilePermissions(745, $filename);
    }

    public function testCopyShouldKeepExecutionPermission()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $sourceFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_source_file';
        $targetFilePath = $this->workspace . \DIRECTORY_SEPARATOR . 'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        chmod($sourceFilePath, 0745);

        Filesystem::copy($sourceFilePath, $targetFilePath);

        $this->assertFilePermissions(767, $targetFilePath);
    }

    /**
     * Normalize the given path (transform each blackslash into a real directory separator).
     *
     * @param string $path
     */
    private function normalize(string $path): string
    {
        return str_replace('/', \DIRECTORY_SEPARATOR, $path);
    }
}
