<?php
/*
 * FlameCore Filesystem
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Filesystem;

use FlameCore\Common\StaticClass;
use FlameCore\Filesystem\Exception\InvalidArgumentException;
use FlameCore\Filesystem\Exception\RuntimeException;

/**
 * Contains utility methods for handling path strings.
 *
 * The methods in this class are able to deal with both Unix and Windows paths
 * with both forward and backward slashes. All methods return normalized parts
 * containing only forward slashes and no excess "." and ".." segments.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Thomas Schulz <mail@king2500.net>
 * @author Théo Fidry <theo.fidry@gmail.com>
 * @author Christian Neff <christian.neff@gmail.com>
 */
class Paths
{
    use StaticClass;

    public const SEPARATOR = DIRECTORY_SEPARATOR;

    /**
     * Canonicalizes the given path.
     *
     * During normalization, all slashes are replaced by forward slashes ("/").
     * Furthermore, all "." and ".." segments are removed as far as possible.
     *
     * @param string $path
     * @param string $separator
     * @param bool   $useBackslash
     *
     * @return string
     */
    public static function canonicalize(string $path, bool $useBackslash = false): string
    {
        $parts = $path === '' ? [] : preg_split('~[/\\\\]+~', $path);
        $result = [];
        foreach ($parts as $part) {
            if ($part === '..' && $result && end($result) !== '..' && end($result) !== '') {
                array_pop($result);
            } elseif ($part !== '.') {
                $result[] = $part;
            }
        }

        $separator = $useBackslash ? '\\' : '/';
        return $result === [''] ? $separator : implode($separator, $result);
    }

    /**
     * Normalizes the given path.
     *
     * During normalization, all slashes are replaced by forward slashes ("/") by default.
     * Contrary to {@link canonicalize()}, this method does not remove invalid
     * or dot path segments. Consequently, it is much more efficient and should
     * be used whenever the given path is known to be a valid, absolute system path.
     *
     * This method is able to deal with both UNIX and Windows paths.
     *
     * @param string $path
     * @param bool   $useBackslash
     */
    public static function normalize(string $path, bool $useBackslash = false): string
    {
        if ($useBackslash) {
            return str_replace('/', '\\', $path);
        } else {
            return str_replace('\\', '/', $path);
        }
    }

    /**
     * Returns the directory part of the path.
     *
     * This method is similar to PHP's dirname(), but handles various cases
     * where dirname() returns a weird result:
     *
     *  - dirname() does not accept backslashes on UNIX
     *  - dirname("C:/flamecore") returns "C:", not "C:/"
     *  - dirname("C:/") returns ".", not "C:/"
     *  - dirname("C:") returns ".", not "C:/"
     *  - dirname("flamecore") returns ".", not ""
     *  - dirname() does not canonicalize the result
     *
     * This method fixes these shortcomings and behaves like dirname()
     * otherwise.
     *
     * The result is a canonical path.
     *
     *
     * @param string $path
     *
     * @return string The canonical directory part. Returns the root directory
     *                if the root directory is passed. Returns an empty string
     *                if a relative path is passed that contains no slashes.
     *                Returns an empty string if an empty string is passed.
     */
    public static function getDirectory(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $path = self::canonicalize($path);

        // Maintain scheme
        if (false !== ($schemeSeparatorPosition = mb_strpos($path, '://'))) {
            $scheme = mb_substr($path, 0, $schemeSeparatorPosition + 3);
            $path = mb_substr($path, $schemeSeparatorPosition + 3);
        } else {
            $scheme = '';
        }

        if (false === ($dirSeparatorPosition = strrpos($path, '/'))) {
            return '';
        }

        // Directory equals root directory "/"
        if ($dirSeparatorPosition === 0) {
            return $scheme . '/';
        }

        // Directory equals Windows root "C:/"
        if ($dirSeparatorPosition === 2 && ctype_alpha($path[0]) && $path[1] === ':') {
            return $scheme . mb_substr($path, 0, 3);
        }

        return $scheme . mb_substr($path, 0, $dirSeparatorPosition);
    }

    /**
     * Returns canonical path of the user's home directory.
     *
     * Supported operating systems: Unix, Windows 8 and up
     *
     * If the operation system or environment isn't supported, an exception is thrown.
     *
     * @throws RuntimeException if your operation system or environment isn't supported
     */
    public static function getHomeDirectory(): string
    {
        // For Unix support
        if (getenv('HOME')) {
            return self::canonicalize(getenv('HOME'));
        }

        // For Windows >= 8 support
        if (getenv('HOMEDRIVE') && getenv('HOMEPATH')) {
            return self::canonicalize(getenv('HOMEDRIVE') . getenv('HOMEPATH'));
        }

        throw new RuntimeException('Cannot find the home directory path: Your environment or operation system is not supported.');
    }

