<?php
/*
 * FlameCore Filesystem Component
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

declare(strict_types=1);

namespace FlameCore\Filesystem\Exception;

/**
 * This exception is thrown when part of a file or directory cannot be found.
 */
class DirectoryNotFoundException extends IOException
{
}
