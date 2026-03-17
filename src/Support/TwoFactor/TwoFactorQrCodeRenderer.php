<?php

namespace Xul\AuthKit\Support\TwoFactor;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * TwoFactorQrCodeRenderer
 *
 * Renders otpauth URIs as inline SVG QR codes for two-factor setup pages.
 *
 * Responsibilities:
 * - Accept an otpauth provisioning URI.
 * - Render a QR code as SVG markup.
 * - Fail softly with an empty string when the payload is unavailable.
 */
final class TwoFactorQrCodeRenderer
{
    /**
     * Render the given otpauth URI as inline SVG markup.
     */
    public function render(?string $uri, int $size = 220): string
    {
        $uri = is_string($uri) ? trim($uri) : '';

        if ($uri === '') {
            return '';
        }

        $renderer = new ImageRenderer(
            new RendererStyle(max(120, $size)),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($uri);
    }
}