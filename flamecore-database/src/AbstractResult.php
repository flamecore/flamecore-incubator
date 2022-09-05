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
 * Result set returned by a database query
 *
 * @author Christian Neff <christian.neff@gmail.com>
 */
abstract class AbstractResult implements ResultInterface
{
    /**
     * {@inheritdoc}
     */
    public function hasRows()
    {
        return $this->numRows() > 0;
    }
}
