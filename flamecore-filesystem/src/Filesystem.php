<?php
/*
 * FlameCore Filesystem Component
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Filesystem;

use FlameCore\Common\StaticClass;
use FlameCore\Filesystem\Exception\IOException;
use FlameCore\Filesystem\Exception\FileNotFoundException;

/**
 * Provides basic utility to manipulate the file system.
 *
 * @author Symfony team and contributors
 * @author Christian Neff <christian.neff@gmail.com>
 */
class Filesystem
{
    use StaticClass;

    private static $lastError;

    /**
     * Copies a file.
     *
     * If the target file is older than the origin file, it's always overwritten.
     * If the target file is newer, it is overwritten only when the
     * $overwriteNewerFiles option is set to true.
     *
     * @param string $originFile
     * @param string $targetFile
     * @param bool   $overwriteNewerFiles
     *
     * @throws FileNotFoundException When originFile doesn't exist
     * @throws IOException           When copy fails
     */
    public static function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false)
    {
        $originIsLocal = stream_is_local($originFile) || stripos($originFile, 'file://') === 0;
        if ($originIsLocal && !is_file($originFile)) {
            throw new FileNotFoundException(sprintf('Failed to copy "%s" because file does not exist.', $originFile), 0, null, $originFile);
        }

        self::createDir(\dirname($targetFile));

        $doCopy = true;
        if (!$overwriteNewerFiles && parse_url($originFile, \PHP_URL_HOST) === null && is_file($targetFile)) {
            $doCopy = filemtime($originFile) > filemtime($targetFile);
        }

        if ($doCopy) {
            // https://bugs.php.net/64634
            if (!$source = self::box('fopen', $originFile, 'r')) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s" because source file could not be opened for reading: ', $originFile, $targetFile) . self::$lastError, 0, null, $originFile);
            }

            // Stream context created to allow files overwrite when using FTP stream wrapper - disabled by default
            if (!$target = self::box('fopen', $targetFile, 'w', false, stream_context_create(['ftp' => ['overwrite' => true]]))) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s" because target file could not be opened for writing: ', $originFile, $targetFile) . self::$lastError, 0, null, $originFile);
            }

            $bytesCopied = stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            unset($source, $target);

            if (!is_file($targetFile)) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s".', $originFile, $targetFile), 0, null, $originFile);
            }

            if ($originIsLocal) {
                // Like `cp`, preserve executable permission bits
                self::box('chmod', $targetFile, fileperms($targetFile) | (fileperms($originFile) & 0111));

                if ($bytesCopied !== $bytesOrigin = filesize($originFile)) {
                    throw new IOException(sprintf('Failed to copy the whole content of "%s" to "%s" (%g of %g bytes copied).', $originFile, $targetFile, $bytesCopied, $bytesOrigin), 0, null, $originFile);
                }
            }
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @param string|iterable $dirs The directory path
     * @param int             $mode
     *
     * @throws IOException On any directory creation failure
     */
    public static function createDir($dirs, int $mode = 0777)
    {
        foreach (self::toIterable($dirs) as $dir) {
            if (is_dir($dir)) {
                continue;
            }

            if (!self::box('mkdir', $dir, $mode, true) && !is_dir($dir)) {
                throw new IOException(sprintf('Failed to create "%s": ', $dir) . self::$lastError, 0, null, $dir);
            }
        }
    }

    /**
     * Checks the existence of files or directories.
     *
     * @param string|iterable $files A filename, an array of files, or a \Traversable instance to check
     *
     * @return bool true if the file exists, false otherwise
     */
    public static function exists($files)
    {
        $maxPathLength = \PHP_MAXPATHLEN - 2;

        foreach (self::toIterable($files) as $file) {
            if (\strlen($file) > $maxPathLength) {
                throw new IOException(sprintf('Could not check if file exist because path length exceeds %d characters.', $maxPathLength), 0, null, $file);
            }

            if (!file_exists($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets access and modification time of file.
     *
     * @param string|iterable $files A filename, an array of files, or a \Traversable instance to create
     * @param int|null        $time  The touch time as a Unix timestamp, if not supplied the current system time is used
     * @param int|null        $atime The access time as a Unix timestamp, if not supplied the current system time is used
     *
     * @throws IOException When touch fails
     */
    public static function touch($files, ?int $time = null, ?int $atime = null)
    {
        foreach (self::toIterable($files) as $file) {
            if (!($time ? self::box('touch', $file, $time, $atime) : self::box('touch', $file))) {
                throw new IOException(sprintf('Failed to touch "%s": ', $file) . self::$lastError, 0, null, $file);
            }
        }
    }

    /**
     * Removes files or directories.
     *
     * @param string|iterable $files A filename, an array of files, or a \Traversable instance to remove
     *
     * @throws IOException When removal fails
     */
    public static function remove($files)
    {
        if ($files instanceof \Traversable) {
            $files = iterator_to_array($files, false);
        } elseif (!\is_array($files)) {
            $files = [$files];
        }

        self::doRemove($files, false);
    }

    private static function doRemove(array $files, bool $isRecursive): void
    {
        $files = array_reverse($files);
        foreach ($files as $file) {
            if (is_link($file)) {
                // See https://bugs.php.net/52176
                if (!(self::box('unlink', $file) || '\\' !== \DIRECTORY_SEPARATOR || self::box('rmdir', $file)) && file_exists($file)) {
                    throw new IOException(sprintf('Failed to remove symlink "%s": ', $file) . self::$lastError);
                }
            } elseif (is_dir($file)) {
                if (!$isRecursive) {
                    $tmpName = \dirname(realpath($file)) . '/.' . strrev(strtr(base64_encode(random_bytes(2)), '/=', '-.'));

                    if (file_exists($tmpName)) {
                        try {
                            self::doRemove([$tmpName], true);
                        } catch (IOException) {
                        }
                    }

                    if (!file_exists($tmpName) && self::box('rename', $file, $tmpName)) {
                        $origFile = $file;
                        $file = $tmpName;
                    } else {
                        $origFile = null;
                    }
                }

                $files = new \FilesystemIterator($file, \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS);
                self::doRemove(iterator_to_array($files, true), true);

                if (!self::box('rmdir', $file) && file_exists($file) && !$isRecursive) {
                    $lastError = self::$lastError;

                    if ($origFile !== null && self::box('rename', $file, $origFile)) {
                        $file = $origFile;
                    }

                    throw new IOException(sprintf('Failed to remove directory "%s": ', $file) . $lastError);
                }
            } elseif (!self::box('unlink', $file) && (str_contains(self::$lastError, 'Permission denied') || file_exists($file))) {
                throw new IOException(sprintf('Failed to remove file "%s": ', $file) . self::$lastError);
            }
        }
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @param string|iterable $files     A filename, an array of files, or a \Traversable instance to change mode
     * @param int             $mode      The new mode (octal)
     * @param int             $umask     The mode mask (octal)
     * @param bool            $recursive Whether change the mod recursively or not
     *
     * @throws IOException When the change fails
     */
    public static function chmod($files, int $mode, int $umask = 0000, bool $recursive = false)
    {
        foreach (self::toIterable($files) as $file) {
            if ((\PHP_VERSION_ID < 80000 || \is_int($mode)) && !self::box('chmod', $file, $mode & ~$umask)) {
                throw new IOException(sprintf('Failed to chmod file "%s": ', $file) . self::$lastError, 0, null, $file);
            }
            if ($recursive && is_dir($file) && !is_link($file)) {
                self::chmod(new \FilesystemIterator($file), $mode, $umask, true);
            }
        }
    }

    /**
     * Change the owner of an array of files or directories.
     *
     * @param string|iterable $files     A filename, an array of files, or a \Traversable instance to change owner
     * @param string|int      $user      A user name or number
     * @param bool            $recursive Whether change the owner recursively or not
     *
     * @throws IOException When the change fails
     */
    public static function chown($files, $user, bool $recursive = false)
    {
        foreach (self::toIterable($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {
                self::chown(new \FilesystemIterator($file), $user, true);
            }
            if (is_link($file) && \function_exists('lchown')) {
                if (!self::box('lchown', $file, $user)) {
                    throw new IOException(sprintf('Failed to chown file "%s": ', $file) . self::$lastError, 0, null, $file);
                }
            } else {
                if (!self::box('chown', $file, $user)) {
                    throw new IOException(sprintf('Failed to chown file "%s": ', $file) . self::$lastError, 0, null, $file);
                }
            }
        }
    }

    /**
     * Change the group of an array of files or directories.
     *
     * @param string|iterable $files     A filename, an array of files, or a \Traversable instance to change group
     * @param string|int      $group     A group name or number
     * @param bool            $recursive Whether change the group recursively or not
     *
     * @throws IOException When the change fails
     */
    public static function chgrp($files, $group, bool $recursive = false)
    {
        foreach (self::toIterable($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {
                self::chgrp(new \FilesystemIterator($file), $group, true);
            }
            if (is_link($file) && \function_exists('lchgrp')) {
                if (!self::box('lchgrp', $file, $group)) {
                    throw new IOException(sprintf('Failed to chgrp file "%s": ', $file) . self::$lastError, 0, null, $file);
                }
            } else {
                if (!self::box('chgrp', $file, $group)) {
                    throw new IOException(sprintf('Failed to chgrp file "%s": ', $file) . self::$lastError, 0, null, $file);
                }
            }
        }
    }

    /**
     * Renames a file or a directory.
     *
     * @param string $origin
     * @param string $target
     * @param bool   $overwrite
     *
     * @throws IOException When target file or directory already exists
     * @throws IOException When origin cannot be renamed
     */
    public static function rename(string $origin, string $target, bool $overwrite = false)
    {
        // we check that target does not exist
        if (!$overwrite && self::isReadable($target)) {
            throw new IOException(sprintf('Cannot rename because the target "%s" already exists.', $target), 0, null, $target);
        }

        if (!self::box('rename', $origin, $target)) {
            if (is_dir($origin)) {
                // See https://bugs.php.net/54097 & https://php.net/rename#113943
                self::mirror($origin, $target, null, ['override' => $overwrite, 'delete' => $overwrite]);
                self::remove($origin);

                return;
            }
            throw new IOException(sprintf('Cannot rename "%s" to "%s": ', $origin, $target) . self::$lastError, 0, null, $target);
        }
    }

    /**
     * Tells whether a file exists and is readable.
     *
     * @param string $filename
     *
     * @throws IOException When windows path is longer than 258 characters
     */
    public static function isReadable(string $filename): bool
    {
        $maxPathLength = \PHP_MAXPATHLEN - 2;

        if (\strlen($filename) > $maxPathLength) {
            throw new IOException(sprintf('Could not check if file is readable because path length exceeds %d characters.', $maxPathLength), 0, null, $filename);
        }

        return is_readable($filename);
    }

    /**
     * Creates a symbolic link or copy a directory.
     *
     * @param string $targetDir
     * @param string $linkDir
     * @param bool   $copyOnWindows
     *
     * @throws IOException When symlink fails
     */
    public static function symlink(string $targetDir, string $linkDir, bool $copyOnWindows = false)
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $targetDir = strtr($targetDir, '/', '\\');
            $linkDir = strtr($linkDir, '/', '\\');

            if ($copyOnWindows) {
                self::mirror($targetDir, $linkDir);

                return;
            }
        }

        self::createDir(\dirname($linkDir));

        if (is_link($linkDir)) {
            if (readlink($linkDir) === $targetDir) {
                return;
            }

            self::remove($linkDir);
        }

        if (!self::box('symlink', $targetDir, $linkDir)) {
            self::throwLinkException($targetDir, $linkDir, 'symbolic');
        }
    }

    /**
     * Creates a hard link, or several hard links to a file.
     *
     * @param string|string[] $targetFiles The target file(s)
     * @param string          $originFile
     *
     * @throws FileNotFoundException When original file is missing or not a file
     * @throws IOException           When link fails, including if link already exists
     */
    public static function hardlink(string $originFile, $targetFiles)
    {
        if (!self::exists($originFile)) {
            throw new FileNotFoundException(null, 0, null, $originFile);
        }

        if (!is_file($originFile)) {
            throw new FileNotFoundException(sprintf('Origin file "%s" is not a file.', $originFile));
        }

        foreach (self::toIterable($targetFiles) as $targetFile) {
            if (is_file($targetFile)) {
                if (fileinode($originFile) === fileinode($targetFile)) {
                    continue;
                }
                self::remove($targetFile);
            }

            if (!self::box('link', $originFile, $targetFile)) {
                self::throwLinkException($originFile, $targetFile, 'hard');
            }
        }
    }

    /**
     * @param string $linkType Name of the link type, typically 'symbolic' or 'hard'
     * @param string $target
     * @param string $link
     */
    private static function throwLinkException(string $target, string $link, string $linkType)
    {
        if (self::$lastError) {
            if ('\\' === \DIRECTORY_SEPARATOR && strpos(self::$lastError, 'error code(1314)') !== false) {
                throw new IOException(sprintf('Unable to create %s link due to error code 1314: \'A required privilege is not held by the client\'. Do you have the required Administrator rights?', $linkType), 0, null, $link);
            }
        }

        throw new IOException(sprintf('Failed to create %s link to "%s" from "%s": %s', $linkType, $target, $link, self::$lastError), 0, null, $link);
    }

    /**
     * Resolves links in paths.
     *
     * With $canonicalize = false (default)
     *      - if $path does not exist or is not a link, returns null
     *      - if $path is a link, returns the next direct target of the link without considering the existence of the target
     *
     * With $canonicalize = true
     *      - if $path does not exist, returns null
     *      - if $path exists, returns its absolute fully resolved final version
     *
     * @param string $path
     * @param bool   $canonicalize
     *
     * @return string|null
     */
    public static function readlink(string $path, bool $canonicalize = false)
    {
        if (!$canonicalize && !is_link($path)) {
            return null;
        }

        if ($canonicalize) {
            if (!self::exists($path)) {
                return null;
            }

            return realpath($path);
        }

        return readlink($path);
    }

    /**
     * Mirrors a directory to another.
     *
     * Copies files and directories from the origin directory into the target directory. By default:
     *
     *  - existing files in the target directory will be overwritten, except if they are newer (see the `override` option)
     *  - files in the target directory that do not exist in the source directory will not be deleted (see the `delete` option)
     *
     * @param \Traversable|null $iterator  Iterator that filters which files and directories to copy, if null a recursive iterator is created
     * @param array             $options   An array of boolean options
     *                                     Valid options are:
     *                                     - $options['override'] If true, target files newer than origin files are overwritten (see copy(), defaults to false)
     *                                     - $options['copy_on_windows'] Whether to copy files instead of links on Windows (see symlink(), defaults to false)
     *                                     - $options['delete'] Whether to delete files that are not in the source directory (defaults to false)
     * @param string            $originDir
     * @param string            $targetDir
     *
     * @throws IOException When file type is unknown
     */
    public static function mirror(string $originDir, string $targetDir, ?\Traversable $iterator = null, array $options = [])
    {
        $targetDir = rtrim($targetDir, '/\\');
        $originDir = rtrim($originDir, '/\\');
        $originDirLen = \strlen($originDir);

        if (!self::exists($originDir)) {
            throw new IOException(sprintf('The origin directory specified "%s" was not found.', $originDir), 0, null, $originDir);
        }

        // Iterate in destination folder to remove obsolete entries
        if (self::exists($targetDir) && isset($options['delete']) && $options['delete']) {
            $deleteIterator = $iterator;
            if ($deleteIterator === null) {
                $flags = \FilesystemIterator::SKIP_DOTS;
                $deleteIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($targetDir, $flags), \RecursiveIteratorIterator::CHILD_FIRST);
            }
            $targetDirLen = \strlen($targetDir);
            foreach ($deleteIterator as $file) {
                $origin = $originDir . substr($file->getPathname(), $targetDirLen);
                if (!self::exists($origin)) {
                    self::remove($file);
                }
            }
        }

        $copyOnWindows = $options['copy_on_windows'] ?? false;

        if ($iterator === null) {
            $flags = $copyOnWindows ? \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS : \FilesystemIterator::SKIP_DOTS;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($originDir, $flags), \RecursiveIteratorIterator::SELF_FIRST);
        }

        self::createDir($targetDir);
        $filesCreatedWhileMirroring = [];

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            if ($file->getPathname() === $targetDir || $filePath === $targetDir || isset($filesCreatedWhileMirroring[$filePath])) {
                continue;
            }

            $target = $targetDir . substr($file->getPathname(), $originDirLen);
            $filesCreatedWhileMirroring[$target] = true;

            if (!$copyOnWindows && is_link($file)) {
                self::symlink($file->getLinkTarget(), $target);
            } elseif (is_dir($file)) {
                self::createDir($target);
            } elseif (is_file($file)) {
                self::copy($filePath, $target, $options['override'] ?? false);
            } else {
                throw new IOException(sprintf('Unable to guess "%s" file type.', $file), 0, null, $file);
            }
        }
    }

    /**
     * Creates a temporary file.
     *
     * @param string $prefix The prefix of the generated temporary filename
     *                       Note: Windows uses only the first three characters of prefix
     * @param string $suffix The suffix of the generated temporary filename
     * @param string $dir
     *
     * @throws IOException on failure
     *
     * @return string The new temporary filename (with path)
     */
    public static function tempnam(string $dir, string $prefix, string $suffix = '')
    {
        [$scheme, $hierarchy] = self::getSchemeAndHierarchy($dir);

        // If no scheme or scheme is "file" or "gs" (Google Cloud) create temp file in local filesystem
        if (($scheme === null || $scheme === 'file' || $scheme === 'gs') && $suffix === '') {
            // If tempnam failed or no scheme return the filename otherwise prepend the scheme
            if ($tmpFile = self::box('tempnam', $hierarchy, $prefix)) {
                if ($scheme !== null && $scheme !== 'gs') {
                    return $scheme . '://' . $tmpFile;
                }

                return $tmpFile;
            }

            throw new IOException('A temporary file could not be created: ' . self::$lastError);
        }

        // Loop until we create a valid temp file or have reached 10 attempts
        for ($i = 0; $i < 10; ++$i) {
            // Create a unique filename
            $tmpFile = $dir . '/' . $prefix . uniqid(mt_rand(), true) . $suffix;

            // Use fopen instead of file_exists as some streams do not support stat
            // Use mode 'x+' to atomically check existence and create to avoid a TOCTOU vulnerability
            if (!$handle = self::box('fopen', $tmpFile, 'x+')) {
                continue;
            }

            // Close the file if it was successfully opened
            self::box('fclose', $handle);

            return $tmpFile;
        }

        throw new IOException('A temporary file could not be created: ' . self::$lastError);
    }

    /**
     * Creates a temporary directory.
     *
     * @param string $prefix The prefix of the generated temporary directory
     * @param string $suffix The suffix of the generated temporary directory
     *
     * @throws IOException on failure
     *
     * @return string The new temporary directory (with path)
     */
    public static function tempdir(string $prefix = '', string $suffix = '')
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid(mt_rand(), true) . $suffix;
        self::createDir($path);

        return $path;
    }

    /**
     * Reads the content of a file.
     *
     * @param string $file
     *
     * @throws IOException on failure
     */
    public static function readFile(string $file): string
    {
        $content = self::box('file_get_contents', $file);

        if ($content === false) {
            throw new IOException(sprintf('Unable to read file "%s": %s', $file, self::$lastError));
        }

        return $content;
    }

    /**
     * Atomically dumps content into a file.
     *
     * @param string|resource $content  The data to write into the file
     * @param string          $filename
     *
     * @throws IOException if the file cannot be written to
     */
    public static function writeFile(string $filename, $content)
    {
        if (\is_array($content)) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be string or resource, array given.', __METHOD__));
        }

        $dir = \dirname($filename);

        if (!is_dir($dir)) {
            self::createDir($dir);
        }

        if (!is_writable($dir)) {
            throw new IOException(sprintf('Unable to write to the "%s" directory.', $dir), 0, null, $dir);
        }

        // Will create a temp file with 0600 access rights
        // when the filesystem supports chmod.
        $tmpFile = self::tempnam($dir, basename($filename));

        try {
            if (self::box('file_put_contents', $tmpFile, $content) === false) {
                throw new IOException(sprintf('Failed to write file "%s": ', $filename) . self::$lastError, 0, null, $filename);
            }

            self::box('chmod', $tmpFile, file_exists($filename) ? fileperms($filename) : 0666 & ~umask());

            self::rename($tmpFile, $filename, true);
        } finally {
            if (file_exists($tmpFile)) {
                self::box('unlink', $tmpFile);
            }
        }
    }

    /**
     * Appends content to an existing file.
     *
     * @param string|resource $content  The content to append
     * @param string          $filename
     *
     * @throws IOException If the file is not writable
     */
    public static function appendToFile(string $filename, $content)
    {
        if (\is_array($content)) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be string or resource, array given.', __METHOD__));
        }

        $dir = \dirname($filename);

        if (!is_dir($dir)) {
            self::createDir($dir);
        }

        if (!is_writable($dir)) {
            throw new IOException(sprintf('Unable to write to the "%s" directory.', $dir), 0, null, $dir);
        }

        if (self::box('file_put_contents', $filename, $content, \FILE_APPEND) === false) {
            throw new IOException(sprintf('Failed to write file "%s": %s', $filename, self::$lastError), 0, null, $filename);
        }
    }

    private static function toIterable($files): iterable
    {
        return \is_array($files) || $files instanceof \Traversable ? $files : [$files];
    }

    /**
     * Gets a 2-tuple of scheme (may be null) and hierarchical part of a filename (e.g. file:///tmp -> [file, tmp]).
     *
     * @param string $filename
     */
    private static function getSchemeAndHierarchy(string $filename): array
    {
        $components = explode('://', $filename, 2);

        return \count($components) === 2 ? [$components[0], $components[1]] : [null, $components[0]];
    }

    /**
     * @param mixed    ...$args
     * @param callable $func
     *
     * @return mixed
     */
    private static function box(callable $func, ...$args)
    {
        self::$lastError = null;
        set_error_handler(__CLASS__ . '::handleError');
        try {
            $result = $func(...$args);
            restore_error_handler();

            return $result;
        } catch (\Throwable $e) {
            restore_error_handler();

            throw $e;
        }
    }

    /**
     * @internal
     *
     * @param mixed $type
     * @param mixed $msg
     */
    public static function handleError($type, $msg)
    {
        self::$lastError = $msg;
    }
}
