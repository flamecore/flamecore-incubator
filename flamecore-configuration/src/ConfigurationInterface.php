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

/**
 * The Configuration interface.
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
interface ConfigurationInterface
{
    /**
     * @param string $path
     *
     * @return bool
     */
    public function has(string $path): bool;

    /**
     * Get an item using "dot" notation.
     *
     * @param string $path
     * @param bool   $raw
     *
     * @return mixed
     */
    public function get(string $path, bool $raw = false);

    /**
     * Get an item using "dot" notation or the given default value if the item is not declared.
     *
     * @param string $path
     * @param mixed  $default
     * @param bool   $raw
     *
     * @return mixed
     */
    public function getOr(string $path, $default, bool $raw = false);

    /**
     * Get all items.
     *
     * @param bool $raw
     *
     * @return array
     */
    public function all(bool $raw = false): array;

    /**
     * @param string $path
     * @param array  $defaults
     *
     * @return static
     */
    public function extract(string $path, array $defaults = []): self;
}
