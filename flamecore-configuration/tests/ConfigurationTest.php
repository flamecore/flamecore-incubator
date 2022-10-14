<?php
/*
 * flamecore-incubator
 * Copyright (C) 2022 Christian Neff
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Configuration\Tests;

use FlameCore\Configuration\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testBasic()
    {
        $array = $this->makeSampleArray();
        $config = new Configuration($array);

        $this->assertTrue($config->has('foo'));
        $this->assertTrue($config->has('bar.baz'));

        $this->assertEquals('bar', $config->get('foo'));
        $this->assertEquals('qux', $config->get('bar.baz'));
        $this->assertEquals(['baz' => 'qux'], $config->get('bar'));

        $this->assertEquals('default', $config->getOr('nonexistent', 'default'));

        $this->assertEquals($array, $config->all());

        $extracted = $config->extract('bar');
        $this->assertEquals(['baz' => 'qux'], $extracted->all());
    }

    public function testSet()
    {
        $config = new Configuration();
        $config->set('foo', 'bar');
        $config->set('bar.baz', 'qux');

        $this->assertEquals('bar', $config->get('foo'));
        $this->assertEquals('qux', $config->get('bar.baz'));
    }

    public function testSetMultiple()
    {
        $config = new Configuration();
        $config->setMultiple([
            'foo' => 'bar',
            'bar.baz' => 'qux'
        ]);

        $this->assertEquals('bar', $config->get('foo'));
        $this->assertEquals('qux', $config->get('bar.baz'));
    }

    public function testRemove()
    {
        $config = new Configuration($this->makeSampleArray());
        $config->remove('foo');
        $config->remove('bar.baz');

        $this->assertEquals(['bar' => []], $config->all());
    }

    /**
     * @return array
     */
    protected function makeSampleArray(): array
    {
        return [
            'foo' => 'bar',
            'bar' => [
                'baz' => 'qux'
            ],
        ];
    }
}
