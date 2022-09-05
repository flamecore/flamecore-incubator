<?php
/*
 * FlameCore Configuration
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Configuration\Loader;

use FlameCore\Configuration\ConfigurationInterface;

/**
 * The ConfigLoaderInterface class.
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
interface ConfigLoaderInterface
{
    /**
     * Loads a resource into a Configuration object.
     *
     * @param string $resource The resource name
     *
     * @return ConfigurationInterface|null
     *
     * @throws \Exception If something went wrong
     */
    public function load(string $resource): ConfigurationInterface;
}
