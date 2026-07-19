<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ActivityLogContext
{
    public static function fromRequest(Request $request): array
    {
        $userAgent = $request->userAgent() ?: null;

        return [
            'ip_address' => $request->ip(),
            'browser' => self::detectBrowser($userAgent),
            'operating_system' => self::detectOperatingSystem($userAgent),
            'device_type' => self::detectDeviceType($userAgent),
            'user_agent' => $userAgent,
        ];
    }

    public static function system(): array
    {
        return [
            'ip_address' => null,
            'browser' => null,
            'operating_system' => null,
            'device_type' => null,
            'user_agent' => null,
        ];
    }

    public static function detectBrowser(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'Edg/') =>
                'Microsoft Edge' . self::extractVersion($userAgent, 'Edg/'),

            str_contains($userAgent, 'OPR/') =>
                'Opera' . self::extractVersion($userAgent, 'OPR/'),

            str_contains($userAgent, 'Firefox/') =>
                'Mozilla Firefox' . self::extractVersion($userAgent, 'Firefox/'),

            str_contains($userAgent, 'Chrome/') =>
                'Google Chrome' . self::extractVersion($userAgent, 'Chrome/'),

            str_contains($userAgent, 'Version/')
                && str_contains($userAgent, 'Safari/') =>
                'Safari' . self::extractVersion($userAgent, 'Version/'),

            default => 'Tidak diketahui',
        };
    }

    public static function detectOperatingSystem(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return match (true) {
            str_contains($userAgent, 'Android') => 'Android',

            str_contains($userAgent, 'iPhone'),
            str_contains($userAgent, 'iPad'),
            str_contains($userAgent, 'iPod') => 'iOS',

            str_contains($userAgent, 'Windows NT') => 'Windows',

            str_contains($userAgent, 'Macintosh'),
            str_contains($userAgent, 'Mac OS X') => 'macOS',

            str_contains($userAgent, 'Linux') => 'Linux',

            default => 'Tidak diketahui',
        };
    }

    public static function detectDeviceType(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return match (true) {
            preg_match(
                '/bot|crawler|spider|slurp|bingpreview/i',
                $userAgent
            ) === 1 => 'Bot',

            str_contains($userAgent, 'iPad'),
            str_contains($userAgent, 'Tablet'),
            str_contains($userAgent, 'Android')
                && !str_contains($userAgent, 'Mobile') => 'Tablet',

            str_contains($userAgent, 'Mobile'),
            str_contains($userAgent, 'iPhone'),
            str_contains($userAgent, 'Android') => 'Mobile',

            str_contains($userAgent, 'Windows'),
            str_contains($userAgent, 'Macintosh'),
            str_contains($userAgent, 'Linux') => 'Desktop',

            default => 'Tidak diketahui',
        };
    }

    private static function extractVersion(
        string $userAgent,
        string $marker
    ): string {
        $pattern = '/'
            . preg_quote($marker, '/')
            . '([0-9]+)/';

        if (
            preg_match($pattern, $userAgent, $matches) !== 1
            || empty($matches[1])
        ) {
            return '';
        }

        return ' ' . $matches[1];
    }
}
