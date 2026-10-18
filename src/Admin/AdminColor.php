<?php

declare(strict_types=1);

namespace Seablast\Seablast\Admin;

/** Shared strict color policy for stored values and API input. */
final class AdminColor
{
    /** @param mixed $value */
    public static function normalize($value): ?string
    {
        return is_string($value) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1
            ? strtolower($value)
            : null;
    }
}
