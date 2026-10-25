<?php

namespace App\Support;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

class ImageResizer
{
    private const QUALITY = 80;

    private ImageManagerInterface $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(Driver::class, autoOrientation: true, strip: true);
    }

    /** @throws ImageException when the data is not a readable image */
    public function fitAsWebp(string $binary, int $maxSize): string
    {
        return (string) $this->manager->decodeBinary($binary)
            ->scaleDown($maxSize, $maxSize)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);
    }

    /** @throws ImageException when the data is not a readable image */
    public function squareAsWebp(string $binary, int $size): string
    {
        return (string) $this->manager->decodeBinary($binary)
            ->cover($size, $size)
            ->encodeUsingFormat(Format::WEBP, quality: self::QUALITY);
    }
}
