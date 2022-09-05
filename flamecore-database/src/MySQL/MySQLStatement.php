<?php
/*
 * FlameCore Database Component
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Database\MySQL;

use FlameCore\Database\AbstractStatement;

/**
 * Prepared MySQL statement
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
class MySQLStatement extends AbstractStatement
{
    /**
     * @var \mysqli_stmt
     */
    protected $stmt;

    /**
     * Creates a prepared statement.
     *
     * @param \mysqli $conn The database connection
     * @param string $statement The SQL statement to prepare
     * @throws \RuntimeException on failure.
     */
    public function __construct(\mysqli $conn, $statement)
    {
        $stmt = mysqli_prepare($conn, $statement);

        if (!$stmt) {
            throw new \RuntimeException(sprintf('Failed to prepare database statement: %s', mysqli_error($conn)));
        }

        if (($count = mysqli_stmt_param_count($stmt)) > 0) {
            $this->parameters = array_fill(0, $count, null);
        }

        $this->stmt = $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $parameters = null)
    {
        if (!empty($this->parameters)) {
            $length = count($this->parameters);

            if (!empty($parameters)) {
                if (count($parameters) != $length) {
                    throw new \OutOfRangeException(sprintf('Not enough or too many parameters. (%d given, expected %d)', count($parameters), $length));
                }

                $parameters = array_replace($this->parameters, $parameters);
            } else {
                $parameters = $this->parameters;
            }

            $types = '';
            $values = array();

            foreach ($parameters as $i => $parameter) {
                $value = $this->encode($parameter);

                if (is_int($value)) {
                    $type = 'i';
                } elseif (is_float($value)) {
                    $type = 'd';
                } else {
                    $type = 's';
                }

                $types .= $type;

                $values[$i] = $value;
                $values[$i] = &$values[$i];
            }

            call_user_func_array('mysqli_stmt_bind_param', array_merge([$this->stmt, $types], $values));
        }

        if (!mysqli_stmt_execute($this->stmt)) {
            throw new \RuntimeException(sprintf('Failed to execute database statement: %s', mysqli_stmt_error($this->stmt)));
        }

        $this->executed = true;

        if ($result = mysqli_stmt_get_result($this->stmt)) {
            return new MySQLResult($result);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getAffectedRows()
    {
        return mysqli_stmt_affected_rows($this->stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        return mysqli_stmt_errno($this->stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorInfo()
    {
        return array(
            mysqli_stmt_sqlstate($this->stmt),
            mysqli_stmt_errno($this->stmt),
            mysqli_stmt_error($this->stmt)
        );
    }
}