    /**
     * Returns the root directory of a path. The result is a canonical path.
     *
     *
     * @param string $path
     *
     * @return string The canonical root directory. Returns an empty string if
     *                the given path is relative or empty.
     */
    public static function getRoot(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Maintain scheme
        if (false !== ($schemeSeparatorPosition = strpos($path, '://'))) {
            $scheme = substr($path, 0, $schemeSeparatorPosition + 3);
            $path = substr($path, $schemeSeparatorPosition + 3);
        } else {
            $scheme = '';
        }

        $firstCharacter = $path[0];

        // UNIX root "/" or "\" (Windows style)
        if ($firstCharacter === '/' || $firstCharacter === '\\') {
            return $scheme . '/';
        }

        $length = mb_strlen($path);

        // Windows root
        if ($length > 1 && $path[1] === ':' && ctype_alpha($firstCharacter)) {
            // Special case: "C:"
            if ($length === 2) {
                return $scheme . $path . '/';
            }

            // Normal case: "C:/ or "C:\"
            if ($path[2] === '/' || $path[2] === '\\') {
                return $scheme . $firstCharacter . $path[1] . '/';
            }
        }

        return '';
    }

    /**
     * Returns whether the given path is an absolute path.
     *
     * @param string $path The path to check
     *
     * @return bool
     */
    public static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        // Strip scheme
        if (false !== ($schemeSeparatorPosition = mb_strpos($path, '://'))) {
            $path = mb_substr($path, $schemeSeparatorPosition + 3);
        }

        $firstCharacter = $path[0];

        // UNIX root "/" or "\" (Windows style)
        if ($firstCharacter === '/' || $firstCharacter === '\\') {
            return true;
        }

