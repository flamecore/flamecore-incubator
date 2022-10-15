<?php
/*
 * FlameCore Filesystem Component
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

declare(strict_types=1);

namespace FlameCore\Filesystem\Tests;

use FlameCore\Filesystem\Paths;
use PHPUnit\Framework\TestCase;

class PathsTest extends TestCase
{
    /**
     * @dataProvider provideTestPaths
     *
     * @param string $path
     * @param bool   $isAbsolute
     */
    public function testIsRelative(string $path, bool $isAbsolute)
    {
        $result = Paths::isRelative($path);

        $this->assertEquals(!$isAbsolute, $result);
    }

    /**
     * @dataProvider provideTestPaths
     *
     * @param string $path
     * @param bool   $isAbsolute
     */
    public function testIsAbsolute(string $path, bool $isAbsolute)
    {
        $result = Paths::isAbsolute($path);

        $this->assertEquals($isAbsolute, $result);
    }

    public function provideTestPaths()
    {
        return [
            // path, is absolute
            ['/var/lib', true],
            ['c:\\\\var\\lib', true],
            ['\\var\\lib', true],
            ['var/lib', false],
            ['../var/lib', false],
            ['', false],
        ];
    }

    /**
     * @dataProvider provideDataForNormalize
     *
     * @param mixed $expected
     * @param mixed $param
     * @param mixed $useBackslashes
     */
    public function testNormalize($expected, $param, $useBackslashes = false)
    {
        $this->assertEquals($expected, Paths::normalize($param, $useBackslashes));
    }

    public function provideDataForNormalize()
    {
        return [
            ['', ''],
            ['/', '\\'],
            ['/', '/'],
            ['file', 'file'],
            ['file/', 'file/'],
            ['d:/file', 'd:/file'],
            ['d:/file', 'd:\file'],
            ['/file', '/file'],
            ['/', '//'],
            ['/foo/', '//foo//'],
            ['\\', '/', true]
        ];
    }

    /**
     * @dataProvider provideDataForJoin
     *
     * @param mixed $expected
     * @param array $params
     */
    public function testJoin($expected, array $params)
    {
        $this->assertSame($expected, Paths::join(...$params));
    }

    public function provideDataForJoin()
    {
        $sep = DIRECTORY_SEPARATOR;

        return [
            ['', ['']],
            [$sep, ['\\']],
            [$sep, ['/']],
            ["a{$sep}b", ['a', 'b']],
            ["{$sep}a{$sep}b{$sep}", ['/a/', '/b/']],
            ["{$sep}", ['/a/', '/../']],
        ];
    }
}
