<?php
/*
 * FlameCore Configuration
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Configuration;

use FlameCore\Common\Arrays\ArrayEntry;

/**
 * Class Configuration.
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
class Configuration extends ConfigurationBase
{
    /**
     * Set the value of the given item using "dot" notation.
     *
     * @param string $path
     * @param mixed  $value
     */
    public function set(string $path, $value): void
    {
        ArrayEntry::fromPath($this->values, $path)->set($value);
    }

    /**
     * Set the values of multiple items using an array.
     *
     * @param array $values A one-dimensional array in the form `path => value`
     */
    public function setMultiple(array $values): void
    {
        foreach ($values as $path => $value) {
            $this->set($path, $value);
        }
    }

    /**
     * @param string $path
     */
    public function remove(string $path): void
    {
        ArrayEntry::fromPath($this->values, $path)->remove();
    }

    /**
     * Merge multiple items using a hierarchical multi-level array or another Configuration object.
     *
     * @param array|ConfigurationInterface $newValues
     */
    public function merge($newValues)
    {
        if (is_object($newValues) && $newValues instanceof ConfigurationInterface) {
            $newValues = $newValues->all(true);
        }

        $this->values = array_merge_recursive($this->values, $newValues);
    }

    /**
     * Replace multiple items using a hierarchical multi-level array or another Configuration object.
     *
     * @param array|ConfigurationInterface $newValues Hierarchical multi-level array or another Configuration object
     */
    public function replace($newValues)
    {
        if (is_object($newValues) && $newValues instanceof ConfigurationInterface) {
            $newValues = $newValues->all(true);
        }

        $this->values = array_replace_recursive($this->values, $newValues);
    }

    /**
     * @param array $variables
     */
    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }
}
