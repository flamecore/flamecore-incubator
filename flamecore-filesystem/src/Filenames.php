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

/**
 * Contains utility methods for handling filename parts of path strings.
 *
 * The methods in this class are able to deal with both Unix and Windows paths
 * with both forward and backward slashes.
 *
 * @author Symfony team and contributors
 */
class Filenames
{
    use StaticClass;

    /**
     * Returns the file name without the extension from a file path.
     *
     * @param string|null $extension if specified, only that extension is cut
     *                               off (may contain leading dot)
     * @param string      $path
     */
    public static function getFilenameWithoutExtension(string $path, ?string $extension = null)
    {
        if ($path === '') {
            return '';
        }

        if ($extension !== null) {
            // remove extension and trailing dot
            return rtrim(basename($path, $extension), '.');
        }

        return pathinfo($path, \PATHINFO_FILENAME);
    }

    /**
     * Returns the extension from a file path (without leading dot).
     *
     * @param bool   $forceLowerCase forces the extension to be lower-case
     * @param string $path
     */
    public static function getExtension(string $path, bool $forceLowerCase = false): string
    {
        if ($path === '') {
            return '';
        }

        $extension = pathinfo($path, \PATHINFO_EXTENSION);

        if ($forceLowerCase) {
            $extension = self::toLower($extension);
        }

        return $extension;
    }

    /**
     * Returns whether the path has an (or the specified) extension.
     *
     * @param string               $path       the path string
     * @param string|string[]|null $extensions if null or not provided, checks if
     *                                         an extension exists, otherwise
     *                                         checks for the specified extension
     *                                         or array of extensions (with or
     *                                         without leading dot)
     * @param bool                 $ignoreCase whether to ignore case-sensitivity
     */
    public static function hasExtension(string $path, $extensions = null, bool $ignoreCase = false): bool
    {
        if ($path === '') {
            return false;
        }

        $actualExtension = self::getExtension($path, $ignoreCase);

        // Only check if path has any extension
        if ($extensions === [] || $extensions === null) {
            return $actualExtension !== '';
        }

        if (\is_string($extensions)) {
            $extensions = [$extensions];
        }

        foreach ($extensions as $key => $extension) {
            if ($ignoreCase) {
                $extension = self::toLower($extension);
            }

            // remove leading '.' in extensions array
            $extensions[$key] = ltrim($extension, '.');
        }

        return \in_array($actualExtension, $extensions, true);
    }

    /**
     * Changes the extension of a path string.
     *
     * @param string $path      The path string with filename.ext to change.
     * @param string $extension new extension (with or without leading dot)
     *
     * @return string the path string with new file extension
     */
    public static function changeExtension(string $path, string $extension): string
    {
        if ($path === '') {
            return '';
        }

        $actualExtension = self::getExtension($path);
        $extension = ltrim($extension, '.');

        // No extension for paths
        if (mb_substr($path, -1) === '/') {
            return $path;
        }

        // No actual extension in path
        if (empty($actualExtension)) {
            return $path . (mb_substr($path, -1) === '.' ? '' : '.') . $extension;
        }

        return mb_substr($path, 0, -mb_strlen($actualExtension)) . $extension;
    }
}
