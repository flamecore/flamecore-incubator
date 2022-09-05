<?php
/*
 * FlameCore Configuration
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Configuration\Resolver;

use Symfony\Component\OptionsResolver\Exception\NoSuchOptionException;

/**
 * The ConfigResolver interface.
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
interface ConfigResolverInterface
{
    /**
     * @param string|string[]      $options
     * @param bool                 $required
     * @param string|string[]|null $types
     * @param mixed                $values
     * @param mixed                $default
     *
     * @return $this
     */
    public function define(string|array $options, bool $required = false, string|array|null $types = null, $values = null, $default = null);

    /**
     * @param array $options
     *
     * @return array
     *
     * @throws NoSuchOptionException
     */
    public function resolve(array $options): array;
}
