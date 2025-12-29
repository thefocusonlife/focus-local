<?php
declare(strict_types=1);

final class ImageCapabilities
{
    public static function imagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists('Imagick');
    }

    public static function gdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * @return 'imagick'|'gd'
     */
    public static function resolvedDriver(): string
    {
        $requested = strtolower((string) IMAGE_DRIVER);

        if ($requested === 'imagick') {
            if (!self::imagickAvailable()) {
                throw new RuntimeException(
                    'IMAGE_DRIVER=imagick forced but Imagick is not available.',
                );
            }
            return 'imagick';
        }

        if ($requested === 'gd') {
            if (!self::gdAvailable()) {
                throw new RuntimeException('IMAGE_DRIVER=gd forced but GD is not available.');
            }
            return 'gd';
        }

        // auto
        if (self::imagickAvailable()) {
            return 'imagick';
        }
        if (self::gdAvailable()) {
            return 'gd';
        }

        throw new RuntimeException('No image driver available (Imagick/GD missing).');
    }
}
