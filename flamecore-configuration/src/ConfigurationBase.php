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

use FlameCore\Common\Arrays;
use FlameCore\Common\Arrays\ArrayEntry;
use FlameCore\Configuration\Resolver\ConfigResolverInterface;

/**
 * The ConfigurationBase class
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
class ConfigurationBase implements ConfigurationInterface
{
    /**
     * @var array
     */
    protected $values;

    /**
     * @var array
     */
    protected $variables = [];

    /**
     * Configuration constructor.
     *
     * @param array|\ArrayAccess $values
     * @param array              $variables
     */
    public function __construct($values = [], array $variables = [])
    {
        if (!Arrays::isAccessible($values)) {
            throw new \InvalidArgumentException('The value of the $values parameter must be either an array or an object implementing ArrayAccess.');
        }

        $this->values = $values;
        $this->variables = $variables;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $path): bool
    {
        return ArrayEntry::fromPath($this->values, $path)->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path, bool $raw = false)
    {
        $entry = ArrayEntry::fromPath($this->values, $path);

        if (!$entry->exists()) {
            throw new NotFoundException(sprintf('The configuration path "%s" does not exist.', $path));
        }

        return !$raw ? $this->value($entry->get()) : $entry->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getOr(string $path, mixed $default, bool $raw = false)
    {
        try {
            return $this->get($path, $raw);
        } catch (NotFoundException) {
            return !$raw ? $this->value($default) : $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all(bool $raw = false): array
    {
        return !$raw ? $this->value($this->values) : $this->values;
    }

    /**
     * @param string $path
     * @param array  $defaults
     *
     * @return ConfigurationInterface
     */
    public function extract(string $path, array $defaults = []): ConfigurationInterface
    {
        $values = $this->get($path, true);

        if (!is_array($values) || $values === []) {
            throw new \LogicException(sprintf('Entry "%s" is no or an empty array', $path));
        }

        return new static($values, $this->variables);
    }

    /**
     * @param ConfigResolverInterface $resolver
     * @param array                   $defaults
     */
    public function resolve(ConfigResolverInterface $resolver, array $defaults = []): void
    {
        $values = array_replace_recursive($defaults, $this->values);

        $this->values = $resolver->resolve($values);
    }

    /**
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    protected function value($value)
    {
        if (is_object($value)) {
            if ($value instanceof \Closure || method_exists($value, '__invoke')) {
                return $value($this->values, $this->variables);
            } elseif ($value instanceof \Stringable) {
                return (string) $value;
            } else {
                return array_map([$this, 'value'], (array) $value);
            }
        } elseif (is_string($value)) {
            $callback = function ($matches) {
                $varName = $matches[1];
                $value = $this->getOr($varName, $this->variables[$varName] ?? null);

                if ($value === null) {
                    throw new NotFoundException(sprintf('Cannot resolve inline variable "${%s}".', $varName));
                }

                return $value;
            };

            return preg_replace_callback('#\${([\w\.]+)}#', $callback, $value);
        } elseif (is_array($value)) {
            return array_map([$this, 'value'], $value);
        }

        return $value;
    }
}
