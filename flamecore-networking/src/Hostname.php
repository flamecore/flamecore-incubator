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
 * The Hostname entity class.
 *
 * @author chthomas <christian.h.thomas@me.com>
 */
final class Hostname
{
    public readonly string $value;

    /**
     * Creates a new Hostname object.
     *
     * @param string $value The hostname as a string
     *
     * @throws \InvalidArgumentException if the given string is not a valid hostname.
     */
    public function __construct(string $value)
    {
        $hostname = self::normalizeHostname($value);

        if (filter_var($hostname, FILTER_VALIDATE_DOMAIN) !== $hostname) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid hostname.', $hostname));
        }

        $this->value = $hostname;
    }

    /**
     * Converts the given value to a Hostname object.
     *
     * @param string|Hostname $input The value to convert
     *
     * @return self
     */
    public static function from(self|string $input): self
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
     * Returns the hostname without the trailing dot.
     *
     * @return string
     */
    public function getHostnameWithoutTrailingDot(): string
    {
        return substr($this->value, 0, -1);
    }

    /**
     * Checks if the given Hostname object is equal to this one.
     *
     * @param Hostname $hostname The Hostname object to compare with
     *
     * @return bool
     */
    public function equals(self $hostname): bool
    {
        return $this->value === (string) $hostname;
    }

    /**
     * Returns whether the hostname is punycode encoded or not.
     *
     * @return bool
     */
    public function isPunycoded(): bool
    {
        return $this->toUTF8() !== $this->value;
    }

    /**
     * Returns the hostname in UTF-8 encoding.
     *
     * @return string
     */
    public function toUTF8(): string
    {
        return (string) idn_to_utf8($this->value, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
    }

    /**
     * Normalizes the given hostname.
     *
     * @param string $hostname The hostname to normalize
     *
     * @return string
     */
    private static function normalizeHostname(string $hostname): string
    {
        $hostname = self::generatePunycode(mb_strtolower(trim($hostname)));

        if (!str_ends_with($hostname, '.')) {
            return $hostname . '.';
        }

        return $hostname;
    }

    /**
     * Generates punycode for the given hostname.
     *
     * @param string $hostname The hostname to generate punycode for
     *
     * @return string
     */
    private static function generatePunycode(string $hostname): string
    {
        return (string) idn_to_ascii($hostname, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
    }
}