        // Windows root
        if (mb_strlen($path) > 1 && ctype_alpha($firstCharacter) && $path[1] === ':') {
            // Special case: "C:"
            if (mb_strlen($path) === 2) {
                return true;
            }

            // Normal case: "C:/ or "C:\"
            if ($path[2] === '/' || $path[2] === '\\') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the given path is a relative path.
     *
     * @param string $path The path to check
     *
     * @return bool
     */
    public static function isRelative(string $path): bool
    {
        return !self::isAbsolute($path);
    }

    /**
     * Turns a relative path into an absolute path in canonical form.
     *
     * Usually, the relative path is appended to the given base path. Dot
     * segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * echo Fiename::makeAbsolute("../style.css", "/flamecore/puli/css");
     * // => /flamecore/puli/style.css
     * ```
     *
     * If an absolute path is passed, that path is returned unless its root
     * directory is different than the one of the base path. In that case, an
     * exception is thrown.
     *
     * ```php
     * Fiename::makeAbsolute("/style.css", "/flamecore/puli/css");
     * // => /style.css
     *
     * Fiename::makeAbsolute("C:/style.css", "C:/flamecore/puli/css");
     * // => C:/style.css
     *
     * Fiename::makeAbsolute("C:/style.css", "/flamecore/puli/css");
     * // InvalidArgumentException
     * ```
     *
     * If the base path is not an absolute path, an exception is thrown.
     *
     * The result is a canonical path.
     *
     * @param string $basePath an absolute base path
     * @param string $path
     *
     * @throws InvalidArgumentException if the base path is not absolute or if
     *                                  the given path is an absolute path with
     *                                  a different root than the base path
     */
    public static function makeAbsolute(string $path, string $basePath): string
    {
        if ($basePath === '') {
            throw new InvalidArgumentException(sprintf('The base path must be a non-empty string. Got: "%s".', $basePath));
        }

        if (!self::isAbsolute($basePath)) {
            throw new InvalidArgumentException(sprintf('The base path "%s" is not an absolute path.', $basePath));
        }

        if (self::isAbsolute($path)) {
            return self::canonicalize($path);
        }

        if (false !== ($schemeSeparatorPosition = mb_strpos($basePath, '://'))) {
            $scheme = mb_substr($basePath, 0, $schemeSeparatorPosition + 3);
            $basePath = mb_substr($basePath, $schemeSeparatorPosition + 3);
        } else {
            $scheme = '';
        }

        return $scheme . self::canonicalize(rtrim($basePath, '/\\') . '/' . $path);
    }

    /**
     * Turns a path into a relative path.
     *
     * The relative path is created relative to the given base path:
     *
     * ```php
     * echo Fiename::makeRelative("/flamecore/style.css", "/flamecore/puli");
     * // => ../style.css
     * ```
     *
     * If a relative path is passed and the base path is absolute, the relative
     * path is returned unchanged:
     *
     * ```php
     * Fiename::makeRelative("style.css", "/flamecore/puli/css");
     * // => style.css
     * ```
     *
     * If both paths are relative, the relative path is created with the
     * assumption that both paths are relative to the same directory:
     *
     * ```php
     * Fiename::makeRelative("style.css", "flamecore/puli/css");
     * // => ../../../style.css
     * ```
     *
     * If both paths are absolute, their root directory must be the same,
     * otherwise an exception is thrown:
     *
     * ```php
     * Fiename::makeRelative("C:/flamecore/style.css", "/flamecore/puli");
     * // InvalidArgumentException
     * ```
     *
     * If the passed path is absolute, but the base path is not, an exception
     * is thrown as well:
     *
     * ```php
     * Fiename::makeRelative("/flamecore/style.css", "flamecore/puli");
     * // InvalidArgumentException
     * ```
     *
     * If the base path is not an absolute path, an exception is thrown.
     *
     * The result is a canonical path.
     *
     *
     * @param string $path
     * @param string $basePath
     *
     * @throws InvalidArgumentException if the base path is not absolute or if
     *                                  the given path has a different root
     *                                  than the base path
     */
    public static function makeRelative(string $path, string $basePath): string
    {
        $path = self::canonicalize($path);
        $basePath = self::canonicalize($basePath);

        [$root, $relativePath] = self::split($path);
        [$baseRoot, $relativeBasePath] = self::split($basePath);

        // If the base path is given as absolute path and the path is already
        // relative, consider it to be relative to the given absolute path
        // already
        if ($root === '' && $baseRoot !== '') {
            // If base path is already in its root
            if ($relativeBasePath === '') {
                $relativePath = ltrim($relativePath, './\\');
            }

            return $relativePath;
        }

        // If the passed path is absolute, but the base path is not, we
        // cannot generate a relative path
        if ($root !== '' && $baseRoot === '') {
            throw new InvalidArgumentException(sprintf('The absolute path "%s" cannot be made relative to the relative path "%s". You should provide an absolute base path instead.', $path, $basePath));
        }

        // Fail if the roots of the two paths are different
        if ($baseRoot && $root !== $baseRoot) {
            throw new InvalidArgumentException(sprintf('The path "%s" cannot be made relative to "%s", because they have different roots ("%s" and "%s").', $path, $basePath, $root, $baseRoot));
        }

        if ($relativeBasePath === '') {
            return $relativePath;
        }

        // Build a "../../" prefix with as many "../" parts as necessary
        $parts = explode('/', $relativePath);
        $baseParts = explode('/', $relativeBasePath);
        $dotDotPrefix = '';

        // Once we found a non-matching part in the prefix, we need to add
        // "../" parts for all remaining parts
        $match = true;

        foreach ($baseParts as $index => $basePart) {
            if ($match && isset($parts[$index]) && $basePart === $parts[$index]) {
                unset($parts[$index]);

                continue;
            }

            $match = false;
            $dotDotPrefix .= '../';
        }

        return rtrim($dotDotPrefix . implode('/', $parts), '/');
    }

    /**
     * Returns whether the given path is on the local filesystem.
     *
     * @param string $path
     */
    public static function isLocal(string $path): bool
    {
        return $path !== '' && mb_strpos($path, '://') === false;
    }

    /**
     * Returns the longest common base path in canonical form of a set of paths or
     * `null` if the paths are on different Windows partitions.
     *
     * Dot segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * $basePath = Fiename::getLongestCommonBasePath([
     *     '/flamecore/css/style.css',
     *     '/flamecore/css/..'
     * ]);
     * // => /flamecore
     * ```
     *
     * The root is returned if no common base path can be found:
     *
     * ```php
     * $basePath = Fiename::getLongestCommonBasePath([
     *     '/flamecore/css/style.css',
     *     '/puli/css/..'
     * ]);
     * // => /
     * ```
     *
     * If the paths are located on different Windows partitions, `null` is
     * returned.
     *
     * ```php
     * $basePath = Fiename::getLongestCommonBasePath([
     *     'C:/flamecore/css/style.css',
     *     'D:/flamecore/css/..'
     * ]);
     * // => null
     * ```
     *
     * @param string[] $paths
     */
    public static function getLongestCommonBasePath(string ...$paths): ?string
    {
        [$bpRoot, $basePath] = self::split(self::canonicalize(reset($paths)));

        for (next($paths); key($paths) !== null && $basePath !== ''; next($paths)) {
            [$root, $path] = self::split(self::canonicalize(current($paths)));

            // If we deal with different roots (e.g. C:/ vs. D:/), it's time
            // to quit
            if ($root !== $bpRoot) {
                return null;
            }

            // Make the base path shorter until it fits into path
            while (true) {
                if ($basePath === '.') {
                    // No more base paths
                    $basePath = '';

                    // next path
                    continue 2;
                }

                // Prevent false positives for common prefixes
                // see isBasePath()
                if (mb_strpos($path . '/', $basePath . '/') === 0) {
                    // next path
                    continue 2;
                }

                $basePath = \dirname($basePath);
            }
        }

        return $bpRoot . $basePath;
    }

    /**
     * Joins two or more path strings into a canonical path.
     *
     * @param string[] $paths
     */
    public static function join(string ...$paths): string
    {
        $finalPath = null;
        $wasScheme = false;

        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }

            if ($finalPath === null) {
                // For first part we keep slashes, like '/top', 'C:\' or 'phar://'
                $finalPath = $path;
                $wasScheme = (mb_strpos($path, '://') !== false);
                continue;
            }

            // Only add slash if previous part didn't end with '/' or '\'
            if (!\in_array(mb_substr($finalPath, -1), ['/', '\\'])) {
                $finalPath .= '/';
            }

            // If first part included a scheme like 'phar://' we allow \current part to start with '/', otherwise trim
            $finalPath .= $wasScheme ? $path : ltrim($path, '/');
            $wasScheme = false;
        }

