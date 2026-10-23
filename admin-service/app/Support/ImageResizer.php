<?php

namespace App\Support;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

/**
 * Re-encoding to WebP also drops EXIF metadata (camera, GPS location) from uploads.
 * Photos are turned upright first (autoOrientation), since EXIF rotation is lost.
 */
final class ImageResizer
{
    private const QUALITY = 80;

    private ImageManagerInterface $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(Driver::class, autoOrientation: true, strip: true);
    }

    /**
     * Fits the image inside a $maxSize square, never enlarging it.
     *
     * @throws ImageException when the data is not a readable image
     */
    public function fitAsWebp(string $binary, int $maxSize): string
    {
        return (string) $this->manager->decodeBinary($binary)
            ->scaleDown($maxSize, $maxSize)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);
    }

    /**
     * Crops to a centred $size × $size square.
     *
     * @throws ImageException when the data is not a readable image
     */
    public function squareAsWebp(string $binary, int $size): string
    {
        return (string) $this->manager->decodeBinary($binary)
            ->cover($size, $size)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);
    }
}
