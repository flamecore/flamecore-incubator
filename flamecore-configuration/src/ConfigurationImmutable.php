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
 * Class ConfigurationImmutable.
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
class ConfigurationImmutable implements ConfigurationInterface
{
    /**
     * @var array
     */
    private $values;

    /**
     * @var array
     */
    private $variables = [];

    /**
     * Configuration constructor.
     *
     * @param array|\ArrayAccess $values
     * @param array              $variables
     */
    public function __construct($values, array $variables = [])
    {
        if (!$this->isAccessible($values)) {
            throw new \InvalidArgumentException('');
        }

        $this->values = $values;
        $this->variables = $variables;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $path): bool
    {
        try {
            $this->get($path, true);

            return true;
        } catch (NotFoundException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see https://stackoverflow.com/a/39118759/2039901
     */
    public function get(string $path, bool $raw = false)
    {
        $values = $this->values;

        $segments = $this->split($path);
        foreach ($segments as $segment) {
            if (!$this->isAccessible($values) || !$this->keyExists($values, $segment)) {
                throw new NotFoundException(sprintf('Entry "%s" is not defined', $path));
            }

            $values = $values[$segment];
        }

        return !$raw ? $this->value($values) : $values;
    }

    /**
     * {@inheritdoc}
     */
    public function getOr(string $path, $default, bool $raw = false)
    {
        try {
            return $this->get($path, $raw);
        } catch (NotFoundException $e) {
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
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @param string $path
     *
     * @return string[]
     */
    protected function split(string $path)
    {
        return explode('.', $path);
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
                return $value($this, $this->variables);
            } elseif (method_exists($value, '__toString')) {
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

    /**
     * Determine whether the given value is array accessible.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isAccessible($value)
    {
        return is_array($value) || $value instanceof \ArrayAccess;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param array|\ArrayAccess $values
     * @param string|int         $key
     *
     * @return bool
     */
    protected function keyExists($values, $key)
    {
        if ($values instanceof \ArrayAccess) {
            return $values->offsetExists($key);
        }

        return isset($values[$key]);
    }
}
