<?php
/*
 * FlameCore Database Component
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Database;

/**
 * The abstract Statement class
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
abstract class AbstractStatement implements StatementInterface
{
    /**
     * @var array
     */
    protected $parameters = array();

    /**
     * @var bool
     */
    protected $executed = false;

    /**
     * {@inheritdoc}
     */
    public function bind($parameter, &$value)
    {
        $index = $parameter - 1;

        if (!isset($this->parameters[$index])) {
            throw new \OutOfRangeException(sprintf('Parameter position %d does not exist.', $parameter));
        }

        $this->parameters[$index] = &$value;
    }

    /**
     * {@inheritdoc}
     */
    public function isExecuted()
    {
        return $this->executed;
    }

    /**
     * Encodes a PHP value for use in a SQL statement.
     *
     * @param mixed $value The value to encode
     * @return string
     */
    protected function encode($value)
    {
        if (is_bool($value)) {
            return (int) $value;
        } elseif (is_scalar($value)) {
            return $value;
        } elseif ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        } elseif (is_array($value)) {
            return implode(',', $value);
        } else {
            throw new \InvalidArgumentException(sprintf('Cannot encode value of type %s.', gettype($value)));
        }
    }
}
