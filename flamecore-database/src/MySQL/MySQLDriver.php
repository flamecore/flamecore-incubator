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

use FlameCore\Database\AbstractDriver;

/**
 * This class allows you to execute operations in a MySQL database
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
class MySQLDriver extends AbstractDriver
{
    /**
     * The link identifier of the connection
     *
     * @var \mysqli
     */
    protected $link;

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $this->link = @mysqli_connect($this->host, $this->user, $this->password, $this->database);

        if (mysqli_connect_errno()) {
            throw new \RuntimeException(sprintf('Failed connecting to the database: %s', mysqli_connect_error()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        mysqli_close($this->link);
    }

    /**
     * {@inheritdoc}
     */
    public function query($query, array $vars = null)
    {
        $stmt = $this->prepare($query);
        $result = $stmt->execute($vars);

        if (!$result) {
            throw new \RuntimeException(sprintf('Failed to execute database query: %s', $stmt->getErrorInfo()[2]));
        }

        $this->queryCount++;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function exec($sql, array $vars = null)
    {
        $stmt = $this->prepare($sql);
        $result = $stmt->execute($vars);

        if (!$result) {
            throw new \RuntimeException(sprintf('Failed to execute database statement: %s', $stmt->getErrorInfo()[2]));
        }

        return $stmt->getAffectedRows();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($sql)
    {
        $sql = $this->interpolate($sql);

        return new MySQLStatement($this->link, $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function select($table, $columns = '*', array $params = [])
    {
        if (is_array($columns)) {
            $columns = implode(', ', array_map([$this, 'quoteField'], $columns));
        } elseif ($columns != '*') {
            $columns = $this->quoteField($columns);
        }

        $sql = sprintf(
            'SELECT %s FROM %s',
            $columns,
            $this->quoteField($this->prefix.$table)
        );

        if (isset($params['where'])) {
            $sql .= ' WHERE '.$params['where'];
        }
        if (isset($params['limit'])) {
            $sql .= ' LIMIT '.$params['limit'];
        }
        if (isset($params['group'])) {
            $sql .= ' GROUP BY '.$params['group'];
        }
        if (isset($params['order'])) {
            $sql .= ' ORDER BY '.$params['order'];
        }

        return $this->query($sql, isset($params['vars']) ? $params['vars'] : null);
    }

    /**
     * {@inheritdoc}
     */
    public function insert($table, array $data)
    {
        $columns = array();
        $values = array();

        foreach ($data as $column => $value) {
            $columns[] = '`'.$column.'`';
            $values[]  = $value;
        }

        $params = array_fill(0, count($values), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES(%s)',
            $this->quoteField($this->prefix.$table),
            implode(', ', $columns),
            implode(', ', $params)
        );

        return $this->exec($sql, $values);
    }

    /**
     * {@inheritdoc}
     */
    public function update($table, $data, array $params = [])
    {
        $dataset = array();
        $values = array();

        foreach ($data as $column => $value) {
            $dataset[] = sprintf('%s = ?', $this->quoteField($column));
            $values[]  = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s',
            $this->quoteField($this->prefix.$table),
            implode(', ', $dataset)
        );


        if (isset($params['where'])) {
            $sql .= ' WHERE '.$params['where'];
        }
        if (isset($params['limit'])) {
            $sql .= ' LIMIT '.$params['limit'];
        }

        $vars = isset($params['vars']) ? array_merge($values, (array) $params['vars']) : $values;

        return $this->exec($sql, $vars);
    }

    /**
     * {@inheritdoc}
     */
    public function batch($statements)
    {
        if (is_array($statements)) {
            $statements = implode(';', $statements);
        }

        $statements = $this->interpolate($statements);

        if (mysqli_multi_query($this->link, $statements)) {
            $i = 1;
            do {
                $i++;
            } while (mysqli_next_result($this->link));
        }

        if (mysqli_errno($this->link)) {
            throw new \RuntimeException(sprintf('Batch execution prematurely ended at statement %d: %s', $i, mysqli_error($this->link)));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function import($file)
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \LogicException(sprintf('File "%s" does not exist or is not readable.', $file));
        }

        $sql = trim(file_get_contents($file));
        $sql = preg_replace('@(([\'"`]).*?[^\\\]\2)|((?:\#|--).*?$|/\*(?:[^/*]|/(?!\*)|\*(?!/)|(?R))*\*\/)\s*|(?<=;)\s+@ms', '$1', $sql);

        return $this->batch($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function insertID()
    {
        return mysqli_insert_id($this->link);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        if (mysqli_begin_transaction($this->link)) {
            $this->inTransaction = true;
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        if ($this->inTransaction && mysqli_commit($this->link)) {
            $this->inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        if ($this->inTransaction) {
            return mysqli_rollback($this->link);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction()
    {
        return $this->inTransaction;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($string)
    {
        return mysqli_real_escape_string($this->link, $string);
    }

    /**
     * {@inheritdoc}
     */
    public function quote($string)
    {
        $string = $this->escape($string);
        $string = addcslashes($string, '%_');

        return "'$string'";
    }

    /**
     * {@inheritdoc}
     */
    public function quoteField($field)
    {
        return '`'.$this->escape($field).'`';
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        return mysqli_errno($this->link);
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorInfo()
    {
        return array(
            mysqli_sqlstate($this->link),
            mysqli_errno($this->link),
            mysqli_error($this->link)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCharset()
    {
        return mysqli_character_set_name($this->link);
    }

    /**
     * {@inheritdoc}
     */
    public function setCharset($charset)
    {
        return mysqli_set_charset($this->link, (string) $charset);
    }
}
