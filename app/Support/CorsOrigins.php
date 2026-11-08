<?php

namespace App\Support;

/**
 * The sites a page may call this app from with the user's cookies (config/cors.php): the app's own address, and any extra
 * front end listed in CORS_ALLOWED_ORIGINS. An origin is a scheme, a host and a port, nothing else; a wildcard or anything
 * that is not http(s) is dropped, because credentials are allowed.
 */
final class CorsOrigins
{
    /** @return list<string> */
    public static function for(?string $appUrl, ?string $extra = null): array
    {
        $origins = [];

        foreach (array_merge([(string) $appUrl], explode(',', (string) $extra)) as $url) {
            if (($origin = self::originOf(trim($url))) !== null) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    private static function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        return $scheme.'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
