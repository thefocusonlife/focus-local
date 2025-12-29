<?php
declare(strict_types=1);

interface ImageProcessorInterface
{
    /**
     * Resize the image at $srcPath to fit within $maxDim (preserve aspect ratio),
     * and write the result to $destPath.
     *
     * $format: 'jpg'|'webp'|'png'
     */
    public function resizeToMaxDim(
        string $srcPath,
        string $destPath,
        int $maxDim,
        string $format,
        int $quality,
    ): void;
}