        if ($finalPath === null) {
            return '';
        }

        return self::canonicalize($finalPath);
    }

    /**
     * Returns whether a path is a base path of another path.
     *
     * Dot segments ("." and "..") are removed/collapsed and all slashes turned
     * into forward slashes.
     *
     * ```php
     * Fiename::isBasePath('/flamecore', '/flamecore/css');
     * // => true
     *
     * Fiename::isBasePath('/flamecore', '/flamecore');
     * // => true
     *
     * Fiename::isBasePath('/flamecore', '/flamecore/..');
     * // => false
     *
     * Fiename::isBasePath('/flamecore', '/infernum');
     * // => false
     * ```
     *
     * @param string $basePath
     * @param string $ofPath
     */
    public static function isBasePath(string $basePath, string $ofPath): bool
    {
        $basePath = self::canonicalize($basePath);
        $ofPath = self::canonicalize($ofPath);

        // Append slashes to prevent false positives when two paths have
        // a common prefix, for example /base/foo and /base/foobar.
        // Don't append a slash for the root "/", because then that root
        // won't be discovered as common prefix ("//" is not a prefix of
        // "/foobar/").
        return mb_strpos($ofPath . '/', rtrim($basePath, '/') . '/') === 0;
    }

    /**
     * @param string $root
     * @param string $pathWithoutRoot
     *
     * @return non-empty-string[]
     */
    private static function findCanonicalParts(string $root, string $pathWithoutRoot): array
    {
        $parts = explode('/', $pathWithoutRoot);

        $canonicalParts = [];

        // Collapse "." and "..", if possible
        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }

            // Collapse ".." with the previous part, if one exists
            // Don't collapse ".." if the previous part is also ".."
            if ($part === '..' && \count($canonicalParts) > 0 && $canonicalParts[\count($canonicalParts) - 1] !== '..') {
                array_pop($canonicalParts);

                continue;
            }

            // Only add ".." prefixes for relative paths
            if ($part !== '..' || $root === '') {
                $canonicalParts[] = $part;
            }
        }

        return $canonicalParts;
    }

    /**
     * Splits a canonical path into its root directory and the remainder.
     *
     * If the path has no root directory, an empty root directory will be
     * returned.
     *
     * If the root directory is a Windows style partition, the resulting root
     * will always contain a trailing slash.
     *
     * list ($root, $path) = Fiename::split("C:/flamecore")
     * // => ["C:/", "flamecore"]
     *
     * list ($root, $path) = Fiename::split("C:")
     * // => ["C:/", ""]
     *
     *
     * @param string $path
     *
     * @return array{string, string} an array with the root directory and the remaining relative path
     */
    private static function split(string $path): array
    {
        if ($path === '') {
            return ['', ''];
        }

        // Remember scheme as part of the root, if any
        if (false !== ($schemeSeparatorPosition = mb_strpos($path, '://'))) {
            $root = mb_substr($path, 0, $schemeSeparatorPosition + 3);
            $path = mb_substr($path, $schemeSeparatorPosition + 3);
        } else {
            $root = '';
        }

        $length = mb_strlen($path);

        // Remove and remember root directory
        if (mb_strpos($path, '/') === 0) {
            $root .= '/';
            $path = $length > 1 ? mb_substr($path, 1) : '';
        } elseif ($length > 1 && ctype_alpha($path[0]) && $path[1] === ':') {
            if ($length === 2) {
                // Windows special case: "C:"
                $root .= $path . '/';
                $path = '';
            } elseif ($path[2] === '/') {
                // Windows normal case: "C:/"..
                $root .= mb_substr($path, 0, 3);
                $path = $length > 3 ? mb_substr($path, 3) : '';
            }
        }

        return [$root, $path];
    }

    private static function toLower(string $string): string
    {
        if (false !== $encoding = mb_detect_encoding($string)) {
            return mb_strtolower($string, $encoding);
        }

        return strtolower($string, $encoding);
    }
}
