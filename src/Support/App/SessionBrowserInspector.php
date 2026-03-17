<?php

namespace Xul\AuthKit\Support\App;

/**
 * SessionBrowserInspector
 *
 * Resolves lightweight browser, platform, and device labels from a user-agent
 * string for authenticated session display.
 *
 * Notes:
 * - This intentionally stays heuristic-based and lightweight.
 * - It is not intended to be a fully comprehensive user-agent parser.
 */
final class SessionBrowserInspector
{
    /**
     * Resolve a lightweight browser label from a user agent string.
     */
    public function resolveBrowserLabel(string $userAgent): string
    {
        $agent = mb_strtolower($userAgent);

        return match (true) {
            str_contains($agent, 'edg/') => 'Microsoft Edge',
            str_contains($agent, 'opr/'),
            str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'chrome/')
            && ! str_contains($agent, 'edg/')
            && ! str_contains($agent, 'opr/') => 'Chrome',
            str_contains($agent, 'firefox/') => 'Firefox',
            str_contains($agent, 'safari/')
            && ! str_contains($agent, 'chrome/') => 'Safari',
            default => 'Unknown browser',
        };
    }

    /**
     * Resolve a lightweight platform label from a user agent string.
     */
    public function resolvePlatformLabel(string $userAgent): string
    {
        $agent = mb_strtolower($userAgent);

        return match (true) {
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'mac os'),
            str_contains($agent, 'macintosh') => 'macOS',
            str_contains($agent, 'iphone'),
            str_contains($agent, 'ipad'),
            str_contains($agent, 'ios') => 'iOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'linux') => 'Linux',
            default => 'Unknown platform',
        };
    }

    /**
     * Resolve a lightweight device label from a user agent string.
     */
    public function resolveDeviceLabel(string $userAgent): string
    {
        $agent = mb_strtolower($userAgent);

        return match (true) {
            str_contains($agent, 'ipad') => 'Tablet',
            str_contains($agent, 'tablet') => 'Tablet',
            str_contains($agent, 'mobile'),
            str_contains($agent, 'iphone'),
            (str_contains($agent, 'android') && str_contains($agent, 'mobile')) => 'Mobile device',
            default => 'Desktop device',
        };
    }
}