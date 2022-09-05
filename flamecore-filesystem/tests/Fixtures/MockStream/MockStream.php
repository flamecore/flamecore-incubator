<?php
/*
 * FlameCore Filesystem
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Filesystem\Tests\Fixtures\MockStream;

/**
 * Mock stream class to be used with stream_wrapper_register.
 * stream_wrapper_register('mock', 'FlameCore\Filesystem\Tests\Fixtures\MockStream\MockStream').
 */
class MockStream
{
    /**
     * Opens file or URL.
     *
     * @param string $path        Specifies the URL that was passed to the original function
     * @param string $mode        The mode used to open the file, as detailed for fopen()
     * @param int    $options     Holds additional flags set by the streams API
     * @param string $opened_path If the path is opened successfully, and STREAM_USE_PATH is set in options,
     *                            opened_path should be set to the full path of the file/resource that was actually opened
     */
    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        return true;
    }

    /**
     * @param string $path  The file path or URL to stat
     * @param array  $flags Holds additional flags set by the streams API
     *
     * @return array File stats
     */
    public function url_stat($path, $flags): array
    {
        return [];
    }
}
