<?php
/*
 * FlameCore Filesystem
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

declare(strict_types=1);

namespace FlameCore\Filesystem\Tests;

use FlameCore\Filesystem\Exception\InvalidArgumentException;
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
     */
    public function testNormalize($expected, $param)
    {
        $this->assertSame($expected, Paths::normalize($param));
    }

    public function provideDataForNormalize()
    {
        $sep = DIRECTORY_SEPARATOR;

        return [
            ['', ''],
            [$sep, '\\'],
            [$sep, '/'],
            ['file', 'file'],
            ["file{$sep}", 'file/'],
            ["d:{$sep}file", 'd:/file'],
            ["d:{$sep}file", 'd:\file'],
            ["{$sep}file", '/file'],
            ['', '.'],
            [$sep, '\\.'],
            [$sep, '/.'],
            [$sep, '.\\'],
            [$sep, './'],
            [$sep, '/file/..'],
            [$sep, '/file/../'],
            ['', 'file/..'],
            [$sep, 'file/../'],
            ["{$sep}..", '/file/../..'],
            ["{$sep}..{$sep}", '/file/../../'],
            ['..', 'file/../..'],
            ["..{$sep}", 'file/../../'],
            ["{$sep}..{$sep}bar", '/file/../../bar'],
            ["..{$sep}bar", 'file/../../bar'],
            ["{$sep}..{$sep}bar", '/file/./.././.././bar'],
            ["..{$sep}bar", 'file/../../bar/.'],
            ["{$sep}..{$sep}bar{$sep}", '/file/./.././.././bar/'],
            ["..{$sep}bar{$sep}", 'file/../../bar/./'],
            [$sep, '//'],
            ["{$sep}foo{$sep}", '//foo//'],
            ["{$sep}", '//foo//..//'],
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

    /**
     * @dataProvider provideDataForMakeRelative
     *
     * @param mixed $endPath
     * @param mixed $startPath
     * @param mixed $expectedPath
     */
    public function testMakeRelative($endPath, $startPath, $expectedPath)
    {
        $path = Paths::makeRelative($endPath, $startPath);

        $this->assertEquals($expectedPath, $path);
    }

    public function provideDataForMakeRelative()
    {
        $paths = [
            ['/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component', '../'],
            ['/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component/', '../'],
            ['/var/lib/symfony/src/Symfony', '/var/lib/symfony/src/Symfony/Component', '../'],
            ['/var/lib/symfony/src/Symfony', '/var/lib/symfony/src/Symfony/Component/', '../'],
            ['/usr/lib/symfony/', '/var/lib/symfony/src/Symfony/Component', '../../../../../../usr/lib/symfony/'],
            ['/var/lib/symfony/src/Symfony/', '/var/lib/symfony/', 'src/Symfony/'],
            ['/aa/bb', '/aa/bb', './'],
            ['/aa/bb', '/aa/bb/', './'],
            ['/aa/bb/', '/aa/bb', './'],
            ['/aa/bb/', '/aa/bb/', './'],
            ['/aa/bb/cc', '/aa/bb/cc/dd', '../'],
            ['/aa/bb/cc', '/aa/bb/cc/dd/', '../'],
            ['/aa/bb/cc/', '/aa/bb/cc/dd', '../'],
            ['/aa/bb/cc/', '/aa/bb/cc/dd/', '../'],
            ['/aa/bb/cc', '/aa', 'bb/cc/'],
            ['/aa/bb/cc', '/aa/', 'bb/cc/'],
            ['/aa/bb/cc/', '/aa', 'bb/cc/'],
            ['/aa/bb/cc/', '/aa/', 'bb/cc/'],
            ['/a/aab/bb', '/a/aa', '../aab/bb/'],
            ['/a/aab/bb', '/a/aa/', '../aab/bb/'],
            ['/a/aab/bb/', '/a/aa', '../aab/bb/'],
            ['/a/aab/bb/', '/a/aa/', '../aab/bb/'],
            ['/a/aab/bb/', '/', 'a/aab/bb/'],
            ['/a/aab/bb/', '/b/aab', '../../a/aab/bb/'],
            ['/aab/bb', '/aa', '../aab/bb/'],
            ['/aab', '/aa', '../aab/'],
            ['/aa/bb/cc', '/aa/dd/..', 'bb/cc/'],
            ['/aa/../bb/cc', '/aa/dd/..', '../bb/cc/'],
            ['/aa/bb/../../cc', '/aa/../dd/..', 'cc/'],
            ['/../aa/bb/cc', '/aa/dd/..', 'bb/cc/'],
            ['/../../aa/../bb/cc', '/aa/dd/..', '../bb/cc/'],
            ['C:/aa/bb/cc', 'C:/aa/dd/..', 'bb/cc/'],
            ['C:/aa/bb/cc', 'c:/aa/dd/..', 'bb/cc/'],
            ['c:/aa/../bb/cc', 'c:/aa/dd/..', '../bb/cc/'],
            ['C:/aa/bb/../../cc', 'C:/aa/../dd/..', 'cc/'],
            ['C:/../aa/bb/cc', 'C:/aa/dd/..', 'bb/cc/'],
            ['C:/../../aa/../bb/cc', 'C:/aa/dd/..', '../bb/cc/'],
            ['D:/', 'C:/aa/../bb/cc', 'D:/'],
            ['D:/aa/bb', 'C:/aa', 'D:/aa/bb/'],
            ['D:/../../aa/../bb/cc', 'C:/aa/dd/..', 'D:/bb/cc/'],
        ];

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $paths[] = ['c:\var\lib/symfony/src/Symfony/', 'c:/var/lib/symfony/', 'src/Symfony/'];
        }

        return $paths;
    }

    public function testMakeRelativeWithRelativeStartPath()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The start path "var/lib/symfony/src/Symfony/Component" is not absolute.');
        $this->assertSame('../../../', Paths::makeRelative('/var/lib/symfony/', 'var/lib/symfony/src/Symfony/Component'));
    }

    public function testMakeRelativeWithRelativeEndPath()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The end path "var/lib/symfony/" is not absolute.');
        $this->assertSame('../../../', Paths::makeRelative('var/lib/symfony/', '/var/lib/symfony/src/Symfony/Component'));
    }
}
