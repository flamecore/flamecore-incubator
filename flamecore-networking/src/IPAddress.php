<?php
/*
 * FlameCore Networking Component
 * Copyright (C) 2022 FlameCore Team
 *
 * Permission to use, copy, modify, and/or distribute this software for
 * any purpose with or without fee is hereby granted, provided that the
 * above copyright notice and this permission notice appear in all copies.
 */

namespace FlameCore\Networking;

/**
 * The IPAddress entity class.
 *
 * @author Christian Thomas <christian.h.thomas@me.com>
 */
final class IPAddress
{
    public readonly string $value;

    /**
     * Creates a new IPAddress object.
     *
     * @param string $value The IP address as a string
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $value)
    {
        $value = trim($value);

        if (!self::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid IP address.', $value));
        }

        $this->value = $value;
    }

    /**
     * Check if the given string is a valid IP address.
     *
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_IP);
    }

    /**
     * Converts the given value to an IPAddress object.
     *
     * @param IPAddress|string $input The value to convert
     *
     * @return IPAddress
     */
    public static function from(self|string $input): IPAddress
    {
        return is_string($input) ? new self($input) : $input;
    }

    /**
     * Converts the entity object to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Checks if the given IPAddress object is equal to this one.
     *
     * @return bool
     */
    public function equals(IPAddress $value): bool
    {
        return $this->value === (string) $value;
    }

    /**
     * Returns whether the IP address is an IPv6 address.
     *
     * @return bool
     */
    public function isIPv6(): bool
    {
        return (bool) filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * Returns whether the IP address is an IPv4 address.
     *
     * @return bool
     */
    public function isIPv4(): bool
    {
        return (bool) filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }
}
