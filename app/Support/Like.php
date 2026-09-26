<?php

namespace App\Support;

/**
 * A word somebody typed into a search box, made safe to put inside LIKE: `%` and `_` are text there, not wildcards, so "50%" finds
 * "50%" and a lone "_" no longer matches every row (MySQL's escape character is the backslash). Every search of the app goes through it.
 */
final class Like
{
    public static function escape(string $term): string
    {
        return addcslashes($term, '\\%_');
    }

    /** contains: %term% */
    public static function contains(string $term): string
    {
        return '%' . self::escape($term) . '%';
    }

    /** starts with: term% */
    public static function startsWith(string $term): string
    {
        return self::escape($term) . '%';
    }
}
